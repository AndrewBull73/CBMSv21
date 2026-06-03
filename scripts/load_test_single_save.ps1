param(
    [int]$Fy = 2026,
    [int]$Version = 5,
    [int]$Workers = 4,
    [int]$IterationsPerWorker = 20,
    [string]$Type = "",
    [int]$MinTxId = 0,
    [int]$MaxTxId = 0,
    [switch]$IncludeChildren,
    [int]$MaxCandidates = 2000,
    [ValidateSet("shuffle", "spread_ceiling")]
    [string]$Scenario = "shuffle",
    [int]$PerCeilingLimit = 5,
    [int]$ThinkTimeMinMs = 0,
    [int]$ThinkTimeMaxMs = 0,
    [switch]$OnlyCeilingStatusNull,
    [string]$TxIds = "",
    [ValidateSet("sproc", "redis")]
    [string]$CeilingEngine = "sproc",
    [switch]$NoCeilingChecks,
    [int]$RetryDeadlocks = 15,
    [string]$LogPath = "",
    [string]$StopFile = ""
)

$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
$phpScript = Join-Path $repoRoot "backend-php\\public\\process_transaction_stub.php"
$dbConfigPathPhp = (Join-Path $repoRoot "backend-php\\config\\db.php").Replace('\', '/').Replace("'", "\\'")

if (-not (Test-Path $phpScript)) {
    throw "process_transaction_stub.php not found at: $phpScript"
}

if ($Workers -lt 1) { $Workers = 1 }
if ($IterationsPerWorker -lt 1) { $IterationsPerWorker = 1 }
if ($RetryDeadlocks -lt 0) { $RetryDeadlocks = 0 }
if ($MaxCandidates -lt 1) { $MaxCandidates = 2000 }
if ($PerCeilingLimit -lt 1) { $PerCeilingLimit = 1 }
if ($ThinkTimeMinMs -lt 0) { $ThinkTimeMinMs = 0 }
if ($ThinkTimeMaxMs -lt 0) { $ThinkTimeMaxMs = 0 }
if ($ThinkTimeMaxMs -lt $ThinkTimeMinMs) { $ThinkTimeMaxMs = $ThinkTimeMinMs }

function Get-CandidateTxIds {
    param(
        [int]$FyParam,
        [int]$VersionParam,
        [string]$TypeParam,
        [int]$MinTxIdParam,
        [int]$MaxTxIdParam,
        [bool]$IncludeChildrenParam,
        [int]$MaxCandidatesParam,
        [string]$ScenarioParam,
        [int]$PerCeilingLimitParam,
        [bool]$OnlyCeilingStatusNullParam
    )

    $typeEsc = if ([string]::IsNullOrWhiteSpace($TypeParam)) { '' } else { $TypeParam.Replace("'", "''") }
    $childWhere = if ($IncludeChildrenParam) { '' } else { " AND (HeadRecordID IS NULL OR HeadRecordID = TransactionID)" }
    $typeWhere = if ([string]::IsNullOrWhiteSpace($typeEsc)) { '' } else { " AND TransactionTypeCode = '" + $typeEsc + "'" }
    $minWhere = if ($MinTxIdParam -gt 0) { " AND TransactionID >= " + [string]$MinTxIdParam } else { '' }
    $maxWhere = if ($MaxTxIdParam -gt 0) { " AND TransactionID <= " + [string]$MaxTxIdParam } else { '' }
    $statusWhere = if ($OnlyCeilingStatusNullParam) { " AND CeilingStatus IS NULL" } else { '' }

    if ($ScenarioParam -eq "spread_ceiling") {
        $selectorSql = @"
WITH Spread AS (
    SELECT
        TransactionID,
        ROW_NUMBER() OVER (PARTITION BY CeilingDefinitionID ORDER BY NEWID()) AS rn
    FROM dbo.tblTransactionInput
    WHERE FiscalYearID = ${FyParam}
      AND VersionID = ${VersionParam}
      AND CeilingDefinitionID IS NOT NULL
      ${typeWhere}${minWhere}${maxWhere}${childWhere}${statusWhere}
)
SELECT TOP (${MaxCandidatesParam}) TransactionID
FROM Spread
WHERE rn <= ${PerCeilingLimitParam}
ORDER BY NEWID()
"@
    } else {
        $selectorSql = "SELECT TOP (${MaxCandidatesParam}) TransactionID FROM dbo.tblTransactionInput WHERE FiscalYearID = ${FyParam} AND VersionID = ${VersionParam}${typeWhere}${minWhere}${maxWhere}${childWhere}${statusWhere} ORDER BY TransactionID ASC"
    }
    $selectorSqlEsc = $selectorSql.Replace("'", "''")
    $selectorPhp = @'
<?php
require '__DB_CONFIG__';
$sql = '__SQL__';
$stmt = $conn->query($sql);
foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : []) as $id) {
    echo (int)$id . PHP_EOL;
}
?>
'@
    $selectorPhp = $selectorPhp.Replace('__DB_CONFIG__', $dbConfigPathPhp).Replace('__SQL__', $selectorSqlEsc)

    $output = $selectorPhp | php 2>&1
    if (-not $output) { return @() }

    $ids = @()
    foreach ($line in $output) {
        $s = "$line".Trim()
        if ($s -match '^\d+$') {
            $ids += [int]$s
        }
    }
    return $ids | Select-Object -Unique
}

