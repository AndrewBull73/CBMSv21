<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class TrainingScenarioModel
{
    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsScenarioCatalog(): bool
    {
        $sql = "
            SELECT
                OBJECT_ID(N'dbo.tblTrainingScenarios', N'U') AS ScenariosTableId,
                OBJECT_ID(N'dbo.tblTrainingScenarioSteps', N'U') AS StepsTableId,
                OBJECT_ID(N'dbo.tblTrainingScenarioSamples', N'U') AS SamplesTableId
        ";
        $row = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
        return (int) ($row['ScenariosTableId'] ?? 0) > 0
            && (int) ($row['StepsTableId'] ?? 0) > 0
            && (int) ($row['SamplesTableId'] ?? 0) > 0;
    }

    public function listAllActive(?string $languageCode = null): array
    {
        if (!$this->supportsScenarioCatalog()) {
            return [];
        }

        $stmt = $this->db->query("
            SELECT
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
            WHERE ActiveFlag = 1
            ORDER BY SortOrder, ScenarioTitle
        ");
        $scenarios = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $this->hydrateScenarioCollection($scenarios, $languageCode);
    }

    public function findByCode(string $scenarioCode, ?string $languageCode = null): ?array
    {
        $scenarioCode = trim($scenarioCode);
        if ($scenarioCode === '' || !$this->supportsScenarioCatalog()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
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
              AND ActiveFlag = 1
        ");
        $stmt->execute([':code' => $scenarioCode]);
        $scenario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$scenario) {
            return null;
        }

        $rows = $this->hydrateScenarioCollection([$scenario], $languageCode);
        return $rows[0] ?? null;
    }

    private function hydrateScenarioCollection(array $scenarioRows, ?string $languageCode = null): array
    {
        if ($scenarioRows === []) {
            return [];
        }

        $codes = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['ScenarioCode'] ?? '')),
            $scenarioRows
        )));
        if ($codes === []) {
            return [];
        }

        $scenarioTranslations = $this->loadScenarioTranslationsByScenario($codes, $languageCode);
        $stepsByScenario = $this->loadStepsByScenario($codes, $languageCode);
        $samplesByScenario = $this->loadSamplesByScenario($codes, $languageCode);

        $results = [];
        foreach ($scenarioRows as $row) {
            $code = trim((string) ($row['ScenarioCode'] ?? ''));
            if ($code === '') {
                continue;
            }

            $translation = $scenarioTranslations[$code] ?? [];
            $prerequisites = [];
            $rawPrerequisites = trim((string) (($translation['PrerequisitesJson'] ?? '') ?: ($row['PrerequisitesJson'] ?? '')));
            if ($rawPrerequisites !== '') {
                $decoded = json_decode($rawPrerequisites, true);
                if (is_array($decoded)) {
                    $prerequisites = array_values(array_filter(array_map('strval', $decoded)));
                }
            }

            $results[] = [
                'id' => $code,
                'title' => (string) (($translation['ScenarioTitle'] ?? '') ?: ($row['ScenarioTitle'] ?? $code)),
                'screen_family' => (string) ($row['ScreenFamily'] ?? ''),
                'module' => (string) (($translation['ModuleName'] ?? '') ?: ($row['ModuleName'] ?? '')),
                'audience' => (string) (($translation['Audience'] ?? '') ?: ($row['Audience'] ?? '')),
                'difficulty' => (string) ($row['Difficulty'] ?? ''),
                'description' => (string) (($translation['Description'] ?? '') ?: ($row['Description'] ?? '')),
                'runner_route' => (string) ($row['RunnerRoute'] ?? ''),
                'next_scenario_id' => trim((string) ($row['NextScenarioCode'] ?? '')),
                'sort_order' => (int) ($row['SortOrder'] ?? 0),
                'prerequisites' => $prerequisites,
                'steps' => $stepsByScenario[$code] ?? [],
                'sample_defs' => $samplesByScenario[$code] ?? [],
            ];
        }

        return $results;
    }

    private function loadScenarioTranslationsByScenario(array $codes, ?string $languageCode = null): array
    {
        $languageCode = trim((string) $languageCode);
        if ($languageCode === '' || strtolower($languageCode) === 'en') {
            return [];
        }

        $tableExists = (int) ($this->db->query("SELECT OBJECT_ID(N'dbo.tblTrainingScenarioTranslations', N'U')")->fetchColumn() ?: 0) > 0;
        if (!$tableExists) {
            return [];
        }

        $placeholders = [];
        $params = [':lang' => $languageCode];
        foreach ($codes as $index => $code) {
            $placeholder = ':tcode' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $code;
        }

        $stmt = $this->db->prepare("
            SELECT
                ScenarioCode,
                ScenarioTitle,
                ModuleName,
                Audience,
                Description,
                PrerequisitesJson
            FROM dbo.tblTrainingScenarioTranslations
            WHERE LanguageCode = :lang
              AND ScenarioCode IN (" . implode(', ', $placeholders) . ")
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['ScenarioCode'] ?? ''));
            if ($code === '') {
                continue;
            }
            $map[$code] = $row;
        }
        return $map;
    }

    private function loadStepsByScenario(array $codes, ?string $languageCode = null): array
    {
        $placeholders = [];
        $params = [];
        foreach ($codes as $index => $code) {
            $placeholder = ':code' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $code;
        }

        $stmt = $this->db->prepare("
            SELECT
                ScenarioCode,
                StepNo,
                Route,
                TargetElementID,
                StepTitle,
                InstructionText,
                CompletionMode,
                SampleKey,
                ExpectedUserSampleKey
            FROM dbo.tblTrainingScenarioSteps
            WHERE ActiveFlag = 1
              AND ScenarioCode IN (" . implode(', ', $placeholders) . ")
            ORDER BY ScenarioCode, SortOrder, StepNo
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stepTranslations = $this->loadStepTranslationsByScenario($codes, $languageCode);

        $map = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['ScenarioCode'] ?? ''));
            if ($code === '') {
                continue;
            }
            $stepNo = (int) ($row['StepNo'] ?? 0);
            $translation = $stepTranslations[$code][$stepNo] ?? [];
            $map[$code][] = [
                'number' => $stepNo,
                'route' => (string) ($row['Route'] ?? ''),
                'target' => (string) ($row['TargetElementID'] ?? ''),
                'title' => (string) (($translation['StepTitle'] ?? '') ?: ($row['StepTitle'] ?? '')),
                'instruction' => (string) (($translation['InstructionText'] ?? '') ?: ($row['InstructionText'] ?? '')),
                'completion_mode' => (string) ($row['CompletionMode'] ?? ''),
                'sample_key' => trim((string) ($row['SampleKey'] ?? '')),
                'expected_user_sample_key' => trim((string) ($row['ExpectedUserSampleKey'] ?? '')),
            ];
        }

        return $map;
    }

    private function loadStepTranslationsByScenario(array $codes, ?string $languageCode = null): array
    {
        $languageCode = trim((string) $languageCode);
        if ($languageCode === '' || strtolower($languageCode) === 'en') {
            return [];
        }

        $tableExists = (int) ($this->db->query("SELECT OBJECT_ID(N'dbo.tblTrainingScenarioStepTranslations', N'U')")->fetchColumn() ?: 0) > 0;
        if (!$tableExists) {
            return [];
        }

        $placeholders = [];
        $params = [':lang' => $languageCode];
        foreach ($codes as $index => $code) {
            $placeholder = ':scode' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $code;
        }

        $stmt = $this->db->prepare("
            SELECT
                ScenarioCode,
                StepNo,
                StepTitle,
                InstructionText
            FROM dbo.tblTrainingScenarioStepTranslations
            WHERE LanguageCode = :lang
              AND ScenarioCode IN (" . implode(', ', $placeholders) . ")
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['ScenarioCode'] ?? ''));
            $stepNo = (int) ($row['StepNo'] ?? 0);
            if ($code === '' || $stepNo <= 0) {
                continue;
            }
            $map[$code][$stepNo] = $row;
        }
        return $map;
    }

    private function loadSamplesByScenario(array $codes, ?string $languageCode = null): array
    {
        $placeholders = [];
        $params = [];
        foreach ($codes as $index => $code) {
            $placeholder = ':sampleCode' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $code;
        }

        $stmt = $this->db->prepare("
            SELECT
                ScenarioCode,
                SampleKey,
                SampleValueTemplate
            FROM dbo.tblTrainingScenarioSamples
            WHERE ActiveFlag = 1
              AND ScenarioCode IN (" . implode(', ', $placeholders) . ")
            ORDER BY ScenarioCode, SortOrder, SampleKey
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $sampleTranslations = $this->loadSampleTranslationsByScenario($codes, $languageCode);

        $map = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['ScenarioCode'] ?? ''));
            $sampleKey = trim((string) ($row['SampleKey'] ?? ''));
            if ($code === '' || $sampleKey === '') {
                continue;
            }
            $translatedValue = (string) ($sampleTranslations[$code][$sampleKey]['SampleValueTemplate'] ?? '');
            $map[$code][$sampleKey] = $translatedValue !== ''
                ? $translatedValue
                : (string) ($row['SampleValueTemplate'] ?? '');
        }

        return $map;
    }

    private function loadSampleTranslationsByScenario(array $codes, ?string $languageCode = null): array
    {
        $languageCode = trim((string) $languageCode);
        if ($languageCode === '' || strtolower($languageCode) === 'en') {
            return [];
        }

        $tableExists = (int) ($this->db->query("SELECT OBJECT_ID(N'dbo.tblTrainingScenarioSampleTranslations', N'U')")->fetchColumn() ?: 0) > 0;
        if (!$tableExists) {
            return [];
        }

        $placeholders = [];
        $params = [':lang' => $languageCode];
        foreach ($codes as $index => $code) {
            $placeholder = ':sampleScenario' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $code;
        }

        $stmt = $this->db->prepare("
            SELECT
                ScenarioCode,
                SampleKey,
                SampleValueTemplate
            FROM dbo.tblTrainingScenarioSampleTranslations
            WHERE LanguageCode = :lang
              AND ScenarioCode IN (" . implode(', ', $placeholders) . ")
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['ScenarioCode'] ?? ''));
            $sampleKey = trim((string) ($row['SampleKey'] ?? ''));
            if ($code === '' || $sampleKey === '') {
                continue;
            }
            $map[$code][$sampleKey] = $row;
        }

        return $map;
    }
}
