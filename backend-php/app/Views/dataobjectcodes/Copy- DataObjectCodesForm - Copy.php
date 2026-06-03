<?php declare(strict_types=1);
/** @var array  $row
 *  @var array  $types
 *  @var string $_csrf
 *  @var string $title
 *  @var string $mode   'create' | 'edit'
 *  @var array  $errors
 *  @var array  $parents  // <-- NEW: list of possible parents
 */

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$row    = $row ?? [];
$mode   = $mode ?? 'create';
$errors = $errors ?? [];
$parents = $parents ?? [];  // ← passed from controller

$code    = (string)($row['DataObjectCode']       ?? '');
$name    = (string)($row['DataObjectName']       ?? '');
$parent  = (string)($row['DataObjectCodeParent'] ?? '');
$typeId  = (string)($row['DataObjectTypeID']     ?? '');
$desc    = (string)($row['DataObjectDesc']       ?? '');
$status  = (string)($row['DataObjectCodeStatus'] ?? '');

$isEdit = ($mode === 'edit');
?>
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>
      <i class="bi bi-collection me-2"></i><?= h(__t($title ?? ($isEdit ? 'docodes_edit_title' : 'docodes_add_title'))) ?>
    </strong>
    <a href="index.php?route=dataobjectcodes/index" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left-short"></i> <?= __t('back_to_list') ?>
    </a>
  </div>

  <div class="card-body">
    <form class="needs-validation" novalidate method="post" action="index.php?route=dataobjectcodes/save">
      <input type="hidden" name="_csrf" value="<?= h($_csrf ?? csrf_token()) ?>">

      <?php if ($isEdit): ?>
        <input type="hidden" name="DataObjectCode" value="<?= h($code) ?>">
      <?php endif; ?>

      <div class="row g-3">
        <!-- CODE -->
        <div class="col-md-4">
          <label for="DataObjectCode" class="form-label"><?= __t('code') ?></label>
          <input
            type="text"
            class="form-control form-control-sm<?= isset($errors['DataObjectCode']) ? ' is-invalid' : '' ?>"
            id="DataObjectCode"
            name="DataObjectCode"
            value="<?= h($code) ?>"
            maxlength="20"
            <?= $isEdit ? 'readonly' : 'required' ?>
            pattern="^[A-Za-z0-9_\-\.]+$"
            <?php if (!$isEdit): ?>placeholder="<?= __t('docodes_code_ph') ?>"<?php endif; ?>
          >
          <div class="form-text"><?= __t('docodes_code_help') ?></div>
          <div class="invalid-feedback">
            <?= h($errors['DataObjectCode'] ?? __t('docodes_code_invalid')) ?>
          </div>
        </div>

        <!-- NAME -->
        <div class="col-md-8">
          <label for="DataObjectName" class="form-label"><?= __t('name') ?></label>
          <input
            type="text"
            class="form-control form-control-sm<?= isset($errors['DataObjectName']) ? ' is-invalid' : '' ?>"
            id="DataObjectName"
            name="DataObjectName"
            value="<?= h($name) ?>"
            maxlength="100"
            required
            placeholder="<?= __t('docodes_name_ph') ?>"
          >
          <div class="invalid-feedback">
            <?= h($errors['DataObjectName'] ?? __t('docodes_name_invalid')) ?>
          </div>
        </div>

        <!-- PARENT – NOW A DROPDOWN -->
        <div class="col-md-4">
          <label for="DataObjectCodeParent" class="form-label"><?= __t('parent_code') ?></label>
          <select
            id="DataObjectCodeParent"
            name="DataObjectCodeParent"
            class="form-select form-select-sm<?= isset($errors['DataObjectCodeParent']) ? ' is-invalid' : '' ?>"
          >
            <option value=""><?= __t('none') ?></option>
            <?php foreach ($parents as $p): ?>
              <?php 
                $pcode = (string)($p['DataObjectCode'] ?? '');
                $pname = (string)($p['DataObjectName'] ?? '');
                $ptype = (string)($p['DataObjectTypeName'] ?? $p['DataObjectTypeID'] ?? '');
              ?>
                <option value="<?= h($pcode) ?>" <?= $parent === $pcode ? 'selected' : '' ?>>
                  <?= h($pcode) ?> – <?= h($pname) ?> (Level <?= $p['Level'] ?> - <?= h($p['DataObjectTypeName']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text"><?= __t('docodes_parent_help') ?></div>
          <div class="invalid-feedback">
            <?= h($errors['DataObjectCodeParent'] ?? __t('docodes_parent_invalid')) ?>
          </div>
        </div>

        <!-- TYPE -->
        <div class="col-md-4">
          <label for="DataObjectTypeID" class="form-label"><?= __t('type') ?></label>
          <select
            id="DataObjectTypeID"
            name="DataObjectTypeID"
            class="form-select form-select-sm<?= isset($errors['DataObjectTypeID']) ? ' is-invalid' : '' ?>"
            required
          >
            <option value=""><?= __t('select_type') ?></option>
            <?php foreach ($types as $t): ?>
              <?php $tid = (string)($t['DataObjectTypeID'] ?? ''); $tname = (string)($t['DataObjectTypeName'] ?? $tid); ?>
              <option value="<?= h($tid) ?>" <?= $typeId === $tid ? 'selected' : '' ?>>
                <?= h($tname) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">
            <?= h($errors['DataObjectTypeID'] ?? __t('docodes_type_invalid')) ?>
          </div>
        </div>

        <!-- STATUS -->
        <div class="col-md-4">
          <label for="DataObjectCodeStatus" class="form-label"><?= __t('status') ?></label>
          <select
            id="DataObjectCodeStatus"
            name="DataObjectCodeStatus"
            class="form-select form-select-sm<?= isset($errors['DataObjectCodeStatus']) ? ' is-invalid' : '' ?>"
          >
            <option value=""><?= __t('none') ?></option>
            <option value="Active"   <?= $status === 'Active'   ? 'selected' : '' ?>><?= __t('active') ?></option>
            <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>><?= __t('inactive') ?></option>
          </select>
          <div class="invalid-feedback">
            <?= h($errors['DataObjectCodeStatus'] ?? __t('docodes_status_invalid')) ?>
          </div>
        </div>

        <!-- DESCRIPTION -->
        <div class="col-12">
          <label for="DataObjectDesc" class="form-label"><?= __t('description') ?></label>
          <textarea
            id="DataObjectDesc"
            name="DataObjectDesc"
            class="form-control form-control-sm<?= isset($errors['DataObjectDesc']) ? ' is-invalid' : '' ?>"
            rows="3"
            placeholder="<?= __t('optional') ?>"
          ><?= h($desc) ?></textarea>
          <div class="invalid-feedback">
            <?= h($errors['DataObjectDesc'] ?? __t('docodes_desc_invalid')) ?>
          </div>
        </div>
      </div>

      <hr class="my-4">

      <div class="d-flex justify-content-between">
        <div class="text-muted small">
          <i class="bi bi-info-circle me-1"></i>
          <?= $isEdit
            ? __t('docodes_edit_hint', ['code' => h($code)])
            : __t('docodes_create_hint')
          ?>
        </div>
        <div class="d-flex gap-2">
          <a href="index.php?route=dataobjectcodes/index" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-x-circle me-1"></i> <?= __t('cancel') ?>
          </a>
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-check2-circle me-1"></i> <?= __t('save') ?>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
  (function () {
    'use strict';
    const form = document.querySelector('.needs-validation');
    if (!form) return;

    form.addEventListener('submit', function (event) {
      const code   = document.getElementById('DataObjectCode');
      const parent = document.getElementById('DataObjectCodeParent');
      if (code && parent && code.value && parent.value && code.value === parent.value) {
        parent.setCustomValidity('<?= __t('docodes_parent_same') ?>');
      } else if (parent) {
        parent.setCustomValidity('');
      }

      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  })();
</script>