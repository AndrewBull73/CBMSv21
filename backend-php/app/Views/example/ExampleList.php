<?php
declare(strict_types=1);

/** @var array $records */
/** @var int $currentPage */
/** @var int $totalPages */
/** @var int $totalCount */
/** @var string $q */
/** @var array|null $flash */

if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
?>
<div class="container mt-4">

  <!-- Flash -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= h($flash['type'] ?? 'info') ?> alert-dismissible fade show mb-3" role="alert">
      <?= h($flash['text'] ?? '') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="mb-0">
        <i class="bi bi-box me-2"></i><?= __t('menu_example') ?>
      </h3>
      <a href="index.php?route=example/edit" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i> <?= __t('create_example') ?>
      </a>
    </div>
    <div class="card-body">

      <!-- Filters -->
      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="example/list">
        <div class="col-md-6">
          <input type="text" name="q" value="<?= h($q ?? '') ?>"
                 class="form-control" placeholder="<?= __t('search') ?>...">
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-search me-1"></i><?= __t('filter') ?>
          </button>
        </div>
      </form>

      <!-- Table -->
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= __t('id') ?></th>
              <th><?= __t('name') ?></th>
              <th><?= __t('description') ?></th>
              <th><?= __t('created_at') ?></th>
              <th><?= __t('updated_at') ?></th>
              <th class="text-end"><?= __t('actions') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($records)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3"><?= __t('no_records_found') ?></td></tr>
          <?php else: ?>
            <?php foreach ($records as $r): ?>
              <tr>
                <td><?= h((string)($r['ExampleID'] ?? '')) ?></td>
                <td><?= h((string)($r['Name'] ?? '')) ?></td>
                <td><?= h((string)($r['Description'] ?? '')) ?></td>
                <td><?= !empty($r['CreatedAt']) ? date('d/m/Y', strtotime($r['CreatedAt'])) : '' ?></td>
                <td><?= !empty($r['UpdatedAt']) ? date('d/m/Y', strtotime($r['UpdatedAt'])) : '' ?></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm" role="group">
                    <a href="index.php?route=example/edit&id=<?= h((string)$r['ExampleID']) ?>"
                       class="btn btn-outline-secondary" title="<?= __t('edit') ?>">
                      <i class="bi bi-pencil-square"></i>
                    </a>
                    <a href="index.php?route=example/delete&id=<?= h((string)$r['ExampleID']) ?>"
                       class="btn btn-outline-danger"
                       onclick="return confirm('<?= __t('confirm_delete') ?>');"
                       title="<?= __t('delete') ?>">
                      <i class="bi bi-trash"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <nav class="mt-3" aria-label="Example pagination">
          <ul class="pagination justify-content-center">
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="index.php?route=example/list&page=<?= $currentPage-1 ?>&q=<?= urlencode($q) ?>">
                <?= __t('prev') ?>
              </a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="index.php?route=example/list&page=<?= $i ?>&q=<?= urlencode($q) ?>">
                  <?= $i ?>
                </a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="index.php?route=example/list&page=<?= $currentPage+1 ?>&q=<?= urlencode($q) ?>">
                <?= __t('next') ?>
              </a>
            </li>
          </ul>
          <p class="text-center text-muted small">
            <?= __t('showing') ?> <?= count($records) ?> <?= __t('of') ?> <?= $totalCount ?> <?= __t('entries') ?>
          </p>
        </nav>
      <?php endif; ?>

    </div>
  </div>
</div>
