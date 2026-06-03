<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\SessionHelper;
use App\Shared\TrainingScenarioCatalog;
use App\Models\UserModel;
use App\Models\AuditModel;
use App\Models\RoleModel;
use App\Models\TrainingProgressModel;
use App\Models\UserRoleModel;

require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/training_features.php';

final class UsersController extends BaseController
{
    protected array $acl = [
        '*'      => ['auth' => true, 'permsAny' => ['USERS_ADMIN']],
        'list'   => ['auth' => true, 'permsAny' => ['USERS_VIEW','USERS_ADMIN']],
        'edit'   => ['auth' => true, 'permsAny' => ['USERS_EDIT','USERS_ADMIN']],
        'save'   => ['auth' => true, 'permsAny' => ['USERS_EDIT','USERS_ADMIN']],
        'unlock' => ['auth' => true, 'permsAny' => ['USERS_ADMIN']],
        'saveRoles'      => ['auth' => true, 'permsAny' => ['USERS_EDIT','USERS_ADMIN']],
        'exportPdf'      => ['auth' => true, 'permsAny' => ['USERS_VIEW','USERS_ADMIN']],
        'exportUserPdf'  => ['auth' => true, 'permsAny' => ['USERS_VIEW','USERS_ADMIN']],
        'upload'         => ['auth' => true, 'permsAny' => ['USERS_ADMIN']],
        'uploadProcess'  => ['auth' => true, 'permsAny' => ['USERS_ADMIN']],
    ];

    public function __construct()
    {
        parent::__construct();
        TrainingScenarioCatalog::setDb($this->db instanceof \PDO ? $this->db : null);
    }

    /** Show list of users with filters + pagination */
public function list(): void
{
    require __DIR__ . '/../../config/db.php';
    $this->syncUsersListReturnStepIfNeeded();
    $q = trim((string)($_GET['q'] ?? ''));
    $this->syncUsersListTrainingFieldStep($q);
    $this->completeUsersTrainingNavigationStepIfNeeded(0);
    $model = new UserModel($conn);

    $department = trim((string)($_GET['department'] ?? ''));
    $status     = ($_GET['status'] ?? '') !== '' ? (string)$_GET['status'] : '';

    $filters = [
        'q'          => $q,
        'department' => $department,
        'status'     => $status,
    ];
    \App\Shared\SessionHelper::set('users.filters', $filters);

    $perPage     = 10;
    $currentPage = max(1, (int)($_GET['page'] ?? 1));

    $totalCount = $model->countFiltered($q, $status, $department);

    // ✅ calculate correct offset
    $offset = ($currentPage - 1) * $perPage;

    $users      = $model->listFiltered($q, $status, $department, $offset, $perPage);
    $totalPages = (int)ceil($totalCount / $perPage);

    $flash = SessionHelper::get('flash.message', null);

    $this->render('users/UsersList', [
        'title'       => __t('menu_users'),
        'users'       => $users,
        'currentPage' => $currentPage,
        'totalPages'  => $totalPages,
        'totalCount'  => $totalCount,
        'flash'       => $flash,
        'trainingGuide' => $this->buildUsersTrainingGuide('users/list'),
        'trainingEnabled' => training_features_enabled($this->db),
    ]);

    if ($flash !== null) {
        SessionHelper::forget('flash.message');
    }
}


    /** Edit existing user or show blank form */
    public function edit(): void
    {
        require __DIR__ . '/../../config/db.php';

        $id = (int)($_GET['id'] ?? 0);
        $this->completeUsersTrainingNavigationStepIfNeeded($id);

        $userModel     = new UserModel($conn);
        $roleModel     = new RoleModel($conn);
        $userRoleModel = new UserRoleModel($conn);

        $user = $id > 0 ? $userModel->find($id) : null;

        // ✅ Get all roles in system
        $roles = $roleModel->listAll();

        // ✅ Flatten user roles to an array of RoleIDs only
        $userRoles = $id > 0
            ? array_column($userRoleModel->listByUser($id), 'RoleID')
            : [];

        $flash = SessionHelper::get('flash.message', null);

        $this->render('users/UserForm', [
            'title'     => $id > 0 ? __t('edit_user') : __t('create_user'),
            'user'      => $user,
            'roles'     => $roles,
            'userRoles' => $userRoles,
            'flash'     => $flash,
            'trainingGuide' => $this->buildUsersTrainingGuide('users/edit', $id),
            'trainingEnabled' => training_features_enabled($this->db),
        ]);

        if ($flash !== null) {
            SessionHelper::forget('flash.message');
        }
    }

