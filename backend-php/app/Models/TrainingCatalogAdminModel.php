<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class TrainingCatalogAdminModel
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsScenarioCatalog(): bool
    {
        $sql = "
            SELECT
                OBJECT_ID(N'dbo.tblTrainingScenarios', N'U') AS ScenariosTableId,
                OBJECT_ID(N'dbo.tblTrainingScenarioSteps', N'U') AS StepsTableId,
                OBJECT_ID(N'dbo.tblTrainingScenarioSamples', N'U') AS SamplesTableId
        ";
        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
        return (int) ($row['ScenariosTableId'] ?? 0) > 0
            && (int) ($row['StepsTableId'] ?? 0) > 0
            && (int) ($row['SamplesTableId'] ?? 0) > 0;
    }

    public function listScenarios(array $filters = []): array
    {
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(
                s.ScenarioCode LIKE :q
                OR s.ScenarioTitle LIKE :q
                OR ISNULL(s.ScreenFamily, \'\') LIKE :q
                OR ISNULL(s.ModuleName, \'\') LIKE :q
                OR ISNULL(s.Audience, \'\') LIKE :q
            )';
            $params[':q'] = '%' . $q . '%';
        }

        $active = trim((string) ($filters['active'] ?? ''));
        if ($active === '1' || $active === '0') {
            $where[] = 's.ActiveFlag = :active';
            $params[':active'] = (int) $active;
        }

        $module = trim((string) ($filters['module'] ?? ''));
        if ($module !== '') {
            $where[] = 's.ModuleName = :module';
            $params[':module'] = $module;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT
                s.TrainingScenarioID,
                s.ScenarioCode,
                s.ScenarioTitle,
                s.ScreenFamily,
                s.ModuleName,
                s.Audience,
                s.Difficulty,
                s.RunnerRoute,
                s.NextScenarioCode,
                s.ActiveFlag,
                s.SortOrder,
                s.UpdatedDate,
                StepCount = (
                    SELECT COUNT(*)
                    FROM dbo.tblTrainingScenarioSteps st
                    WHERE st.ScenarioCode = s.ScenarioCode
                      AND st.ActiveFlag = 1
                ),
                SampleCount = (
                    SELECT COUNT(*)
                    FROM dbo.tblTrainingScenarioSamples sa
                    WHERE sa.ScenarioCode = s.ScenarioCode
                      AND sa.ActiveFlag = 1
                ),
                TranslationCount =
                    (SELECT COUNT(*) FROM dbo.tblTrainingScenarioTranslations tr WHERE tr.ScenarioCode = s.ScenarioCode)
                    + (SELECT COUNT(*) FROM dbo.tblTrainingScenarioStepTranslations tr WHERE tr.ScenarioCode = s.ScenarioCode)
                    + (SELECT COUNT(*) FROM dbo.tblTrainingScenarioSampleTranslations tr WHERE tr.ScenarioCode = s.ScenarioCode)
            FROM dbo.tblTrainingScenarios s
            {$whereSql}
            ORDER BY s.SortOrder, s.ScenarioTitle, s.ScenarioCode
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listModuleOptions(): array
    {
        $sql = "
            SELECT DISTINCT ModuleName
            FROM dbo.tblTrainingScenarios
            WHERE NULLIF(LTRIM(RTRIM(ISNULL(ModuleName, ''))), '') IS NOT NULL
            ORDER BY ModuleName
        ";
        $stmt = $this->pdo->query($sql);
        return array_map(
            static fn (array $row): string => (string) ($row['ModuleName'] ?? ''),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    public function listScenarioOptions(bool $activeOnly = false): array
    {
        $sql = "
            SELECT ScenarioCode, ScenarioTitle
            FROM dbo.tblTrainingScenarios
            " . ($activeOnly ? 'WHERE ActiveFlag = 1' : '') . "
            ORDER BY SortOrder, ScenarioTitle, ScenarioCode
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getScenario(string $scenarioCode): ?array
    {
        $scenarioCode = trim($scenarioCode);
        if ($scenarioCode === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                TrainingScenarioID,
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
            FROM dbo.tblTrainingScenarios
            WHERE ScenarioCode = :code
        ");
        $stmt->execute([':code' => $scenarioCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['Prerequisites'] = $this->decodeJsonArray((string) ($row['PrerequisitesJson'] ?? ''));
        $row['Samples'] = $this->listSamples($scenarioCode);
        return $row;
    }

    public function saveScenario(array $data, int $updatedBy): string
    {
        $scenarioCode = trim((string) ($data['ScenarioCode'] ?? ''));
        $scenarioTitle = trim((string) ($data['ScenarioTitle'] ?? ''));
        $screenFamily = trim((string) ($data['ScreenFamily'] ?? ''));
        $moduleName = trim((string) ($data['ModuleName'] ?? ''));
        $runnerRoute = trim((string) ($data['RunnerRoute'] ?? ''));

        if ($scenarioCode === '' || $scenarioTitle === '' || $screenFamily === '' || $moduleName === '' || $runnerRoute === '') {
            throw new \RuntimeException('Scenario code, title, screen family, module, and runner route are required.');
        }

        $this->pdo->beginTransaction();
        try {
            $existing = $this->getScenarioRow($scenarioCode);
            $params = [
                ':code' => $scenarioCode,
                ':title' => $scenarioTitle,
                ':screen_family' => $screenFamily,
                ':module_name' => $moduleName,
                ':audience' => $this->nullIfEmpty($data['Audience'] ?? null),
                ':difficulty' => $this->nullIfEmpty($data['Difficulty'] ?? null),
                ':description' => $this->nullIfEmpty($data['Description'] ?? null),
                ':runner_route' => $runnerRoute,
                ':next_scenario_code' => $this->nullIfEmpty($data['NextScenarioCode'] ?? null),
                ':prerequisites_json' => $this->encodeJsonArray($data['Prerequisites'] ?? []),
                ':active_flag' => !empty($data['ActiveFlag']) ? 1 : 0,
                ':sort_order' => max(0, (int) ($data['SortOrder'] ?? 0)),
                ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
            ];

            if ($existing !== null) {
                $sql = "
                    UPDATE dbo.tblTrainingScenarios
                    SET ScenarioTitle = :title,
                        ScreenFamily = :screen_family,
                        ModuleName = :module_name,
                        Audience = :audience,
                        Difficulty = :difficulty,
                        Description = :description,
                        RunnerRoute = :runner_route,
                        NextScenarioCode = :next_scenario_code,
                        PrerequisitesJson = :prerequisites_json,
                        ActiveFlag = :active_flag,
                        SortOrder = :sort_order,
                        UpdatedBy = :updated_by,
                        UpdatedDate = SYSDATETIME()
                    WHERE ScenarioCode = :code
                ";
            } else {
                $sql = "
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
                        SortOrder,
                        CreatedBy,
                        UpdatedBy
                    )
                    VALUES
                    (
                        :code,
                        :title,
                        :screen_family,
                        :module_name,
                        :audience,
                        :difficulty,
                        :description,
                        :runner_route,
                        :next_scenario_code,
                        :prerequisites_json,
                        :active_flag,
                        :sort_order,
                        :updated_by,
                        :updated_by
                    )
                ";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $this->syncScenarioSamples($scenarioCode, $this->normalizeSamples($data['Samples'] ?? []), $updatedBy);

            $this->pdo->commit();
            return $scenarioCode;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function listSteps(string $scenarioCode): array
    {
        $scenarioCode = trim($scenarioCode);
        if ($scenarioCode === '') {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT
                TrainingScenarioStepID,
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
                SortOrder,
                UpdatedDate
            FROM dbo.tblTrainingScenarioSteps
            WHERE ScenarioCode = :code
            ORDER BY SortOrder, StepNo
        ");
        $stmt->execute([':code' => $scenarioCode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getStep(string $scenarioCode, int $stepNo): ?array
    {
        $scenarioCode = trim($scenarioCode);
        if ($scenarioCode === '' || $stepNo <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                TrainingScenarioStepID,
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
            FROM dbo.tblTrainingScenarioSteps
            WHERE ScenarioCode = :code
              AND StepNo = :step_no
        ");
        $stmt->execute([
            ':code' => $scenarioCode,
            ':step_no' => $stepNo,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveStep(array $data, int $updatedBy): int
    {
        $scenarioCode = trim((string) ($data['ScenarioCode'] ?? ''));
        $stepNo = (int) ($data['StepNo'] ?? 0);
        $oldStepNo = (int) ($data['OldStepNo'] ?? 0);
        $route = trim((string) ($data['Route'] ?? ''));
        $stepTitle = trim((string) ($data['StepTitle'] ?? ''));
        $instructionText = trim((string) ($data['InstructionText'] ?? ''));
        $completionMode = trim((string) ($data['CompletionMode'] ?? ''));

        if ($scenarioCode === '' || $stepNo <= 0 || $route === '' || $stepTitle === '' || $instructionText === '' || $completionMode === '') {
            throw new \RuntimeException('Scenario, step number, route, title, instruction, and completion mode are required.');
        }

        $existing = $oldStepNo > 0 ? $this->getStep($scenarioCode, $oldStepNo) : $this->getStep($scenarioCode, $stepNo);
        $params = [
            ':scenario_code' => $scenarioCode,
            ':step_no' => $stepNo,
            ':old_step_no' => $oldStepNo > 0 ? $oldStepNo : $stepNo,
            ':route' => $route,
            ':target' => $this->nullIfEmpty($data['TargetElementID'] ?? null),
            ':step_title' => $stepTitle,
            ':instruction_text' => $instructionText,
            ':completion_mode' => $completionMode,
            ':sample_key' => $this->nullIfEmpty($data['SampleKey'] ?? null),
            ':expected_user_sample_key' => $this->nullIfEmpty($data['ExpectedUserSampleKey'] ?? null),
            ':active_flag' => !empty($data['ActiveFlag']) ? 1 : 0,
            ':sort_order' => max(0, (int) ($data['SortOrder'] ?? $stepNo)),
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
        ];

        $this->pdo->beginTransaction();
        try {
            if ($existing !== null) {
                $stmt = $this->pdo->prepare("
                    UPDATE dbo.tblTrainingScenarioSteps
                    SET StepNo = :step_no,
                        Route = :route,
                        TargetElementID = :target,
                        StepTitle = :step_title,
                        InstructionText = :instruction_text,
                        CompletionMode = :completion_mode,
                        SampleKey = :sample_key,
                        ExpectedUserSampleKey = :expected_user_sample_key,
                        ActiveFlag = :active_flag,
                        SortOrder = :sort_order,
                        UpdatedBy = :updated_by,
                        UpdatedDate = SYSDATETIME()
                    WHERE ScenarioCode = :scenario_code
                      AND StepNo = :old_step_no
                ");
                $stmt->execute($params);

                if ($oldStepNo > 0 && $oldStepNo !== $stepNo) {
                    $moveTranslations = $this->pdo->prepare("
                        UPDATE dbo.tblTrainingScenarioStepTranslations
                        SET StepNo = :step_no,
                            UpdatedBy = :updated_by,
                            UpdatedDate = SYSDATETIME()
                        WHERE ScenarioCode = :scenario_code
                          AND StepNo = :old_step_no
                    ");
                    $moveTranslations->execute([
                        ':scenario_code' => $scenarioCode,
                        ':step_no' => $stepNo,
                        ':old_step_no' => $oldStepNo,
                        ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
                    ]);
                }
            } else {
                $stmt = $this->pdo->prepare("
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
                        SortOrder,
                        CreatedBy,
                        UpdatedBy
                    )
                    VALUES
                    (
                        :scenario_code,
                        :step_no,
                        :route,
                        :target,
                        :step_title,
                        :instruction_text,
                        :completion_mode,
                        :sample_key,
                        :expected_user_sample_key,
                        :active_flag,
                        :sort_order,
                        :updated_by,
                        :updated_by
                    )
                ");
                $stmt->execute($params);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return $stepNo;
    }

    public function archiveStep(string $scenarioCode, int $stepNo, int $updatedBy): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE dbo.tblTrainingScenarioSteps
            SET ActiveFlag = 0,
                UpdatedBy = :updated_by,
                UpdatedDate = SYSDATETIME()
            WHERE ScenarioCode = :scenario_code
              AND StepNo = :step_no
        ");
        $stmt->execute([
            ':scenario_code' => trim($scenarioCode),
            ':step_no' => $stepNo,
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
        ]);
    }

    public function getTranslationsBundle(string $scenarioCode, string $languageCode): array
    {
        $scenarioCode = trim($scenarioCode);
        $languageCode = trim($languageCode);
        $scenario = $this->getScenario($scenarioCode);
        if ($scenario === null || $languageCode === '') {
            return [
                'scenario' => [],
                'steps' => [],
                'samples' => [],
            ];
        }

        $scenarioTranslationStmt = $this->pdo->prepare("
            SELECT ScenarioTitle, ModuleName, Audience, Description, PrerequisitesJson
            FROM dbo.tblTrainingScenarioTranslations
            WHERE ScenarioCode = :code
              AND LanguageCode = :language
        ");
        $scenarioTranslationStmt->execute([
            ':code' => $scenarioCode,
            ':language' => $languageCode,
        ]);
        $scenarioTranslation = $scenarioTranslationStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $scenarioTranslation['Prerequisites'] = $this->decodeJsonArray((string) ($scenarioTranslation['PrerequisitesJson'] ?? ''));

        $stepRows = $this->listSteps($scenarioCode);
        $stepTranslationStmt = $this->pdo->prepare("
            SELECT StepNo, StepTitle, InstructionText
            FROM dbo.tblTrainingScenarioStepTranslations
            WHERE ScenarioCode = :code
              AND LanguageCode = :language
        ");
        $stepTranslationStmt->execute([
            ':code' => $scenarioCode,
            ':language' => $languageCode,
        ]);
        $stepTranslations = [];
        foreach ($stepTranslationStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $stepTranslations[(int) ($row['StepNo'] ?? 0)] = $row;
        }

        $sampleRows = $scenario['Samples'] ?? [];
        $sampleTranslationStmt = $this->pdo->prepare("
            SELECT SampleKey, SampleValueTemplate
            FROM dbo.tblTrainingScenarioSampleTranslations
            WHERE ScenarioCode = :code
              AND LanguageCode = :language
        ");
        $sampleTranslationStmt->execute([
            ':code' => $scenarioCode,
            ':language' => $languageCode,
        ]);
        $sampleTranslations = [];
        foreach ($sampleTranslationStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $sampleTranslations[(string) ($row['SampleKey'] ?? '')] = $row;
        }

        return [
            'scenario' => $scenarioTranslation,
            'steps' => $stepRows,
            'stepTranslations' => $stepTranslations,
            'samples' => $sampleRows,
            'sampleTranslations' => $sampleTranslations,
        ];
    }

    public function saveTranslationsBundle(string $scenarioCode, string $languageCode, array $data, int $updatedBy): void
    {
        $scenarioCode = trim($scenarioCode);
        $languageCode = trim($languageCode);
        if ($scenarioCode === '' || $languageCode === '') {
            throw new \RuntimeException('Scenario and language are required.');
        }

        $this->pdo->beginTransaction();
        try {
            $scenarioTitle = trim((string) ($data['ScenarioTitle'] ?? ''));
            $moduleName = trim((string) ($data['ModuleName'] ?? ''));
            $audience = trim((string) ($data['Audience'] ?? ''));
            $description = trim((string) ($data['Description'] ?? ''));
            $prerequisites = $this->normalizeTextLines($data['Prerequisites'] ?? []);

            $hasScenarioTranslation = $scenarioTitle !== '' || $moduleName !== '' || $audience !== '' || $description !== '' || $prerequisites !== [];
            if ($hasScenarioTranslation) {
                $stmt = $this->pdo->prepare("
                    MERGE dbo.tblTrainingScenarioTranslations AS target
                    USING (SELECT :code AS ScenarioCode, :language AS LanguageCode) AS source
                    ON target.ScenarioCode = source.ScenarioCode
                   AND target.LanguageCode = source.LanguageCode
                    WHEN MATCHED THEN
                        UPDATE SET
                            ScenarioTitle = :title,
                            ModuleName = :module_name,
                            Audience = :audience,
                            Description = :description,
                            PrerequisitesJson = :prerequisites_json,
                            UpdatedBy = :updated_by,
                            UpdatedDate = SYSDATETIME()
                    WHEN NOT MATCHED THEN
                        INSERT (ScenarioCode, LanguageCode, ScenarioTitle, ModuleName, Audience, Description, PrerequisitesJson, CreatedBy, UpdatedBy)
                        VALUES (:code, :language, :title, :module_name, :audience, :description, :prerequisites_json, :created_by, :updated_by);
                ");
                $stmt->execute([
                    ':code' => $scenarioCode,
                    ':language' => $languageCode,
                    ':title' => $this->nullIfEmpty($scenarioTitle),
                    ':module_name' => $this->nullIfEmpty($moduleName),
                    ':audience' => $this->nullIfEmpty($audience),
                    ':description' => $this->nullIfEmpty($description),
                    ':prerequisites_json' => $this->encodeJsonArray($prerequisites),
                    ':created_by' => $updatedBy > 0 ? $updatedBy : null,
                    ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    DELETE FROM dbo.tblTrainingScenarioTranslations
                    WHERE ScenarioCode = :code
                      AND LanguageCode = :language
                ");
                $stmt->execute([
                    ':code' => $scenarioCode,
                    ':language' => $languageCode,
                ]);
            }

            foreach (($data['StepTitles'] ?? []) as $stepNo => $title) {
                $stepNo = (int) $stepNo;
                if ($stepNo <= 0) {
                    continue;
                }
                $instruction = trim((string) (($data['StepInstructions'] ?? [])[$stepNo] ?? ''));
                $title = trim((string) $title);

                if ($title === '' && $instruction === '') {
                    $stmt = $this->pdo->prepare("
                        DELETE FROM dbo.tblTrainingScenarioStepTranslations
                        WHERE ScenarioCode = :code
                          AND LanguageCode = :language
                          AND StepNo = :step_no
                    ");
                    $stmt->execute([
                        ':code' => $scenarioCode,
                        ':language' => $languageCode,
                        ':step_no' => $stepNo,
                    ]);
                    continue;
                }

                $stmt = $this->pdo->prepare("
                    MERGE dbo.tblTrainingScenarioStepTranslations AS target
                    USING (SELECT :code AS ScenarioCode, :language AS LanguageCode, :step_no AS StepNo) AS source
                    ON target.ScenarioCode = source.ScenarioCode
                   AND target.LanguageCode = source.LanguageCode
                   AND target.StepNo = source.StepNo
                    WHEN MATCHED THEN
                        UPDATE SET
                            StepTitle = :title,
                            InstructionText = :instruction_text,
                            UpdatedBy = :updated_by,
                            UpdatedDate = SYSDATETIME()
                    WHEN NOT MATCHED THEN
                        INSERT (ScenarioCode, StepNo, LanguageCode, StepTitle, InstructionText, CreatedBy, UpdatedBy)
                        VALUES (:code, :step_no, :language, :title, :instruction_text, :created_by, :updated_by);
                ");
                $stmt->execute([
                    ':code' => $scenarioCode,
                    ':language' => $languageCode,
                    ':step_no' => $stepNo,
                    ':title' => $this->nullIfEmpty($title),
                    ':instruction_text' => $this->nullIfEmpty($instruction),
                    ':created_by' => $updatedBy > 0 ? $updatedBy : null,
                    ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
                ]);
            }

            foreach (($data['SampleValues'] ?? []) as $sampleKey => $sampleValue) {
                $sampleKey = trim((string) $sampleKey);
                if ($sampleKey === '') {
                    continue;
                }
                $sampleValue = trim((string) $sampleValue);
                if ($sampleValue === '') {
                    $stmt = $this->pdo->prepare("
                        DELETE FROM dbo.tblTrainingScenarioSampleTranslations
                        WHERE ScenarioCode = :code
                          AND LanguageCode = :language
                          AND SampleKey = :sample_key
                    ");
                    $stmt->execute([
                        ':code' => $scenarioCode,
                        ':language' => $languageCode,
                        ':sample_key' => $sampleKey,
                    ]);
                    continue;
                }

                $stmt = $this->pdo->prepare("
                    MERGE dbo.tblTrainingScenarioSampleTranslations AS target
                    USING (SELECT :code AS ScenarioCode, :language AS LanguageCode, :sample_key AS SampleKey) AS source
                    ON target.ScenarioCode = source.ScenarioCode
                   AND target.LanguageCode = source.LanguageCode
                   AND target.SampleKey = source.SampleKey
                    WHEN MATCHED THEN
                        UPDATE SET
                            SampleValueTemplate = :sample_value,
                            UpdatedBy = :updated_by,
                            UpdatedDate = SYSDATETIME()
                    WHEN NOT MATCHED THEN
                        INSERT (ScenarioCode, SampleKey, LanguageCode, SampleValueTemplate, CreatedBy, UpdatedBy)
                        VALUES (:code, :sample_key, :language, :sample_value, :created_by, :updated_by);
                ");
                $stmt->execute([
                    ':code' => $scenarioCode,
                    ':language' => $languageCode,
                    ':sample_key' => $sampleKey,
                    ':sample_value' => $sampleValue,
                    ':created_by' => $updatedBy > 0 ? $updatedBy : null,
                    ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function listTranslationLanguages(string $scenarioCode): array
    {
        $scenarioCode = trim($scenarioCode);
        if ($scenarioCode === '') {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT LanguageCode
            FROM (
                SELECT LanguageCode FROM dbo.tblTrainingScenarioTranslations WHERE ScenarioCode = :scenario_code_1
                UNION
                SELECT LanguageCode FROM dbo.tblTrainingScenarioStepTranslations WHERE ScenarioCode = :scenario_code_2
                UNION
                SELECT LanguageCode FROM dbo.tblTrainingScenarioSampleTranslations WHERE ScenarioCode = :scenario_code_3
            ) x
            ORDER BY LanguageCode
        ");
        $stmt->execute([
            ':scenario_code_1' => $scenarioCode,
            ':scenario_code_2' => $scenarioCode,
            ':scenario_code_3' => $scenarioCode,
        ]);
        return array_map(
            static fn(array $row): string => (string) ($row['LanguageCode'] ?? ''),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    private function getScenarioRow(string $scenarioCode): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT TrainingScenarioID, ScenarioCode
            FROM dbo.tblTrainingScenarios
            WHERE ScenarioCode = :code
        ");
        $stmt->execute([':code' => $scenarioCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function listSamples(string $scenarioCode): array
    {
        $stmt = $this->pdo->prepare("
            SELECT SampleKey, SampleValueTemplate, ActiveFlag, SortOrder
            FROM dbo.tblTrainingScenarioSamples
            WHERE ScenarioCode = :code
            ORDER BY SortOrder, SampleKey
        ");
        $stmt->execute([':code' => $scenarioCode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function syncScenarioSamples(string $scenarioCode, array $samples, int $updatedBy): void
    {
        $existing = $this->listSamples($scenarioCode);
        $existingKeys = [];
        foreach ($existing as $row) {
            $existingKeys[] = (string) ($row['SampleKey'] ?? '');
        }

        foreach ($samples as $index => $sample) {
            $sampleKey = (string) ($sample['SampleKey'] ?? '');
            if ($sampleKey === '') {
                continue;
            }

            $stmt = $this->pdo->prepare("
                MERGE dbo.tblTrainingScenarioSamples AS target
                USING (SELECT :code AS ScenarioCode, :sample_key AS SampleKey) AS source
                ON target.ScenarioCode = source.ScenarioCode
               AND target.SampleKey = source.SampleKey
                WHEN MATCHED THEN
                    UPDATE SET
                        SampleValueTemplate = :sample_value,
                        ActiveFlag = 1,
                        SortOrder = :sort_order,
                        UpdatedBy = :updated_by,
                        UpdatedDate = SYSDATETIME()
                WHEN NOT MATCHED THEN
                    INSERT (ScenarioCode, SampleKey, SampleValueTemplate, ActiveFlag, SortOrder, CreatedBy, UpdatedBy)
                    VALUES (:code, :sample_key, :sample_value, 1, :sort_order, :created_by, :updated_by);
            ");
            $stmt->execute([
                ':code' => $scenarioCode,
                ':sample_key' => $sampleKey,
                ':sample_value' => (string) ($sample['SampleValueTemplate'] ?? ''),
                ':sort_order' => ($index + 1) * 10,
                ':created_by' => $updatedBy > 0 ? $updatedBy : null,
                ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
            ]);
        }

        $newKeys = array_map(static fn(array $row): string => (string) ($row['SampleKey'] ?? ''), $samples);
        $keysToDelete = array_values(array_diff($existingKeys, $newKeys));
        if ($keysToDelete !== []) {
            $params = [':code' => $scenarioCode];
            $placeholders = [];
            foreach ($keysToDelete as $index => $sampleKey) {
                $placeholder = ':delete_key_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $sampleKey;
            }

            $inClause = implode(', ', $placeholders);
            $deleteSamples = $this->pdo->prepare("
                DELETE FROM dbo.tblTrainingScenarioSamples
                WHERE ScenarioCode = :code
                  AND SampleKey IN ({$inClause})
            ");
            $deleteSamples->execute($params);

            $deleteSampleTranslations = $this->pdo->prepare("
                DELETE FROM dbo.tblTrainingScenarioSampleTranslations
                WHERE ScenarioCode = :code
                  AND SampleKey IN ({$inClause})
            ");
            $deleteSampleTranslations->execute($params);
        }
    }

    private function normalizeSamples(mixed $value): array
    {
        $rows = [];
        if (is_string($value)) {
            $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || !str_contains($line, '=')) {
                    continue;
                }
                [$sampleKey, $sampleValue] = array_map('trim', explode('=', $line, 2));
                if ($sampleKey === '') {
                    continue;
                }
                $rows[] = [
                    'SampleKey' => $sampleKey,
                    'SampleValueTemplate' => $sampleValue,
                ];
            }
        } elseif (is_array($value)) {
            foreach ($value as $sampleKey => $sampleValue) {
                $sampleKey = trim((string) $sampleKey);
                if ($sampleKey === '') {
                    continue;
                }
                $rows[] = [
                    'SampleKey' => $sampleKey,
                    'SampleValueTemplate' => trim((string) $sampleValue),
                ];
            }
        }
        return $rows;
    }

    private function normalizeTextLines(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn(mixed $line): string => trim((string) $line),
                $value
            ), static fn(string $line): bool => $line !== ''));
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];
        return array_values(array_filter(array_map(
            static fn(string $line): string => trim($line),
            $lines
        ), static fn(string $line): bool => $line !== ''));
    }

    private function decodeJsonArray(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $decoded), static fn(string $line): bool => trim($line) !== ''));
    }

    private function encodeJsonArray(mixed $value): ?string
    {
        $rows = $this->normalizeTextLines($value);
        if ($rows === []) {
            return null;
        }
        return (string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
