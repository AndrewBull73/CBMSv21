<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\SessionHelper;
use App\Models\DataObjectCodesModel;

require_once __DIR__ . '/../../shared/csrf.php';

final class DataObjectCodesAPIController extends BaseController
{
    /** Require auth + permission for all actions */
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['DATAOBJECTCODES_ADMIN']],
    ];

    /** Context-aware: needs FiscalYearID/VersionID in session */
    protected bool $requiresContext = true;

    /**
     * GET / dataobjectcodes.api/list
     * Query params: page, pageSize, q, sortCol, sortDir, typeId, status
     * Returns: { ok: true, data: { rows: [...], total: N } }
     */
    public function list(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Ensure DB handle in this scope
        if (!isset($conn) || !($conn instanceof \PDO)) {
            require __DIR__ . '/../../config/db.php';
        }
        require_once __DIR__ . '/../Models/DataObjectCodesModel.php';

        $fy       = (int) (SessionHelper::get('FiscalYearID') ?? 0);
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize = max(1, (int) ($_GET['pageSize'] ?? 50));
        $search   = isset($_GET['q']) ? (string) $_GET['q'] : null;
        $sortCol  = isset($_GET['sortCol']) ? (string) $_GET['sortCol'] : null;
        $sortDir  = isset($_GET['sortDir']) ? (string) $_GET['sortDir'] : null;
        $typeId   = isset($_GET['typeId']) && $_GET['typeId'] !== '' ? (int) $_GET['typeId'] : null;
        $status   = isset($_GET['status']) ? (string) $_GET['status'] : null;

        try {
            $model = new DataObjectCodesModel($conn);
            $out   = $model->listPaged($fy, $page, $pageSize, $search, $sortCol, $sortDir, $typeId, $status);

            echo json_encode(
                ['ok' => true, 'data' => $out],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(
                ['ok' => false, 'error' => 'Server error.'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
    }

    /**
     * GET / dataobjectcodes.api/get?code=...
     * Returns: { ok: true, data: {...} }
     */
    public function get(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($conn) || !($conn instanceof \PDO)) {
            require __DIR__ . '/../../config/db.php';
        }
        require_once __DIR__ . '/../Models/DataObjectCodesModel.php';

        $fy   = (int) (SessionHelper::get('FiscalYearID') ?? 0);
        $code = (string) ($_GET['code'] ?? '');

        if ($code === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing code'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        try {
            $model = new DataObjectCodesModel($conn);
            $row   = $model->getOne($fy, $code);

            echo json_encode(['ok' => true, 'data' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Server error.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * POST / dataobjectcodes.api/save
     * Body (form-data): _csrf, DataObjectCode, DataObjectName, DataObjectCodeParent, DataObjectTypeID, DataObjectDesc, DataObjectCodeStatus
     * Returns: { ok: true }
     */
    public function save(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Bad CSRF'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!isset($conn) || !($conn instanceof \PDO)) {
            require __DIR__ . '/../../config/db.php';
        }
        require_once __DIR__ . '/../Models/DataObjectCodesModel.php';

        $fy     = (int) (SessionHelper::get('FiscalYearID') ?? 0);
        $userId = (int) (SessionHelper::get('auth.user_id') ?? 0);

        // Gather inputs
        $data = [
            'DataObjectCode'        => trim((string) ($_POST['DataObjectCode'] ?? '')),
            'DataObjectName'        => trim((string) ($_POST['DataObjectName'] ?? '')),
            'DataObjectCodeParent'  => trim((string) ($_POST['DataObjectCodeParent'] ?? '')),
            'DataObjectTypeID'      => (int) ($_POST['DataObjectTypeID'] ?? 0),
            'DataObjectDesc'        => isset($_POST['DataObjectDesc']) ? (string) $_POST['DataObjectDesc'] : null,
            'DataObjectCodeStatus'  => isset($_POST['DataObjectCodeStatus']) ? (string) $_POST['DataObjectCodeStatus'] : null,
            'attributes'            => $_POST['attributes'] ?? [],
        ];

        try {
            $model = new DataObjectCodesModel($conn);
            if (!$model->upsert($fy, $data, $userId)) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'error' => $model->getLastError() !== '' ? $model->getLastError() : 'Save failed.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }
            $this->auditEvent('UPSERT', 'DataObjectCode', $fy . ':' . $data['DataObjectCode'], [
                'FiscalYearID' => $fy,
                'DataObjectCode' => $data['DataObjectCode'],
                'DataObjectName' => $data['DataObjectName'],
                'DataObjectTypeID' => $data['DataObjectTypeID'],
                'Channel' => 'api',
            ]);

            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\InvalidArgumentException $e) {
            $this->logHandledException('DataObjectCodesAPIController::save invalid argument', $e, [
                'fiscalYearId' => $fy,
                'dataObjectCode' => $data['DataObjectCode'],
            ]);
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\PDOException $e) {
            // SQLSTATE 23*** indicates integrity constraint violation (FK, CHECK, UNIQUE)
            $msg = (strpos($e->getCode(), '23') === 0)
                ? 'Constraint violation. Check Type/Parent and try again.'
                : 'Database error.';
            $this->logHandledException('DataObjectCodesAPIController::save database error', $e, [
                'fiscalYearId' => $fy,
                'dataObjectCode' => $data['DataObjectCode'],
            ]);
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            $this->logHandledException('DataObjectCodesAPIController::save failed', $e, [
                'fiscalYearId' => $fy,
                'dataObjectCode' => $data['DataObjectCode'],
            ]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Server error.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * POST / dataobjectcodes.api/delete
     * Body (form-data): _csrf, DataObjectCode
     * Returns: { ok: true }
     */
    public function delete(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Bad CSRF'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!isset($conn) || !($conn instanceof \PDO)) {
            require __DIR__ . '/../../config/db.php';
        }
        require_once __DIR__ . '/../Models/DataObjectCodesModel.php';

        $fy   = (int) (SessionHelper::get('FiscalYearID') ?? 0);
        $code = trim((string) ($_POST['DataObjectCode'] ?? ''));

        if ($code === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing code'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        try {
            $model = new DataObjectCodesModel($conn);
            if (!$model->delete($fy, $code)) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'error' => $model->getLastError() !== '' ? $model->getLastError() : 'Delete failed.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }
            $this->auditEvent('DELETE', 'DataObjectCode', $fy . ':' . $code, [
                'FiscalYearID' => $fy,
                'DataObjectCode' => $code,
                'Channel' => 'api',
            ]);

            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\PDOException $e) {
            $isFk = (strpos($e->getCode(), '23') === 0);
            $this->logHandledException('DataObjectCodesAPIController::delete database error', $e, [
                'fiscalYearId' => $fy,
                'dataObjectCode' => $code,
            ]);
            http_response_code(400);
            echo json_encode(
                ['ok' => false, 'error' => $isFk ? 'Cannot delete: code has children or is referenced.' : 'Database error.'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (\Throwable $e) {
            $this->logHandledException('DataObjectCodesAPIController::delete failed', $e, [
                'fiscalYearId' => $fy,
                'dataObjectCode' => $code,
            ]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Server error.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
