<?php declare(strict_types=1); ?>
<?php $selectedRoles = is_array($selectedRoles ?? null) ? $selectedRoles : []; ?>
<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong><?= htmlspecialchars($title ?? 'Audience Preview', ENT_QUOTES) ?></strong>
    <a href="index.php?route=systemmessages/createForm" class="btn btn-sm btn-outline-secondary">Back to Create</a>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <div><strong>Message:</strong> <?= htmlspecialchars($msg['Title'] ?? '', ENT_QUOTES) ?></div>
      <div><strong>Window (UTC):</strong> <?= htmlspecialchars((string) ($msg['StartAt'] ?? ''), ENT_QUOTES) ?> - <?= htmlspecialchars((string) ($msg['EndAt'] ?? '-'), ENT_QUOTES) ?></div>
      <div><strong>Severity:</strong> <?= htmlspecialchars((string) ($msg['Severity'] ?? 'info'), ENT_QUOTES) ?></div>
      <div><strong>Requires Ack:</strong> <?= !empty($msg['RequiresAck']) ? 'Yes' : 'No' ?></div>
      <div><strong>Status:</strong> <?= htmlspecialchars((string) ($msg['Status'] ?? 'draft'), ENT_QUOTES) ?></div>
      <div><strong>Scope:</strong> FY <?= htmlspecialchars((string) ($msg['ScopeFiscalYearID'] ?? 'any'), ENT_QUOTES) ?>, Version <?= htmlspecialchars((string) ($msg['ScopeVersionID'] ?? 'any'), ENT_QUOTES) ?></div>
      <div><strong>Data Object Scope:</strong> <?= !empty($scopeCode) ? htmlspecialchars((string) $scopeCode, ENT_QUOTES) . ' (includes descendants)' : 'GLOBAL' ?></div>
      <div><strong>Role Filter:</strong> <?= $selectedRoles !== [] ? htmlspecialchars(implode(', ', $selectedRoles), ENT_QUOTES) : 'All roles in audience' ?></div>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="alert alert-info mb-0">
          <div><strong>Total users (unique):</strong> <?= (int) ($counts['users'] ?? 0) ?></div>
          <div><strong>Total emails:</strong> <?= (int) ($counts['emails'] ?? 0) ?></div>
        </div>
      </div>
    </div>

    <hr>
    <h6>Sample recipients (emails)</h6>
    <?php if (!empty($sample)): ?>
      <ul class="small">
        <?php foreach ($sample as $e): ?>
          <li><?= htmlspecialchars((string) $e, ENT_QUOTES) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="text-muted">No recipients resolved.</div>
    <?php endif; ?>
  </div>
</div>
