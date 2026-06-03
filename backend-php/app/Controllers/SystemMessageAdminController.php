<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SystemMessageModel;
use App\Services\AudienceService;
use App\Shared\SessionHelper;

class SystemMessageAdminController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['SYSADMIN']],
    ];

    private SystemMessageModel $model;

    public function __construct()
    {
        parent::__construct();
        require __DIR__ . '/../../config/db.php'; // $conn (PDO)
        $this->db = $conn;
        $this->model = new SystemMessageModel($conn);
    }

    // GET form
    public function createForm(): void
    {
        $this->render('systemmessages/create', [
            'title' => 'Create System Message',
            'formAction' => 'index.php?route=systemmessages/create',
            'submitLabels' => [
                'draft' => 'Save Draft',
                'publish' => 'Publish',
            ],
            'availableRoles' => $this->fetchAvailableRoles($this->db),
            'defaults' => [
                'ScopeFiscalYearID' => (int) SessionHelper::get('FiscalYearID', 0),
                'ScopeVersionID' => (int) SessionHelper::get('VersionID', 0),
                'ScopeDataObjectCode' => (string) SessionHelper::get('scope.dataobject_code', ''),
                'Severity' => 'info',
                'Priority' => 10,
                'AudienceGlobal' => 1,
                'IsHtml' => 1,
                'IsDismissible' => 1,
                'IncludeDescendants' => 1,
            ],
        ]);
    }

    public function editForm(): void
    {
        require __DIR__ . '/../../config/db.php';
        $id = (int)($_GET['MessageID'] ?? 0);
        if ($id <= 0) {
            $this->flashError('Missing MessageID.');
            header('Location: index.php?route=systemmessages/list');
            exit;
        }

        $row = $this->model->getById($id);
        if (!$row) {
            $this->flashError('Message not found.');
            header('Location: index.php?route=systemmessages/list');
            exit;
        }

        $defaults = $this->normalizeMessageRow($row);
        $defaults['ScopeDataObjectCode'] = (string) ($this->fetchFirstCode($conn, $id) ?? '');
        $defaults['StartAt'] = $this->formatDateTimeLocal($defaults['StartAt'] ?? null);
        $defaults['EndAt'] = $this->formatDateTimeLocal($defaults['EndAt'] ?? null);
        $defaults['SendEmail'] = $row['EmailAlso'] ?? 0;
        $defaults['EmailSubject'] = $row['EmailSubject'] ?? '';
        $defaults['Body'] = $defaults['Body'] ?? '';
        $defaults['SelectedRoles'] = $this->fetchRoles($conn, $id);

        $this->render('systemmessages/create', [
            'title' => 'Edit System Message',
            'formAction' => 'index.php?route=systemmessages/update',
            'submitLabels' => [
                'draft' => 'Update Draft',
                'publish' => 'Update & Publish',
            ],
            'availableRoles' => $this->fetchAvailableRoles($this->db),
            'defaults' => $defaults,
            'messageId' => $id,
        ]);
    }

    public function list(): void
    {
        $status = trim((string) ($_GET['status'] ?? ''));
        $rows = $this->model->listMessages($status !== '' ? $status : null, 200);

        $this->render('systemmessages/list', [
            'title' => 'System Messages',
            'rows' => $rows,
            'status' => $status,
        ]);
    }

    // POST create + (optionally) publish
    public function create(): void
    {
        $this->assertPostWithCsrf('index.php?route=systemmessages/createForm');

        require __DIR__ . '/../../config/db.php';
        $userId = (int) SessionHelper::get('auth.user_id', 0);

        [$data, $codes, $users, $roles] = $this->buildMessagePayload($userId);

        try {
            $id = $this->model->create($data, $codes, $users, $roles);
            $this->auditEvent('CREATE', 'SystemMessage', (string) $id, [
                'Title' => $data['Title'] ?? '',
                'Status' => $data['Status'] ?? '',
                'Severity' => $data['Severity'] ?? '',
                'AudienceGlobal' => $data['AudienceGlobal'] ?? 0,
                'ScopeFiscalYearID' => $data['ScopeFiscalYearID'] ?? null,
                'ScopeVersionID' => $data['ScopeVersionID'] ?? null,
            ]);
            header('Location: index.php?route=systemmessages/preview&MessageID=' . $id);
            exit;
        } catch (\Throwable $e) {
            $this->logHandledException('SystemMessageAdminController::create failed', $e, [
                'title' => $data['Title'] ?? '',
            ]);
            $this->flashError('Create failed: ' . $e->getMessage());
            header('Location: index.php?route=systemmessages/createForm');
            exit;
        }
    }

    public function update(): void
    {
        $this->assertPostWithCsrf('index.php?route=systemmessages/list');

        require __DIR__ . '/../../config/db.php';
        $userId = (int) SessionHelper::get('auth.user_id', 0);
        $messageId = (int)($_POST['MessageID'] ?? 0);
        if ($messageId <= 0) {
            $this->flashError('Missing MessageID.');
            header('Location: index.php?route=systemmessages/list');
            exit;
        }

        [$data, $codes, $users, $roles] = $this->buildMessagePayload($userId);

        try {
            $this->model->update($messageId, $data, $codes, $users, $roles, $userId);
            $this->auditEvent('UPDATE', 'SystemMessage', (string) $messageId, [
                'Title' => $data['Title'] ?? '',
                'Status' => $data['Status'] ?? '',
                'Severity' => $data['Severity'] ?? '',
                'AudienceGlobal' => $data['AudienceGlobal'] ?? 0,
                'ScopeFiscalYearID' => $data['ScopeFiscalYearID'] ?? null,
                'ScopeVersionID' => $data['ScopeVersionID'] ?? null,
            ]);
            header('Location: index.php?route=systemmessages/preview&MessageID=' . $messageId);
            exit;
        } catch (\Throwable $e) {
            $this->logHandledException('SystemMessageAdminController::update failed', $e, [
                'messageId' => $messageId,
                'title' => $data['Title'] ?? '',
            ]);
            $this->flashError('Update failed: ' . $e->getMessage());
            header('Location: index.php?route=systemmessages/editForm&MessageID=' . $messageId);
            exit;
        }
    }

    // GET audience preview
    public function preview(): void
    {
        require __DIR__ . '/../../config/db.php';
        $id = (int)($_GET['MessageID'] ?? 0);
        if (!$id) { http_response_code(400); echo 'Missing MessageID'; return; }

        $row = $this->model->getById($id);
        if (!$row) { http_response_code(404); echo 'Message not found'; return; }
        $row = $this->normalizeMessageRow($row);

        $aud = new AudienceService($conn);
        $codes = $this->fetchCodes($conn, $id);
        $scopeCode = $this->fetchFirstCode($conn, $id) ?? '';
        $selectedRoles = $this->fetchRoles($conn, $id);

        $uids = $aud->resolveUserIds(
            !empty($row['AudienceGlobal']),
            $codes,
            !empty($row['IncludeDescendants']),
            [],
            $selectedRoles,
            !empty($row['ScopeFiscalYearID']) ? (int)$row['ScopeFiscalYearID'] : null
        );
        $emails = $aud->resolveEmails($uids);

        $this->render('systemmessages/preview', [
            'title'  => 'Audience Preview',
            'msg'    => $row,
            'counts' => [
                'users'  => count($uids),
                'emails' => count($emails),
            ],
            'scopeCode' => $scopeCode !== '' ? $scopeCode : (string)($row['ScopeDataObjectCode'] ?? ''),
            'selectedRoles' => $selectedRoles,
            'sample' => array_slice($emails, 0, 200),
        ]);
    }

    public function setStatus(): void
    {
        $this->assertPostWithCsrf('index.php?route=systemmessages/list');

        $messageId = (int) ($_POST['MessageID'] ?? 0);
        $status = trim((string) ($_POST['Status'] ?? ''));
        $userId = (int) SessionHelper::get('auth.user_id', 0);
        $allowed = ['draft', 'published', 'archived'];

        if ($messageId <= 0 || !in_array($status, $allowed, true)) {
            $this->flashError('Invalid message status update request.');
            header('Location: index.php?route=systemmessages/list');
            exit;
        }

        try {
            $this->model->updateStatus($messageId, $status, $userId);
            $this->auditEvent('STATUS_UPDATE', 'SystemMessage', (string) $messageId, [
                'Status' => $status,
            ]);
            $this->flashSuccess('Message status updated to ' . $status . '.');
        } catch (\Throwable $e) {
            $this->logHandledException('SystemMessageAdminController::setStatus failed', $e, [
                'messageId' => $messageId,
                'status' => $status,
            ]);
            $this->flashError('Failed to update message status: ' . $e->getMessage());
        }

        header('Location: index.php?route=systemmessages/list');
        exit;
    }

    private function normalizeMessageRow(array $row): array
    {
        $row['Body'] = $row['Body'] ?? ($row['BodyHtml'] ?? '');
        $row['StartAt'] = $row['StartAt'] ?? ($row['DeliveryStartUTC'] ?? null);
        $row['EndAt'] = $row['EndAt'] ?? ($row['DeliveryEndUTC'] ?? null);
        $row['RequiresAck'] = $row['RequiresAck'] ?? ($row['RequireAck'] ?? 0);
        $row['AudienceGlobal'] = $row['AudienceGlobal'] ?? ($row['IsGlobal'] ?? 0);
        $row['IncludeDescendants'] = $row['IncludeDescendants'] ?? ($row['DescendantTarget'] ?? 0);
        $row['ScopeFiscalYearID'] = $row['ScopeFiscalYearID'] ?? ($row['FiscalYearID'] ?? null);
        $row['ScopeVersionID'] = $row['ScopeVersionID'] ?? ($row['VersionID'] ?? null);
        $row['ScopeDataObjectCode'] = $row['ScopeDataObjectCode'] ?? null;

        if (isset($row['Severity']) && is_numeric($row['Severity'])) {
            $severityMap = [
                1 => 'info',
                2 => 'success',
                3 => 'warning',
                4 => 'danger',
            ];
            $row['Severity'] = $severityMap[(int) $row['Severity']] ?? 'info';
        }

        return $row;
    }

    private function fetchCodes($db, int $id): array {
        $q = $db->prepare("SELECT DataObjectCode FROM dbo.tblSystemMessageDataObject WHERE MessageID = :id");
        $q->bindValue(':id', $id, \PDO::PARAM_INT); $q->execute();
        return array_column($q->fetchAll(\PDO::FETCH_ASSOC), 'DataObjectCode');
    }
    private function fetchFirstCode($db, int $id): ?string {
        $q = $db->prepare("SELECT TOP 1 DataObjectCode FROM dbo.tblSystemMessageDataObject WHERE MessageID = :id ORDER BY DataObjectCode");
        $q->bindValue(':id', $id, \PDO::PARAM_INT);
        $q->execute();
        $value = $q->fetchColumn();
        return $value === false ? null : (string) $value;
    }
    private function fetchRoles($db, int $id): array {
        $q = $db->prepare("SELECT RoleName FROM dbo.tblSystemMessageRole WHERE MessageID = :id ORDER BY RoleName");
        $q->bindValue(':id', $id, \PDO::PARAM_INT);
        $q->execute();
        return array_map('strval', array_column($q->fetchAll(\PDO::FETCH_ASSOC), 'RoleName'));
    }
    private function fetchAvailableRoles($db): array {
        $q = $db->query("SELECT RoleName FROM dbo.tblRoles WHERE Active = 1 ORDER BY RoleName");
        return array_map('strval', array_column($q->fetchAll(\PDO::FETCH_ASSOC), 'RoleName'));
    }
    private function buildMessagePayload(int $userId): array
    {
        $scopeCode = trim((string)($_POST['ScopeDataObjectCode'] ?? ''));
        $codes = $scopeCode !== '' ? [$scopeCode] : [];
        $roles = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['RoleNames'] ?? [])))));
        $users = [];
        $audienceGlobal = $scopeCode === '' ? 1 : 0;

        $data = [
            'Title'              => $_POST['Title'] ?? '',
            'Body'               => $_POST['Body'] ?? '',
            'Severity'           => $_POST['Severity'] ?? 'info',
            'IsHtml'             => !empty($_POST['IsHtml']) ? 1 : 0,
            'IsDismissible'      => !empty($_POST['IsDismissible']) ? 1 : 0,
            'AudienceGlobal'     => $audienceGlobal,
            'StartAt'            => $this->normalizeDateTimeForSql($_POST['StartAt'] ?? null) ?? gmdate('Y-m-d H:i:s'),
            'EndAt'              => $this->normalizeDateTimeForSql($_POST['EndAt'] ?? null),
            'Priority'           => (int)($_POST['Priority'] ?? 10),
            'IncludeDescendants' => $scopeCode !== '' ? 1 : 0,
            'ScopeFiscalYearID'  => trim((string)($_POST['ScopeFiscalYearID'] ?? '')) !== '' ? (int)$_POST['ScopeFiscalYearID'] : null,
            'ScopeVersionID'     => trim((string)($_POST['ScopeVersionID'] ?? '')) !== '' ? (int)$_POST['ScopeVersionID'] : null,
            'ScopeDataObjectCode'=> $scopeCode !== '' ? $scopeCode : null,
            'RequiresAck'        => !empty($_POST['RequiresAck']) ? 1 : 0,
            'SendEmail'          => !empty($_POST['SendEmail']) ? 1 : 0,
            'EmailSubject'       => $_POST['EmailSubject'] ?? null,
            'Status'             => (($_POST['Action'] ?? 'draft') === 'publish') ? 'published' : 'draft',
            'CreatedBy'          => $userId,
        ];

        return [$data, $codes, $users, $roles];
    }

    private function formatDateTimeLocal(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return '';
        }
    }

    private function normalizeDateTimeForSql(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            $normalized = str_replace('T', ' ', $value);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
                return $normalized . ':00';
            }
            return $normalized;
        }
    }
}
