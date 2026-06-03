<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$id = (int) ($record['ActivityID'] ?? 0);
$types = ['OPERATIONAL', 'PROJECT'];
$statuses = ['PLANNED', 'ONGOING', 'COMPLETE'];
$supportsActivityProjectLink = !empty($supportsActivityProjectLink);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><?= $id > 0 ? 'Edit Activity' : 'Create Activity' ?></h3>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=strategy-delivery/save-activity">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="ActivityID" value="<?= $id ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Activity Name</label>
            <input type="text" name="ActivityName" class="form-control" required value="<?= h((string) ($record['ActivityName'] ?? '')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Output</label>
            <select name="OutputID" class="form-select" required>
              <option value="">Select output</option>
              <?php foreach (($outputOptions ?? []) as $option): ?>
                <option value="<?= (int) $option['OutputID'] ?>" <?= ((int) ($record['OutputID'] ?? 0) === (int) $option['OutputID']) ? 'selected' : '' ?>>
                  <?= h((string) $option['ProgramName'] . ' / ' . $option['OutputName']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Activity Type</label>
            <select name="ActivityTypeCode" class="form-select">
              <?php foreach ($types as $type): ?>
                <option value="<?= $type ?>" <?= (($record['ActivityTypeCode'] ?? 'OPERATIONAL') === $type) ? 'selected' : '' ?>><?= $type ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($supportsActivityProjectLink): ?>
            <div class="col-md-6">
              <label class="form-label">Project</label>
              <select name="ProjectID" class="form-select">
                <option value="">Not linked to a project</option>
                <?php foreach (($projectOptions ?? []) as $option): ?>
                  <option value="<?= (int) ($option['ProjectID'] ?? 0) ?>" <?= ((int) ($record['ProjectID'] ?? 0) === (int) ($option['ProjectID'] ?? 0)) ? 'selected' : '' ?>>
                    <?= h(trim((string) (($option['ProjectCode'] ?? '') . ' - ' . ($option['ProjectName'] ?? '')), ' -')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Choose the strategic project when this activity is part of a project delivery stream.</div>
            </div>
          <?php endif; ?>
          <div class="col-md-3">
            <label class="form-label">Implementation Status</label>
            <select name="ImplementationStatusCode" class="form-select">
              <?php foreach ($statuses as $status): ?>
                <option value="<?= $status ?>" <?= (($record['ImplementationStatusCode'] ?? 'PLANNED') === $status) ? 'selected' : '' ?>><?= $status ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date" name="StartDate" class="form-control" value="<?= h((string) ($record['StartDate'] ?? '')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date" name="EndDate" class="form-control" value="<?= h((string) ($record['EndDate'] ?? '')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Location Code</label>
            <input type="text" name="LocationCode" class="form-control" value="<?= h((string) ($record['LocationCode'] ?? '')) ?>">
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ProcurementRequiredFlag" id="procurementRequiredFlag" <?= ((int) ($record['ProcurementRequiredFlag'] ?? 0) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="procurementRequiredFlag">Procurement Required</label>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="ActivityDescription" class="form-control" rows="3"><?= h((string) ($record['ActivityDescription'] ?? '')) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Dependencies</label>
            <textarea name="Dependencies" class="form-control" rows="3"><?= h((string) ($record['Dependencies'] ?? '')) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Risk Notes</label>
            <textarea name="RiskNotes" class="form-control" rows="3"><?= h((string) ($record['RiskNotes'] ?? '')) ?></textarea>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ActiveFlag" id="activityActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="activityActiveFlag">Active</label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
          <a href="index.php?route=strategy-delivery/activities" class="btn btn-secondary">Back</a>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
