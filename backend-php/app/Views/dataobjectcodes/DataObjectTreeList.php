<?php declare(strict_types=1);
/** @var string $title */
/** @var int $fiscalYearId */
/** @var array $rows */
/** @var array $treeNodes */
/** @var int $total */
/** @var array $filters */
/** @var string $_csrf */

use App\Shared\SessionHelper;

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$rows = is_array($rows ?? null) ? $rows : [];
$treeNodes = is_array($treeNodes ?? null) ? $treeNodes : [];
$filters = is_array($filters ?? null) ? $filters : [];
$fiscalYearId = (int)($fiscalYearId ?? 0);
$total = (int)($total ?? 0);
$page = max(1, (int)($filters['page'] ?? 1));
$pageSize = max(1, (int)($filters['pageSize'] ?? 50));
$pages = max(1, (int)ceil(($total ?: 0) / $pageSize));

$baseParams = [
    'route' => 'dataobjectcodes/hierarchy',
    'q' => (string)($filters['q'] ?? ''),
    'ancestor_code' => (string)($filters['ancestor_code'] ?? ''),
    'descendant_code' => (string)($filters['descendant_code'] ?? ''),
    'depth' => (string)($filters['depth'] ?? ''),
    'pageSize' => $pageSize,
];
$qs = static fn(array $extra = []): string => 'index.php?' . http_build_query(array_replace($baseParams, $extra));
$perms = SessionHelper::get('auth.perms', []);
$canEdit = is_array($perms)
    && (in_array('ADMIN_ALL', $perms, true)
        || in_array('DATAOBJECTCODES_ADMIN', $perms, true)
        || in_array('DATAOBJECTCODES_EDIT', $perms, true));

$childrenByParent = [];
$nodeByCode = [];
foreach ($treeNodes as $node) {
    $code = trim((string)($node['DataObjectCode'] ?? ''));
    if ($code === '') {
        continue;
    }
    $parent = trim((string)($node['DataObjectCodeParent'] ?? ''));
    $nodeByCode[$code] = $node;
    $childrenByParent[$parent][] = $code;
}
foreach ($treeNodes as $node) {
    $code = trim((string)($node['DataObjectCode'] ?? ''));
    $parent = trim((string)($node['DataObjectCodeParent'] ?? ''));
    if ($code !== '' && $parent !== '' && !isset($nodeByCode[$parent])) {
        $childrenByParent[''][] = $code;
    }
}

if (!function_exists('renderDataObjectHierarchyTree')) {
    function renderDataObjectHierarchyTree(
        string $parentCode,
        array $childrenByParent,
        array $nodeByCode,
        bool $searchActive,
        array $visited = [],
        int $depth = 0
    ): void {
        $children = $childrenByParent[$parentCode] ?? [];
        if ($children === []) {
            return;
        }

        echo '<div class="' . ($depth === 0 ? 'dataobject-tree' : 'dataobject-tree-children') . '">';
        foreach ($children as $code) {
            if (isset($visited[$code])) {
                continue;
            }

            $node = $nodeByCode[$code] ?? [];
            $name = trim((string)($node['DataObjectName'] ?? ''));
            $typeName = trim((string)($node['DataObjectTypeName'] ?? ($node['DataObjectTypeID'] ?? '')));
            $hasChildren = !empty($childrenByParent[$code]);
            $isMatch = (int)($node['IsSearchMatch'] ?? 0) === 1;
            $hasMatchDescendant = (int)($node['HasSearchMatchDescendant'] ?? 0) === 1;
            $open = $searchActive && ($isMatch || $hasMatchDescendant);
            $visited[$code] = true;

            echo '<div class="dataobject-tree-node">';
            if ($hasChildren) {
                echo '<details' . ($open ? ' open' : '') . '>';
                echo '<summary>';
            } else {
                echo '<div class="dataobject-tree-leaf">';
            }

            echo '<span class="dataobject-tree-code' . ($isMatch ? ' text-primary' : '') . '">' . h($code) . '</span>';
            if ($name !== '') {
                echo '<span class="dataobject-tree-name">' . h($name) . '</span>';
            }
            if ($typeName !== '') {
                echo '<span class="badge text-bg-light border ms-2">' . h($typeName) . '</span>';
            }

            if ($hasChildren) {
                echo '</summary>';
                renderDataObjectHierarchyTree($code, $childrenByParent, $nodeByCode, $searchActive, $visited, $depth + 1);
                echo '</details>';
            } else {
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}
?>
<style>
  .dataobject-tree,
  .dataobject-tree-children {
    display: grid;
    gap: .25rem;
  }
  .dataobject-tree-children {
    margin-left: 1.75rem;
    padding-left: .85rem;
    border-left: 1px solid #dee2e6;
  }
  .dataobject-tree-node summary,
  .dataobject-tree-leaf {
    padding: .4rem .5rem;
    border-radius: .25rem;
  }
  .dataobject-tree-node summary:hover,
  .dataobject-tree-leaf:hover {
    background: #f8f9fa;
  }
  .dataobject-tree-code {
    display: inline-block;
    min-width: 5.5rem;
    font-weight: 600;
  }
  .dataobject-tree-name {
    color: #495057;
  }
</style>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h3 class="mb-0"><i class="bi bi-diagram-2 me-2"></i><?= h((string)($title ?? 'Data Object Hierarchy')) ?></h3>
        <div class="small text-muted mt-1">View ancestor and descendant links generated from Data Object Code parent relationships.</div>
      </div>
      <?php if ($canEdit): ?>
        <div class="d-inline-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#treeRebuildHierarchyModal">
            <i class="bi bi-diagram-2 me-1"></i>Rebuild Hierarchy
          </button>
        </div>
      <?php endif; ?>
    </div>

    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        Current fiscal year: <strong><?= $fiscalYearId > 0 ? h((string)$fiscalYearId) : 'Not set' ?></strong>
      </div>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="dataobjectcodes/hierarchy">
        <div class="col-md-8">
          <input class="form-control" type="text" name="q" value="<?= h((string)($filters['q'] ?? '')) ?>" placeholder="Search code or name">
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" class="btn btn-outline-primary">Search</button>
        </div>
        <div class="col-md-2 d-grid">
          <a href="index.php?route=dataobjectcodes/hierarchy" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>

      <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">
          Showing <?= count($treeNodes) ?> data object node(s)<?= trim((string)($filters['q'] ?? '')) !== '' ? ' matching the current search path' : '' ?>.
        </div>
      </div>

      <div class="border rounded p-3 bg-white">
        <?php if ($treeNodes === []): ?>
          <div class="text-center text-muted py-3">No hierarchy nodes found.</div>
        <?php else: ?>
          <?php renderDataObjectHierarchyTree('', $childrenByParent, $nodeByCode, trim((string)($filters['q'] ?? '')) !== ''); ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($canEdit): ?>
<div class="modal fade" id="treeRebuildHierarchyModal" tabindex="-1" aria-labelledby="treeRebuildHierarchyModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="treeRebuildHierarchyModalLabel">Rebuild Data Object Hierarchy</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="index.php?route=dataobjectcodes/rebuildHierarchy">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= h($_csrf ?? csrf_token()) ?>">
          <p class="mb-2">Rebuild hierarchy links for the current fiscal year<?= $fiscalYearId > 0 ? ' (FY ' . h((string)$fiscalYearId) . ')' : '' ?>?</p>
          <div class="alert alert-warning mb-0">
            This will replace existing hierarchy links in <code>tblDataObjectTree</code> for the current fiscal year using parent codes in <code>tblDataObjectCodes</code>.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-diagram-2 me-1"></i>Rebuild Hierarchy
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
