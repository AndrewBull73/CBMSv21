<?php
$embedded = isset($embedInLayout) && $embedInLayout === true;
require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ESTIMATES_VIEW', 'ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN'],
    'csrfPost' => true,
    'embeddedOnly' => true,
    'isEmbedded' => $embedded,
]);

// Ensure connection is visible even when this file is included from a controller.
if (!isset($conn) || !($conn instanceof PDO)) {
    $conn = $GLOBALS['conn'] ?? null;
}

// Simple CRUD + compute screen for tblTransactionInput

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo __t('txe_db_connection_not_available');
    exit;
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$repoRoot = dirname(__DIR__, 2);
$procScript = $repoRoot . DIRECTORY_SEPARATOR . 'backend-php' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'process_transaction_stub.php';

$messages = [];
$errors = [];
$calcOutput = '';
$record = [];
$txList = [];
$matchingTxList = [];
$txCeilingMap = [];
$recordCeiling = null;
$ctxFy = 0;
$ctxVer = 0;
$ctxDataObject = '';
$ctxDataObjectName = '';
$ctxDataObjectPath = [];
$transactionTypes = [];
$uomOptions = [];

// Load column list for optional extra fields
$columns = [];
try {
    $stmtCols = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'tblTransactionInput'");
    $columns = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $errors[] = __t('txe_failed_load_columns') . ': ' . $e->getMessage();
}
$columnSet = array_flip($columns);

$protected = [
    'TransactionID' => true,
    'CreatedDate' => true,
    'UpdatedDate' => true,
];

try {
    if (class_exists('\\App\\Shared\\SessionHelper')) {
        $ctxFy = (int)\App\Shared\SessionHelper::get('FiscalYearID', 0);
        $ctxVer = (int)\App\Shared\SessionHelper::get('VersionID', 0);
        $ctxDataObject = (string)\App\Shared\SessionHelper::get('scope.dataobject_code', '');
        $ctxDataObjectName = (string)\App\Shared\SessionHelper::get('scope.dataobject_name', '');
    } else {
        $ctxFy = (int)($_SESSION['cbmsv21']['FiscalYearID'] ?? 0);
        $ctxVer = (int)($_SESSION['cbmsv21']['VersionID'] ?? 0);
        $ctxDataObject = (string)($_SESSION['cbmsv21']['scope']['dataobject_code'] ?? '');
        $ctxDataObjectName = (string)($_SESSION['cbmsv21']['scope']['dataobject_name'] ?? '');
    }
} catch (Throwable $e) {
    $errors[] = __t('txe_failed_read_context') . ': ' . $e->getMessage();
}

