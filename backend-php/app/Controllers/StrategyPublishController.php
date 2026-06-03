<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\StrategicBudgetingAdminModel;
use App\Models\StrategicBudgetingModel;
use App\Models\SystemSettingsModel;
use App\Models\WorkflowAssignmentModel;
use App\Models\WorkflowTaskModel;
use App\Models\WorkflowTaskStatusModel;
use App\Models\WorkflowTaskTypeModel;
use App\Services\MailService;
use App\Shared\SessionHelper;

final class StrategyPublishController extends BaseController
{
    private const PUBLISH_PERMS = ['STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'];

    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => self::PUBLISH_PERMS],
    ];

    protected bool $requiresContext = true;

    public function requests(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $this->render('strategy/SegmentPublishRequestList', [
            'title' => 'Segment Publication Requests',
            'context' => $ctx,
            'fiscalYearId' => $fy,
            'versionId' => $ver,
            'workflowInstalled' => $model->supportsSegmentPublicationWorkflow(),
            'requests' => $model->listSegmentPublishRequests($fy, $ver),
        ]);
    }

    public function requestForm(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);
        $request = $id > 0 ? $model->getSegmentPublishRequest($id) : null;

        $this->render('strategy/SegmentPublishRequestForm', [
            'title' => $id > 0 ? 'Edit Segment Publication Request' : 'New Segment Publication Request',
            'context' => $ctx,
            'request' => $request,
            'fiscalYearId' => $fy,
            'versionId' => $ver,
            'workflowInstalled' => $model->supportsSegmentPublicationWorkflow(),
        ]);
    }

    public function saveRequest(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_POST['StrategicSegmentPublishRequestID'] ?? 0);

        try {
            $requestId = $model->saveSegmentPublishRequest([
                'FiscalYearID' => $fy,
                'VersionID' => $ver,
                'RequestTitle' => trim((string) ($_POST['RequestTitle'] ?? '')),
                'RequestNotes' => trim((string) ($_POST['RequestNotes'] ?? '')),
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ], $id > 0 ? $id : null);
            $this->flashSuccess('Segment publication request saved.');
            header('Location: index.php?route=strategy-publish/request-view&id=' . $requestId);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Segment publication request save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-publish/request-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }
    }

    public function requestView(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);
        $request = $model->getSegmentPublishRequest($id);

        if ($request === null) {
            $this->flashError('Segment publication request was not found.');
            header('Location: index.php?route=strategy-publish/requests');
            exit;
        }

        $this->render('strategy/SegmentPublishRequestView', [
            'title' => 'Segment Publication Request',
            'context' => $ctx,
            'fiscalYearId' => $fy,
            'workflowInstalled' => $model->supportsSegmentPublicationWorkflow(),
            'request' => $request,
            'lines' => $model->listSegmentPublishRequestLines($id),
            'dimensions' => $model->listSegmentPublishDimensions((int) ($request['FiscalYearID'] ?? $fy)),
            'canApprovePublication' => $this->canApproveSegmentPublication(),
        ]);
    }

    public function lineForm(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $requestId = (int) ($_GET['request_id'] ?? 0);
        $lineId = (int) ($_GET['id'] ?? 0);
        $request = $model->getSegmentPublishRequest($requestId);
        $line = $lineId > 0 ? $model->getSegmentPublishRequestLine($lineId) : null;

        if ($request === null) {
            $this->flashError('Segment publication request was not found.');
            header('Location: index.php?route=strategy-publish/requests');
            exit;
        }

        $this->render('strategy/SegmentPublishRequestLineForm', [
            'title' => $lineId > 0 ? 'Edit Publication Line' : 'Add Publication Line',
            'context' => $ctx,
            'request' => $request,
            'line' => $line,
            'workflowInstalled' => $model->supportsSegmentPublicationWorkflow(),
            'dimensions' => $model->listSegmentPublishDimensions((int) ($request['FiscalYearID'] ?? $fy)),
            'dataObjectOptions' => $model->listDataScopeOrgUnitOptions((int) ($request['FiscalYearID'] ?? $fy), null),
            'availableSegments' => $model->listAvailableSegments(),
        ]);
    }

    public function saveLine(): void
    {
        $model = $this->buildModel();
        $requestId = (int) ($_POST['StrategicSegmentPublishRequestID'] ?? 0);
        $lineId = (int) ($_POST['StrategicSegmentPublishRequestLineID'] ?? 0);

        try {
            $model->saveSegmentPublishRequestLine([
                'StrategicSegmentPublishRequestID' => $requestId,
                'StrategicDimensionCode' => trim((string) ($_POST['StrategicDimensionCode'] ?? '')),
                'DataObjectCode' => trim((string) ($_POST['DataObjectCode'] ?? '')),
                'SegmentCode' => trim((string) ($_POST['SegmentCode'] ?? '')),
                'SegmentName' => trim((string) ($_POST['SegmentName'] ?? '')),
                'SegmentExternalID' => trim((string) ($_POST['SegmentExternalID'] ?? '')),
                'ParentSegmentNo' => (int) ($_POST['ParentSegmentNo'] ?? 0),
                'ParentSegmentCode' => trim((string) ($_POST['ParentSegmentCode'] ?? '')),
                'SortOrder' => trim((string) ($_POST['SortOrder'] ?? '')),
                'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ], $lineId > 0 ? $lineId : null);
            $this->flashSuccess('Publication line saved.');
        } catch (\Throwable $e) {
            $this->flashError('Publication line save failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-publish/request-view&id=' . $requestId);
        exit;
    }

    public function deleteLine(): void
    {
        $model = $this->buildModel();
        $lineId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $requestId = (int) ($_POST['request_id'] ?? $_GET['request_id'] ?? 0);

        try {
            $model->deleteSegmentPublishRequestLine($lineId);
            $this->flashSuccess('Publication line removed.');
        } catch (\Throwable $e) {
            $this->flashError('Publication line delete failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-publish/request-view&id=' . $requestId);
        exit;
    }

    public function transition(): void
    {
        $model = $this->buildModel();
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $action = trim((string) ($_POST['publish_action'] ?? ''));
        $normalizedAction = strtolower($action);

        try {
            if (in_array($normalizedAction, ['approve', 'reject'], true) && !$this->canApproveSegmentPublication()) {
                throw new \RuntimeException('You do not have permission to approve or reject segment publication requests.');
            }
            $model->transitionSegmentPublishRequest($requestId, $action, (int) SessionHelper::get('auth.user_id', 1));
            $this->syncSegmentPublicationWorkflowTasks($requestId, $normalizedAction);
            $this->flashSuccess('Publication request updated.');
        } catch (\Throwable $e) {
            $this->flashError('Publication request update failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-publish/request-view&id=' . $requestId);
        exit;
    }

    public function publish(): void
    {
        $model = $this->buildModel();
        $requestId = (int) ($_POST['request_id'] ?? 0);

        try {
            if (!$this->canApproveSegmentPublication()) {
                throw new \RuntimeException('You do not have permission to publish approved segment requests.');
            }
            $summary = $model->publishApprovedSegmentRequest($requestId, (int) SessionHelper::get('auth.user_id', 1));
            $this->syncSegmentPublicationWorkflowTasks($requestId, 'publish');
            $this->flashSuccess(
                'Approved segment lines published. Created: '
                . (int) ($summary['published'] ?? 0)
                . '; Failed: '
                . (int) ($summary['failed'] ?? 0)
            );
        } catch (\Throwable $e) {
            $this->flashError('Segment publication failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-publish/request-view&id=' . $requestId);
        exit;
    }

    private function buildModel(): StrategicBudgetingAdminModel
    {
        return new StrategicBudgetingAdminModel($this->db);
    }

    private function canApproveSegmentPublication(): bool
    {
        return \App\Core\Rbac::canAny(self::PUBLISH_PERMS);
    }

    private function syncSegmentPublicationWorkflowTasks(int $requestId, string $triggerAction): void
    {
        if ($requestId <= 0 || !$this->db instanceof \PDO) {
            return;
        }

        $admin = $this->buildModel();
        $request = $admin->getSegmentPublishRequest($requestId);
        if ($request === null) {
            return;
        }

        $lines = $admin->listSegmentPublishRequestLines($requestId);
        $workflowTasks = new WorkflowTaskModel($this->db);
        $statusModel = new WorkflowTaskStatusModel($this->db);
        $typeModel = new WorkflowTaskTypeModel($this->db);

        $status = strtoupper(trim((string) ($request['RequestStatusCode'] ?? 'DRAFT')));
        $approvalTypeId = $typeModel->findIdByName('Approval Task') ?? 4;
        $publishTypeId = $typeModel->findIdByName('Approval Task') ?? 4;
        $openStatusId = $statusModel->findOpenStatusId() ?? 1;
        $completedStatusId = $statusModel->findCompletedStatusId() ?? 3;

        if ($status === 'PENDING' && $triggerAction === 'submit') {
            $this->closeSegmentPublicationTasksByType($workflowTasks, $completedStatusId, $requestId, $publishTypeId);
            $assignees = $this->resolveSegmentPublicationWorkflowAssignees(
                'APPROVAL',
                $request,
                $lines,
                self::PUBLISH_PERMS
            );
            $this->ensureSegmentPublicationTasks(
                $workflowTasks,
                $openStatusId,
                $approvalTypeId,
                $request,
                $lines,
                $assignees,
                'Approve Segment Publication Request',
                'Review the submitted segment publication request and record the approval or rejection decision.',
                'Open Publication Request for Approval'
            );
            return;
        }

        if (in_array($status, ['APPROVED', 'PARTIAL'], true) && $triggerAction === 'approve') {
            $this->closeSegmentPublicationTasksByType($workflowTasks, $completedStatusId, $requestId, $approvalTypeId);
            $assignees = $this->resolveSegmentPublicationWorkflowAssignees(
                'PUBLISH',
                $request,
                $lines,
                self::PUBLISH_PERMS
            );
            $this->ensureSegmentPublicationTasks(
                $workflowTasks,
                $openStatusId,
                $publishTypeId,
                $request,
                $lines,
                $assignees,
                'Publish Segment Publication Request',
                'Publish the approved segment lines into the live Strategy segment values register.',
                'Open Publication Request to Publish'
            );
            return;
        }

        if (in_array($status, ['REJECTED', 'PUBLISHED', 'PARTIAL'], true) || in_array($triggerAction, ['reject', 'publish'], true)) {
            $this->closeSegmentPublicationTasksByType($workflowTasks, $completedStatusId, $requestId, null);
        }
    }

    private function ensureSegmentPublicationTasks(
        WorkflowTaskModel $workflowTasks,
        int $openStatusId,
        int $taskTypeId,
        array $request,
        array $lines,
        array $assignees,
        string $taskVerb,
        string $descriptionLead,
        string $taskLinkLabel
    ): void {
        if ($assignees === []) {
            return;
        }

        $requestId = (int) ($request['StrategicSegmentPublishRequestID'] ?? 0);
        $titleBase = trim((string) ($request['RequestTitle'] ?? 'Segment Publication Request'));
        $fy = (int) ($request['FiscalYearID'] ?? 0);
        $ver = (int) ($request['VersionID'] ?? 0);
        $scopeSummary = $this->buildSegmentPublicationScopeSummary($lines, $fy);
        $appUrl = $this->getAppBaseUrl();
        $taskUrl = $appUrl . '/backend-php/public/index.php?' . http_build_query(array_filter([
            'route' => 'strategy-publish/request-view',
            'id' => $requestId,
            'link_context' => 1,
            'scope_dataobject_code' => $scopeSummary['singleCode'] !== '' ? $scopeSummary['singleCode'] : null,
            'scope_dataobject_name' => $scopeSummary['singleName'] !== '' ? $scopeSummary['singleName'] : null,
            'fy' => $fy > 0 ? $fy : null,
            'ver' => $ver > 0 ? $ver : null,
        ], static fn($v) => $v !== null && $v !== ''));

        foreach ($assignees as $assignee) {
            $assigneeId = (int) ($assignee['UserID'] ?? 0);
            if ($assigneeId <= 0) {
                continue;
            }

            $existing = $workflowTasks->findOpenByRelatedEntityKeyAndAssignee('StrategicSegmentPublishRequest', (string) $requestId, $assigneeId);
            $data = [
                'TaskTypeID' => $taskTypeId,
                'StatusID' => $openStatusId,
                'Title' => $taskVerb . ': ' . $titleBase,
                'Description' => $descriptionLead
                    . "\n\nPublication Request: " . $titleBase
                    . ($scopeSummary['label'] !== '' ? "\nDataScopes: " . $scopeSummary['label'] : ''),
                'CreatedByUserID' => (int) SessionHelper::get('auth.user_id', 1),
                'AssignedToUserID' => $assigneeId,
                'RelatedEntity' => 'StrategicSegmentPublishRequest',
                'RelatedKey' => (string) $requestId,
                'DueDate' => date('Y-m-d', strtotime('+3 days')),
                'CompletedAt' => null,
                'UpdatedBy' => (int) SessionHelper::get('auth.user_id', 1),
                'TaskUrl' => $taskUrl,
                'TaskLinkLabel' => $taskLinkLabel,
            ];

            if ($existing !== null) {
                $workflowTasks->update((int) ($existing['WorkflowTaskID'] ?? 0), $data);
                continue;
            }

            $workflowTasks->create($data);
            $this->notifySegmentPublicationWorkflowTaskAssignee($assignee, $data);
        }
    }

    private function closeSegmentPublicationTasksByType(
        WorkflowTaskModel $workflowTasks,
        int $completedStatusId,
        int $requestId,
        ?int $taskTypeId
    ): void {
        $openTasks = $workflowTasks->listOpenByRelatedEntityKey('StrategicSegmentPublishRequest', (string) $requestId);
        foreach ($openTasks as $task) {
            if ($taskTypeId !== null && (int) ($task['TaskTypeID'] ?? 0) !== $taskTypeId) {
                continue;
            }
            $workflowTasks->updateStatus(
                (int) ($task['WorkflowTaskID'] ?? 0),
                $completedStatusId,
                gmdate('Y-m-d H:i:s'),
                (int) SessionHelper::get('auth.user_id', 1)
            );
        }
    }

    private function resolveSegmentPublicationWorkflowAssignees(
        string $stageCode,
        array $request,
        array $lines,
        array $fallbackPermissions
    ): array {
        $assignees = [];
        if ($this->db instanceof \PDO) {
            $assignmentModel = new WorkflowAssignmentModel($this->db);
            if ($assignmentModel->supportsWorkflowAssignments()) {
                foreach ($this->listSegmentPublicationScopeCodes($lines) as $scopeCode) {
                    $resolved = $assignmentModel->resolveAssignments(
                        'SEGMENT_PUBLICATION',
                        strtoupper(trim($stageCode)),
                        (int) ($request['FiscalYearID'] ?? 0),
                        (int) ($request['VersionID'] ?? 0),
                        $scopeCode
                    );
                    foreach ($resolved as $row) {
                        $userId = (int) ($row['UserID'] ?? 0);
                        if ($userId > 0) {
                            $assignees[$userId] = $row;
                        }
                    }
                }
            }
        }

        if ($assignees !== []) {
            return array_values($assignees);
        }

        return $this->listActiveUsersByPermissions($fallbackPermissions);
    }

    private function listSegmentPublicationScopeCodes(array $lines): array
    {
        $codes = [];
        foreach ($lines as $line) {
            $code = trim((string) ($line['DataObjectCode'] ?? ''));
            if ($code !== '' && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }
        if ($codes === []) {
            $codes[] = '0';
        }
        return $codes;
    }

    private function buildSegmentPublicationScopeSummary(array $lines, int $fiscalYearId): array
    {
        $labels = [];
        $singleCode = '';
        $singleName = '';
        foreach ($lines as $line) {
            $code = trim((string) ($line['DataObjectCode'] ?? ''));
            if ($code === '' || isset($labels[$code])) {
                continue;
            }
            $name = trim((string) ($line['DataObjectName'] ?? ''));
            $labels[$code] = $name !== '' ? ($code . ' - ' . $name) : $code;
            if (count($labels) === 1) {
                $singleCode = $code;
                $singleName = $name;
            } else {
                $singleCode = '';
                $singleName = '';
            }
        }

        return [
            'label' => implode('; ', array_values($labels)),
            'singleCode' => $singleCode,
            'singleName' => $singleName,
        ];
    }

    private function listActiveUsersByPermissions(array $permissionCodes): array
    {
        if (!$this->db instanceof \PDO) {
            return [];
        }

        $permissionCodes = array_values(array_unique(array_filter(array_map(
            static fn($v) => strtoupper(trim((string) $v)),
            $permissionCodes
        ))));
        if ($permissionCodes === []) {
            return [];
        }

        $permPlaceholders = [];
        $params = [];
        foreach ($permissionCodes as $index => $code) {
            $placeholder = ':perm' . $index;
            $permPlaceholders[] = $placeholder;
            $params[$placeholder] = $code;
        }

        $sql = "
            SELECT DISTINCT
                u.UserID,
                u.Username,
                LTRIM(RTRIM(u.DisplayName)) AS DisplayName,
                u.Email
            FROM dbo.tblUsers u
            INNER JOIN dbo.tblUserRoles ur
                ON ur.UserID = u.UserID
            INNER JOIN dbo.tblRolePermissions rp
                ON rp.RoleID = ur.RoleID
            INNER JOIN dbo.tblPermissions p
                ON p.PermissionID = rp.PermissionID
            INNER JOIN dbo.tblRoles r
                ON r.RoleID = ur.RoleID
            WHERE u.IsActive = 1
              AND (r.Active = 1 OR r.Active IS NULL)
              AND (p.Active = 1 OR p.Active IS NULL)
              AND UPPER(p.PermissionCode) IN (" . implode(', ', $permPlaceholders) . ")
            ORDER BY LTRIM(RTRIM(u.DisplayName)) ASC, u.Username ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function notifySegmentPublicationWorkflowTaskAssignee(array $assignee, array $taskData): void
    {
        if (!$this->db instanceof \PDO) {
            return;
        }

        $email = trim((string) ($assignee['Email'] ?? ''));
        if ($email === '') {
            return;
        }

        $settings = new SystemSettingsModel($this->db);
        $subject = 'CBMS workflow task: ' . (string) ($taskData['Title'] ?? 'Segment Publication Request');
        $body = '
            <p>Dear ' . htmlspecialchars((string) ($assignee['DisplayName'] ?? $assignee['Username'] ?? 'User')) . ',</p>
            <p>A workflow task has been assigned to you in CBMS.</p>
            <ul>
              <li><strong>Task:</strong> ' . htmlspecialchars((string) ($taskData['Title'] ?? '')) . '</li>
              <li><strong>Due Date:</strong> ' . htmlspecialchars((string) ($taskData['DueDate'] ?? '-')) . '</li>
            </ul>
            <p>' . nl2br(htmlspecialchars((string) ($taskData['Description'] ?? ''))) . '</p>
            <p><a href="' . htmlspecialchars((string) ($taskData['TaskUrl'] ?? '')) . '">' . htmlspecialchars((string) ($taskData['TaskLinkLabel'] ?? 'Open Task in CBMS')) . '</a></p>
        ';

        try {
            $mailer = new MailService($this->db);
            $mailer->sendEmail(
                $email,
                $subject,
                $body,
                $settings->get('EMAIL_ERROR_FROM', 'noreply@cbmsv2.local')
            );
        } catch (\Throwable $e) {
            app_log('Segment publication workflow task email failed', [
                'userId' => (int) ($assignee['UserID'] ?? 0),
                'email' => $email,
                'error' => $e->getMessage(),
            ], 'error');
        }
    }

    private function getAppBaseUrl(): string
    {
        if (!$this->db instanceof \PDO) {
            return 'http://localhost/CBMSv21';
        }
        $settings = new SystemSettingsModel($this->db);
        return rtrim($settings->get('APP_URL', 'http://localhost/CBMSv21'), '/');
    }
}
