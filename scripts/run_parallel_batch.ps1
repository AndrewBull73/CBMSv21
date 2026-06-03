param(
    [int]$Fy = 2026,
    [int]$Version = 5,
    [int]$Shards = 4,
    [int]$Retry = 15,
    [string]$Type = "",
    [ValidateSet("sproc", "redis")]
    [string]$CeilingFinalEngine = "sproc",
    [switch]$InvalidateScope,
    [switch]$NoCeilingChecks,
    [switch]$PostRunErrorBeforeCeilingCheck,
    [string]$LogPath = "",
    [string]$StopFile = "",
    [string]$BatchSavePolicy = ""
)

$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
$phpScript = Join-Path $repoRoot "backend-php\\public\\process_transaction_stub.php"

if (-not (Test-Path $phpScript)) {
    throw "process_transaction_stub.php not found at: $phpScript"
}

if ([string]::IsNullOrWhiteSpace($LogPath)) {
    $logDir = Join-Path $repoRoot "logs"
    if (-not (Test-Path $logDir)) {
        New-Item -ItemType Directory -Path $logDir | Out-Null
    }
    $stamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $LogPath = Join-Path $logDir "batch_run_${stamp}.log"
}

if ([string]::IsNullOrWhiteSpace($StopFile)) {
    $StopFile = "$LogPath.stop"
}

function Write-Log {
    param([string]$Message)
    $line = ("[{0}] {1}" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), $Message)
    Add-Content -Path $LogPath -Value $line
    Write-Output $line
}

function Read-NewShardLines {
    param(
        [hashtable]$Shard,
        [string]$PathKey,
        [string]$OffsetKey,
        [string]$BufferKey
    )

    $path = [string]$Shard[$PathKey]
    if ([string]::IsNullOrWhiteSpace($path) -or -not (Test-Path $path)) {
        return @()
    }

    $offset = 0L
    if ($Shard.ContainsKey($OffsetKey) -and $null -ne $Shard[$OffsetKey]) {
        $offset = [long]$Shard[$OffsetKey]
    }
    $buffer = ''
    if ($Shard.ContainsKey($BufferKey) -and $null -ne $Shard[$BufferKey]) {
        $buffer = [string]$Shard[$BufferKey]
    }
    $fs = [System.IO.File]::Open($path, [System.IO.FileMode]::Open, [System.IO.FileAccess]::Read, [System.IO.FileShare]::ReadWrite)
    try {
        if ($offset -gt $fs.Length) {
            $offset = 0
            $buffer = ''
        }
        $fs.Seek($offset, [System.IO.SeekOrigin]::Begin) | Out-Null
        $sr = New-Object System.IO.StreamReader($fs, [System.Text.Encoding]::UTF8, $true, 4096, $true)
        $text = $sr.ReadToEnd()
        $Shard[$OffsetKey] = $fs.Position
    } finally {
        $fs.Dispose()
    }

    if ([string]::IsNullOrEmpty($text)) {
        $Shard[$BufferKey] = $buffer
        return @()
    }

    $combined = $buffer + $text
    $parts = $combined -split "`r?`n", 0
    $complete = @()
    if ($combined -match "(`r`n|`n)$") {
        $Shard[$BufferKey] = ''
        $complete = $parts | Where-Object { $_ -ne '' }
    } else {
        $Shard[$BufferKey] = $parts[-1]
        if ($parts.Count -gt 1) {
            $complete = $parts[0..($parts.Count - 2)] | Where-Object { $_ -ne '' }
        }
    }

    return $complete
}

function Flush-ShardProcessOutput {
    param(
        [System.Collections.ArrayList]$Shards,
        [string]$OutPrefix = 'SHARD_OUT',
        [string]$ErrPrefix = 'SHARD_ERR',
        [string]$EndPrefix = 'SHARD_END'
    )

    foreach ($shard in $Shards) {
        foreach ($line in (Read-NewShardLines -Shard $shard -PathKey 'stdout_path' -OffsetKey 'stdout_offset' -BufferKey 'stdout_buffer')) {
            $payload = [string]$line
            Write-Log ("{0}|Idx={1}|{2}" -f $OutPrefix, $shard.idx, $payload)
            if ($payload -match 'Total:\s+(\d+)\s+\|\s+Success:\s+(\d+)\s+\|\s+Failed:\s+(\d+)\s+\|\s+Elapsed:\s+(\d+)\s+ms') {
                $t = $Matches[1]; $ok = $Matches[2]; $fail = $Matches[3]; $ms = $Matches[4]
                Write-Log ("{0}|Idx={1}|Total={2}|Success={3}|Failed={4}|ElapsedMs={5}" -f $EndPrefix, $shard.idx, $t, $ok, $fail, $ms)
            } elseif ($payload -match 'Bulk Metrics \| TxAvgMs: (\d+) \| CeilingSnapshotMs: (\d+) \| CeilingPrecheckMs: (\d+) \| CeilingFinalSprocMs: (\d+) \| CeilingFinalRedisMs: (\d+) \| CeilingSyncBackMs: (\d+) \| DbQueries: (\d+) \| RedisGets: (\d+) \| RedisSets: (\d+)') {
                Write-Log ("SHARD_METRICS|Idx={0}|TxAvgMs={1}|CeilingSnapshotMs={2}|CeilingPrecheckMs={3}|CeilingFinalSprocMs={4}|CeilingFinalRedisMs={5}|CeilingSyncBackMs={6}|DbQueries={7}|RedisGets={8}|RedisSets={9}" -f $shard.idx, $Matches[1], $Matches[2], $Matches[3], $Matches[4], $Matches[5], $Matches[6], $Matches[7], $Matches[8], $Matches[9])
            }
        }
        foreach ($line in (Read-NewShardLines -Shard $shard -PathKey 'stderr_path' -OffsetKey 'stderr_offset' -BufferKey 'stderr_buffer')) {
            Write-Log ("{0}|Idx={1}|{2}" -f $ErrPrefix, $shard.idx, [string]$line)
        }
    }
}

