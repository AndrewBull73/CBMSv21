<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];

$defaultCategoryMap = [
    'APP' => 'Application',
    'AUTH' => 'Authentication',
    'CBMS' => 'Authentication',
    'CLIENT' => 'Application',
    'DEFAULT' => 'Base Configuration',
    'ERROR' => 'Monitoring & Alerts',
    'FIN' => 'Financial Configuration',
    'GL' => 'Financial Configuration',
    'LOGIN' => 'Authentication',
    'SESSION' => 'Session Management',
    'SLOW' => 'Monitoring & Alerts',
    'SMTP' => 'Email',
];

$categoryCodeMap = [
    'Application' => 'APP',
    'Authentication' => 'AUTH',
    'Base Configuration' => 'BASE',
    'Email' => 'SMTP',
    'Financial Configuration' => 'FIN',
    'Monitoring & Alerts' => 'MON',
    'Other' => 'OTHER',
    'Session Management' => 'SESS',
];

$suggestCategory = static function (array $row) use ($defaultCategoryMap): string {
    $category = trim((string) ($row['Category'] ?? ''));
    if ($category !== '') {
        return $category;
    }

    $key = trim((string) ($row['SettingKey'] ?? ''));
    if ($key === '') {
        return 'Other';
    }

    $prefix = $key;
    if (str_contains($key, '_')) {
        $prefix = explode('_', $key, 2)[0];
    }

    return $defaultCategoryMap[$prefix] ?? 'Other';
};

$normalizeCategoryCode = static function (string $category) use ($categoryCodeMap): string {
    $trimmed = trim($category);
    if ($trimmed === '') {
        return 'OTHER';
    }
    if (isset($categoryCodeMap[$trimmed])) {
        return $categoryCodeMap[$trimmed];
    }
    return strtoupper(preg_replace('/[^A-Z0-9]+/', '_', $trimmed)) ?: 'OTHER';
};

$isTruthySettingValue = static function (string $value): bool {
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
};

