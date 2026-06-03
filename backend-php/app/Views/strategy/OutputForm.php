<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$id = (int) ($record['OutputID'] ?? 0);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><?= $id > 0 ? 'Edit Output' : 'Create Output' ?></h3>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=strategy-delivery/save-output">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="OutputID" value="<?= $id ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Output Name</label>
            <input type="text" name="OutputName" class="form-control" required value="<?= h((string) ($record['OutputName'] ?? '')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Program</label>
            <select name="ProgramID" class="form-select" required>
              <option value="">Select program</option>
              <?php foreach (($programOptions ?? []) as $option): ?>
                <option value="<?= (int) $option['ProgramID'] ?>" <?= ((int) ($record['ProgramID'] ?? 0) === (int) $option['ProgramID']) ? 'selected' : '' ?>>
                  <?= h((string) $option['ProgramName']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">SubProgram</label>
            <select name="SubProgramID" class="form-select">
              <option value="">None</option>
              <?php foreach (($subProgramOptions ?? []) as $option): ?>
                <option value="<?= (int) $option['SubProgramID'] ?>" <?= ((int) ($record['SubProgramID'] ?? 0) === (int) $option['SubProgramID']) ? 'selected' : '' ?>>
                  <?= h((string) $option['ProgramName'] . ' / ' . $option['SubProgramName']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Output Owner DataScope Org Unit</label>
            <select name="OutputOwnerDataObjectCode" class="form-select">
              <option value="">None</option>
              <?php foreach (($orgUnitOptions ?? []) as $option): ?>
                <option value="<?= h((string) $option['DataObjectCode']) ?>" <?= (($record['OutputOwnerDataObjectCode'] ?? '') === ($option['DataObjectCode'] ?? '')) ? 'selected' : '' ?>>
                  <?= h((string) $option['DataObjectCode'] . ' / ' . $option['DataObjectName']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="OutputDescription" class="form-control" rows="4"><?= h((string) ($record['OutputDescription'] ?? '')) ?></textarea>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ActiveFlag" id="outputActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="outputActiveFlag">Active</label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
          <a href="index.php?route=strategy-delivery/outputs" class="btn btn-secondary">Back</a>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