function Start-BatchShardProcess {
    param(
        [string[]]$PhpArgs,
        [int]$ShardIdx,
        [string]$Prefix = 'SHARD'
    )

    $stdoutPath = "{0}.{1}{2}.out.log" -f $LogPath, $Prefix.ToLowerInvariant(), $ShardIdx
    $stderrPath = "{0}.{1}{2}.err.log" -f $LogPath, $Prefix.ToLowerInvariant(), $ShardIdx
    New-Item -ItemType File -Path $stdoutPath -Force | Out-Null
    New-Item -ItemType File -Path $stderrPath -Force | Out-Null

    $proc = Start-Process -FilePath "php" `
        -ArgumentList $PhpArgs `
        -RedirectStandardOutput $stdoutPath `
        -RedirectStandardError $stderrPath `
        -NoNewWindow `
        -PassThru

    return @{
        idx = $ShardIdx
        process = $proc
        stdout_path = $stdoutPath
        stderr_path = $stderrPath
        stdout_offset = 0L
        stderr_offset = 0L
        stdout_buffer = ''
        stderr_buffer = ''
    }
}

function Stop-ShardProcesses {
    param([System.Collections.ArrayList]$Shards)

    foreach ($shard in $Shards) {
        $proc = $shard.process
        if ($null -ne $proc) {
            try {
                if (-not $proc.HasExited) {
                    Stop-Process -Id $proc.Id -Force -ErrorAction Stop
                }
            } catch {}
        }
    }
}

function Get-ShardCounts {
    param([System.Collections.ArrayList]$Shards)

    $running = 0
    $completed = 0
    foreach ($shard in $Shards) {
        $proc = $shard.process
        if ($null -eq $proc) {
            continue
        }
        try { $proc.Refresh() } catch {}
        if ($proc.HasExited) {
            $completed++
        } else {
            $running++
        }
    }
    return @{ running = $running; completed = $completed }
}

Write-Log "BATCH_START|Fy=$Fy|Version=$Version|Shards=$Shards|Retry=$Retry|Type=$Type|CeilingFinalEngine=$CeilingFinalEngine|Invalidate=$($InvalidateScope.IsPresent)|NoCeilingChecks=$($NoCeilingChecks.IsPresent)"
Write-Log "BATCH_STOP_FILE|Path=$StopFile"
$batchStart = Get-Date

Write-Log "BATCH_MODE_ON"
& php $phpScript --batch-mode-on --batch-save-policy="$BatchSavePolicy" | ForEach-Object { Write-Log $_ }

try {
    $cancelled = $false
    $shardProcesses = [System.Collections.ArrayList]::new()
    for ($i = 0; $i -lt $Shards; $i++) {
        $args = @(
            $phpScript,
            "--bulk-run",
            "--bulk-fy=$Fy",
            "--bulk-version=$Version",
            "--bulk-head-only",
            "--bulk-shard-index=$i",
            "--bulk-shard-count=$Shards",
            "--retry-deadlocks=$Retry",
            "--ceiling-sproc-check",
            "--ceiling-engine=$CeilingFinalEngine"
        )
        if (-not [string]::IsNullOrWhiteSpace($Type)) {
            $args += "--bulk-transaction-type=$Type"
        }
        if ($InvalidateScope.IsPresent) {
            $args += "--invalidate-scope"
        }
        if ($NoCeilingChecks.IsPresent) {
            $args += "--no-ceiling-check"
            $args += "--ceiling-precheck=0"
            $args += "--ceiling-engine=$CeilingFinalEngine"
        }

        Write-Log ("SHARD_START|Idx={0}|TotalShards={1}" -f $i, $Shards)
        $shard = Start-BatchShardProcess -PhpArgs $args -ShardIdx $i -Prefix 'SHARD'
        Write-Log ("SHARD_PID|Idx={0}|Pid={1}" -f $i, $shard.process.Id)
        [void]$shardProcesses.Add($shard)
    }

    $lastHeartbeat = [DateTime]::UtcNow.AddSeconds(-10)
    while ($true) {
        Flush-ShardProcessOutput -Shards $shardProcesses
        if (Test-Path $StopFile) {
            Write-Log "BATCH_CANCELLED|Reason=stop_file_detected"
            Stop-ShardProcesses -Shards $shardProcesses
            $cancelled = $true
            break
        }
        $counts = Get-ShardCounts -Shards $shardProcesses
        $runningCount = [int]$counts.running
        $completedCount = [int]$counts.completed
        if ($runningCount -le 0) {
            break
        }
        if ((([DateTime]::UtcNow - $lastHeartbeat).TotalSeconds -ge 5) -or $completedCount -gt 0) {
            Write-Log ("BATCH_HEARTBEAT|RunningShards={0}|CompletedShards={1}|TotalShards={2}" -f $runningCount, $completedCount, $Shards)
            $lastHeartbeat = [DateTime]::UtcNow
        }
        Start-Sleep -Seconds 1
    }

    Flush-ShardProcessOutput -Shards $shardProcesses

    if ($PostRunErrorBeforeCeilingCheck.IsPresent -and -not $NoCeilingChecks.IsPresent -and -not $cancelled) {
        Write-Log "POST_BATCH_RECOVERY_START|Filter=CeilingStatusCheck='ERROR BEFORE CEILING CHECK'"
        $retryShardProcesses = [System.Collections.ArrayList]::new()
        for ($i = 0; $i -lt $Shards; $i++) {
            $retryArgs = @(
                $phpScript,
                "--bulk-run",
                "--bulk-fy=$Fy",
                "--bulk-version=$Version",
                "--bulk-head-only",
                "--bulk-shard-index=$i",
                "--bulk-shard-count=$Shards",
                "--retry-deadlocks=$Retry",
                "--ceiling-sproc-check",
                "--ceiling-engine=$CeilingFinalEngine",
                "--bulk-ceiling-status-check=ERROR BEFORE CEILING CHECK"
            )
            if (-not [string]::IsNullOrWhiteSpace($Type)) {
                $retryArgs += "--bulk-transaction-type=$Type"
            }
            if ($InvalidateScope.IsPresent) {
                $retryArgs += "--invalidate-scope"
            }

            Write-Log ("POST_SHARD_START|Idx={0}|TotalShards={1}" -f $i, $Shards)
            $retryShard = Start-BatchShardProcess -PhpArgs $retryArgs -ShardIdx $i -Prefix 'POST_SHARD'
            Write-Log ("POST_SHARD_PID|Idx={0}|Pid={1}" -f $i, $retryShard.process.Id)
            [void]$retryShardProcesses.Add($retryShard)
        }

        $lastPostHeartbeat = [DateTime]::UtcNow.AddSeconds(-10)
        while ($true) {
            Flush-ShardProcessOutput -Shards $retryShardProcesses -OutPrefix 'POST_SHARD_OUT' -ErrPrefix 'POST_SHARD_ERR' -EndPrefix 'POST_SHARD_END'
            if (Test-Path $StopFile) {
                Write-Log "POST_BATCH_RECOVERY_CANCELLED|Reason=stop_file_detected"
                Stop-ShardProcesses -Shards $retryShardProcesses
                break
            }
            $counts = Get-ShardCounts -Shards $retryShardProcesses
            $runningCount = [int]$counts.running
            $completedCount = [int]$counts.completed
            if ($runningCount -le 0) {
                break
            }
            if ((([DateTime]::UtcNow - $lastPostHeartbeat).TotalSeconds -ge 5) -or $completedCount -gt 0) {
                Write-Log ("POST_BATCH_HEARTBEAT|RunningShards={0}|CompletedShards={1}|TotalShards={2}" -f $runningCount, $completedCount, $Shards)
                $lastPostHeartbeat = [DateTime]::UtcNow
            }
            Start-Sleep -Seconds 1
        }

        Flush-ShardProcessOutput -Shards $retryShardProcesses -OutPrefix 'POST_SHARD_OUT' -ErrPrefix 'POST_SHARD_ERR' -EndPrefix 'POST_SHARD_END'
        Write-Log "POST_BATCH_RECOVERY_END"
    }
} finally {
    Write-Log "BATCH_MODE_OFF"
    & php $phpScript --batch-mode-off | ForEach-Object { Write-Log $_ }
    Write-Log ("BATCH_END|ElapsedMs={0}" -f [int]((Get-Date) - $batchStart).TotalMilliseconds)
    Write-Log "Batch run completed. Log: $LogPath"
}