$grouped = [];
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }
    $category = $suggestCategory($row);
    $row['_ResolvedCategory'] = $category;
    $grouped[$category][] = $row;
}
ksort($grouped);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-gear me-2"></i><?= __t('system_settings') ?></h3>
        <div class="small text-muted mt-1">Maintain grouped system settings with consistent naming, category, and description metadata.</div>
      </div>
      <a id="system-settings-usage-map-btn" href="index.php?route=system-settings/usage-map" class="btn btn-sm btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i>Usage Map</a>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=system-settings/save" class="row g-3 mb-3 js-setting-row" id="system-settings-create-form">
        <?= csrf_field(); ?>
        <div class="col-md-3">
          <input id="systemSettingKey" class="form-control" name="SettingKey" value="AUTH_LOGIN_URL" placeholder="Setting key">
        </div>
        <div class="col-md-3">
          <input id="systemSettingCategory" class="form-control" name="Category" value="Authentication" placeholder="Category">
        </div>
        <div class="col-md-2">
          <select id="systemSettingType" class="form-select js-setting-type-select" name="SettingType">
            <option value="string" selected>string</option>
            <option value="bool">bool</option>
            <option value="int">int</option>
            <option value="json">json</option>
          </select>
        </div>
        <div class="col-md-4">
          <input id="systemSettingDescription" class="form-control" name="Description" placeholder="Short purpose of the setting">
        </div>
        <div class="col-md-9">
          <input id="systemSettingValue" class="form-control js-setting-value-input" name="SettingValue" placeholder="Setting value">
          <select class="form-select js-setting-value-bool d-none" name="SettingValue" disabled>
            <option value="1">True</option>
            <option value="0">False</option>
          </select>
        </div>
        <div class="col-md-3 d-grid">
          <button id="system-settings-save-btn" class="btn btn-primary" type="submit"><i class="bi bi-plus-circle me-1"></i>Add / Update Setting</button>
        </div>
      </form>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">Recommended naming pattern: use uppercase area prefixes such as <code>APP_</code>, <code>AUTH_</code>, <code>SESSION_</code>, <code>SMTP_</code>, <code>FIN_</code>, and <code>STRATEGY_</code> so the catalogue stays consistent as it grows.</div>
      </div>

      <form method="post" action="index.php?route=system-settings/save" id="system-settings-bulk-form">
        <?= csrf_field(); ?>
        <div class="d-flex justify-content-end mb-3">
          <button id="system-settings-save-all-btn" class="btn btn-primary" type="submit">
            <i class="bi bi-save me-1"></i>Save All Changes
          </button>
        </div>

      <?php $settingIndex = 0; ?>
      <?php foreach ($grouped as $category => $settings): ?>
        <?php $categoryCode = $normalizeCategoryCode($category); ?>
        <div class="card shadow-sm mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= h($category) ?></h5>
            <span class="badge text-bg-light border"><?= h($categoryCode) ?></span>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th><?= __t('key') ?></th>
                    <th><?= __t('value') ?></th>
                    <th><?= __t('type') ?></th>
                    <th>Category</th>
                    <th><?= __t('description') ?></th>
                    <th><?= __t('updated_by') ?></th>
                    <th><?= __t('updated_at') ?></th>
                    <th class="text-end"><?= __t('actions') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($settings as $r): ?>
                    <?php $fieldPrefix = 'Settings[' . $settingIndex++ . ']'; ?>
                    <?php
                      $currentType = strtolower((string) ($r['SettingType'] ?? 'string'));
                      $currentValue = (string) ($r['SettingValue'] ?? '');
                      $isBool = $currentType === 'bool';
                    ?>
                    <tr class="js-setting-row">
                      <td style="width:16%">
                        <input class="form-control form-control-sm" name="<?= h($fieldPrefix) ?>[SettingKey]" value="<?= h((string) ($r['SettingKey'] ?? '')) ?>" readonly>
                      </td>
                      <td style="width:22%">
                        <input class="form-control form-control-sm js-setting-value-input <?= $isBool ? 'd-none' : '' ?>" name="<?= h($fieldPrefix) ?>[SettingValue]" value="<?= h($currentValue) ?>" <?= $isBool ? 'disabled' : '' ?>>
                        <select class="form-select form-select-sm js-setting-value-bool <?= $isBool ? '' : 'd-none' ?>" name="<?= h($fieldPrefix) ?>[SettingValue]" <?= $isBool ? '' : 'disabled' ?>>
                          <option value="1" <?= $isTruthySettingValue($currentValue) ? 'selected' : '' ?>>True</option>
                          <option value="0" <?= !$isTruthySettingValue($currentValue) ? 'selected' : '' ?>>False</option>
                        </select>
                      </td>
                      <td style="width:9%">
                        <select class="form-select form-select-sm js-setting-type-select" name="<?= h($fieldPrefix) ?>[SettingType]">
                          <?php foreach (['string','bool','int','json'] as $t): ?>
                            <option value="<?= h($t) ?>" <?= strtolower((string) ($r['SettingType'] ?? 'string')) === $t ? 'selected' : '' ?>><?= __t($t) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td style="width:14%">
                        <input class="form-control form-control-sm" name="<?= h($fieldPrefix) ?>[Category]" value="<?= h((string) ($r['_ResolvedCategory'] ?? '')) ?>">
                      </td>
                      <td style="width:22%">
                        <input class="form-control form-control-sm" name="<?= h($fieldPrefix) ?>[Description]" value="<?= h((string) ($r['Description'] ?? '')) ?>">
                      </td>
                      <td><?= h((string) ($r['UpdatedBy'] ?? '')) ?></td>
                      <td><?= h((string) ($r['UpdatedAt'] ?? '')) ?></td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" type="submit" title="Save all changes">
                          <i class="bi bi-save"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.js-setting-row').forEach((row) => {
  const typeSelect = row.querySelector('.js-setting-type-select');
  const textInput = row.querySelector('.js-setting-value-input');
  const boolSelect = row.querySelector('.js-setting-value-bool');

  if (!typeSelect || !textInput || !boolSelect) {
    return;
  }

  const isTruthy = (value) => ['1', 'true', 'yes', 'on'].includes(String(value).trim().toLowerCase());
  let wasBool = typeSelect.value === 'bool';
  const syncValueControl = () => {
    const isBool = typeSelect.value === 'bool';

    if (isBool) {
      if (!wasBool) {
        boolSelect.value = isTruthy(textInput.value) ? '1' : '0';
      }
      boolSelect.disabled = false;
      boolSelect.classList.remove('d-none');
      textInput.disabled = true;
      textInput.classList.add('d-none');
      wasBool = true;
      return;
    }

    if (wasBool) {
      textInput.value = boolSelect.value;
    }
    textInput.disabled = false;
    textInput.classList.remove('d-none');
    boolSelect.disabled = true;
    boolSelect.classList.add('d-none');
    wasBool = false;
  };

  typeSelect.addEventListener('change', syncValueControl);
  syncValueControl();
});
</script>
