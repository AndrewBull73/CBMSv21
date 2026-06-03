<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\SessionHelper;
use App\Models\AuditModel;

final class AuditController extends BaseController
{
    protected bool $requiresContext = true;

    protected array $acl = [
        // Deny everything by default
        '*'    => ['auth' => true, 'permsAny' => ['SYSADMIN']],

        // List action requires AUDIT_VIEW or SYSADMIN
        'list' => ['auth' => true, 'permsAny' => ['AUDIT_VIEW','SYSADMIN']],
    ];

    private AuditModel $model;

    public function __construct()
    {
        parent::__construct(); // ✅ enforce auth + ACL checks

        require __DIR__ . '/../../config/db.php';     // $conn is PDO
        require_once __DIR__ . '/../Models/AuditModel.php';
        $this->model = new AuditModel($conn);
    }

    public function list(): void
    {
        $q            = trim((string)($_GET['q'] ?? ''));
        $entity       = trim((string)($_GET['entity'] ?? ''));
        $userFilter   = trim((string)($_GET['userFilter'] ?? ''));
        $actionFilter = strtoupper(trim((string)($_GET['actionFilter'] ?? '')));
        $startDate    = trim((string)($_GET['startDate'] ?? ''));
        $endDate      = trim((string)($_GET['endDate'] ?? ''));
        $page         = max(1, (int)($_GET['page'] ?? 1));
        $pageSize     = max(1, min(200, (int)($_GET['pageSize'] ?? 25)));

        // Always filter by current FiscalYearID and VersionID from session
        $fy  = SessionHelper::get('FiscalYearID');
        $ver = SessionHelper::get('VersionID');

        $res   = $this->model->listLogs(
            $q,
            $entity,
            $userFilter,
            $actionFilter,
            $startDate,
            $endDate,
            $page,
            $pageSize,
            $fy,
            $ver
        );

        $rows  = $res['items'] ?? [];
        $total = (int)($res['total'] ?? 0);
        $ents  = $this->model->distinctEntities();

        $flash = SessionHelper::get('flash.message', null);

        $this->render('audit/AuditListView', [
            'title'        => __t('audit_log_title'),
            'rows'         => $rows,
            'total'        => $total,
            'page'         => $page,
            'pageSize'     => $pageSize,
            'q'            => $q,
            'entities'     => $ents,
            'entityFilter' => $entity,
            'userFilter'   => $userFilter,
            'actionFilter' => $actionFilter,
            'startDate'    => $startDate,
            'endDate'      => $endDate,
            'fiscalYearID' => $fy,
            'versionID'    => $ver,
            'flash'        => $flash,
        ]);

        if ($flash !== null) {
            SessionHelper::forget('flash.message');
        }
    }
}
