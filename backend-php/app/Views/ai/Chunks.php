<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$document = is_array($document ?? null) ? $document : null;
$chunks = array_values(is_array($chunks ?? null) ? $chunks : []);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="mb-0"><i class="bi bi-list-ul me-2"></i>Knowledge Chunks</h3>
      <a class="btn btn-sm btn-outline-secondary" href="index.php?route=ai-knowledge/documents">Documents</a>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php elseif ($document === null): ?>
        <div class="alert alert-warning mb-0">Document not found.</div>
      <?php else: ?>
        <h4 class="h5"><?= h((string) ($document['Title'] ?? '')) ?></h4>
        <?php foreach ($chunks as $chunk): ?>
          <div id="chunk-<?= h((string) (int) ($chunk['ChunkNumber'] ?? 0)) ?>" class="border rounded p-3 bg-white mb-3">
            <div class="small text-muted mb-2">Chunk <?= h((string) (int) ($chunk['ChunkNumber'] ?? 0)) ?></div>
            <div style="white-space: pre-wrap;"><?= h((string) ($chunk['ChunkText'] ?? '')) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