try {
    if ($ctxFy > 0 && $ctxDataObject !== '') {
        $stmtPath = $conn->prepare(
            "SELECT tr.Depth,
                    c.DataObjectCode,
                    c.DataObjectName,
                    c.DataObjectTypeID,
                    t.DataObjectTypeName
             FROM dbo.tblDataObjectTree tr
             INNER JOIN dbo.tblDataObjectCodes c
                 ON c.FiscalYearID = tr.FiscalYearID
                AND c.DataObjectCode = tr.AncestorCode
             LEFT JOIN dbo.tblDataObjectTypes t
                 ON t.DataObjectTypeID = c.DataObjectTypeID
             WHERE tr.FiscalYearID = ?
               AND tr.DescendantCode = ?
             ORDER BY tr.Depth DESC"
        );
        $stmtPath->execute([$ctxFy, $ctxDataObject]);
        $ctxDataObjectPath = $stmtPath->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    $ctxDataObjectPath = [];
}

function coerceNull($v) {
    if ($v === '' || $v === null) {
        return null;
    }
    return $v;
}

function normalizeRecordTypeCode($value): string
{
    return strtoupper(trim((string)$value));
}

function runCalculation(int $txId, bool $ceilingOn, string $ceilingEngine): string {
    $php = PHP_BINARY ?: 'php';
    $script = __DIR__ . DIRECTORY_SEPARATOR . 'process_transaction_stub.php';

    $args = [];
    $args[] = $php;
    $args[] = $script;
    $args[] = '--tx=' . $txId;

    if (!$ceilingOn) {
        $args[] = '--no-ceiling-check';
    }
    if ($ceilingEngine !== '') {
        $args[] = '--ceiling-engine=' . $ceilingEngine;
    }

    $cmd = '';
    foreach ($args as $arg) {
        $cmd .= escapeshellarg($arg) . ' ';
    }
    $cmd .= '2>&1';

    $out = shell_exec($cmd);
    return $out ? $out : '';
}

/**
 * @return array<int, array<string, mixed>>
 */
function loadTransactionTypeOptions(PDO $conn): array
{
    try {
        $stmt = $conn->query("
            SELECT TransactionTypeCode, TransactionTypeName
            FROM dbo.tblTransactionTypes
            ORDER BY TransactionTypeCode
        ");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function loadUomOptionsForTransactionType(PDO $conn, string $transactionTypeCode, int $fy): array
{
    $tt = trim($transactionTypeCode);
    if ($tt === '' || $fy <= 0) {
        return [];
    }

    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT
                u.UOMCode,
                u.CalculationID
            FROM dbo.tblUOMs u
            WHERE u.FiscalYearID = ?
              AND u.TransactionType = ?
              AND u.UOMStatus = 'Active'
              AND u.UOMCode IS NOT NULL
              AND LTRIM(RTRIM(u.UOMCode)) <> ''
            ORDER BY u.UOMCode, u.CalculationID
        ");
        $stmt->execute([$fy, $tt]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function loadConfiguredGlAccountSegmentNo(PDO $conn): ?int
{
    try {
        $stmt = $conn->prepare("
            SELECT TOP 1 SettingValue
            FROM dbo.tblSystemSettings
            WHERE SettingKey IN (?, ?)
            ORDER BY CASE
                WHEN SettingKey = ? THEN 0
                WHEN SettingKey = ? THEN 1
                ELSE 99
            END
        ");
        $stmt->execute([
            'FIN_GL_ACCOUNT_SEGMENT_NO',
            'GLAccountSegmentNo',
            'FIN_GL_ACCOUNT_SEGMENT_NO',
            'GLAccountSegmentNo',
        ]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }
        $segmentNo = (int)trim((string)$value);
        if ($segmentNo < 1 || $segmentNo > 20) {
            return null;
        }
        return $segmentNo;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * @return array<int, array{value:string,label:string,level:int}>
 */
function loadGlGroupingOptions(PDO $conn, int $fy, int $ver, string $budgetClassCode): array
{
    $budgetClassId = (int)trim($budgetClassCode);
    if ($fy <= 0 || $ver <= 0 || $budgetClassId <= 0) {
        return [];
    }

    try {
        $stmt = $conn->prepare("
            SELECT Prefix, GroupName, Level, SortOrder
            FROM dbo.tblGLGrouping
            WHERE FiscalYearID = ?
              AND VersionID = ?
              AND BudgetClassID = ?
              AND Prefix IS NOT NULL
              AND LTRIM(RTRIM(Prefix)) <> ''
            ORDER BY SortOrder, Level, Prefix, GroupName
        ");
        $stmt->execute([$fy, $ver, $budgetClassId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $prefix = trim((string)($row['Prefix'] ?? ''));
        $groupName = trim((string)($row['GroupName'] ?? ''));
        $level = max(0, (int)($row['Level'] ?? 0));
        if ($prefix === '') {
            continue;
        }
        $key = $prefix;
        if (isset($out[$key])) {
            continue;
        }
        $label = $prefix . ($groupName !== '' ? ' - ' . $groupName : '');
        if ($level > 0) {
            $label .= ' (L' . $level . ')';
        }
        $out[$key] = [
            'value' => $prefix,
            'label' => $label,
            'level' => $level,
        ];
    }

    return array_values($out);
}

function deriveGlGroupingPrefixFromAccountCode(string $glAccountCode, array $groupOptions): string
{
    $glAccountCode = trim($glAccountCode);
    if ($glAccountCode === '' || empty($groupOptions)) {
        return '';
    }

    $bestPrefix = '';
    foreach ($groupOptions as $option) {
        $prefix = trim((string)($option['value'] ?? ''));
        if ($prefix === '' || stripos($glAccountCode, $prefix) !== 0) {
            continue;
        }
        if (strlen($prefix) > strlen($bestPrefix)) {
            $bestPrefix = $prefix;
        }
    }

    return $bestPrefix;
}

/**
 * @return array<int, array<string, mixed>>
 */
function loadMatchingTransactionsForEditor(
    PDO $conn,
    int $fy,
    int $ver,
    string $dataObjectCode,
    string $transactionTypeCode,
    string $glGroupingPrefix = ''
): array {
    $tt = trim($transactionTypeCode);
    $glPrefix = trim($glGroupingPrefix);
    if ($fy <= 0 || $ver <= 0 || $dataObjectCode === '' || $tt === '') {
        return [];
    }

    $params = [$fy, $ver, $dataObjectCode, $tt];
    $where = "
        WHERE ti.FiscalYearID = ?
          AND ti.VersionID = ?
          AND ti.DataObjectCode = ?
          AND ti.TransactionTypeCode = ?
    ";
    if ($glPrefix !== '') {
        $where .= " AND ti.GLAccountCode LIKE ? ";
        $params[] = $glPrefix . '%';
    }

    $sql = "
        WITH Latest AS (
            SELECT rf.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY rf.TransactionID
                       ORDER BY rf.CalculatedDate DESC, rf.TransactionResultFlatID DESC
                   ) AS rn
            FROM dbo.tblTransactionResultFlat rf
        )
        SELECT TOP 100
            ti.TransactionID,
            ti.TransactionTypeCode,
            ti.AccountCode,
            ti.GLAccountCode,
            ti.CalculationID,
            ti.UOMCodeInpC,
            ti.CreatedDate,
            COALESCE(l.BPTotal, ti.BPTotalInpN, 0) AS BPTotal
        FROM dbo.tblTransactionInput ti
        LEFT JOIN Latest l
            ON l.TransactionID = ti.TransactionID
           AND l.rn = 1
        {$where}
        ORDER BY
            CASE WHEN ti.TransactionID = ISNULL(TRY_CONVERT(int, ?), 0) THEN 0 ELSE 1 END,
            ti.TransactionID DESC
    ";
    $params[] = isset($_GET['tx']) ? (int)$_GET['tx'] : (isset($_POST['TransactionID']) ? (int)$_POST['TransactionID'] : 0);

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $txId = isset($_POST['TransactionID']) ? (int)$_POST['TransactionID'] : 0;
    $existing = [];
    if ($txId > 0) {
        try {
            $stmtExisting = $conn->prepare('SELECT TOP 1 * FROM tblTransactionInput WHERE TransactionID = ?');
            $stmtExisting->execute([$txId]);
            $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $existing = [];
        }
    }

    $input = [
        'HeadRecordID' => coerceNull($_POST['HeadRecordID'] ?? ($existing['HeadRecordID'] ?? null)),
        'RecordTypeCode' => trim((string)($_POST['RecordTypeCode'] ?? ($existing['RecordTypeCode'] ?? 'H'))),
        'FiscalYearID' => coerceNull($_POST['FiscalYearID'] ?? ($existing['FiscalYearID'] ?? ($ctxFy > 0 ? $ctxFy : null))),
        'VersionID' => coerceNull($_POST['VersionID'] ?? ($existing['VersionID'] ?? ($ctxVer > 0 ? $ctxVer : null))),
        'DataObjectCode' => trim((string)($_POST['DataObjectCode'] ?? ($existing['DataObjectCode'] ?? $ctxDataObject))),
        'TransactionTypeCode' => trim((string)($_POST['TransactionTypeCodeRefresh'] ?? $_POST['TransactionTypeCode'] ?? ($existing['TransactionTypeCode'] ?? ''))),
        'AccountCode' => coerceNull($_POST['AccountCode'] ?? null),
        'GLAccountCode' => coerceNull($_POST['GLAccountCode'] ?? null),
        'UOMCodeInpC' => coerceNull($_POST['UOMCodeInpC'] ?? null),
        'CalculationID' => coerceNull($_POST['CalculationID'] ?? ($existing['CalculationID'] ?? null)),
        'UseLiveRates' => !empty($_POST['UseLiveRates']) ? 1 : 0,
        'BPQtyInpN' => coerceNull($_POST['BPQtyInpN'] ?? null),
    ];
    if ($input['HeadRecordID'] === null) {
        $input['HeadRecordID'] = 0;
    }

    for ($i = 1; $i <= 12; $i++) {
        $input['BP' . $i . 'QtyInpN'] = coerceNull($_POST['BP' . $i . 'QtyInpN'] ?? null);
        $input['BP' . $i . 'UOMRate'] = coerceNull($_POST['BP' . $i . 'UOMRate'] ?? null);
        $input['BP' . $i . 'InpN'] = coerceNull($_POST['BP' . $i . 'InpN'] ?? null);
    }
    for ($s = 1; $s <= 20; $s++) {
        $segKey = 'Segment' . $s . 'Code';
        $input[$segKey] = coerceNull($_POST[$segKey] ?? ($existing[$segKey] ?? null));
    }

    $inputSegmentPositionMeta = loadSegmentPositionMeta($conn);
    $inputSegmentLabels = loadSegmentLabels($conn);
    $glAccountSegmentNo = loadConfiguredGlAccountSegmentNo($conn);
    $scopeDerivedSegments = deriveScopeSegmentValues($ctxDataObjectPath, $inputSegmentLabels);
    $segmentConfigForInput = loadSegmentConfig(
        $conn,
        (string)$input['TransactionTypeCode'],
        (int)($input['FiscalYearID'] ?? 0),
        (int)($input['VersionID'] ?? 0),
        false
    );
    foreach ($segmentConfigForInput as $cfg) {
        $segNo = (int)($cfg['SegmentNo'] ?? 0);
        if ($segNo < 1 || $segNo > 20) {
            continue;
        }
        if (((int)($cfg['VisibleFlag'] ?? 0)) === 1) {
            continue;
        }
        if (!isset($scopeDerivedSegments[$segNo])) {
            continue;
        }
        $input['Segment' . $segNo . 'Code'] = $scopeDerivedSegments[$segNo];
    }
    $input['AccountCode'] = coerceNull(deriveAccountCodeFromSegments($input, $inputSegmentPositionMeta));
    $derivedGlAccountCode = deriveGlAccountCode($input, $glAccountSegmentNo);
    if ($derivedGlAccountCode !== '') {
        $input['GLAccountCode'] = $derivedGlAccountCode;
    }
    if (trim((string)($input['UOMCodeInpC'] ?? '')) !== '' && empty($input['CalculationID'])) {
        $uomRows = loadUomOptionsForTransactionType($conn, (string)$input['TransactionTypeCode'], (int)($input['FiscalYearID'] ?? 0));
        foreach ($uomRows as $uomRow) {
            if (trim((string)($uomRow['UOMCode'] ?? '')) !== trim((string)$input['UOMCodeInpC'])) {
                continue;
            }
            $calcId = (int)($uomRow['CalculationID'] ?? 0);
            if ($calcId > 0) {
                $input['CalculationID'] = $calcId;
            }
            break;
        }
    }

    $extra = trim((string)($_POST['ExtraFields'] ?? ''));
    if ($extra !== '') {
        $decoded = json_decode($extra, true);
        if (!is_array($decoded)) {
            $errors[] = __t('txe_extra_fields_json_invalid');
        } else {
            foreach ($decoded as $k => $v) {
                if (!isset($columnSet[$k]) || isset($protected[$k])) {
                    $errors[] = "Extra field not allowed: {$k}";
                    continue;
                }
                $input[$k] = $v;
            }
        }
    }

    if ($action === 'refresh_config') {
        $postedTransactionType = trim((string)($_POST['TransactionTypeCodeRefresh'] ?? $_POST['TransactionTypeCode'] ?? ''));
        $existingTransactionType = trim((string)($existing['TransactionTypeCode'] ?? ''));
        $transactionTypeChanged = $postedTransactionType !== '' && $postedTransactionType !== $existingTransactionType;

        $record = array_merge($existing, $input);

        if ($postedTransactionType !== '') {
            $record['TransactionTypeCode'] = $postedTransactionType;
        }

        if ($transactionTypeChanged) {
            $record['CalculationID'] = null;
            $record['UOMCodeInpC'] = null;
            $record['BPQtyInpN'] = null;
            for ($s = 1; $s <= 20; $s++) {
                $record['Segment' . $s . 'Code'] = null;
            }
        }

        if ($txId > 0) {
            $record['TransactionID'] = $txId;
        }
    } elseif ($action === 'delete') {
        if ($txId <= 0) {
            $errors[] = __t('txe_transaction_id_required_delete');
        } else {
            $stmt = $conn->prepare('DELETE FROM tblTransactionInput WHERE TransactionID = ?');
            $stmt->execute([$txId]);
            $messages[] = __t('txe_deleted_transaction') . " {$txId}.";
            $record = [];
        }
    } elseif ($action === 'save' || $action === 'save_compute' || $action === 'compute') {
        if ($action !== 'compute') {
            // Basic required fields
            if ($input['FiscalYearID'] === null || $input['VersionID'] === null || $input['DataObjectCode'] === '' || $input['TransactionTypeCode'] === '') {
                $errors[] = __t('txe_required_fields_missing');
            }
            $segmentCfgForValidation = loadSegmentConfig($conn, (string)$input['TransactionTypeCode'], (int)($input['FiscalYearID'] ?? 0), (int)($input['VersionID'] ?? 0), false);
            foreach ($segmentCfgForValidation as $cfg) {
                $segNo = (int)($cfg['SegmentNo'] ?? 0);
                if ($segNo < 1 || $segNo > 20) {
                    continue;
                }
                $required = ((int)($cfg['RequiredFlag'] ?? 0)) === 1;
                if (!$required) {
                    continue;
                }
                $segKey = 'Segment' . $segNo . 'Code';
                $segVal = trim((string)($input[$segKey] ?? ''));
                if ($segVal === '') {
                    $errors[] = $segKey . ' is required for TransactionTypeCode ' . (string)$input['TransactionTypeCode'] . '.';
                }
            }
            if ($input['RecordTypeCode'] === '') {
                $input['RecordTypeCode'] = 'H';
            }

            if (empty($errors)) {
                if ($txId > 0) {
                    $sets = [];
                    $vals = [];
                    foreach ($input as $k => $v) {
                        if (!isset($columnSet[$k]) || isset($protected[$k])) {
                            continue;
                        }
                        $sets[] = "{$k} = ?";
                        $vals[] = $v;
                    }
                    $sets[] = 'UpdatedBy = ?';
                    $sets[] = 'UpdatedDate = GETDATE()';
                    $vals[] = 1;

                    $sql = 'UPDATE tblTransactionInput SET ' . implode(', ', $sets) . ' WHERE TransactionID = ?';
                    $vals[] = $txId;
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($vals);
                    $messages[] = __t('txe_updated_transaction') . " {$txId}.";
                } else {
                    $cols = [];
                    $place = [];
                    $vals = [];
                    foreach ($input as $k => $v) {
                        if (!isset($columnSet[$k]) || isset($protected[$k])) {
                            continue;
                        }
                        $cols[] = $k;
                        $place[] = '?';
                        $vals[] = $v;
                    }
                    $cols[] = 'CreatedBy';
                    $place[] = '?';
                    $vals[] = 1;

                    $sql = 'INSERT INTO tblTransactionInput (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $place) . ')';
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($vals);
                    $txId = (int)$conn->lastInsertId();

                    if (strtoupper($input['RecordTypeCode']) === 'H' && (int)($input['HeadRecordID'] ?? 0) === 0) {
                        $stmt = $conn->prepare('UPDATE tblTransactionInput SET HeadRecordID = ? WHERE TransactionID = ?');
                        $stmt->execute([$txId, $txId]);
                    }
                    $messages[] = __t('txe_inserted_transaction') . " {$txId}.";
                }
            }
        }

        if (empty($errors) && $txId > 0 && ($action === 'save_compute' || $action === 'compute')) {
            $ceilingOn = !empty($_POST['CeilingChecks']);
            $ceilingEngine = trim((string)($_POST['CeilingEngine'] ?? 'redis'));
            $calcOutput = runCalculation($txId, $ceilingOn, $ceilingEngine);
            $messages[] = __t('txe_calculation_executed') . " {$txId}.";
        }

        if ($txId > 0) {
            $stmt = $conn->prepare('SELECT * FROM tblTransactionInput WHERE TransactionID = ?');
            $stmt->execute([$txId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
    }
}

// Load record by GET tx
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['tx'])) {
    $txId = (int)$_GET['tx'];
    if ($txId > 0) {
        $stmt = $conn->prepare('SELECT * FROM tblTransactionInput WHERE TransactionID = ?');
        $stmt->execute([$txId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$record) {
            $errors[] = __t('txe_transaction_not_found') . " {$txId}.";
        }
    }
}

// Load transaction list for current context
try {
    if ($ctxFy > 0 && $ctxVer > 0 && $ctxDataObject !== '') {
        $stmtList = $conn->prepare(
            "SELECT TOP 200 TransactionID, HeadRecordID, RecordTypeCode, TransactionTypeCode, CalculationID, CeilingDefinitionID, UOMCodeInpC, CreatedDate
             FROM tblTransactionInput
             WHERE FiscalYearID = ? AND VersionID = ? AND DataObjectCode = ?
             ORDER BY TransactionTypeCode, TransactionID DESC"
        );
        $stmtList->execute([$ctxFy, $ctxVer, $ctxDataObject]);
        $txList = $stmtList->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errors[] = __t('txe_failed_load_tx_list') . ': ' . $e->getMessage();
}

$transactionTypes = loadTransactionTypeOptions($conn);

try {
    $txIdsForCeilings = [];
    if (!empty($record)) {
        $recordTxId = (int)($record['TransactionID'] ?? 0);
        if ($recordTxId > 0) {
            $txIdsForCeilings[] = $recordTxId;
        }
    }

    $txCeilingMap = loadCeilingSnapshotsForTransactions($conn, $txIdsForCeilings);
    if (!empty($record)) {
        $recordTxId = (int)($record['TransactionID'] ?? 0);
        if ($recordTxId > 0 && isset($txCeilingMap[$recordTxId])) {
            $recordCeiling = $txCeilingMap[$recordTxId];
        }
    }
} catch (Throwable $e) {
    // Non-fatal UI enrichment only.
    $txCeilingMap = [];
    $recordCeiling = null;
}

function val(array $record, string $key, $default = '') {
    if (array_key_exists($key, $record)) {
        return $record[$key];
    }
    return $default;
}

/**
 * @return array<int, array<string, mixed>>
 */
function loadCeilingSnapshotsForTransactions(PDO $conn, array $transactionIds): array
{
    $txIds = array_values(array_unique(array_map('intval', $transactionIds)));
    $txIds = array_values(array_filter($txIds, static fn(int $id): bool => $id > 0));
    if (empty($txIds)) {
        return [];
    }

    $in = implode(',', array_fill(0, count($txIds), '?'));
    $sql = "
        SELECT
            ti.TransactionID,
            xa.CeilingDefinitionID,
            xa.BalanceBPTotal,
            xa.CeilingBPTotal,
            xa.LastTransactionID
        FROM dbo.tblTransactionInput ti
        OUTER APPLY (
            SELECT TOP 1
                cb.CeilingDefinitionID,
                cb.BalanceBPTotal,
                cd.CeilingBPTotal,
                cb.LastTransactionID
            FROM dbo.tblCeilingBalance cb
            LEFT JOIN dbo.tblCeilingDefinition cd
                ON cd.CeilingDefinitionID = cb.CeilingDefinitionID
            WHERE cb.CeilingDefinitionID = ti.CeilingDefinitionID
               OR cb.LastTransactionID = ti.TransactionID
            ORDER BY CASE WHEN cb.CeilingDefinitionID = ti.CeilingDefinitionID THEN 0 ELSE 1 END,
                     cb.CeilingDefinitionID
        ) xa
        WHERE ti.TransactionID IN ($in)
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($txIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $out = [];
    foreach ($rows as $row) {
        $txId = (int)($row['TransactionID'] ?? 0);
        if ($txId <= 0) {
            continue;
        }
        $out[$txId] = [
            'ceiling_definition_id' => isset($row['CeilingDefinitionID']) ? (int)$row['CeilingDefinitionID'] : null,
            'balance_total' => isset($row['BalanceBPTotal']) ? (float)$row['BalanceBPTotal'] : null,
            'ceiling_total' => isset($row['CeilingBPTotal']) ? (float)$row['CeilingBPTotal'] : null,
            'last_transaction_id' => isset($row['LastTransactionID']) ? (int)$row['LastTransactionID'] : null,
        ];
    }

    return $out;
}

/**
 * @return array<int, array<string, string>>
 */
function loadSegmentOptions(PDO $conn, int $segmentNo, int $fy, int $ver, string $lookupSourceType = 'tblSegments', ?string $lookupFilter = null): array
{
    $out = [];
    if (strtolower(trim($lookupSourceType)) === 'tblsegments') {
        try {
            $dataObjectCode = '';
            if (class_exists('\\App\\Shared\\SessionHelper')) {
                $dataObjectCode = (string)\App\Shared\SessionHelper::get('scope.dataobject_code', '');
            } else {
                $dataObjectCode = (string)($_SESSION['cbmsv21']['scope']['dataobject_code'] ?? '');
            }

            $sql = "SELECT SegmentCode, SegmentName, SortOrder
                    FROM dbo.tblSegmentValues
                    WHERE SegmentNo = ?
                      AND FiscalYearID = ?
                      AND DataObjectCode = ?
                      AND ActiveFlag = 1
                      AND SegmentCode IS NOT NULL
                      AND LTRIM(RTRIM(SegmentCode)) <> ''";
            $params = [$segmentNo, $fy, $dataObjectCode];

            $flt = trim((string)$lookupFilter);
            if ($flt !== '') {
                $sql .= " AND SegmentCode LIKE ?";
                $params[] = $flt;
            }

            $sql .= " ORDER BY SortOrder, SegmentCode, SegmentName";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $code = trim((string)($row['SegmentCode'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $name = trim((string)($row['SegmentName'] ?? ''));
                $out[] = [
                    'value' => $code,
                    'label' => $name !== '' ? ($code . ' - ' . $name) : $code,
                ];
            }
        } catch (Throwable $e) {
            $out = [];
        }
    }

    $deduped = [];
    foreach ($out as $item) {
        $value = trim((string)($item['value'] ?? ''));
        if ($value === '') {
            continue;
        }
        $deduped[$value] = [
            'value' => $value,
            'label' => (string)($item['label'] ?? $value),
        ];
    }

    return array_values($deduped);
}

/**
 * Ensures a saved/current segment value still appears in the dropdown even if
 * the current UI filter would otherwise hide it.
 *
 * @param array<int, array<string, string>> $options
 * @return array<int, array<string, string>>
 */
function ensureSegmentOptionPresent(array $options, string $currentValue): array
{
    $currentValue = trim($currentValue);
    if ($currentValue === '') {
        return $options;
    }

    foreach ($options as $option) {
        if (trim((string)($option['value'] ?? '')) === $currentValue) {
            return $options;
        }
    }

    array_unshift($options, [
        'value' => $currentValue,
        'label' => $currentValue . ' (selected)',
    ]);

    return $options;
}

/**
 * @param array<int, array<string, mixed>> $options
 * @return array<int, array<string, mixed>>
 */
function ensureUomOptionPresent(array $options, string $currentValue, string $currentCalculationId = ''): array
{
    $currentValue = trim($currentValue);
    if ($currentValue === '') {
        return $options;
    }

    foreach ($options as $option) {
        if (trim((string)($option['UOMCode'] ?? '')) === $currentValue) {
            return $options;
        }
    }

    array_unshift($options, [
        'UOMCode' => $currentValue,
        'CalculationID' => $currentCalculationId !== '' ? (int)$currentCalculationId : null,
    ]);

    return $options;
}

/**
 * Returns segment config rows for the transaction type.
 * Falls back to default Segment1-3 if no config found.
 *
 * @return array<int,array<string,mixed>>
 */
function loadSegmentConfig(PDO $conn, string $transactionTypeCode, int $fy, int $ver, bool $visibleOnly = true): array
{
    $rows = [];
    $tt = trim($transactionTypeCode);
    if ($tt !== '') {
        try {
            $sql = "
                SELECT SegmentNo, VisibleFlag, RequiredFlag, LookupSourceType, LookupFilter, DisplayOrder
                FROM dbo.tblTransactionTypeSegmentConfig
                WHERE TransactionTypeCode = ?
                  AND ActiveFlag = 1
                  AND (FiscalYearID IS NULL OR FiscalYearID = ?)
                  AND (VersionID IS NULL OR VersionID = ?)
                ORDER BY
                    CASE WHEN FiscalYearID IS NULL THEN 1 ELSE 0 END,
                    CASE WHEN VersionID IS NULL THEN 1 ELSE 0 END,
                    DisplayOrder ASC,
                    SegmentNo ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$tt, $fy, $ver]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $rows = [];
        }
    }

    if (empty($rows)) {
        return [
            ['SegmentNo' => 1, 'VisibleFlag' => 1, 'RequiredFlag' => 0, 'LookupSourceType' => 'tblSegments', 'LookupFilter' => null, 'DisplayOrder' => 1],
            ['SegmentNo' => 2, 'VisibleFlag' => 1, 'RequiredFlag' => 0, 'LookupSourceType' => 'tblSegments', 'LookupFilter' => null, 'DisplayOrder' => 2],
            ['SegmentNo' => 3, 'VisibleFlag' => 1, 'RequiredFlag' => 0, 'LookupSourceType' => 'tblSegments', 'LookupFilter' => null, 'DisplayOrder' => 3],
        ];
    }

    if ($visibleOnly) {
        $rows = array_values(array_filter($rows, static function ($r): bool {
            return ((int)($r['VisibleFlag'] ?? 0)) === 1;
        }));
    }

    return $rows;
}

/**
 * @return array<int, array{label:string,group:string,display_order:int}>
 */
function loadSegmentDisplayMeta(PDO $conn): array
{
    try {
        $stmt = $conn->query("
            SELECT SegmentCode, SegmentName, SegmentGroup, DisplayOrder
            FROM dbo.tblSegments
            WHERE SegmentCode IS NOT NULL
            ORDER BY TRY_CONVERT(int, SegmentCode), SegmentCode
        ");
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        return [];
    }

    $meta = [];
    foreach ($rows as $row) {
        $segmentCode = (int)trim((string)($row['SegmentCode'] ?? '0'));
        $segmentName = trim((string)($row['SegmentName'] ?? ''));
        $segmentGroup = trim((string)($row['SegmentGroup'] ?? ''));
        $displayOrder = (int)($row['DisplayOrder'] ?? 0);
        if ($segmentCode <= 0 || $segmentName === '') {
            continue;
        }
        $meta[$segmentCode] = [
            'label' => $segmentName,
            'group' => $segmentGroup !== '' ? $segmentGroup : 'Segments',
            'display_order' => $displayOrder > 0 ? $displayOrder : $segmentCode,
        ];
    }

    return $meta;
}

/**
 * @return array<int,string>
 */
function loadSegmentLabels(PDO $conn): array
{
    $labels = [];
    foreach (loadSegmentDisplayMeta($conn) as $segmentCode => $meta) {
        $labels[(int)$segmentCode] = (string)($meta['label'] ?? '');
    }

    return $labels;
}

/**
 * @return array<int, array{start:int,end:int,max_length:int,min_length:int,delimiter:string}>
 */
function loadSegmentPositionMeta(PDO $conn): array
{
    try {
        $stmt = $conn->query("
            SELECT SegmentCode, StartPoint, EndPoint, MaxLength, MinLength, Delimiter
            FROM dbo.tblSegments
            WHERE SegmentCode IS NOT NULL
            ORDER BY TRY_CONVERT(int, SegmentCode), SegmentCode
        ");
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        return [];
    }

    $meta = [];
    foreach ($rows as $row) {
        $segmentCode = (int)trim((string)($row['SegmentCode'] ?? '0'));
        if ($segmentCode <= 0) {
            continue;
        }
        $meta[$segmentCode] = [
            'start' => max(0, (int)($row['StartPoint'] ?? 0)),
            'end' => max(0, (int)($row['EndPoint'] ?? 0)),
            'max_length' => max(0, (int)($row['MaxLength'] ?? 0)),
            'min_length' => max(0, (int)($row['MinLength'] ?? 0)),
            'delimiter' => trim((string)($row['Delimiter'] ?? '')),
        ];
    }

    return $meta;
}

function deriveAccountCodeFromSegments(array $values, array $positionMeta): string
{
    return formatAccountCodeFromSegments($values, $positionMeta);
}

function formatAccountCodeFromSegments(array $values, array $positionMeta): string
{
    if (empty($positionMeta)) {
        return '';
    }

    uasort($positionMeta, static function (array $a, array $b): int {
        $cmp = ((int)($a['start'] ?? 0)) <=> ((int)($b['start'] ?? 0));
        if ($cmp !== 0) {
            return $cmp;
        }
        return ((int)($a['end'] ?? 0)) <=> ((int)($b['end'] ?? 0));
    });

    $parts = [];
    $delimiter = '';
    foreach ($positionMeta as $segmentNo => $meta) {
        $segmentValue = trim((string)($values['Segment' . $segmentNo . 'Code'] ?? ''));
        if ($segmentValue === '') {
            continue;
        }
        $parts[] = $segmentValue;
        if ($delimiter === '') {
            $delimiter = trim((string)($meta['delimiter'] ?? ''));
        }
    }

    if (empty($parts)) {
        return '';
    }

    return $delimiter !== '' ? implode($delimiter, $parts) : implode('', $parts);
}

/**
 * @param array<int, string> $segmentValues
 * @return array<int, string>
 */
function loadSegmentValueNames(PDO $conn, int $fy, string $dataObjectCode, array $segmentValues): array
{
    $pairs = [];
    foreach ($segmentValues as $segmentNo => $value) {
        $segmentNo = (int)$segmentNo;
        $value = trim((string)$value);
        if ($segmentNo <= 0 || $value === '') {
            continue;
        }
        $pairs[$segmentNo] = $value;
    }

    if ($fy <= 0 || $dataObjectCode === '' || empty($pairs)) {
        return [];
    }

    $segmentNos = array_keys($pairs);
    $in = implode(',', array_fill(0, count($segmentNos), '?'));
    $params = array_merge([$fy, $dataObjectCode], $segmentNos);

    try {
        $stmt = $conn->prepare("
            SELECT SegmentNo, SegmentCode, SegmentName
            FROM dbo.tblSegmentValues
            WHERE FiscalYearID = ?
              AND DataObjectCode = ?
              AND SegmentNo IN ($in)
              AND ActiveFlag = 1
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $segmentNo = (int)($row['SegmentNo'] ?? 0);
        $segmentCode = trim((string)($row['SegmentCode'] ?? ''));
        $segmentName = trim((string)($row['SegmentName'] ?? ''));
        if ($segmentNo <= 0 || $segmentCode === '' || !isset($pairs[$segmentNo])) {
            continue;
        }
        if ($pairs[$segmentNo] !== $segmentCode || isset($out[$segmentNo])) {
            continue;
        }
        $out[$segmentNo] = $segmentName;
    }

    return $out;
}

/**
 * @param array<int, string> $segmentLabels
 * @param array<int, string> $segmentValueNames
 * @return array<int, array{segment_no:int,label:string,value:string,value_name:string}>
 */
function buildSegmentContextParts(array $values, array $positionMeta, array $segmentLabels, array $segmentValueNames = []): array
{
    if (empty($positionMeta)) {
        return [];
    }

    $ordered = [];
    foreach ($positionMeta as $segmentNo => $meta) {
        $segmentNo = (int)$segmentNo;
        $value = trim((string)($values['Segment' . $segmentNo . 'Code'] ?? ''));
        if ($segmentNo <= 0 || $value === '') {
            continue;
        }
        $ordered[] = [
            'segment_no' => $segmentNo,
            'start' => (int)($meta['start'] ?? 0),
            'label' => trim((string)($segmentLabels[$segmentNo] ?? ('Segment ' . $segmentNo))),
            'value' => $value,
            'value_name' => trim((string)($segmentValueNames[$segmentNo] ?? '')),
        ];
    }

    usort($ordered, static function (array $a, array $b): int {
        $cmp = ((int)$a['start']) <=> ((int)$b['start']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return ((int)$a['segment_no']) <=> ((int)$b['segment_no']);
    });

    return array_map(static function (array $row): array {
        unset($row['start']);
        return $row;
    }, $ordered);
}

function deriveGlAccountCode(array $values, ?int $segmentNo): string
{
    if ($segmentNo === null) {
        return '';
    }

    return trim((string)($values['Segment' . $segmentNo . 'Code'] ?? ''));
}

function normalizeScopeSegmentName(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    return $value;
}

/**
 * @param array<int, array<string, mixed>> $ctxDataObjectPath
 * @param array<int, string> $segmentLabels
 * @return array<int, string>
 */
function deriveScopeSegmentValues(array $ctxDataObjectPath, array $segmentLabels): array
{
    if (empty($ctxDataObjectPath) || empty($segmentLabels)) {
        return [];
    }

    $labelMap = [];
    foreach ($segmentLabels as $segmentNo => $label) {
        $normalized = normalizeScopeSegmentName((string)$label);
        if ($segmentNo > 0 && $normalized !== '') {
            $labelMap[$normalized] = (int)$segmentNo;
        }
    }

    $out = [];
    foreach ($ctxDataObjectPath as $node) {
        $typeName = normalizeScopeSegmentName((string)($node['DataObjectTypeName'] ?? ''));
        $code = trim((string)($node['DataObjectCode'] ?? ''));
        if ($typeName === '' || $code === '' || !isset($labelMap[$typeName])) {
            continue;
        }
        $segmentNo = $labelMap[$typeName];
        if (!isset($out[$segmentNo])) {
            $out[$segmentNo] = $code;
        }
    }

    return $out;
}

$txIdVal = (int)($record['TransactionID'] ?? ($_POST['TransactionID'] ?? 0));
$requestedTransactionType = trim((string)($_POST['TransactionTypeCodeRefresh'] ?? $_POST['TransactionTypeCode'] ?? $_GET['tt'] ?? ''));
$currentTransactionType = $requestedTransactionType !== ''
    ? $requestedTransactionType
    : trim((string)($record['TransactionTypeCode'] ?? ''));
$currentUomCode = trim((string)($record['UOMCodeInpC'] ?? $_POST['UOMCodeInpC'] ?? ''));
$currentCalculationId = trim((string)($record['CalculationID'] ?? $_POST['CalculationID'] ?? ''));
$requestedGlGroupingPrefix = trim((string)($_POST['GLGroupingPrefix'] ?? $_GET['glg'] ?? ''));
$editorRouteUrl = 'index.php?route=transaction-input/editor';
$transactionTypeChangedForRender = $requestedTransactionType !== ''
    && $requestedTransactionType !== trim((string)($record['TransactionTypeCode'] ?? ''));
if ($transactionTypeChangedForRender) {
    $record['TransactionTypeCode'] = $currentTransactionType;
    $record['CalculationID'] = null;
    $record['UOMCodeInpC'] = null;
    $record['BPQtyInpN'] = null;
    for ($s = 1; $s <= 20; $s++) {
        $record['Segment' . $s . 'Code'] = null;
    }
    $currentUomCode = '';
    $currentCalculationId = '';
}
$uomOptions = loadUomOptionsForTransactionType($conn, $currentTransactionType, $ctxFy);
$glAccountSegmentNo = loadConfiguredGlAccountSegmentNo($conn);
$glGroupingOptions = loadGlGroupingOptions($conn, $ctxFy, $ctxVer, $currentTransactionType);
if ($currentCalculationId === '' && $currentUomCode !== '' && !empty($uomOptions)) {
    foreach ($uomOptions as $uomRow) {
        if (trim((string)($uomRow['UOMCode'] ?? '')) === $currentUomCode && (int)($uomRow['CalculationID'] ?? 0) > 0) {
            $currentCalculationId = (string)(int)$uomRow['CalculationID'];
            if (empty($record['CalculationID'])) {
                $record['CalculationID'] = $currentCalculationId;
            }
            break;
        }
    }
}
$uomOptions = ensureUomOptionPresent($uomOptions, $currentUomCode, $currentCalculationId);
$segmentConfigRows = loadSegmentConfig($conn, $currentTransactionType, $ctxFy, $ctxVer);
$segmentDisplayMeta = loadSegmentDisplayMeta($conn);
$segmentPositionMeta = loadSegmentPositionMeta($conn);
$segmentLabels = [];
foreach ($segmentDisplayMeta as $segmentNo => $meta) {
    $segmentLabels[(int)$segmentNo] = (string)($meta['label'] ?? '');
}
$scopeDerivedSegments = deriveScopeSegmentValues($ctxDataObjectPath, $segmentLabels);
$allSegmentConfigRows = loadSegmentConfig($conn, $currentTransactionType, $ctxFy, $ctxVer, false);
foreach ($allSegmentConfigRows as $cfg) {
    $segNo = (int)($cfg['SegmentNo'] ?? 0);
    if ($segNo < 1 || $segNo > 20) {
        continue;
    }
    if (((int)($cfg['VisibleFlag'] ?? 0)) === 1) {
        continue;
    }
    if (!isset($scopeDerivedSegments[$segNo])) {
        continue;
    }
    $segKey = 'Segment' . $segNo . 'Code';
    if (trim((string)($record[$segKey] ?? '')) === '') {
        $record[$segKey] = $scopeDerivedSegments[$segNo];
    }
}
$derivedAccountCode = deriveAccountCodeFromSegments($record, $segmentPositionMeta);
if ($derivedAccountCode !== '') {
    $record['AccountCode'] = $derivedAccountCode;
}
$derivedGlAccountCode = deriveGlAccountCode($record, $glAccountSegmentNo);
if ($derivedGlAccountCode !== '') {
    $record['GLAccountCode'] = $derivedGlAccountCode;
}
$currentGlGroupingPrefix = $requestedGlGroupingPrefix !== ''
    ? $requestedGlGroupingPrefix
    : deriveGlGroupingPrefixFromAccountCode((string)($record['GLAccountCode'] ?? ''), $glGroupingOptions);
$matchingTxList = loadMatchingTransactionsForEditor(
    $conn,
    $ctxFy,
    $ctxVer,
    $ctxDataObject,
    $currentTransactionType,
    $currentGlGroupingPrefix
);
$isChildRecord = normalizeRecordTypeCode($record['RecordTypeCode'] ?? 'H') === 'C';
$glAccountIsDerived = !$isChildRecord && $glAccountSegmentNo !== null;
$segmentUiRows = [];
foreach ($segmentConfigRows as $cfg) {
    $segNo = (int)($cfg['SegmentNo'] ?? 0);
    if ($segNo < 1 || $segNo > 20) {
        continue;
    }
    $segKey = 'Segment' . $segNo . 'Code';
    $currentSegmentValue = trim((string)val($record, $segKey));
    $opts = loadSegmentOptions(
        $conn,
        $segNo,
        $ctxFy,
        $ctxVer,
        (string)($cfg['LookupSourceType'] ?? 'tblSegments'),
        isset($cfg['LookupFilter']) ? (string)$cfg['LookupFilter'] : null
    );
    if ($glAccountSegmentNo !== null && $segNo === $glAccountSegmentNo && $currentGlGroupingPrefix !== '') {
        $opts = array_values(array_filter($opts, static function (array $option) use ($currentGlGroupingPrefix): bool {
            $value = trim((string)($option['value'] ?? ''));
            return $value !== '' && stripos($value, $currentGlGroupingPrefix) === 0;
        }));
    }
    $opts = ensureSegmentOptionPresent($opts, $currentSegmentValue);
    $segmentUiRows[] = [
        'segment_no' => $segNo,
        'label' => (string)($segmentDisplayMeta[$segNo]['label'] ?? ('Segment' . $segNo . 'Code')),
        'group' => (string)($segmentDisplayMeta[$segNo]['group'] ?? 'Segments'),
        'display_order' => (int)($cfg['DisplayOrder'] ?? ($segmentDisplayMeta[$segNo]['display_order'] ?? $segNo)),
        'required' => ((int)($cfg['RequiredFlag'] ?? 0)) === 1,
        'options' => $opts,
    ];
}
usort($segmentUiRows, static function (array $a, array $b): int {
    $cmp = ((int)($a['display_order'] ?? 0)) <=> ((int)($b['display_order'] ?? 0));
    if ($cmp !== 0) {
        return $cmp;
    }
    return ((int)($a['segment_no'] ?? 0)) <=> ((int)($b['segment_no'] ?? 0));
});
$segmentUiGroups = [];
foreach ($segmentUiRows as $segUi) {
    $groupName = trim((string)($segUi['group'] ?? 'Segments'));
    if ($groupName === '') {
        $groupName = 'Segments';
    }
    if (!isset($segmentUiGroups[$groupName])) {
        $segmentUiGroups[$groupName] = [];
    }
    $segmentUiGroups[$groupName][] = $segUi;
}
$totalConfiguredSegmentCount = count($allSegmentConfigRows);
$visibleSegmentCount = count($segmentUiRows);
$hiddenSegmentCount = max(0, $totalConfiguredSegmentCount - $visibleSegmentCount);
$requiredSegmentCount = 0;
$filledSegmentCount = 0;
$missingRequiredSegmentCount = 0;
foreach ($segmentUiRows as $segUi) {
    if (!empty($segUi['required'])) {
        $requiredSegmentCount++;
    }
    $segNo = (int)($segUi['segment_no'] ?? 0);
    $segValue = $segNo > 0 ? trim((string)val($record, 'Segment' . $segNo . 'Code')) : '';
    if ($segValue !== '') {
        $filledSegmentCount++;
    }
    if (!empty($segUi['required']) && $segValue === '') {
        $missingRequiredSegmentCount++;
    }
}
$showSegmentsExpanded = $txIdVal <= 0 || $missingRequiredSegmentCount > 0;
$segmentsAccordionButtonClass = $showSegmentsExpanded ? 'accordion-button' : 'accordion-button collapsed';
$segmentsAccordionPanelClass = $showSegmentsExpanded ? 'accordion-collapse collapse show' : 'accordion-collapse collapse';
$segmentSummaryText = $visibleSegmentCount > 0
    ? ($filledSegmentCount . ' / ' . $visibleSegmentCount)
    : 'Auto-derived';
$showMatchingTransactionsExpanded = $txIdVal <= 0 || ($currentTransactionType !== '' && empty($matchingTxList));
$matchingTransactionsButtonClass = $showMatchingTransactionsExpanded ? 'accordion-button' : 'accordion-button collapsed';
$matchingTransactionsPanelClass = $showMatchingTransactionsExpanded ? 'accordion-collapse collapse show' : 'accordion-collapse collapse';
$showCoreSelectionExpanded = $currentTransactionType === '' || $txIdVal <= 0;
$coreSelectionButtonClass = $showCoreSelectionExpanded ? 'accordion-button' : 'accordion-button collapsed';
$coreSelectionPanelClass = $showCoreSelectionExpanded ? 'accordion-collapse collapse show' : 'accordion-collapse collapse';
$currentAccountCode = trim((string)val($record, 'AccountCode'));
$formattedAccountCode = formatAccountCodeFromSegments($record, $segmentPositionMeta);
$segmentContextValueMap = [];
foreach ($segmentPositionMeta as $segmentNo => $_meta) {
    $segmentNo = (int)$segmentNo;
    $value = trim((string)($record['Segment' . $segmentNo . 'Code'] ?? ''));
    if ($segmentNo > 0 && $value !== '') {
        $segmentContextValueMap[$segmentNo] = $value;
    }
}
$segmentValueNames = loadSegmentValueNames($conn, $ctxFy, $ctxDataObject, $segmentContextValueMap);
$segmentContextParts = buildSegmentContextParts($record, $segmentPositionMeta, $segmentLabels, $segmentValueNames);
$currentGlAccountCode = trim((string)val($record, 'GLAccountCode'));
$bpPeriodsEntered = 0;
for ($bpIndex = 1; $bpIndex <= 12; $bpIndex++) {
    $bpHasValue = trim((string)val($record, 'BP' . $bpIndex . 'QtyInpN')) !== ''
        || trim((string)val($record, 'BP' . $bpIndex . 'UOMRate')) !== ''
        || trim((string)val($record, 'BP' . $bpIndex . 'InpN')) !== '';
    if ($bpHasValue) {
        $bpPeriodsEntered++;
    }
}
?><!doctype html>
<?php if (!$embedded): ?>
<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(__t('menu_transaction_input_editor')) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .tx-summary-stat {
            border: 1px solid var(--bs-border-color);
            border-radius: .375rem;
            background: var(--bs-light);
            padding: .75rem;
            height: 100%;
        }
        .tx-summary-stat .label {
            font-size: .75rem;
            color: var(--bs-secondary-color);
            text-transform: uppercase;
        }
        .tx-segment-group {
            border: 1px solid var(--bs-border-color);
            border-radius: .375rem;
            background: var(--bs-light);
        }
        .tx-segment-group-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--bs-border-color);
        }
        .tx-monthly-table th {
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .tx-monthly-table th:first-child,
        .tx-monthly-table td:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
            background: var(--bs-light);
        }
        .tx-sticky-actions {
            position: sticky;
            bottom: 0;
            z-index: 1020;
            background: rgba(248, 249, 250, 0.97);
            border-top: 1px solid var(--bs-border-color);
            margin: 1rem -1rem -1rem;
            padding: .75rem 1rem 1rem;
        }
        .tx-segment-context-table th,
        .tx-segment-context-table td {
            white-space: nowrap;
            vertical-align: middle;
        }
        .tx-segment-context-table .row-label {
            background: var(--bs-light);
            color: var(--bs-secondary-color);
            font-size: .75rem;
            text-transform: uppercase;
            font-weight: 600;
        }
        .tx-segment-context-table .code-cell {
            font-weight: 600;
        }
        .tx-account-context {
            border: 1px solid #cfe2ff;
            border-radius: .5rem;
            background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
        }
        .tx-account-context-main {
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: .01em;
        }
        .tx-account-context-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: .75rem;
        }
        .tx-account-context-chip {
            border: 1px solid var(--bs-border-color);
            border-radius: 999px;
            background: #fff;
            padding: .35rem .65rem;
            font-size: .875rem;
        }
        .tx-account-context-chip .label {
            color: var(--bs-secondary-color);
            text-transform: uppercase;
            font-size: .7rem;
            margin-right: .35rem;
        }
    </style>
</head>
<body>
<?php endif; ?>
<div class="container-fluid py-3">
    <div class="card shadow-sm mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-pencil-square me-2"></i><?= h(__t('menu_transaction_input_editor')) ?></strong>
            <div class="btn-group">
                <a href="index.php?route=transaction-input/list" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-list-ul me-1"></i><?= h(__t('menu_transaction_input_list')) ?>
                </a>
                <a href="index.php?route=transaction-input/stub" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-play-circle me-1"></i>Stub Runner
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php foreach ($messages as $m): ?>
                <div class="alert alert-success py-2 mb-2"><?= h($m) ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $e): ?>
                <div class="alert alert-danger py-2 mb-2"><?= h($e) ?></div>
            <?php endforeach; ?>

            <div class="tx-account-context mb-3">
                <div class="card-body py-3">
                    <div class="small text-uppercase text-muted mb-1">Account In Context</div>
                    <div class="tx-account-context-main" id="CurrentAccountCodeDisplay"><?= h($formattedAccountCode !== '' ? $formattedAccountCode : ($currentAccountCode !== '' ? $currentAccountCode : '-')) ?></div>
                    <div class="small text-muted mt-1" id="CurrentAccountCodeHelp">
                        <?= ($formattedAccountCode !== '' || $currentAccountCode !== '') ? 'This is the full account code in context for the selected transaction or segment combination, formatted using the segment layout metadata.' : 'Select a transaction or finish the dimensions and segments to establish the account code in context.' ?>
                    </div>
                    <div class="tx-account-context-meta">
                        <div class="tx-account-context-chip" id="CurrentAccountCodeRaw">
                            <span class="label">Stored Account Code</span>
                            <span><?= h($currentAccountCode !== '' ? $currentAccountCode : '-') ?></span>
                        </div>
                        <div class="tx-account-context-chip">
                            <span class="label">Full Account Code</span>
                            <span id="CurrentAccountCodeStat"><?= h($formattedAccountCode !== '' ? $formattedAccountCode : ($currentAccountCode !== '' ? $currentAccountCode : '-')) ?></span>
                        </div>
                        <div class="tx-account-context-chip">
                            <span class="label">GL Code</span>
                            <span id="CurrentGlAccountCodeStat"><?= h($currentGlAccountCode !== '' ? $currentGlAccountCode : '-') ?></span>
                        </div>
                    </div>
                    <div class="mt-3" id="CurrentSegmentContextParts">
                        <?php if (empty($segmentContextParts)): ?>
                            <div class="small text-muted">Segment names and values will appear here once the account is in context.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-bordered align-middle tx-segment-context-table mb-0">
                                    <tbody>
                                        <tr>
                                            <th class="row-label">Segment</th>
                                            <?php foreach ($segmentContextParts as $part): ?>
                                                <th><?= h((string)$part['label']) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                        <tr>
                                            <th class="row-label">Code</th>
                                            <?php foreach ($segmentContextParts as $part): ?>
                                                <td class="code-cell"><?= h((string)$part['value']) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <tr>
                                            <th class="row-label">Name</th>
                                            <?php foreach ($segmentContextParts as $part): ?>
                                                <td class="text-muted fw-normal"><?= h((string)($part['value_name'] !== '' ? $part['value_name'] : '-')) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="tx-summary-stat">
                        <div class="label">Context</div>
                        <div class="fw-semibold"><?= h($ctxDataObject !== '' ? $ctxDataObject : '-') ?></div>
                        <div class="small text-muted">FY <?= h($ctxFy) ?> / Version <?= h($ctxVer) ?></div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="tx-summary-stat">
                        <div class="label">Budget Class</div>
                        <div class="fw-semibold"><?= h($currentTransactionType !== '' ? $currentTransactionType : '-') ?></div>
                        <div class="small text-muted">Entry driver</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="tx-summary-stat">
                        <div class="label">Segments</div>
                        <div class="fw-semibold"><?= h($segmentSummaryText) ?></div>
                        <div class="small text-muted">
                            <?= h($requiredSegmentCount) ?> required
                            <?php if ($hiddenSegmentCount > 0): ?>
                                | <?= h($hiddenSegmentCount) ?> hidden/derived
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="tx-summary-stat">
                        <div class="label">UOM / Calc</div>
                        <div class="fw-semibold"><?= h(($currentUomCode !== '' ? $currentUomCode : '-') . ' / ' . ($currentCalculationId !== '' ? $currentCalculationId : '-')) ?></div>
                        <div class="small text-muted">Derived calc</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="tx-summary-stat">
                        <div class="label">Periods Started</div>
                        <div class="fw-semibold"><?= h($bpPeriodsEntered) ?> / 12</div>
                        <div class="small text-muted">BP rows touched</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="tx-summary-stat">
                        <div class="label">GL Code</div>
                        <div class="fw-semibold"><?= h($currentGlAccountCode !== '' ? $currentGlAccountCode : '-') ?></div>
                        <div class="small text-muted"><?= $glAccountIsDerived ? 'Derived' : 'Manual/derived' ?></div>
                    </div>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="TransactionID" value="<?= h($txIdVal) ?>">

                    <input type="hidden" name="HeadRecordID" value="<?= h((string)val($record, 'HeadRecordID')) ?>">
                    <input type="hidden" name="RecordTypeCode" value="<?= h((string)val($record, 'RecordTypeCode', 'H')) ?>">
                    <input type="hidden" name="FiscalYearID" value="<?= h((string)val($record, 'FiscalYearID', $ctxFy > 0 ? $ctxFy : 2026)) ?>">
                    <input type="hidden" name="VersionID" value="<?= h((string)val($record, 'VersionID', $ctxVer > 0 ? $ctxVer : 5)) ?>">
                    <input type="hidden" name="DataObjectCode" value="<?= h((string)val($record, 'DataObjectCode', $ctxDataObject)) ?>">

                    <div class="accordion mb-3" id="txCoreSelectionAccordion">
                        <div class="accordion-item shadow-sm">
                            <h2 class="accordion-header" id="txCoreSelectionHeading">
                                <button class="<?= h($coreSelectionButtonClass) ?>" type="button" data-bs-toggle="collapse" data-bs-target="#txCoreSelectionPanel" aria-expanded="<?= $showCoreSelectionExpanded ? 'true' : 'false' ?>" aria-controls="txCoreSelectionPanel">
                                    <span class="me-2"><i class="bi bi-1-circle me-1"></i>Core Selection</span>
                                    <span class="small text-muted ms-2">
                                        <?= h($currentTransactionType !== '' ? $currentTransactionType : 'Choose budget class') ?>
                                        <?php if ($txIdVal > 0): ?>
                                            | Tx <?= h((string)$txIdVal) ?>
                                        <?php endif; ?>
                                    </span>
                                </button>
                            </h2>
                            <div id="txCoreSelectionPanel" class="<?= h($coreSelectionPanelClass) ?>" aria-labelledby="txCoreSelectionHeading" data-bs-parent="#txCoreSelectionAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-lg-6">
                                            <label class="form-label">Budget Class</label>
                                            <select class="form-select" name="TransactionTypeCode" id="TransactionTypeCode" required>
                                                <option value=""><?= h(__t('txe_select_transaction_type')) ?></option>
                                                <?php foreach ($transactionTypes as $ttRow): ?>
                                                    <?php
                                                        $ttCode = trim((string)($ttRow['TransactionTypeCode'] ?? ''));
                                                        $ttName = trim((string)($ttRow['TransactionTypeName'] ?? ''));
                                                        $ttLabel = $ttCode . ($ttName !== '' ? ' - ' . $ttName : '');
                                                    ?>
                                                    <option value="<?= h($ttCode) ?>" <?= $currentTransactionType === $ttCode ? 'selected' : '' ?>>
                                                        <?= h($ttLabel) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Select the budget class first to load the required segments and UOM choices.</div>
                                        </div>
                                        <div class="col-lg-6">
                                            <label class="form-label">GL Grouping</label>
                                            <select class="form-select" name="GLGroupingPrefix" id="GLGroupingPrefix">
                                                <option value="">All available GL groupings</option>
                                                <?php foreach ($glGroupingOptions as $groupOption): ?>
                                                    <?php
                                                        $groupValue = trim((string)($groupOption['value'] ?? ''));
                                                        $groupLabel = trim((string)($groupOption['label'] ?? $groupValue));
                                                    ?>
                                                    <option value="<?= h($groupValue) ?>" <?= $currentGlGroupingPrefix === $groupValue ? 'selected' : '' ?>>
                                                        <?= h($groupLabel) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Use GL Grouping to narrow the GL-related segment choices shown below.</div>
                                        </div>
                                        <div class="col-12">
                                            <div class="small text-muted">
                                                Everything else in this section is derived.
                                                GL Grouping only filters the UI. Account codes update from the selections made in Dimensions and Segments.
                                            </div>
                                        </div>
                                    </div>

                                    <input type="hidden" name="AccountCode" id="AccountCode" value="<?= h(val($record, 'AccountCode')) ?>">
                                    <input type="hidden" name="CalculationID" id="CalculationID" value="<?= h($currentCalculationId) ?>">
                                    <input type="hidden" name="GLAccountCode" id="GLAccountCode" value="<?= h(val($record, 'GLAccountCode')) ?>">

                                    <div class="row g-2 mt-2">
                                        <div class="col-md-3">
                                            <div class="tx-summary-stat">
                                                <div class="label">Budget Class</div>
                                                <div class="fw-semibold"><?= h($currentTransactionType !== '' ? $currentTransactionType : '-') ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="tx-summary-stat">
                                                <div class="label">Full Account Code</div>
                                                <div class="fw-semibold"><?= h((string)val($record, 'AccountCode', '-')) ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="tx-summary-stat">
                                                <div class="label">GL Code</div>
                                                <div class="fw-semibold"><?= h($currentGlAccountCode !== '' ? $currentGlAccountCode : '-') ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="tx-summary-stat">
                                                <div class="label">GL Grouping</div>
                                                <div class="fw-semibold"><?= h($currentGlGroupingPrefix !== '' ? $currentGlGroupingPrefix : '-') ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="accordion mt-3" id="txMatchingTransactionsAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="txMatchingTransactionsHeading">
                                        <button class="<?= h($matchingTransactionsButtonClass) ?>" type="button" data-bs-toggle="collapse" data-bs-target="#txMatchingTransactionsPanel" aria-expanded="<?= $showMatchingTransactionsExpanded ? 'true' : 'false' ?>" aria-controls="txMatchingTransactionsPanel">
                                            <span class="me-2">Select Matching Transaction</span>
                                            <span class="small text-muted ms-2"><?= h(count($matchingTxList)) ?> match<?= count($matchingTxList) === 1 ? '' : 'es' ?></span>
                                        </button>
                                    </h2>
                                    <div id="txMatchingTransactionsPanel" class="<?= h($matchingTransactionsPanelClass) ?>" aria-labelledby="txMatchingTransactionsHeading" data-bs-parent="#txMatchingTransactionsAccordion">
                                        <div class="accordion-body p-0">
                                            <div class="p-3 border-bottom bg-light">
                                                <div class="small text-muted">
                                                    Choose the transaction the user wants to work on. The list is filtered to this context and the selected Budget Class<?= $currentGlGroupingPrefix !== '' ? ' and GL Grouping' : '' ?>.
                                                </div>
                                            </div>
                                            <?php if ($currentTransactionType === ''): ?>
                                                <div class="p-3 text-muted">Select a Budget Class first, then choose a matching transaction from this list.</div>
                                            <?php elseif (empty($matchingTxList)): ?>
                                                <div class="p-3 text-muted">No selectable transactions match the current Budget Class<?= $currentGlGroupingPrefix !== '' ? ' and GL Grouping' : '' ?> in this context.</div>
                                            <?php else: ?>
                                                <div class="px-3 py-2 border-bottom small text-muted bg-body-tertiary">
                                                    <?= $txIdVal > 0 ? 'The currently selected transaction is highlighted below.' : 'No transaction is selected yet. Use the button on the right to select one.' ?>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped table-hover mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th><?= h(__t('tx_transaction_id')) ?></th>
                                                                <th>GL Code</th>
                                                                <th>Full Account Code</th>
                                                                <th><?= h(__t('txe_calc_id')) ?></th>
                                                                <th>UOM</th>
                                                                <th class="text-end">Annual</th>
                                                                <th class="text-end">Selection</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($matchingTxList as $matchRow): ?>
                                                                <?php $isCurrentMatch = (int)($matchRow['TransactionID'] ?? 0) === $txIdVal; ?>
                                                                <tr<?= $isCurrentMatch ? ' class="table-primary"' : '' ?>>
                                                                    <td>
                                                                        <?= h((string)($matchRow['TransactionID'] ?? '')) ?>
                                                                        <?php if ($isCurrentMatch): ?>
                                                                            <span class="badge text-bg-primary ms-1">Selected</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td><?= h((string)($matchRow['GLAccountCode'] ?? '')) ?></td>
                                                                    <td><?= h((string)($matchRow['AccountCode'] ?? '')) ?></td>
                                                                    <td><?= h((string)($matchRow['CalculationID'] ?? '')) ?></td>
                                                                    <td><?= h((string)($matchRow['UOMCodeInpC'] ?? '')) ?></td>
                                                                    <td class="text-end"><?= h(number_format((float)($matchRow['BPTotal'] ?? 0), 0)) ?></td>
                                                                    <td class="text-end">
                                                                        <?php if ($isCurrentMatch): ?>
                                                                            <span class="btn btn-sm btn-primary disabled" aria-disabled="true">Selected Transaction</span>
                                                                        <?php else: ?>
                                                                            <a class="btn btn-sm btn-success" href="<?= h($editorRouteUrl) ?>&tx=<?= urlencode((string)($matchRow['TransactionID'] ?? '')) ?>">
                                                                                Select This Transaction
                                                                            </a>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="accordion mb-3" id="txSegmentsAccordion">
                        <div class="accordion-item shadow-sm">
                            <h2 class="accordion-header" id="txSegmentsHeading">
                                <button class="<?= h($segmentsAccordionButtonClass) ?>" type="button" data-bs-toggle="collapse" data-bs-target="#txSegmentsPanel" aria-expanded="<?= $showSegmentsExpanded ? 'true' : 'false' ?>" aria-controls="txSegmentsPanel">
                                    <span class="me-2"><i class="bi bi-2-circle me-1"></i>Dimensions and Segments</span>
                                    <span class="small text-muted ms-2">
                                        <?= $visibleSegmentCount > 0 ? 'Filled: ' . h($segmentSummaryText) : h('No editable fields') ?>
                                    </span>
                                    <?php if ($missingRequiredSegmentCount > 0): ?>
                                        <span class="badge text-bg-warning ms-2"><?= h($missingRequiredSegmentCount) ?> required missing</span>
                                    <?php endif; ?>
                                </button>
                            </h2>
                            <div id="txSegmentsPanel" class="<?= h($segmentsAccordionPanelClass) ?>" aria-labelledby="txSegmentsHeading" data-bs-parent="#txSegmentsAccordion">
                                <div class="accordion-body">
                                    <div class="small text-muted mb-3">
                                        Open this section only when you need to change the saved dimensions or segments.
                                        <?php if ($hiddenSegmentCount > 0): ?>
                                            <?= ' ' . h($hiddenSegmentCount) ?> segment<?= $hiddenSegmentCount === 1 ? '' : 's' ?> are hidden and derived automatically.
                                        <?php endif; ?>
                                    </div>
                                    <?php if (empty($segmentUiGroups)): ?>
                                        <div class="alert alert-light border mb-0">
                                            No editable dimensions or segments are configured for this transaction type. Saved values may still exist and hidden dimensions can still be applied automatically.
                                        </div>
                                    <?php else: ?>
                                        <div class="row g-3">
                                            <?php foreach ($segmentUiGroups as $groupName => $groupRows): ?>
                                                <div class="col-12">
                                                    <div class="tx-segment-group">
                                                        <div class="tx-segment-group-header d-flex justify-content-between align-items-center gap-2">
                                                            <div class="fw-semibold"><?= h((string)$groupName) ?></div>
                                                            <div class="text-muted small"><?= count($groupRows) ?> field<?= count($groupRows) === 1 ? '' : 's' ?></div>
                                                        </div>
                                                        <div class="p-3">
                                                            <div class="row g-3">
                                                                <?php foreach ($groupRows as $segUi): ?>
                                                                    <?php
                                                                        $segNo = (int)$segUi['segment_no'];
                                                                        $segKey = 'Segment' . $segNo . 'Code';
                                                                        $segLabel = trim((string)($segUi['label'] ?? $segKey));
                                                                        $segVal = (string)val($record, $segKey);
                                                                    ?>
                                                                    <div class="col-xl-4 col-md-6">
                                                                        <label class="form-label"><?= h($segLabel) ?><?= !empty($segUi['required']) ? ' *' : '' ?></label>
                                                                        <select class="form-select" name="<?= h($segKey) ?>">
                                                                            <option value="">-- select --</option>
                                                                            <?php foreach (($segUi['options'] ?? []) as $option): ?>
                                                                                <?php
                                                                                    $optionValue = trim((string)($option['value'] ?? ''));
                                                                                    $optionLabel = trim((string)($option['label'] ?? $optionValue));
                                                                                ?>
                                                                                <option value="<?= h($optionValue) ?>" <?= $segVal === $optionValue ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong><i class="bi bi-3-circle me-1"></i><?= h(__t('txe_bp_period_quantities_rates')) ?></strong>
                            <span class="small text-muted"><?= h($bpPeriodsEntered) ?> of 12 periods started</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-lg-6">
                                    <label class="form-label">UOMCodeInpC</label>
                                    <select class="form-select" name="UOMCodeInpC" id="UOMCodeInpC">
                                        <option value=""><?= h(__t('txe_select_uom')) ?></option>
                                        <?php foreach ($uomOptions as $uomRow): ?>
                                            <?php
                                                $uomCode = trim((string)($uomRow['UOMCode'] ?? ''));
                                                $uomCalcId = (int)($uomRow['CalculationID'] ?? 0);
                                                $uomLabel = $uomCode . ($uomCalcId > 0 ? ' (Calc ' . $uomCalcId . ')' : '');
                                                if ($uomCode === '') {
                                                    continue;
                                                }
                                            ?>
                                            <option value="<?= h($uomCode) ?>" data-calc-id="<?= h((string)$uomCalcId) ?>" <?= $currentUomCode === $uomCode ? 'selected' : '' ?>>
                                                <?= h($uomLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text"><?= h(__t('txe_uom_help')) ?></div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label"><?= h(__t('txe_calc_id')) ?></label>
                                    <input class="form-control" type="text" value="<?= h($currentCalculationId !== '' ? $currentCalculationId : '-') ?>" readonly>
                                    <div class="form-text">Derived from selected UOM.</div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label"><?= h(__t('txe_bp_qty_optional')) ?></label>
                                    <input class="form-control" type="number" step="0.000001" name="BPQtyInpN" value="<?= h(val($record, 'BPQtyInpN')) ?>">
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0 tx-monthly-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Field</th>
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <th class="text-center">BP<?= $i ?></th>
                                            <?php endfor; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <th class="table-light"><?= h(__t('txe_qty')) ?> (BPxQtyInpN)</th>
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <td>
                                                    <input class="form-control form-control-sm text-end" type="number" step="0.000001" name="BP<?= $i ?>QtyInpN" value="<?= h(val($record, 'BP' . $i . 'QtyInpN')) ?>">
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                        <tr>
                                            <th class="table-light"><?= h(__t('menu_rates')) ?> (BPxUOMRate)</th>
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <td>
                                                    <input class="form-control form-control-sm text-end" type="number" step="0.000001" name="BP<?= $i ?>UOMRate" value="<?= h(val($record, 'BP' . $i . 'UOMRate')) ?>">
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                        <tr>
                                            <th class="table-light">Amount (BPxInpN)</th>
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <td>
                                                    <input class="form-control form-control-sm text-end" type="number" step="0.000001" name="BP<?= $i ?>InpN" value="<?= h(val($record, 'BP' . $i . 'InpN')) ?>">
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="accordion mb-3" id="txAdvancedAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="txAdvancedHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#txAdvancedPanel" aria-expanded="false" aria-controls="txAdvancedPanel">
                                    Advanced Options
                                </button>
                            </h2>
                            <div id="txAdvancedPanel" class="accordion-collapse collapse" aria-labelledby="txAdvancedHeading" data-bs-parent="#txAdvancedAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="CeilingChecks" value="1" id="CeilingChecks" <?= (!isset($_POST['CeilingChecks']) || !empty($_POST['CeilingChecks'])) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="CeilingChecks"><?= h(__t('txe_ceiling_checks_on')) ?></label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label"><?= h(__t('txe_ceiling_engine')) ?></label>
                                            <input type="hidden" name="CeilingEngine" value="sproc">
                                            <input class="form-control" type="text" value="sproc" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label"><?= h(__t('txe_bp_qty_optional')) ?></label>
                                            <input class="form-control" type="number" step="0.000001" name="BPQtyInpN" value="<?= h(val($record, 'BPQtyInpN')) ?>">
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="UseLiveRates" value="1" id="UseLiveRates" <?= (int)val($record, 'UseLiveRates', 0) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="UseLiveRates"><?= h(__t('txe_use_live_rates')) ?></label>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label"><?= h(__t('txe_extra_fields_optional')) ?></label>
                                            <textarea class="form-control" name="ExtraFields" rows="3" placeholder='{"CurrencyInpC":"AUD","Segment1Code":"ABC"}'></textarea>
                                            <div class="form-text"><?= h(__t('txe_extra_fields_help')) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tx-sticky-actions">
                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <div class="text-muted small">Primary workflow: refresh layout if needed, then save or compute from here.</div>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-outline-secondary" type="submit" name="action" value="refresh_config"><?= h(__t('txe_refresh_layout')) ?></button>
                                <button class="btn btn-primary" type="submit" name="action" value="save"><?= h(__t('save')) ?></button>
                                <button class="btn btn-success" type="submit" name="action" value="save_compute"><?= h(__t('txe_save_compute')) ?></button>
                                <button class="btn btn-outline-primary" type="submit" name="action" value="compute"><?= h(__t('txe_compute_only')) ?></button>
                                <button class="btn btn-outline-danger" type="submit" name="action" value="delete" onclick="return confirm('<?= h(__t('txe_confirm_delete_transaction')) ?>')"><?= h(__t('delete')) ?></button>
                            </div>
                        </div>
                    </div>
                </form>
                <div class="card shadow-sm mb-3">
                    <div class="card-header">
                        <strong><i class="bi bi-info-circle me-1"></i>Screen Context</strong>
                    </div>
                    <div class="card-body">
                        <div class="small text-muted">
                            <?= h(__t('tx_context')) ?>: <?= h(__t('fiscal_year')) ?> <?= h($ctxFy) ?> | <?= h(__t('version')) ?> <?= h($ctxVer) ?> | DataObject <?= h($ctxDataObject) ?>
                        </div>
                        <?php if (!empty($ctxDataObjectPath)): ?>
                            <?php
                                $ctxPathParts = [];
                                foreach ($ctxDataObjectPath as $node) {
                                    $typeName = trim((string)($node['DataObjectTypeName'] ?? 'Level'));
                                    $code = (string)($node['DataObjectCode'] ?? '');
                                    $name = trim((string)($node['DataObjectName'] ?? ''));
                                    if ($code === '') {
                                        continue;
                                    }
                                    $ctxPathParts[] = h($typeName) . ': ' . h($code . ($name !== '' ? ' (' . $name . ')' : ''));
                                }
                            ?>
                            <?php if (!empty($ctxPathParts)): ?>
                                <div class="small text-muted mt-2">Path: <?= implode(' &gt; ', $ctxPathParts) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="accordion" id="txSideAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="txCeilingHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#txCeilingPanel" aria-expanded="false" aria-controls="txCeilingPanel">
                                Ceiling and Existing Transactions
                            </button>
                        </h2>
                        <div id="txCeilingPanel" class="accordion-collapse collapse" aria-labelledby="txCeilingHeading" data-bs-parent="#txSideAccordion">
                            <div class="accordion-body">
                                <div class="border rounded p-3 mb-3">
                                    <h6 class="mb-3">Available Ceiling Balance</h6>
                                    <?php if ($txIdVal <= 0): ?>
                                        <div class="text-muted">Select a TransactionID from the context list to view the available ceiling balance.</div>
                                    <?php elseif ($recordCeiling && $recordCeiling['balance_total'] !== null): ?>
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <div class="small text-muted">Available Left</div>
                                                <div class="fs-5 fw-semibold"><?= h(number_format((float)$recordCeiling['balance_total'], 2)) ?></div>
                                            </div>
                                            <div class="col-6">
                                                <div class="small text-muted">Ceiling Total</div>
                                                <div><?= h(number_format((float)($recordCeiling['ceiling_total'] ?? 0), 2)) ?></div>
                                            </div>
                                            <div class="col-6">
                                                <div class="small text-muted">Definition ID</div>
                                                <div><?= h((string)($recordCeiling['ceiling_definition_id'] ?? '-')) ?></div>
                                            </div>
                                            <div class="col-6">
                                                <div class="small text-muted">Last TransactionID</div>
                                                <div><?= h((string)($recordCeiling['last_transaction_id'] ?? '-')) ?></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted">No ceiling balance is linked to this TransactionID yet.</div>
                                    <?php endif; ?>
                                </div>

                                <div class="border rounded">
                                    <div class="p-3 border-bottom bg-light">
                                        <strong><?= h(__t('txe_transactions_in_context')) ?> (<?= count($txList) ?>)</strong>
                                    </div>
                                    <div class="p-0">
                                        <?php if ($ctxFy <= 0 || $ctxVer <= 0 || $ctxDataObject === ''): ?>
                                            <div class="p-3 text-muted"><?= h(__t('tx_set_context_warning')) ?></div>
                                        <?php elseif (empty($txList)): ?>
                                            <div class="p-3 text-muted"><?= h(__t('txe_no_transactions_in_context')) ?></div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-striped mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th><?= h(__t('tx_transaction_id')) ?></th>
                                                            <th><?= h(__t('tx_transaction_type')) ?></th>
                                                            <th><?= h(__t('txe_calc_id')) ?></th>
                                                            <th><?= h(__t('action')) ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($txList as $row): ?>
                                                            <tr>
                                                                <td><?= h($row['TransactionID']) ?></td>
                                                                <td><?= h($row['TransactionTypeCode']) ?></td>
                                                                <td><?= h($row['CalculationID']) ?></td>
                                                                <td><a class="btn btn-sm btn-outline-secondary" href="<?= h($editorRouteUrl) ?>&tx=<?= urlencode((string)$row['TransactionID']) ?>"><?= h(__t('view')) ?></a></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($calcOutput !== ''): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="txCalcOutputHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#txCalcOutputPanel" aria-expanded="false" aria-controls="txCalcOutputPanel">
                                    <?= h(__t('txe_calculation_output')) ?>
                                </button>
                            </h2>
                            <div id="txCalcOutputPanel" class="accordion-collapse collapse" aria-labelledby="txCalcOutputHeading" data-bs-parent="#txSideAccordion">
                                <div class="accordion-body">
                                    <pre class="bg-dark text-light p-3 rounded mb-0" style="max-height:360px;overflow:auto;"><?= h($calcOutput) ?></pre>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($record)): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="txSnapshotHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#txSnapshotPanel" aria-expanded="false" aria-controls="txSnapshotPanel">
                                    <?= h(__t('txe_current_record_snapshot')) ?>
                                </button>
                            </h2>
                            <div id="txSnapshotPanel" class="accordion-collapse collapse" aria-labelledby="txSnapshotHeading" data-bs-parent="#txSideAccordion">
                                <div class="accordion-body">
                                    <pre class="bg-light p-3 rounded mb-0" style="max-height:260px;overflow:auto;"><?= h(json_encode($record, JSON_PRETTY_PRINT)) ?></pre>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
        </div>
    </div>
</div>
<?php if (!$embedded): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
<script>
  (function () {
    const txType = document.getElementById('TransactionTypeCode');
    const glGrouping = document.getElementById('GLGroupingPrefix');
    const uom = document.getElementById('UOMCodeInpC');
    const calc = document.getElementById('CalculationID');
    const accountCode = document.getElementById('AccountCode');
    const glAccountCode = document.getElementById('GLAccountCode');
    const currentAccountCodeDisplay = document.getElementById('CurrentAccountCodeDisplay');
    const currentAccountCodeStat = document.getElementById('CurrentAccountCodeStat');
    const currentAccountCodeHelp = document.getElementById('CurrentAccountCodeHelp');
    const currentAccountCodeRaw = document.getElementById('CurrentAccountCodeRaw');
    const currentSegmentContextParts = document.getElementById('CurrentSegmentContextParts');
    const currentGlAccountCodeStat = document.getElementById('CurrentGlAccountCodeStat');
    const recordType = document.querySelector('[name="RecordTypeCode"]');
    const glAccountSegmentNo = <?= json_encode($glAccountSegmentNo) ?>;
    const form = document.querySelector('form[method="post"]');
    const editorBaseUrl = '<?= h($editorRouteUrl) ?>';
    const txId = '<?= h((string)$txIdVal) ?>';
    const scopeDerivedSegments = <?= json_encode($scopeDerivedSegments, JSON_UNESCAPED_UNICODE) ?>;
    const segmentPositionMeta = <?= json_encode($segmentPositionMeta, JSON_UNESCAPED_UNICODE) ?>;
    const segmentLabels = <?= json_encode($segmentLabels, JSON_UNESCAPED_UNICODE) ?>;
    const escapeHtml = (value) => String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
    const appendWindowContextParams = (params) => {
      const context = window.CBMSWindowContext || {};
      if (!context.enabled) {
        return;
      }
      params.set('link_context', '1');
      if (Number(context.fy || 0) > 0) {
        params.set('fy', String(context.fy));
      }
      if (Number(context.ver || 0) > 0) {
        params.set('ver', String(context.ver));
      }
      params.set('scope_dataobject_code', String(context.scopeCode || ''));
      if (String(context.scopeCode || '').trim() !== '' && String(context.scopeName || '').trim() !== '') {
        params.set('scope_dataobject_name', String(context.scopeName));
      } else {
        params.delete('scope_dataobject_name');
      }
    };

    if (txType) {
      txType.addEventListener('change', function () {
        const params = new URLSearchParams();
        params.set('route', 'transaction-input/editor');
        if (txId !== '' && txId !== '0') {
          params.set('tx', txId);
        }
        if (txType.value !== '') {
          params.set('tt', txType.value);
        }
        appendWindowContextParams(params);
        window.location.href = 'index.php?' + params.toString();
      });
    }

    if (glGrouping) {
      glGrouping.addEventListener('change', function () {
        const params = new URLSearchParams();
        params.set('route', 'transaction-input/editor');
        if (txId !== '' && txId !== '0') {
          params.set('tx', txId);
        }
        if (txType && txType.value !== '') {
          params.set('tt', txType.value);
        }
        if (glGrouping.value !== '') {
          params.set('glg', glGrouping.value);
        }
        appendWindowContextParams(params);
        window.location.href = 'index.php?' + params.toString();
      });
    }

    function formatAccountCodeFromSegments() {
      const entries = Object.entries(segmentPositionMeta || {})
        .map(([segmentNo, meta]) => ({
          segmentNo: Number(segmentNo),
          start: Number(meta && meta.start ? meta.start : 0),
          delimiter: String(meta && meta.delimiter ? meta.delimiter : '')
        }))
        .sort((a, b) => a.start - b.start || a.segmentNo - b.segmentNo);

      const parts = [];
      let delimiter = '';
      entries.forEach((entry) => {
        const field = document.querySelector(`[name="Segment${entry.segmentNo}Code"]`);
        const visibleValue = field ? String(field.value || '').trim() : '';
        const scopeValue = scopeDerivedSegments[String(entry.segmentNo)] || scopeDerivedSegments[entry.segmentNo] || '';
        const value = visibleValue !== '' ? visibleValue : String(scopeValue).trim();
        if (value === '') return;
        parts.push(value);
        if (delimiter === '' && entry.delimiter !== '') {
          delimiter = entry.delimiter;
        }
      });

      if (parts.length === 0) return '';
      return delimiter !== '' ? parts.join(delimiter) : parts.join('');
    }

    function renderSegmentContextParts() {
      if (!currentSegmentContextParts) return;
      const entries = Object.entries(segmentPositionMeta || {})
        .map(([segmentNo, meta]) => ({
          segmentNo: Number(segmentNo),
          start: Number(meta && meta.start ? meta.start : 0)
        }))
        .sort((a, b) => a.start - b.start || a.segmentNo - b.segmentNo);

      const parts = [];
      entries.forEach((entry) => {
        const field = document.querySelector(`[name="Segment${entry.segmentNo}Code"]`);
        const visibleValue = field ? String(field.value || '').trim() : '';
        const scopeValue = scopeDerivedSegments[String(entry.segmentNo)] || scopeDerivedSegments[entry.segmentNo] || '';
        const value = visibleValue !== '' ? visibleValue : String(scopeValue).trim();
        if (value === '') return;
        const label = String(segmentLabels[String(entry.segmentNo)] || segmentLabels[entry.segmentNo] || `Segment ${entry.segmentNo}`);
        let valueName = '';
        if (field && field.tagName === 'SELECT' && field.selectedIndex >= 0) {
          const selectedOption = field.options[field.selectedIndex];
          const selectedText = selectedOption ? String(selectedOption.text || '').trim() : '';
          if (selectedText !== '' && selectedText !== '-- select --' && selectedText !== value) {
            const prefix = `${value} - `;
            valueName = selectedText.startsWith(prefix) ? selectedText.slice(prefix.length).trim() : selectedText;
          }
        }
        parts.push(
          { label: escapeHtml(label), value: escapeHtml(value), valueName: escapeHtml(valueName !== '' ? valueName : '-') }
        );
      });

      currentSegmentContextParts.innerHTML = parts.length > 0
        ? `
          <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered align-middle tx-segment-context-table mb-0">
              <tbody>
                <tr><th class="row-label">Segment</th>${parts.map((part) => `<th>${part.label}</th>`).join('')}</tr>
                <tr><th class="row-label">Code</th>${parts.map((part) => `<td class="code-cell">${part.value}</td>`).join('')}</tr>
                <tr><th class="row-label">Name</th>${parts.map((part) => `<td class="text-muted fw-normal">${part.valueName}</td>`).join('')}</tr>
              </tbody>
            </table>
          </div>`
        : '<div class="small text-muted">Segment names and values will appear here once the account is in context.</div>';
    }

    function deriveAccountCode() {
      if (!accountCode) return;
      const formattedValue = formatAccountCodeFromSegments();
      accountCode.value = formattedValue;
      const displayValue = formattedValue !== '' ? formattedValue : (accountCode.value !== '' ? accountCode.value : '-');
      if (currentAccountCodeDisplay) currentAccountCodeDisplay.textContent = displayValue;
      if (currentAccountCodeStat) currentAccountCodeStat.textContent = displayValue;
      if (currentAccountCodeRaw) {
        const rawValue = accountCode.value !== '' ? accountCode.value : '-';
        currentAccountCodeRaw.innerHTML = `<span class="label">Stored Account Code</span><span>${escapeHtml(rawValue)}</span>`;
      }
      if (currentAccountCodeHelp) {
        currentAccountCodeHelp.textContent = accountCode.value !== '' || formattedValue !== ''
          ? 'This is the full account code in context for the selected transaction or segment combination, formatted using the segment layout metadata.'
          : 'Select a transaction or finish the dimensions and segments to establish the account code in context.';
      }
      renderSegmentContextParts();
    }

    function deriveGlAccountCode() {
      if (!glAccountCode) return;
      if (!glAccountSegmentNo) return;
      const glSegment = document.querySelector(`[name="Segment${glAccountSegmentNo}Code"]`);
      glAccountCode.value = glSegment ? String(glSegment.value || '').trim() : '';
      if (currentGlAccountCodeStat) {
        currentGlAccountCodeStat.textContent = glAccountCode.value !== '' ? glAccountCode.value : '-';
      }
    }

    if (uom && calc && uom.tagName === 'SELECT') {
      uom.addEventListener('change', function () {
        const selected = uom.options[uom.selectedIndex];
        if (!selected) return;
        const calcId = selected.getAttribute('data-calc-id') || '';
        calc.value = calcId;
      });
    }

    document.querySelectorAll('[name^="Segment"][name$="Code"]').forEach(function (field) {
      field.addEventListener('change', deriveAccountCode);
      if (glAccountSegmentNo && field.name === `Segment${glAccountSegmentNo}Code`) {
        field.addEventListener('change', deriveGlAccountCode);
      }
    });

    deriveAccountCode();
    deriveGlAccountCode();
    renderSegmentContextParts();
  })();
</script>
<?php if (!$embedded): ?>
</body>
</html>
<?php endif; ?>