    /** Save user changes (create or update) */
    public function save(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        require __DIR__ . '/../../config/db.php';
        $model = new UserModel($conn);
        $audit = new AuditModel($conn);

        $id         = (int)($_POST['UserID'] ?? 0);
        $username   = trim((string)($_POST['Username'] ?? ''));
        $email      = trim((string)($_POST['Email'] ?? ''));
        $firstName  = trim((string)($_POST['FirstName'] ?? ''));
        $lastName   = trim((string)($_POST['LastName'] ?? ''));
        $display    = trim((string)($_POST['DisplayName'] ?? ''));
        $phone      = trim((string)($_POST['Phone'] ?? ''));
        $department = trim((string)($_POST['Department'] ?? ''));
        $jobTitle   = trim((string)($_POST['JobTitle'] ?? ''));
        $notes      = trim((string)($_POST['Notes'] ?? ''));
        $isActive   = isset($_POST['IsActive']) ? 1 : 0;
        $forceReset = isset($_POST['ForcePasswordReset']) ? 1 : 0;
        $mustChange = isset($_POST['MustChangePassword']) ? 1 : 0;

        $data = [
            'Username'           => $username,
            'Email'              => $email,
            'FirstName'          => $firstName,
            'LastName'           => $lastName,
            'DisplayName'        => $display,
            'Phone'              => $phone,
            'Department'         => $department,
            'JobTitle'           => $jobTitle,
            'Notes'              => $notes,
            'IsActive'           => $isActive,
            'ForcePasswordReset' => $forceReset,
            'MustChangePassword' => $mustChange,
            'UpdatedBy'          => (int)SessionHelper::get('auth.user_id', 0),
            'UpdatedAt'          => gmdate('Y-m-d H:i:s'),
        ];

        $this->syncUsersEditTrainingFieldStep($_POST);
        $trainingState = $this->getUsersTrainingState();
        $trainingScenarioQuery = '';
        if (is_array($trainingState) && \App\Shared\TrainingScenarioCatalog::isUsersScenario((string) ($trainingState['scenario_id'] ?? ''))) {
            $trainingScenarioQuery = '&training_scenario_id=' . rawurlencode((string) $trainingState['scenario_id']);
        }

        try {
            if ($id > 0) {
                $model->update($id, $data);
                $this->completeUsersTrainingSubmitStepIfNeeded();
                $this->flashSuccess(__t('user_updated', ['user' => $username]));

                $audit->insert([
                    'UserID'       => SessionHelper::get('auth.user_id'),
                    'Username'     => SessionHelper::get('auth.username', 'guest'),
                    'Action'       => 'UPDATE',
                    'Entity'       => 'User',
                    'EntityKey'    => (string)$id,
                    'IPAddress'    => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'Details'      => ['Username' => $username, 'Email' => $email, 'Active' => $isActive],
                    'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                    'VersionID'    => SessionHelper::get('VersionID'),
                ]);

            } else {
                $newId = $model->create($data);
                $this->completeUsersTrainingSubmitStepIfNeeded();
                $this->flashSuccess(__t('user_created', ['user' => $username]));

                $audit->insert([
                    'UserID'       => SessionHelper::get('auth.user_id'),
                    'Username'     => SessionHelper::get('auth.username', 'guest'),
                    'Action'       => 'CREATE',
                    'Entity'       => 'User',
                    'EntityKey'    => (string)$newId,
                    'IPAddress'    => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'Details'      => ['Username' => $username, 'Email' => $email, 'Active' => $isActive],
                    'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                    'VersionID'    => SessionHelper::get('VersionID'),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logHandledException('UsersController::save failed', $e, [
                'userId' => $id,
                'username' => $username,
            ]);
            $this->flashError(__t('user_save_failed') . ': ' . $e->getMessage());
        }

        header('Location: index.php?route=users/list' . $trainingScenarioQuery);
        exit;
    }

    /** Unlock user (clear locks + reset counters) */
    public function unlock(): void
    {
        $this->assertPostWithCsrf('index.php?route=users/list');

        require __DIR__ . '/../../config/db.php';
        $model = new UserModel($conn);
        $audit = new AuditModel($conn);

        $id = (int)($_POST['UserID'] ?? 0);
        if ($id <= 0) {
            $this->flashError(__t('invalid_user'));
            header('Location: index.php?route=users/list');
            exit;
        }

        try {
            $count = $model->unlock($id);
            $user  = $model->find($id);

            $this->flashSuccess(__t('lock_reset_success', [
                'user'  => $user['Username'] ?? (string)$id,
                'count' => (string)$count,
            ]));

            $audit->insert([
                'UserID'       => SessionHelper::get('auth.user_id'),
                'Username'     => SessionHelper::get('auth.username', 'guest'),
                'Action'       => 'UNLOCK',
                'Entity'       => 'User',
                'EntityKey'    => (string)$id,
                'IPAddress'    => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details'      => ['Message' => "Login lock reset for user " . ($user['Username'] ?? $id), 'Count' => $count],
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID'    => SessionHelper::get('VersionID'),
            ]);

        } catch (\Throwable $e) {
            $this->logHandledException('UsersController::unlock failed', $e, [
                'userId' => $id,
            ]);
            $this->flashError(__t('lock_reset_fail', ['msg' => $e->getMessage()]));
        }

        header('Location: index.php?route=users/list');
        exit;
    }

    /** Save assigned roles */
    public function saveRoles(): void
    {
        $this->assertPostWithCsrf('index.php?route=users/list');

        require __DIR__ . '/../../config/db.php';
        $userRoleModel = new UserRoleModel($conn);
        $audit         = new AuditModel($conn);

        $userId  = (int)($_POST['UserID'] ?? 0);
        $roleIds = array_map('intval', $_POST['RoleIDs'] ?? []);

        $trainingState = $this->getUsersTrainingState();
        $trainingScenarioQuery = '';
        if (is_array($trainingState) && \App\Shared\TrainingScenarioCatalog::isUsersScenario((string) ($trainingState['scenario_id'] ?? ''))) {
            $trainingScenarioQuery = '&training_scenario_id=' . rawurlencode((string) $trainingState['scenario_id']);
        }

        if ($userId <= 0) {
            $this->flashError(__t('invalid_user'));
            header('Location: index.php?route=users/list' . $trainingScenarioQuery);
            exit;
        }

        try {
            $userRoleModel->setRoles($userId, $roleIds);
            $this->flashSuccess(__t('roles_updated_successfully'));

            $audit->insert([
                'UserID'       => SessionHelper::get('auth.user_id'),
                'Username'     => SessionHelper::get('auth.username', 'guest'),
                'Action'       => 'UPDATE_ROLES',
                'Entity'       => 'User',
                'EntityKey'    => (string)$userId,
                'IPAddress'    => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details'      => ['RoleIDs' => $roleIds],
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID'    => SessionHelper::get('VersionID'),
            ]);
        } catch (\Throwable $e) {
            $this->logHandledException('UsersController::saveRoles failed', $e, [
                'userId' => $userId,
            ]);
            $this->flashError(__t('roles_update_failed') . ': ' . $e->getMessage());
        }

        header('Location: index.php?route=users/edit&id=' . $userId . $trainingScenarioQuery . '#roles');
        exit;
    }

public function exportPdf(): void
{
    @set_time_limit(120);

    if (!isset($conn) || !($conn instanceof \PDO)) {
        require __DIR__ . '/../../config/db.php';
    }

    $model   = new \App\Models\UserModel($conn);
    $filters = \App\Shared\SessionHelper::get('users.filters', [
        'q'          => '',
        'department' => '',
        'status'     => ''
    ]);

    // ✅ Fetch rows using same filters as list()
        $users = $model->listAllFiltered(
        $filters['q'],
        $filters['status'],
        $filters['department']
    );

    // ✅ Prepare meta line (shows active filters in report)
    $meta = [];
    if ($filters['q'] !== '')         $meta['Search']     = $filters['q'];
    if ($filters['department'] !== '') $meta['Department'] = $filters['department'];
    if ($filters['status'] !== '')     $meta['Status']     = $filters['status'] === '1' ? 'Enabled' : 'Disabled';

    // ✅ Call modular PdfReport
    \App\Shared\PdfReport::render([
        'title'    => 'Users',
        'filename' => 'Users.pdf',
        'columns'  => [
            ['label' => 'ID',           'key' => 'UserID'],
            ['label' => 'Username',     'key' => 'Username'],
            ['label' => 'Display Name', 'key' => 'DisplayName'],
            ['label' => 'Email',        'key' => 'Email'],
            ['label' => 'Department',   'key' => 'Department'],
            ['label' => 'Job',          'key' => 'JobTitle'],
            ['label' => 'Status',       'key' => 'IsActive'],
            ['label' => 'Last Login',   'key' => 'LastLoginAt'],
            ['label' => 'Failed',       'key' => 'FailedLoginCount'],
        ],
        'rows'     => array_map(function ($u) {
            // Normalise row data so PdfReport doesn’t need to know logic
            $u['IsActive'] = ((int)($u['IsActive'] ?? 0) === 1) ? 'Enabled' : 'Disabled';
            return $u;
        }, $users),
        'meta'     => $meta,
    ]);
}

public function exportUserPdf(): void
{
    @set_time_limit(120);
    require __DIR__ . '/../../config/db.php';

    $userModel     = new \App\Models\UserModel($conn);
    $roleModel     = new \App\Models\RoleModel($conn);
    $userRoleModel = new \App\Models\UserRoleModel($conn);

    $id   = (int)($_GET['id'] ?? 0);
    $user = $id > 0 ? $userModel->find($id) : null;
    if (!$user) {
        http_response_code(404);
        echo __t('user_not_found'); // translated
        return;
    }

    $roles     = $roleModel->listAll();
    $userRoles = array_column($userRoleModel->listByUser($id), 'RoleID');

    $esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    // --- Section: Edit Info ---
    $editHtml = "<table class='table table-bordered align-middle'>
        <tbody>
          <tr><th style='width:20%'>" . __t('username_label') . "</th><td style='width:80%'>{$esc($user['Username'] ?? '')}</td></tr>
          <tr><th>" . __t('email') . "</th><td>{$esc($user['Email'] ?? '')}</td></tr>
          <tr><th>" . __t('first_name') . "</th><td>{$esc($user['FirstName'] ?? '')}</td></tr>
          <tr><th>" . __t('last_name') . "</th><td>{$esc($user['LastName'] ?? '')}</td></tr>
          <tr><th>" . __t('display_name') . "</th><td>{$esc($user['DisplayName'] ?? '')}</td></tr>
          <tr><th>" . __t('phone') . "</th><td>{$esc($user['Phone'] ?? '')}</td></tr>
          <tr><th>" . __t('department') . "</th><td>{$esc($user['Department'] ?? '')}</td></tr>
          <tr><th>" . __t('job_title') . "</th><td>{$esc($user['JobTitle'] ?? '')}</td></tr>
          <tr><th>" . __t('status') . "</th><td>" . ((int)($user['IsActive'] ?? 0) === 1 ? __t('enabled') : __t('disabled')) . "</td></tr>
        </tbody>
      </table>";

    // --- Section: Details ---
    $detailsHtml = "<table class='table table-bordered align-middle'>
        <tbody>
          <tr><th style='width:20%'>" . __t('user_id') . "</th><td style='width:80%'>{$esc((string)$user['UserID'])}</td></tr>
          <tr><th>" . __t('last_login') . "</th><td>{$esc($user['LastLoginAt'] ?? '—')}</td></tr>
          <tr><th>" . __t('last_login_ip') . "</th><td>{$esc($user['LastLoginIP'] ?? '—')}</td></tr>
          <tr><th>" . __t('login_count') . "</th><td>{$esc((string)($user['LoginCount'] ?? 0))}</td></tr>
          <tr><th>" . __t('failed_logins') . "</th><td>{$esc((string)($user['FailedLoginCount'] ?? 0))}</td></tr>
          <tr><th>" . __t('last_failed_login') . "</th><td>{$esc($user['LastFailedLoginAt'] ?? '—')}</td></tr>
          <tr><th>" . __t('created_at') . "</th><td>{$esc($user['CreatedAt'] ?? '—')}</td></tr>
          <tr><th>" . __t('created_by') . "</th><td>{$esc((string)($user['CreatedBy'] ?? '—'))}</td></tr>
          <tr><th>" . __t('updated_at') . "</th><td>{$esc($user['UpdatedAt'] ?? '—')}</td></tr>
          <tr><th>" . __t('updated_by') . "</th><td>{$esc((string)($user['UpdatedBy'] ?? '—'))}</td></tr>
        </tbody>
      </table>";

    // --- Section: Assigned Roles ---
    $assignedRoleIds = array_map('intval', $userRoles);
    $rows = '';

    foreach ($roles as $r) {
        $rid      = (int)$r['RoleID'];
        $assigned = in_array($rid, $assignedRoleIds, true) ? __t('yes') : __t('no');
        $rows .= "<tr><td>{$esc((string)$r['RoleName'])}</td><td>{$assigned}</td></tr>";
    }

    if ($rows === '') {
        $rows = "<tr><td colspan='2'>" . __t('no_roles_found') . "</td></tr>";
    }

    $rolesHtml = "<table class='table table-bordered align-middle'>
        <thead><tr><th style='width:20%'>" . __t('role') . "</th><th style='width:80%'>" . __t('assigned') . "</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>";

    // --- Sections for PdfReport ---
    $sections = [
        ['title' => __t('user_details'), 'html' => $editHtml],
        ['title' => __t('user_meta_data'), 'html' => $detailsHtml],
        ['title' => __t('assign_roles'), 'html' => $rolesHtml],
    ];

    // --- Call PdfReport ---
    \App\Shared\PdfReport::render([
        'title'    => __t('user_report') . ': ' . $esc($user['Username']),
        'filename' => 'User_' . $user['UserID'] . '.pdf',
        'mode'     => 'sections',
        'sections' => $sections,
        'meta'     => [
            __t('user_id')      => $user['UserID'],
            __t('generated_by') => SessionHelper::get('auth.username', 'system'),
            __t('generated_on') => date('Y-m-d H:i'),
        ],
    ]);
}

public function upload(): void
{
    $this->render('users/UsersUpload', [
        'title' => __t('upload_users'),
    ]);
}


public function uploadProcess(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo __t('method_not_allowed');
        return;
    }

    require __DIR__ . '/../../config/db.php';
    $validator = new \App\Services\UserUploadValidator($conn);
    $userModel = new \App\Models\UserModel($conn);

    if (!isset($_FILES['uploadFile']) || $_FILES['uploadFile']['error'] !== UPLOAD_ERR_OK) {
        $this->flashError("File upload failed.");
        header("Location: index.php?route=users/upload");
        exit;
    }

    $filePath = $_FILES['uploadFile']['tmp_name'];

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);

