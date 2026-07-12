<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$context = is_array($context ?? null) ? $context : [];
$replaceDocument = is_array($replaceDocument ?? null) ? $replaceDocument : null;
$isReplace = $replaceDocument !== null;
$field = static function (string $name, string $fallback = '') use ($replaceDocument): string {
    return (string) ($replaceDocument[$name] ?? $fallback);
};
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header"><h3 class="mb-0"><i class="bi bi-upload me-2"></i><?= $isReplace ? 'Replace AI Knowledge Document' : 'Upload AI Knowledge Document' ?></h3></div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <form method="post" action="index.php?route=ai-knowledge/upload-document" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
          <input type="hidden" name="ReplaceDocumentID" value="<?= h((string) (int) ($replaceDocument['DocumentID'] ?? 0)) ?>">
          <?php if ($isReplace): ?>
            <div class="alert alert-info">This will deactivate the current copy and index a replacement version.</div>
          <?php endif; ?>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="Title" value="<?= h($field('Title')) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Category</label><input class="form-control" name="Category" value="<?= h($field('Category')) ?>" placeholder="User Manual"></div>
            <div class="col-md-3"><label class="form-label">Module</label><input class="form-control" name="Module" value="<?= h($field('Module', (string) ($context['Module'] ?? ''))) ?>"></div>
            <div class="col-md-3"><label class="form-label">Fiscal Year ID</label><input class="form-control" name="FiscalYearID" value="<?= h($field('FiscalYearID', (string) ($context['FiscalYearID'] ?? ''))) ?>"></div>
            <div class="col-md-3"><label class="form-label">Version ID</label><input class="form-control" name="VersionID" value="<?= h($field('VersionID', (string) ($context['VersionID'] ?? ''))) ?>"></div>
            <div class="col-md-3"><label class="form-label">Audience</label><select class="form-select" name="AudienceCode">
              <?php foreach (['USER', 'PUBLIC', 'ADMIN', 'DEVELOPER'] as $audience): ?>
                <option value="<?= h($audience) ?>" <?= $field('AudienceCode', 'USER') === $audience ? 'selected' : '' ?>><?= h($audience) ?></option>
              <?php endforeach; ?>
            </select></div>
            <div class="col-md-3"><label class="form-label">Ministry Code</label><input class="form-control" name="MinistryCode" value="<?= h($field('MinistryCode')) ?>"></div>
            <div class="col-12"><label class="form-label">File</label><input class="form-control" type="file" name="KnowledgeFile" accept=".txt,.md,.markdown,.html,.htm,.docx,.pdf" <?= $isReplace ? '' : 'required' ?>></div>
            <div class="col-12"><label class="form-label">Extracted Text</label><textarea class="form-control" name="ExtractedText" rows="10" placeholder="Paste PDF text here when the file cannot be extracted automatically."></textarea></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="Notes" rows="2"><?= h($field('Notes')) ?></textarea></div>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="IsActive" value="1" checked id="IsActive"><label class="form-check-label" for="IsActive">Active</label></div></div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-upload me-1"></i><?= $isReplace ? 'Replace and Re-index' : 'Index Document' ?></button>
            <a class="btn btn-outline-secondary" href="index.php?route=ai-knowledge/documents">Cancel</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
