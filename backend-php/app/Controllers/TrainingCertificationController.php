<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\TrainingCatalogAdminModel;
use App\Models\TrainingCertificationModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/training_features.php';
require_once __DIR__ . '/../Models/TrainingCertificationModel.php';
require_once __DIR__ . '/../Models/TrainingCatalogAdminModel.php';

final class TrainingCertificationController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['TRAINING_USER', 'TRAINING_ADMIN', 'TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'modules' => ['auth' => true, 'permsAny' => ['TRAINING_USER', 'TRAINING_ADMIN', 'TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'start' => ['auth' => true, 'permsAny' => ['TRAINING_USER', 'TRAINING_ADMIN', 'TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'take' => ['auth' => true, 'permsAny' => ['TRAINING_USER', 'TRAINING_ADMIN', 'TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'submit' => ['auth' => true, 'permsAny' => ['TRAINING_USER', 'TRAINING_ADMIN', 'TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'result' => ['auth' => true, 'permsAny' => ['TRAINING_USER', 'TRAINING_ADMIN', 'TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'admin' => ['auth' => true, 'permsAny' => ['TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'form' => ['auth' => true, 'permsAny' => ['TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'questions' => ['auth' => true, 'permsAny' => ['TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'question-form' => ['auth' => true, 'permsAny' => ['TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'questionForm' => ['auth' => true, 'permsAny' => ['TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'save-question' => ['auth' => true, 'permsAny' => ['TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'saveQuestion' => ['auth' => true, 'permsAny' => ['TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        'results' => ['auth' => true, 'permsAny' => ['TRAINING_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function modules(): void
    {
        $this->ensureTrainingEnabled();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
            'active' => '1',
        ];

        $model = $this->model();
        $installed = $model->supportsCertificationTables();
        $userId = (int) SessionHelper::get('auth.user_id', 0);
        $this->render('trainingcertifications/Modules', [
            'title' => 'Training Certifications',
            'tableInstalled' => $installed,
            'rows' => $installed ? $model->listCertifications($filters, $userId) : [],
            'filters' => $filters,
            'moduleOptions' => $installed ? $model->listModuleOptions() : [],
            'canManageTraining' => $this->canManageTraining(),
            'createTableScript' => 'backend-php/config/sql/create_training_certification_features.sql',
        ]);
    }

    public function admin(): void
    {
        $this->ensureTrainingEnabled();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $model = $this->model();
        $installed = $model->supportsCertificationTables();
        $this->render('trainingcertifications/AdminList', [
            'title' => 'Certification Catalogue',
            'tableInstalled' => $installed,
            'rows' => $installed ? $model->listCertifications($filters) : [],
            'filters' => $filters,
            'moduleOptions' => $installed ? $model->listModuleOptions() : [],
            'createTableScript' => 'backend-php/config/sql/create_training_certification_features.sql',
        ]);
    }

    public function form(): void
    {
        $this->ensureTrainingEnabled();
        $model = $this->model();
        $installed = $model->supportsCertificationTables();
        $code = trim((string) ($_GET['certification_code'] ?? ''));
        $record = ($installed && $code !== '') ? $model->getCertification($code) : null;

        $moduleOptions = [];
        if ($this->catalogModel()->supportsScenarioCatalog()) {
            $moduleOptions = $this->catalogModel()->listModuleOptions();
        }
        if ($installed) {
            $moduleOptions = array_values(array_unique(array_merge($moduleOptions, $model->listModuleOptions())));
            sort($moduleOptions, SORT_NATURAL | SORT_FLAG_CASE);
        }

        $this->render('trainingcertifications/Form', [
            'title' => $record ? 'Edit Certification' : 'Create Certification',
            'tableInstalled' => $installed,
            'record' => $record,
            'moduleOptions' => $moduleOptions,
        ]);
    }

    public function save(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        $payload = [
            'CertificationCode' => trim((string) ($_POST['CertificationCode'] ?? '')),
            'CertificationTitle' => trim((string) ($_POST['CertificationTitle'] ?? '')),
            'ModuleName' => trim((string) ($_POST['ModuleName'] ?? '')),
            'Description' => trim((string) ($_POST['Description'] ?? '')),
            'PassPercent' => (float) ($_POST['PassPercent'] ?? 80),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'SortOrder' => (int) ($_POST['SortOrder'] ?? 0),
        ];

        try {
            $this->ensureCertificationInstalled();
            $code = $this->model()->saveCertification($payload, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Certification saved.');
            header('Location: index.php?route=training-certifications/form&certification_code=' . urlencode($code));
            return;
        } catch (\Throwable $e) {
            $this->flashError('Certification save failed: ' . $e->getMessage());
            $suffix = $payload['CertificationCode'] !== '' ? '&certification_code=' . urlencode($payload['CertificationCode']) : '';
            header('Location: index.php?route=training-certifications/form' . $suffix);
            return;
        }
    }

    public function questions(): void
    {
        $this->ensureTrainingEnabled();
        $model = $this->model();
        $installed = $model->supportsCertificationTables();
        $code = trim((string) ($_GET['certification_code'] ?? ''));
        if ($installed && $code === '') {
            $first = $model->listCertifications(['active' => '']);
            $code = trim((string) ($first[0]['CertificationCode'] ?? ''));
        }

        $this->render('trainingcertifications/Questions', [
            'title' => 'Certification Questions',
            'tableInstalled' => $installed,
            'certificationCode' => $code,
            'certification' => ($installed && $code !== '') ? $model->getCertification($code) : null,
            'rows' => ($installed && $code !== '') ? $model->listQuestions($code) : [],
            'certificationOptions' => $installed ? $model->listCertifications(['active' => '']) : [],
        ]);
    }

    public function questionForm(): void
    {
        $this->ensureTrainingEnabled();
        $model = $this->model();
        $installed = $model->supportsCertificationTables();
        $code = trim((string) ($_GET['certification_code'] ?? ''));
        $questionNo = (int) ($_GET['question_no'] ?? 0);
        $record = ($installed && $code !== '' && $questionNo > 0) ? $model->getQuestion($code, $questionNo) : null;

        $this->render('trainingcertifications/QuestionForm', [
            'title' => $record ? 'Edit Certification Question' : 'Create Certification Question',
            'tableInstalled' => $installed,
            'certificationCode' => $code,
            'certification' => ($installed && $code !== '') ? $model->getCertification($code) : null,
            'record' => $record,
            'certificationOptions' => $installed ? $model->listCertifications(['active' => '']) : [],
        ]);
    }

    public function saveQuestion(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        $code = trim((string) ($_POST['CertificationCode'] ?? ''));
        $payload = [
            'CertificationCode' => $code,
            'OldQuestionNo' => (int) ($_POST['OldQuestionNo'] ?? 0),
            'QuestionNo' => (int) ($_POST['QuestionNo'] ?? 0),
            'QuestionText' => trim((string) ($_POST['QuestionText'] ?? '')),
            'Options' => trim((string) ($_POST['Options'] ?? '')),
            'CorrectOptionKey' => trim((string) ($_POST['CorrectOptionKey'] ?? '')),
            'Explanation' => trim((string) ($_POST['Explanation'] ?? '')),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'SortOrder' => (int) ($_POST['SortOrder'] ?? 0),
        ];

        try {
            $this->ensureCertificationInstalled();
            $questionNo = $this->model()->saveQuestion($payload, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Certification question saved.');
            header('Location: index.php?route=training-certifications/question-form&certification_code=' . urlencode($code) . '&question_no=' . $questionNo);
            return;
        } catch (\Throwable $e) {
            $this->flashError('Certification question save failed: ' . $e->getMessage());
            $questionNo = (int) ($_POST['OldQuestionNo'] ?? $_POST['QuestionNo'] ?? 0);
            $suffix = $code !== '' ? '&certification_code=' . urlencode($code) : '';
            $suffix .= $questionNo > 0 ? '&question_no=' . $questionNo : '';
            header('Location: index.php?route=training-certifications/question-form' . $suffix);
            return;
        }
    }

    public function start(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        try {
            $this->ensureCertificationInstalled();
            $attemptId = $this->model()->startAttempt(
                trim((string) ($_POST['certification_code'] ?? '')),
                (int) SessionHelper::get('auth.user_id', 0)
            );
            header('Location: index.php?route=training-certifications/take&attempt_id=' . $attemptId);
            return;
        } catch (\Throwable $e) {
            $this->flashError('Certification could not be started: ' . $e->getMessage());
            header('Location: index.php?route=training-certifications/modules');
            return;
        }
    }

    public function take(): void
    {
        $this->ensureTrainingEnabled();
        $model = $this->model();
        $installed = $model->supportsCertificationTables();
        $attemptId = (int) ($_GET['attempt_id'] ?? 0);
        $userId = (int) SessionHelper::get('auth.user_id', 0);
        $attempt = ($installed && $attemptId > 0) ? $model->getAttempt($attemptId, $userId) : null;
        if (is_array($attempt) && (string) ($attempt['Status'] ?? '') === 'submitted') {
            header('Location: index.php?route=training-certifications/result&attempt_id=' . $attemptId);
            return;
        }

        $questions = is_array($attempt)
            ? $model->listQuestions((string) ($attempt['CertificationCode'] ?? ''), true)
            : [];

        $this->render('trainingcertifications/Take', [
            'title' => 'Certification Test',
            'tableInstalled' => $installed,
            'attempt' => $attempt,
            'questions' => $questions,
        ]);
    }

    public function submit(): void
    {
        $this->ensureTrainingEnabled();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo 'Invalid request.';
            return;
        }

        $attemptId = (int) ($_POST['attempt_id'] ?? 0);
        $answers = is_array($_POST['answers'] ?? null) ? $_POST['answers'] : [];
        try {
            $this->ensureCertificationInstalled();
            $this->model()->submitAttempt($attemptId, (int) SessionHelper::get('auth.user_id', 0), $answers);
            header('Location: index.php?route=training-certifications/result&attempt_id=' . $attemptId);
            return;
        } catch (\Throwable $e) {
            $this->flashError('Certification submission failed: ' . $e->getMessage());
            header('Location: index.php?route=training-certifications/take&attempt_id=' . $attemptId);
            return;
        }
    }

    public function result(): void
    {
        $this->ensureTrainingEnabled();
        $model = $this->model();
        $installed = $model->supportsCertificationTables();
        $attemptId = (int) ($_GET['attempt_id'] ?? 0);
        $userId = (int) SessionHelper::get('auth.user_id', 0);
        $attempt = ($installed && $attemptId > 0) ? $model->getAttempt($attemptId, $userId) : null;

        $this->render('trainingcertifications/Result', [
            'title' => 'Certification Result',
            'tableInstalled' => $installed,
            'attempt' => $attempt,
            'answers' => is_array($attempt) ? $model->listAttemptAnswers($attemptId) : [],
        ]);
    }

    public function results(): void
    {
        $this->ensureTrainingEnabled();
        $model = $this->model();
        $installed = $model->supportsCertificationTables();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
            'certification_code' => trim((string) ($_GET['certification_code'] ?? '')),
            'result' => trim((string) ($_GET['result'] ?? '')),
        ];

        $this->render('trainingcertifications/Results', [
            'title' => 'Certification Results',
            'tableInstalled' => $installed,
            'rows' => $installed ? $model->listResults($filters) : [],
            'filters' => $filters,
            'moduleOptions' => $installed ? $model->listModuleOptions() : [],
            'certificationOptions' => $installed ? $model->listCertifications(['active' => '']) : [],
            'createTableScript' => 'backend-php/config/sql/create_training_certification_features.sql',
        ]);
    }

    private function model(): TrainingCertificationModel
    {
        return new TrainingCertificationModel($this->db);
    }

    private function catalogModel(): TrainingCatalogAdminModel
    {
        return new TrainingCatalogAdminModel($this->db);
    }

    private function ensureCertificationInstalled(): void
    {
        if (!$this->model()->supportsCertificationTables()) {
            throw new \RuntimeException('Training certification tables are not installed.');
        }
    }

    private function ensureTrainingEnabled(): void
    {
        if (training_features_enabled($this->db)) {
            return;
        }
        $this->flashError(__t('training_features_disabled'));
        header('Location: index.php?route=home/index');
        exit;
    }

    private function canManageTraining(): bool
    {
        $perms = SessionHelper::get('auth.perms', []);
        if (!is_array($perms)) {
            return false;
        }
        return in_array('TRAINING_ADMIN', $perms, true)
            || in_array('TRAINING_CONFIG', $perms, true)
            || in_array('ADMIN_ALL', $perms, true)
            || in_array('SYSADMIN', $perms, true);
    }
}