        // ✅ Always target the "Users" sheet (admins may save with "Instructions" active)
        $sheet = $spreadsheet->getSheetByName('Users') ?? $spreadsheet->getActiveSheet();

        // ✅ Use numeric indexes (0,1,2,...) not A,B,C
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            throw new \RuntimeException("Empty worksheet.");
        }

        // Header normalisation
        $headerRow = array_map(static fn($v) => trim((string)$v), $rows[0] ?? []);
        $expected  = [
            'Username','Email','FirstName','LastName','DisplayName',
            'Phone','Department','JobTitle','IsActive','RoleID',
            'Notes','ForcePasswordReset','MustChangePassword','Password'
        ];

        // ✅ Robust header check (order must match, case-insensitive, trimmed)
        foreach ($expected as $i => $col) {
            $given = $headerRow[$i] ?? '';
            if (strcasecmp($given, $col) !== 0) {
                throw new \RuntimeException(
                    "Header mismatch at column " . ($i + 1) . ": expected '{$col}', found '{$given}'"
                );
            }
        }

        $imported = 0;

        // Iterate data rows (start at row index 1)
        for ($r = 1; $r < count($rows); $r++) {
            $row = $rows[$r];

            // Skip fully empty rows
            if (!array_filter($row, static fn($v) => (string)$v !== '')) {
                continue;
            }

            // Map by index → assoc by expected header labels
            $assoc = [];
            foreach ($expected as $i => $label) {
                $assoc[$label] = isset($row[$i]) ? (string)$row[$i] : '';
            }

            $valid = $validator->validateRow($assoc, $r + 1);
            if ($valid === null) {
                continue; // validator recorded errors
            }

            // Password handling
            if (($valid['Password'] ?? '') !== '') {
                $valid['PasswordHash'] = password_hash($valid['Password'], PASSWORD_BCRYPT);
            } else {
                $valid['PasswordHash'] = password_hash('ChangeMe123!', PASSWORD_BCRYPT);
                $valid['ForcePasswordReset'] = 1;
            }
            unset($valid['Password']);

            $valid['CreatedBy'] = (int)\App\Shared\SessionHelper::get('auth.user_id', 0);
            $valid['UpdatedBy'] = (int)\App\Shared\SessionHelper::get('auth.user_id', 0);

            if ($userModel->create($valid)) {
                $imported++;
            } else {
                // Record DB error against this row
                $err = $userModel->getLastError() ?: 'Unknown error';
                // You can accumulate this into the validator, or flash directly:
                // $validator->addError("Line " . ($r + 1) . ": DB insert failed - " . $err);
                // Alternatively, accumulate and show once at the end
                $errors[] = "Line " . ($r + 1) . ": DB insert failed - " . $err;
            }
        }

        $allErrors = $validator->getErrors() ?? [];
        if (!empty($errors ?? [])) {
            $allErrors = array_merge($allErrors, $errors);
        }

        if ($allErrors) {
            $this->flashError("Upload completed with errors:<br>" . implode("<br>", $allErrors));
        } else {
            $this->flashSuccess("Successfully imported {$imported} users.");
        }

    } catch (\Throwable $e) {
        $this->flashError("Upload failed: " . $e->getMessage());
    }

    header("Location: index.php?route=users/list");
    exit;
}