$candidateIds = @()
if (-not [string]::IsNullOrWhiteSpace($TxIds)) {
    $candidateIds = $TxIds.Split(',') |
        ForEach-Object { $_.Trim() } |
        Where-Object { $_ -match '^\d+$' } |
        ForEach-Object { [int]$_ } |
        Select-Object -Unique
} else {
    $candidateIds = Get-CandidateTxIds -FyParam $Fy -VersionParam $Version -TypeParam $Type -MinTxIdParam $MinTxId -MaxTxIdParam $MaxTxId -IncludeChildrenParam $IncludeChildren.IsPresent -MaxCandidatesParam $MaxCandidates -ScenarioParam $Scenario -PerCeilingLimitParam $PerCeilingLimit -OnlyCeilingStatusNullParam $OnlyCeilingStatusNull.IsPresent
}

if (-not $candidateIds -or $candidateIds.Count -eq 0) {
    Write-Output "No candidates from filtered query; trying fallback candidate selection."
    $candidateIds = Get-CandidateTxIds -FyParam $Fy -VersionParam $Version -TypeParam "" -MinTxIdParam 0 -MaxTxIdParam 0 -IncludeChildrenParam $false -MaxCandidatesParam $MaxCandidates -ScenarioParam "shuffle" -PerCeilingLimitParam $PerCeilingLimit -OnlyCeilingStatusNullParam $OnlyCeilingStatusNull.IsPresent
}

if (-not $candidateIds -or $candidateIds.Count -eq 0) {
    throw "No candidate transactions found. Adjust Fy/Version/filter settings or pass -TxIds."
}

$baseArgs = @("--retry-deadlocks=$RetryDeadlocks")
if ($NoCeilingChecks.IsPresent) {
    $baseArgs += "--no-ceiling-check"
    $baseArgs += "--ceiling-precheck=0"
} else {
    $baseArgs += "--ceiling-sproc-check"
    $baseArgs += "--ceiling-engine=$CeilingEngine"
}

