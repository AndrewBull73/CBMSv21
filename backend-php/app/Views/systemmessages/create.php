<?php declare(strict_types=1); ?>
<?php $defaults = is_array($defaults ?? null) ? $defaults : []; ?>
<?php $formAction = (string)($formAction ?? 'index.php?route=systemmessages/create'); ?>
<?php $submitLabels = is_array($submitLabels ?? null) ? $submitLabels : ['draft' => 'Save Draft', 'publish' => 'Publish']; ?>
<?php $availableRoles = array_values(array_filter(array_map('strval', is_array($availableRoles ?? null) ? $availableRoles : []))); ?>
<?php $selectedRoles = array_values(array_filter(array_map('strval', is_array($defaults['SelectedRoles'] ?? null) ? $defaults['SelectedRoles'] : []))); ?>
<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong><?= htmlspecialchars($title ?? 'Create System Message', ENT_QUOTES) ?></strong>
    <a href="index.php?route=systemmessages/list" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back to List
    </a>
  </div>
  <div class="card-body">
    <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES) ?>">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) csrf_token(), ENT_QUOTES) ?>">
      <?php if (!empty($messageId)): ?>
        <input type="hidden" name="MessageID" value="<?= (int)$messageId ?>">
      <?php endif; ?>
      <div class="alert alert-info">
        Leave <strong>Data Object Code Scope</strong> blank for a global message. If you enter a code, the message applies to that code and all child codes automatically.
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Title</label>
          <input name="Title" class="form-control" required value="<?= htmlspecialchars((string)($defaults['Title'] ?? ''), ENT_QUOTES) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Severity</label>
          <select name="Severity" class="form-select">
            <option value="info"<?= (($defaults['Severity'] ?? 'info') === 'info') ? ' selected' : '' ?>>info</option>
            <option value="success"<?= (($defaults['Severity'] ?? '') === 'success') ? ' selected' : '' ?>>success</option>
            <option value="warning"<?= (($defaults['Severity'] ?? '') === 'warning') ? ' selected' : '' ?>>warning</option>
            <option value="danger"<?= (($defaults['Severity'] ?? '') === 'danger') ? ' selected' : '' ?>>danger</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Priority (lower is higher)</label>
          <input name="Priority" type="number" class="form-control" value="<?= htmlspecialchars((string) ($defaults['Priority'] ?? 10), ENT_QUOTES) ?>">
        </div>

        <div class="col-12">
          <label class="form-label">Body</label>
          <textarea name="Body" class="form-control" rows="6"><?= htmlspecialchars((string)($defaults['Body'] ?? ''), ENT_QUOTES) ?></textarea>
          <div class="form-text">
            You can include <code>{{CBMS_LOGIN_URL}}</code>, <code>{{CBMS_LOGIN_LINK}}</code>, <code>{{CBMS_SECURE_LOGIN_URL}}</code>, or <code>{{CBMS_SECURE_LOGIN_LINK}}</code> in message or email HTML.
          </div>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="IsHtml" id="ishtml"<?= !empty($defaults['IsHtml']) ? ' checked' : '' ?>>
            <label class="form-check-label" for="ishtml">Body is HTML</label>
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">StartAt (UTC)</label>
          <input name="StartAt" type="datetime-local" class="form-control" value="<?= htmlspecialchars((string)($defaults['StartAt'] ?? ''), ENT_QUOTES) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">EndAt (UTC, optional)</label>
          <input name="EndAt" type="datetime-local" class="form-control" value="<?= htmlspecialchars((string)($defaults['EndAt'] ?? ''), ENT_QUOTES) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Options</label>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="IsDismissible" id="dismiss"<?= !empty($defaults['IsDismissible']) ? ' checked' : '' ?>>
            <label class="form-check-label" for="dismiss">Dismissible (ignored if Requires Ack)</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="RequiresAck" id="reqack"<?= !empty($defaults['RequiresAck']) ? ' checked' : '' ?>>
            <label class="form-check-label" for="reqack">Requires acknowledgement</label>
          </div>
        </div>

        <div class="col-md-3">
          <label class="form-label">Scope: Fiscal Year</label>
          <input name="ScopeFiscalYearID" type="number" class="form-control" placeholder="e.g. 2025" value="<?= !empty($defaults['ScopeFiscalYearID']) ? htmlspecialchars((string) $defaults['ScopeFiscalYearID'], ENT_QUOTES) : '' ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Scope: VersionID (optional)</label>
          <input name="ScopeVersionID" type="number" class="form-control" value="<?= !empty($defaults['ScopeVersionID']) ? htmlspecialchars((string) $defaults['ScopeVersionID'], ENT_QUOTES) : '' ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Data Object Code Scope</label>
          <input name="ScopeDataObjectCode" class="form-control" placeholder="Leave blank for global, e.g. 302 for scoped" value="<?= htmlspecialchars((string)($defaults['ScopeDataObjectCode'] ?? ''), ENT_QUOTES) ?>">
          <div class="form-text">
            Scoped messages automatically include descendant data object codes for visibility and email recipients.
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Roles</label>
          <select name="RoleNames[]" class="form-select" multiple size="8">
            <?php foreach ($availableRoles as $roleName): ?>
              <option value="<?= htmlspecialchars($roleName, ENT_QUOTES) ?>"<?= in_array($roleName, $selectedRoles, true) ? ' selected' : '' ?>>
                <?= htmlspecialchars($roleName, ENT_QUOTES) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">
            Optional. If you select roles, the audience is filtered to users in those roles. If you leave this blank, all roles in the scoped/global audience are included.
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Email</label>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="SendEmail" id="sendemail"<?= !empty($defaults['SendEmail']) ? ' checked' : '' ?>>
            <label class="form-check-label" for="sendemail">Send email to audience</label>
          </div>
          <div class="form-text mb-2">
            For scoped messages, recipients are resolved from <code>tblDataObjectCodeAccess</code>.
          </div>
          <div class="mt-2">
            <label class="form-label">Email Subject</label>
            <input name="EmailSubject" class="form-control" placeholder="Subject line" value="<?= htmlspecialchars((string)($defaults['EmailSubject'] ?? ''), ENT_QUOTES) ?>">
          </div>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button class="btn btn-secondary" name="Action" value="draft"><i class="bi bi-save me-1"></i><?= htmlspecialchars((string)($submitLabels['draft'] ?? 'Save Draft'), ENT_QUOTES) ?></button>
        <button class="btn btn-primary"  name="Action" value="publish"><i class="bi bi-megaphone me-1"></i><?= htmlspecialchars((string)($submitLabels['publish'] ?? 'Publish'), ENT_QUOTES) ?></button>
        <a class="btn btn-outline-secondary" href="index.php?route=systemmessages/createForm"><i class="bi bi-arrow-clockwise me-1"></i>Reset</a>
      </div>
    </form>
  </div>
</div>
