param(
    [Parameter(Mandatory = $true)]
    [string]$JobDefinitionPath
)

$ErrorActionPreference = 'Stop'

function Write-JobState {
    param(
        [string]$Path,
        [hashtable]$State
    )

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, ($State | ConvertTo-Json -Depth 12), $utf8NoBom)
}

function Append-LogLine {
    param(
        [string]$Path,
        [string]$Text
    )

    Add-Content -Path $Path -Value $Text -Encoding UTF8
}

function Get-LastNonEmptyLine {
    param(
        [string]$Text
    )

    if ([string]::IsNullOrWhiteSpace($Text)) {
        return $null
    }

    $lines = @($Text -split "`r?`n" | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
    if ($lines.Count -eq 0) {
        return $null
    }

    return [string]$lines[$lines.Count - 1]
}

if (-not (Test-Path -LiteralPath $JobDefinitionPath)) {
    throw "Job definition not found: $JobDefinitionPath"
}

$job = Get-Content -LiteralPath $JobDefinitionPath -Raw | ConvertFrom-Json
$statePath = [string]$job.statePath
$logPath = [string]$job.logPath
$stopFile = [string]$job.stopFile
$repoRoot = [string]$job.repoRoot
$jobId = [string]$job.jobId
$commands = @($job.commands)
$selection = $job.selection

if ([string]::IsNullOrWhiteSpace($statePath) -or [string]::IsNullOrWhiteSpace($logPath)) {
    throw "Job definition is missing state/log paths."
}

$state = @{
    jobId = $jobId
    status = 'running'
    startedAt = (Get-Date).ToString('s')
    finishedAt = $null
    selection = $selection
    steps = @()
    currentStepIndex = $null
    currentStepLabel = $null
    wrapperPid = $PID
    childPid = $null
    logPath = $logPath
    statePath = $statePath
    stopFile = $stopFile
    jobDefinitionPath = $JobDefinitionPath
    lastMessage = 'Running.'
}

Write-JobState -Path $statePath -State $state
Append-LogLine -Path $logPath -Text ("[{0}] Job {1} started." -f (Get-Date).ToString('s'), $jobId)

try {
    for ($index = 0; $index -lt $commands.Count; $index++) {
        if (([string]::IsNullOrWhiteSpace($stopFile) -eq $false) -and (Test-Path -LiteralPath $stopFile)) {
            $state['status'] = 'cancelled'
            $state['finishedAt'] = (Get-Date).ToString('s')
            $state['currentStepIndex'] = $null
            $state['currentStepLabel'] = $null
            $state['childPid'] = $null
            $state['lastMessage'] = 'Cancelled before the next step started.'
            Write-JobState -Path $statePath -State $state
            Append-LogLine -Path $logPath -Text ("[{0}] Job {1} cancelled before step {2}." -f (Get-Date).ToString('s'), $jobId, ($index + 1))
            exit 1
        }

        $step = $commands[$index]
        $stepLabel = [string]$step.label
        $stepCommand = [string]$step.command
        $stepStarted = Get-Date

        $stepState = @{
            label = $stepLabel
            command = $stepCommand
            status = 'running'
            startedAt = $stepStarted.ToString('s')
            finishedAt = $null
            durationSeconds = $null
            exitCode = $null
            errorMessage = $null
            outputSummary = $null
        }

        $state['steps'] += $stepState
        $state['currentStepIndex'] = $index
        $state['currentStepLabel'] = $stepLabel
        $state['lastMessage'] = "Running $stepLabel"
        Write-JobState -Path $statePath -State $state

        Append-LogLine -Path $logPath -Text ("[{0}] Starting {1}" -f $stepStarted.ToString('s'), $stepLabel)
        Append-LogLine -Path $logPath -Text ("[{0}] Command: {1}" -f $stepStarted.ToString('s'), $stepCommand)

        $stepCmdPath = Join-Path -Path (Split-Path -Parent $statePath) -ChildPath ("step_{0}_{1}.cmd" -f ($index + 1), $jobId)
        $stepCmd = "@echo off`r`ncd /d `"$repoRoot`"`r`n$stepCommand`r`nexit /b %errorlevel%`r`n"
        Set-Content -LiteralPath $stepCmdPath -Value $stepCmd -Encoding ASCII

        $psi = New-Object System.Diagnostics.ProcessStartInfo
        $psi.FileName = 'cmd.exe'
        $psi.Arguments = "/d /c `"$stepCmdPath`""
        $psi.UseShellExecute = $false
        $psi.CreateNoWindow = $true
        $psi.RedirectStandardOutput = $true
        $psi.RedirectStandardError = $true

        $proc = New-Object System.Diagnostics.Process
        $proc.StartInfo = $psi
        $null = $proc.Start()

        $state['childPid'] = $proc.Id
        Write-JobState -Path $statePath -State $state

        $stdOut = $proc.StandardOutput.ReadToEnd()
        $stdErr = $proc.StandardError.ReadToEnd()
        $proc.WaitForExit()
        $exitCode = [int]$proc.ExitCode
        $stepFinished = Get-Date
        $durationSeconds = [Math]::Round(($stepFinished - $stepStarted).TotalSeconds, 3)

        if (-not [string]::IsNullOrWhiteSpace($stdOut)) {
            foreach ($line in ($stdOut -split "`r?`n")) {
                if (-not [string]::IsNullOrWhiteSpace($line)) {
                    Append-LogLine -Path $logPath -Text $line
                }
            }
        }
        if (-not [string]::IsNullOrWhiteSpace($stdErr)) {
            foreach ($line in ($stdErr -split "`r?`n")) {
                if (-not [string]::IsNullOrWhiteSpace($line)) {
                    Append-LogLine -Path $logPath -Text $line
                }
            }
        }

        $state['childPid'] = $null
        $outputSummary = Get-LastNonEmptyLine -Text $stdOut
        if ([string]::IsNullOrWhiteSpace($outputSummary)) {
            $outputSummary = Get-LastNonEmptyLine -Text $stdErr
        }
        $errorSummary = $null
        if ($exitCode -ne 0) {
            $errorSummary = Get-LastNonEmptyLine -Text $stdErr
            if ([string]::IsNullOrWhiteSpace($errorSummary)) {
                $errorSummary = Get-LastNonEmptyLine -Text $stdOut
            }
            if ([string]::IsNullOrWhiteSpace($errorSummary)) {
                $errorSummary = "Step exited with code $exitCode."
            }
        }
        $state['steps'][$index]['finishedAt'] = $stepFinished.ToString('s')
        $state['steps'][$index]['durationSeconds'] = $durationSeconds
        $state['steps'][$index]['exitCode'] = $exitCode
        $state['steps'][$index]['status'] = if ($exitCode -eq 0) { 'completed' } else { 'failed' }
        $state['steps'][$index]['errorMessage'] = $errorSummary
        $state['steps'][$index]['outputSummary'] = $outputSummary
        $state['lastMessage'] = if ($exitCode -eq 0) { "$stepLabel completed." } else { "$stepLabel failed: $errorSummary" }
        Write-JobState -Path $statePath -State $state

        Append-LogLine -Path $logPath -Text ("[{0}] Finished {1} | ExitCode={2} | Duration={3}s" -f $stepFinished.ToString('s'), $stepLabel, $exitCode, $durationSeconds)

        if ($exitCode -ne 0) {
            $state['status'] = 'failed'
            $state['finishedAt'] = $stepFinished.ToString('s')
            $state['currentStepIndex'] = $null
            $state['currentStepLabel'] = $null
            Write-JobState -Path $statePath -State $state
            exit $exitCode
        }
    }

    $state['status'] = if (([string]::IsNullOrWhiteSpace($stopFile) -eq $false) -and (Test-Path -LiteralPath $stopFile)) { 'cancelled' } else { 'completed' }
    $state['finishedAt'] = (Get-Date).ToString('s')
    $state['currentStepIndex'] = $null
    $state['currentStepLabel'] = $null
    $state['childPid'] = $null
    $state['lastMessage'] = if ($state['status'] -eq 'completed') { 'Completed successfully.' } else { 'Cancelled.' }
    Write-JobState -Path $statePath -State $state
    Append-LogLine -Path $logPath -Text ("[{0}] Job {1} finished with status {2}." -f (Get-Date).ToString('s'), $jobId, $state['status'])
}
catch {
    $state['status'] = 'failed'
    $state['finishedAt'] = (Get-Date).ToString('s')
    $state['currentStepIndex'] = $null
    $state['currentStepLabel'] = $null
    $state['childPid'] = $null
    $state['lastMessage'] = $_.Exception.Message
    Write-JobState -Path $statePath -State $state
    Append-LogLine -Path $logPath -Text ("[{0}] Job {1} failed: {2}" -f (Get-Date).ToString('s'), $jobId, $_.Exception.Message)
    throw
}
