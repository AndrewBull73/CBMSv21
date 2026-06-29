<?php
declare(strict_types=1);

use App\Shared\TrainingScenarioCatalog;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

require_once $root . '/config/db.php';
require_once $root . '/app/Models/TrainingScenarioModel.php';
require_once $root . '/app/Shared/TrainingScenarioCatalog.php';

if (!isset($conn) || !($conn instanceof PDO)) {
    fwrite(STDERR, "Database connection was not initialised.\n");
    exit(1);
}

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

TrainingScenarioCatalog::setDb(null);
$catalog = TrainingScenarioCatalog::all();

runSqlFile($conn, $root . '/config/sql/create_tblTrainingProgress.sql');
runSqlFile($conn, $root . '/config/sql/alter_training_progress_utc_and_attempts.sql');
runSqlFile($conn, $root . '/config/sql/create_training_scenario_catalog.sql');
runSqlFile($conn, $root . '/config/sql/create_training_management_features.sql');

$counts = syncCatalog($conn, $catalog);

echo "Training catalogue synced.\n";
echo 'scenarios=' . $counts['scenarios'] . "\n";
echo 'steps=' . $counts['steps'] . "\n";
echo 'samples=' . $counts['samples'] . "\n";

function runSqlFile(PDO $db, string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException('SQL file not found: ' . $path);
    }

    $sql = (string) file_get_contents($path);
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
    $batches = preg_split('/^\s*GO\s*;?\s*$/mi', $sql) ?: [];

    foreach ($batches as $batch) {
        $batch = trim($batch);
        if ($batch === '') {
            continue;
        }
        if (preg_match('/^\s*USE\s+(?:\[[^\]]+\]|[^\s;]+)\s*;?\s*$/i', $batch) === 1) {
            continue;
        }
        $db->exec($batch);
    }
}