Write-Output ("Load test start | Fy={0} Version={1} Workers={2} IterationsPerWorker={3} Candidates={4} Scenario={5} PerCeilingLimit={6} ThinkTimeMinMs={7} ThinkTimeMaxMs={8} OnlyCeilingStatusNull={9} CeilingEngine={10} NoCeilingChecks={11}" -f `
    $Fy, $Version, $Workers, $IterationsPerWorker, $candidateIds.Count, $Scenario, $PerCeilingLimit, $ThinkTimeMinMs, $ThinkTimeMaxMs, $OnlyCeilingStatusNull.IsPresent, $CeilingEngine, $NoCeilingChecks.IsPresent)
if (-not [string]::IsNullOrWhiteSpace($StopFile)) {
    Write-Output ("Load test stop file | Path={0}" -f $StopFile)
}

$startedAt = Get-Date
$jobs = @()
$totalOps = $Workers * $IterationsPerWorker
$assignedTxIds = @()
while ($assignedTxIds.Count -lt $totalOps) {
    $cycle = @($candidateIds | Sort-Object { Get-Random })
    $remaining = $totalOps - $assignedTxIds.Count
    if ($remaining -lt $cycle.Count) {
        $assignedTxIds += @($cycle[0..($remaining - 1)])
    } else {
        $assignedTxIds += $cycle
    }
}
$plannedUnique = @($assignedTxIds | Select-Object -Unique).Count
Write-Output ("Load plan | TotalOps={0} PlannedUniqueTx={1}" -f $totalOps, $plannedUnique)

for ($w = 1; $w -le $Workers; $w++) {
    $start = ($w - 1) * $IterationsPerWorker
    $end = $start + $IterationsPerWorker - 1
    $workerPlan = @($assignedTxIds[$start..$end])
    $jobs += Start-Job -ScriptBlock {
        param($workerId, $plannedTxIds, $scriptPath, $argsBase, $stopFilePath, $thinkMinMs, $thinkMaxMs)

        $rows = @()
        for ($i = 1; $i -le $plannedTxIds.Count; $i++) {
            if (-not [string]::IsNullOrWhiteSpace($stopFilePath) -and (Test-Path $stopFilePath)) {
                break
            }
            $txId = [int]$plannedTxIds[$i - 1]
            $sw = [System.Diagnostics.Stopwatch]::StartNew()
            $out = & php $scriptPath "--tx=$txId" @argsBase 2>&1
            $sw.Stop()

            $text = ($out | ForEach-Object { $_.ToString() }) -join "`n"
            $err = $null
            if ($text -match "Error:\s*(.+)") {
                $err = $Matches[1].Trim()
            }

            $deadlock = ($text -match "deadlocked|deadlock|SQLSTATE\[40001\]")
            $ceilingFailed = ($text -match "Ceiling Check Status:\s*FAILED") -or ($text -match "CEILING FAILED")
            $ok = [string]::IsNullOrEmpty($err)

            $rows += [pscustomobject]@{
                Worker        = $workerId
                Iteration     = $i
                TransactionID = [int]$txId
                Ok            = [bool]$ok
                Deadlock      = [bool]$deadlock
                CeilingFailed = [bool]$ceilingFailed
                DurationMs    = [int]$sw.ElapsedMilliseconds
                Error         = $err
            }

            if ($thinkMaxMs -gt 0) {
                $delay = if ($thinkMaxMs -gt $thinkMinMs) { Get-Random -Minimum $thinkMinMs -Maximum ($thinkMaxMs + 1) } else { $thinkMinMs }
                if ($delay -gt 0) {
                    Start-Sleep -Milliseconds $delay
                }
            }
        }

        return $rows
    } -ArgumentList $w, $workerPlan, $phpScript, $baseArgs, $StopFile, $ThinkTimeMinMs, $ThinkTimeMaxMs
}

Wait-Job -Job $jobs | Out-Null
$results = $jobs | Receive-Job
$jobs | Remove-Job -Force

$total = @($results).Count
$okCount = @($results | Where-Object { $_.Ok }).Count
$failed = @($results | Where-Object { -not $_.Ok })
$failedCount = $failed.Count
$deadlockCount = @($results | Where-Object { $_.Deadlock }).Count
$ceilingFailedCount = @($results | Where-Object { $_.CeilingFailed }).Count
$avgMs = 0.0
if ($total -gt 0) {
    $avgMs = [double](($results | Measure-Object -Property DurationMs -Average).Average)
}
$elapsedMs = [int]((Get-Date) - $startedAt).TotalMilliseconds

Write-Output ("Load test complete | Total={0} Ok={1} Failed={2} DeadlockTagged={3} CeilingFailedTagged={4} AvgMs={5:n2} ElapsedMs={6}" -f `
    $total, $okCount, $failedCount, $deadlockCount, $ceilingFailedCount, $avgMs, $elapsedMs)
if (-not [string]::IsNullOrWhiteSpace($StopFile) -and (Test-Path $StopFile)) {
    Write-Output "Load test cancelled by stop file."
}

if ($failedCount -gt 0) {
    Write-Output "Top Errors:"
    $failed | Group-Object -Property Error | Sort-Object -Property Count -Descending | Select-Object -First 10 | ForEach-Object {
        $msg = if ([string]::IsNullOrWhiteSpace($_.Name)) { "(empty error text)" } else { $_.Name }
        Write-Output ("- {0} | {1}" -f $_.Count, $msg)
    }
}

if (-not [string]::IsNullOrWhiteSpace($LogPath)) {
    $dir = Split-Path -Parent $LogPath
    if (-not [string]::IsNullOrWhiteSpace($dir) -and -not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
    $results | Export-Csv -NoTypeInformation -Path $LogPath -Force
    Write-Output ("Detailed results written to: {0}" -f $LogPath)
}