public function exportExcel(): void
{
    require __DIR__ . '/../../config/db.php';
    $usersModel = new \App\Models\UserModel($conn);

    // Filters from query string
    $q          = (string)($_GET['q'] ?? '');
    $department = (string)($_GET['department'] ?? '');
    $status     = ($_GET['status'] ?? '') !== '' ? (int)$_GET['status'] : null;

    // Get filtered list (no pagination)
    $users = $usersModel->listFiltered($q, $department, $status);

    // Excel
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Headers
    $headers = ['UserID','Username','DisplayName','Email','Department','JobTitle','IsActive','LastLoginAt','FailedLoginCount'];
    $colLetter = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($colLetter . '1', $h);
        $sheet->getStyle($colLetter . '1')->getFont()->setBold(true);
        $sheet->getStyle($colLetter . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD3D3D3'); // light gray
        $colLetter++;
    }

    // ✅ Auto-size columns after populating data
    foreach (range('A', $colLetter) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Freeze header row (row 1)
    $sheet->freezePane('A2');

    // Data
    $rowNum = 2;
    foreach ($users as $u) {
        $colLetter = 'A';
        foreach ($headers as $h) {
            $val = $u[$h] ?? '';
            // Format IsActive → Enabled/Disabled
            if ($h === 'IsActive') {
                $val = ((int)$val === 1) ? 'Enabled' : 'Disabled';
            }
            $sheet->setCellValue($colLetter . $rowNum, $val);
            $colLetter++;
        }
        $rowNum++;
    }

    // Auto-size
    foreach (range('A', $colLetter) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="users.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

private function getUsersTrainingState(): ?array
{
    $requestedScenarioId = trim((string) ($_GET['training_scenario_id'] ?? ''));
    if ($requestedScenarioId === '') {
        $requestedScenarioId = trim((string) SessionHelper::get('training.requested_scenario_id', ''));
    }
    if ($requestedScenarioId !== '' && TrainingScenarioCatalog::isUsersScenario($requestedScenarioId)) {
        $state = SessionHelper::get('training.active');
        if (is_array($state) && (string) ($state['scenario_id'] ?? '') === $requestedScenarioId) {
            return $state;
        }

        if ($this->db instanceof \PDO) {
            $userId = (int) SessionHelper::get('auth.user_id', 0);
            if ($userId > 0) {
                $model = new TrainingProgressModel($this->db);
                if ($model->supportsTrainingProgress()) {
                    $persisted = $model->loadState($userId, $requestedScenarioId);
                    if (is_array($persisted)) {
                        SessionHelper::set('training.active', $persisted);
                        return $persisted;
                    }
                }
            }
        }

        return null;
    }

    $state = SessionHelper::get('training.active');
    if (!is_array($state)) {
        return null;
    }

    $scenarioId = (string)($state['scenario_id'] ?? '');
    if (!TrainingScenarioCatalog::isUsersScenario($scenarioId)) {
        return null;
    }

    return $state;
}

private function buildUsersTrainingGuide(string $route, int $userId = 0): ?array
{
    if (!training_features_enabled($this->db)) {
        return null;
    }

    $state = $this->getUsersTrainingState();
    if ($state === null) {
        return null;
    }

    $scenarioId = (string) ($state['scenario_id'] ?? '');
    $scenario = TrainingScenarioCatalog::get($scenarioId);
    if ($scenario === null) {
        return null;
    }

    $step = TrainingScenarioCatalog::getStep($state);
    $isCompleted = (string)($state['status'] ?? '') === 'completed';

    if (!$isCompleted) {
        $stepRoute = (string)($step['route'] ?? '');
        if ($stepRoute !== $route) {
            return null;
        }
        if ($route === 'users/edit') {
            $expectedUserSampleKey = trim((string) ($step['expected_user_sample_key'] ?? ''));
            if ($expectedUserSampleKey !== '') {
                $expectedUserId = (int) ($state['samples'][$expectedUserSampleKey] ?? 0);
                if ($expectedUserId > 0 && $userId !== $expectedUserId) {
                    return null;
                }
            } elseif ($userId > 0 && $scenarioId === TrainingScenarioCatalog::USERS_CREATE_DEMO) {
                return null;
            }
        }
    }

    $sampleKey = (string)($step['sample_key'] ?? '');
    $sampleValue = $sampleKey !== '' ? (string)($state['samples'][$sampleKey] ?? '') : '';

    return [
        'scenario' => $scenario,
        'state' => $state,
        'step' => $step,
        'isCompleted' => $isCompleted,
        'sampleValue' => $sampleValue,
        'completeUrl' => 'index.php?route=training/complete',
        'runnerUrl' => TrainingScenarioCatalog::startRoute((string) ($state['scenario_id'] ?? '')),
        'stopUrl' => 'index.php?route=training/stop',
        'csrf' => csrf_token(),
    ];
}

private function completeUsersTrainingNavigationStepIfNeeded(int $userId): void
{
    $completedStepNumber = (int) ($_GET['training_step_complete'] ?? 0);
    if ($completedStepNumber <= 0) {
        return;
    }

    $state = $this->getUsersTrainingState();
    if ($state === null || (int)($state['current_step'] ?? 0) !== $completedStepNumber) {
        return;
    }

    $currentStep = TrainingScenarioCatalog::getStep($state);
    if (!is_array($currentStep) || (string) ($currentStep['completion_mode'] ?? '') !== 'navigation') {
        return;
    }

    $expectedUserSampleKey = trim((string) ($currentStep['expected_user_sample_key'] ?? ''));
    if ($expectedUserSampleKey !== '') {
        $expectedUserId = (int) ($state['samples'][$expectedUserSampleKey] ?? 0);
        if ($expectedUserId > 0 && $userId > 0 && $expectedUserId !== $userId) {
            return;
        }
    }

    $advancedState = TrainingScenarioCatalog::advanceState($state, $completedStepNumber);
    if ($advancedState !== null) {
        SessionHelper::set('training.active', $advancedState);
        SessionHelper::set('training.requested_scenario_id', (string) ($advancedState['scenario_id'] ?? ''));
        $this->persistUsersTrainingState($advancedState);
    }
}

private function syncUsersListTrainingFieldStep(string $query): void
{
    $state = $this->getUsersTrainingState();
    if ($state === null || (string)($state['status'] ?? '') !== 'active') {
        return;
    }

    $currentStep = TrainingScenarioCatalog::getStep($state);
    if (!is_array($currentStep)
        || (string)($currentStep['route'] ?? '') !== 'users/list'
        || (string)($currentStep['target'] ?? '') !== 'users-search-input'
        || (string)($currentStep['completion_mode'] ?? '') !== 'field_nonempty') {
        return;
    }

    $query = trim($query);
    if ($query === '') {
        return;
    }

    $stepNumber = (int) ($currentStep['number'] ?? 0);
    if ($stepNumber <= 0) {
        return;
    }

    $advancedState = TrainingScenarioCatalog::advanceState($state, $stepNumber);
    if ($advancedState !== null) {
        SessionHelper::set('training.active', $advancedState);
        SessionHelper::set('training.requested_scenario_id', (string) ($advancedState['scenario_id'] ?? ''));
        $this->persistUsersTrainingState($advancedState);
    }
}

private function syncUsersListReturnStepIfNeeded(): void
{
    $state = $this->getUsersTrainingState();
    if ($state === null || (string)($state['status'] ?? '') !== 'active') {
        return;
    }

    $currentStep = TrainingScenarioCatalog::getStep($state);
    if (!is_array($currentStep)
        || (string)($currentStep['completion_mode'] ?? '') !== 'navigation'
        || (string)($currentStep['route'] ?? '') !== 'users/edit'
        || (string)($currentStep['target'] ?? '') !== 'users-account-back-btn') {
        return;
    }

    $stepNumber = (int) ($currentStep['number'] ?? 0);
    if ($stepNumber <= 0) {
        return;
    }

    $advancedState = TrainingScenarioCatalog::advanceState($state, $stepNumber);
    if ($advancedState !== null) {
        SessionHelper::set('training.active', $advancedState);
        SessionHelper::set('training.requested_scenario_id', (string) ($advancedState['scenario_id'] ?? ''));
        $this->persistUsersTrainingState($advancedState);
    }
}

private function syncUsersEditTrainingFieldStep(array $postData): void
{
    $state = $this->getUsersTrainingState();
    if ($state === null || (string)($state['status'] ?? '') !== 'active') {
        return;
    }

    $currentStep = TrainingScenarioCatalog::getStep($state);
    if (!is_array($currentStep)
        || (string)($currentStep['route'] ?? '') !== 'users/edit'
        || (string)($currentStep['target'] ?? '') !== 'Notes') {
        return;
    }

    $completionMode = (string)($currentStep['completion_mode'] ?? '');
    if (!in_array($completionMode, ['field_matches_sample', 'field_nonempty', 'manual_continue'], true)) {
        return;
    }

    $postedNotes = trim((string) ($postData['Notes'] ?? ''));
    if ($postedNotes === '') {
        return;
    }

    if ($completionMode === 'field_matches_sample') {
        $sampleKey = trim((string) ($currentStep['sample_key'] ?? ''));
        $expected = $sampleKey !== '' ? trim((string) ($state['samples'][$sampleKey] ?? '')) : '';
        if ($expected === '' || $postedNotes !== $expected) {
            return;
        }
    }

    $stepNumber = (int) ($currentStep['number'] ?? 0);
    if ($stepNumber <= 0) {
        return;
    }

    $advancedState = TrainingScenarioCatalog::advanceState($state, $stepNumber);
    if ($advancedState !== null) {
        SessionHelper::set('training.active', $advancedState);
        SessionHelper::set('training.requested_scenario_id', (string) ($advancedState['scenario_id'] ?? ''));
        $this->persistUsersTrainingState($advancedState);
    }
}

private function completeUsersTrainingSubmitStepIfNeeded(): void
{
    $state = $this->getUsersTrainingState();
    if ($state === null || (string)($state['status'] ?? '') !== 'active') {
        return;
    }

    $currentStep = TrainingScenarioCatalog::getStep($state);
    if (!is_array($currentStep) || (string)($currentStep['completion_mode'] ?? '') !== 'submit_success') {
        return;
    }

    $stepNumber = (int)($currentStep['number'] ?? 0);
    if ($stepNumber <= 0) {
        return;
    }

    $advancedState = TrainingScenarioCatalog::advanceState($state, $stepNumber);
    if ($advancedState !== null) {
        SessionHelper::set('training.active', $advancedState);
        SessionHelper::set('training.requested_scenario_id', (string) ($advancedState['scenario_id'] ?? ''));
        $this->persistUsersTrainingState($advancedState);
    }
}

private function persistUsersTrainingState(array $state): void
{
    if (!training_features_enabled($this->db)) {
        return;
    }

    if (!$this->db instanceof \PDO) {
        return;
    }

    $userId = (int) SessionHelper::get('auth.user_id', 0);
    if ($userId <= 0) {
        return;
    }

    $model = new TrainingProgressModel($this->db);
    if (!$model->supportsTrainingProgress()) {
        return;
    }

    $model->saveState($userId, $state, $userId);
}


}