function syncCatalog(PDO $db, array $catalog): array
{
    $scenarioExists = $db->prepare("
        SELECT COUNT(1)
        FROM dbo.tblTrainingScenarios
        WHERE ScenarioCode = :code
    ");

    $insertScenario = $db->prepare("
        INSERT INTO dbo.tblTrainingScenarios
        (
            ScenarioCode,
            ScenarioTitle,
            ScreenFamily,
            ModuleName,
            Audience,
            Difficulty,
            Description,
            RunnerRoute,
            NextScenarioCode,
            PrerequisitesJson,
            ActiveFlag,
            SortOrder
        )
        VALUES
        (
            :code,
            :title,
            :screen_family,
            :module,
            :audience,
            :difficulty,
            :description,
            :runner_route,
            :next_code,
            :prerequisites,
            1,
            :sort_order
        )
    ");

    $updateScenario = $db->prepare("
        UPDATE dbo.tblTrainingScenarios
        SET ScenarioTitle = :title,
            ScreenFamily = :screen_family,
            ModuleName = :module,
            Audience = :audience,
            Difficulty = :difficulty,
            Description = :description,
            RunnerRoute = :runner_route,
            NextScenarioCode = :next_code,
            PrerequisitesJson = :prerequisites,
            ActiveFlag = 1,
            SortOrder = :sort_order,
            UpdatedDate = SYSDATETIME()
        WHERE ScenarioCode = :code
    ");

    $insertStep = $db->prepare("
        INSERT INTO dbo.tblTrainingScenarioSteps
        (
            ScenarioCode,
            StepNo,
            Route,
            TargetElementID,
            StepTitle,
            InstructionText,
            CompletionMode,
            SampleKey,
            ExpectedUserSampleKey,
            ActiveFlag,
            SortOrder
        )
        VALUES
        (
            :code,
            :step_no,
            :route,
            :target,
            :title,
            :instruction,
            :completion_mode,
            :sample_key,
            :expected_user_sample_key,
            1,
            :sort_order
        )
    ");

    $insertSample = $db->prepare("
        INSERT INTO dbo.tblTrainingScenarioSamples
        (
            ScenarioCode,
            SampleKey,
            SampleValueTemplate,
            ActiveFlag,
            SortOrder
        )
        VALUES
        (
            :code,
            :sample_key,
            :sample_value,
            1,
            :sort_order
        )
    ");

    $codes = array_values(array_filter(array_map(static fn($code): string => trim((string) $code), array_keys($catalog))));
    if ($codes === []) {
        return ['scenarios' => 0, 'steps' => 0, 'samples' => 0];
    }

    $db->beginTransaction();
    try {
        $deleteParams = placeholdersForCodes($codes);
        $db->prepare('DELETE FROM dbo.tblTrainingScenarioSteps WHERE ScenarioCode IN (' . $deleteParams['sql'] . ')')
            ->execute($deleteParams['params']);
        $db->prepare('DELETE FROM dbo.tblTrainingScenarioSamples WHERE ScenarioCode IN (' . $deleteParams['sql'] . ')')
            ->execute($deleteParams['params']);

        $scenarioCount = 0;
        $stepCount = 0;
        $sampleCount = 0;
        $scenarioOrder = 10;

        foreach ($catalog as $code => $scenario) {
            $code = trim((string) $code);
            if ($code === '' || !is_array($scenario)) {
                continue;
            }

            $scenarioParams = [
                ':code' => $code,
                ':title' => trim((string) ($scenario['title'] ?? $code)),
                ':screen_family' => trim((string) ($scenario['screen_family'] ?? 'training')),
                ':module' => trim((string) ($scenario['module'] ?? 'Training')),
                ':audience' => nullIfBlank($scenario['audience'] ?? null),
                ':difficulty' => nullIfBlank($scenario['difficulty'] ?? null),
                ':description' => nullIfBlank($scenario['description'] ?? null),
                ':runner_route' => trim((string) ($scenario['runner_route'] ?? 'training/runner')),
                ':next_code' => nullIfBlank($scenario['next_scenario_id'] ?? null),
                ':prerequisites' => encodeStringList($scenario['prerequisites'] ?? []),
                ':sort_order' => $scenarioOrder,
            ];

            $scenarioExists->execute([':code' => $code]);
            $exists = (int) ($scenarioExists->fetchColumn() ?: 0) > 0;
            ($exists ? $updateScenario : $insertScenario)->execute($scenarioParams);
            $scenarioCount++;

            $stepOrder = 10;
            foreach (($scenario['steps'] ?? []) as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $stepNo = (int) ($step['number'] ?? 0);
                if ($stepNo <= 0) {
                    continue;
                }

                $insertStep->execute([
                    ':code' => $code,
                    ':step_no' => $stepNo,
                    ':route' => trim((string) ($step['route'] ?? '')),
                    ':target' => nullIfBlank($step['target'] ?? null),
                    ':title' => trim((string) ($step['title'] ?? ('Step ' . $stepNo))),
                    ':instruction' => trim((string) ($step['instruction'] ?? '')),
                    ':completion_mode' => trim((string) ($step['completion_mode'] ?? 'manual_continue')),
                    ':sample_key' => nullIfBlank($step['sample_key'] ?? null),
                    ':expected_user_sample_key' => nullIfBlank($step['expected_user_sample_key'] ?? null),
                    ':sort_order' => $stepOrder,
                ]);
                $stepCount++;
                $stepOrder += 10;
            }

            $sampleOrder = 10;
            foreach (sampleDefinitionsForScenario($code, $scenario) as $sampleKey => $sampleValue) {
                $sampleKey = trim((string) $sampleKey);
                if ($sampleKey === '') {
                    continue;
                }

                $insertSample->execute([
                    ':code' => $code,
                    ':sample_key' => $sampleKey,
                    ':sample_value' => (string) $sampleValue,
                    ':sort_order' => $sampleOrder,
                ]);
                $sampleCount++;
                $sampleOrder += 10;
            }

            $scenarioOrder += 10;
        }

        $db->commit();
        return ['scenarios' => $scenarioCount, 'steps' => $stepCount, 'samples' => $sampleCount];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function sampleDefinitionsForScenario(string $code, array $scenario): array
{
    $sampleDefs = $scenario['sample_defs'] ?? [];
    if (is_array($sampleDefs) && $sampleDefs !== []) {
        return $sampleDefs;
    }

    return match ($code) {
        TrainingScenarioCatalog::USERS_CREATE_DEMO => [
            'Username' => 'train_user_{stamp}',
            'FirstName' => 'Training',
            'LastName' => 'User',
            'DisplayName' => 'Training User',
            'Email' => 'train_user_{stamp}@example.com',
            'Phone' => '+266 5555 0101',
            'Department' => 'Training Services',
            'JobTitle' => 'Training Officer',
        ],
        TrainingScenarioCatalog::USERS_EDIT_RECORD => [
            'TargetUserID' => '{context.target_user_id}',
            'TargetUsername' => '{context.target_username}',
            'Notes' => 'Reviewed during training on {now_ymd_hm}',
        ],
        default => [],
    };
}

function placeholdersForCodes(array $codes): array
{
    $placeholders = [];
    $params = [];
    foreach (array_values($codes) as $index => $code) {
        $placeholder = ':code' . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $code;
    }

    return ['sql' => implode(', ', $placeholders), 'params' => $params];
}

function encodeStringList(mixed $value): ?string
{
    if (!is_array($value)) {
        return null;
    }

    $items = array_values(array_filter(array_map(static fn($item): string => trim((string) $item), $value)));
    if ($items === []) {
        return null;
    }

    return json_encode($items, JSON_UNESCAPED_SLASHES);
}

function nullIfBlank(mixed $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}
