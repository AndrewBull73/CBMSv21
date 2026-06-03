<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$csrf = h(csrf_token());
$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-journal-text me-2"></i>BSP Narratives</h3>
      <a href="index.php?route=strategy-governance/narrative-form" class="btn btn-sm btn-primary">Create Narrative</a>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">Context: <strong><?= h($yearLabel) ?></strong><?php if ($versionLabel !== ''): ?> / <strong><?= h($versionLabel) ?></strong><?php endif; ?></div>

      <form method="get" class="row g-2 mb-3">
        <input type="hidden" name="route" value="strategy-governance/narratives">
        <div class="col-md-5"><input type="text" name="q" class="form-control form-control-sm" placeholder="Search narratives" value="<?= h((string) ($q ?? '')) ?>"></div>
        <div class="col-md-3">
          <select name="section_code" class="form-select form-select-sm">
            <option value="">All sections</option>
            <?php foreach (($sectionOptions ?? []) as $code => $label): ?>
              <option value="<?= h((string) $code) ?>" <?= ((string) ($sectionCode ?? '') === (string) $code) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
          <a href="index.php?route=strategy-governance/narratives" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Section</th>
              <th>Title</th>
              <th>Scope</th>
              <th class="text-end">Sort</th>
              <th>Locked</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (($records ?? []) === []): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No narrative rows yet.</td></tr>
          <?php else: ?>
            <?php foreach ($records as $row): ?>
              <tr>
                <td><?= h((string) ($row['SectionCode'] ?? '')) ?></td>
                <td>
                  <div class="fw-semibold"><?= h((string) ($row['NarrativeTitle'] ?? 'Untitled')) ?></div>
                  <div class="small text-muted"><?= h(mb_strimwidth((string) ($row['BodyText'] ?? ''), 0, 120, '...')) ?></div>
                </td>
                <td>
                  <?= h((string) (($row['ProgramName'] ?? '') !== '' ? $row['ProgramName'] : (($row['SectorName'] ?? '') !== '' ? $row['SectorName'] : ($row['OrgUnitName'] ?? 'Global')))) ?>
                  <?php if (!empty($row['ProjectName'])): ?>
                    <div class="small text-muted"><?= h((string) (($row['ProjectCode'] ?? '') !== '' ? $row['ProjectCode'] . ' / ' : '') . $row['ProjectName']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?= (int) ($row['SortOrder'] ?? 0) ?></td>
                <td><?= (int) ($row['LockedFlag'] ?? 0) === 1 ? 'Yes' : 'No' ?></td>
                <td class="text-end">
                  <a href="index.php?route=strategy-governance/narrative-form&id=<?= (int) $row['NarrativeID'] ?>" class="btn btn-outline-secondary btn-sm">Edit</a>
                  <form method="post" action="index.php?route=strategy-governance/delete-narrative" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= (int) $row['NarrativeID'] ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit">Archive</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
