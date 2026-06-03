<?php declare(strict_types=1);
/** @var array{code:string,name:string,hasChildren:bool}[] $rows */
/** @var string $selected */
/** @var int $fiscalYearID */
/** @var int $versionID */
/** @var string $backUrl */
/** @var bool $debug */
/** @var array|null $debugStats */
/** @var array<int,string>|null $selectedPath  // optional: ancestors root->...->parent */

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$isIframe = !empty($_GET['iframe']);
$showAll  = ($_GET['showAll'] ?? '') === '1';
?>
<?php if ($isIframe): ?>
<!doctype html>
<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">
<head>
  <meta charset="utf-8">
  <title><?= h($title ?? __t('select_data_scope')) ?></title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/icons/bootstrap-icons.css" rel="stylesheet">
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <style>
    .tree .toggle { cursor: pointer; user-select: none; }
    .tree .children { margin-left: 1.25rem; }
    .tree .node-row:hover { background: #f8f9fa; }
    .tree .node-row.active { background:#e7f1ff; border-left:3px solid #0d6efd; }
  </style>
</head>
<body class="bg-light p-3">
<div class="container-fluid">
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-header d-flex align-items-center justify-content-between">
    <div>
      <strong><?= __t('select_data_scope') ?></strong>
      <span class="text-muted ms-2"><?= __t('fiscal_year') ?>: <?= h((string)$fiscalYearID) ?></span>
      <?php if ($showAll): ?><span class="badge text-bg-secondary ms-2"><?= __t('show_all') ?></span><?php endif; ?>
    </div>
  </div>

  <div class="card-body">
    <?php if (!empty($rows)): ?>
      <div id="tree" class="tree">
        <?php foreach ($rows as $r): ?>
          <div class="node-row py-1" data-code="<?= h($r['code']) ?>">
            <?php if ($r['hasChildren']): ?>
              <span class="toggle me-2" data-code="<?= h($r['code']) ?>" data-loaded="0">
                <i class="bi bi-caret-right-square"></i>
              </span>
            <?php else: ?>
              <i class="bi bi-dot me-2"></i>
            <?php endif; ?>

            <button
              class="btn btn-sm btn-outline-success"
              onclick="selectCode('<?= h($r['code']) ?>','<?= h($r['name']) ?>')"
              title="<?= __t('select') ?>"
            >
              <i class="bi bi-check2-circle"></i>
            </button>

            <span class="ms-2 fw-semibold"><?= h($r['name']) ?></span>
            <span class="ms-2 text-muted">(<?= h($r['code']) ?>)</span>
            <span class="ms-2" data-status-code="<?= h($r['code']) ?>">
              <i class="bi bi-circle text-secondary"></i>
            </span>

            <?php if ($r['hasChildren']): ?>
              <div class="children d-none" id="children-<?= h($r['code']) ?>"></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-warning mb-0">
        <?= __t('no_data_objects_found') ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($debug) && is_array($debugStats ?? null)): ?>
    <div class="card-footer small text-muted">
      <pre class="mb-0"><?= h(json_encode($debugStats, JSON_PRETTY_PRINT)) ?></pre>
    </div>
  <?php endif; ?>
</div>

<script>
const pickerContext = (() => {
  const params = new URLSearchParams(window.location.search);
  const fy = parseInt(params.get('fy') || '0', 10) || <?= (int)($fiscalYearID ?? 0) ?>;
  const ver = parseInt(params.get('ver') || '0', 10) || <?= (int)($versionID ?? 0) ?>;
  const scopeCode = String(params.get('scope_dataobject_code') || '');
  const scopeName = String(params.get('scope_dataobject_name') || '');

  const apply = (url) => {
    const next = new URL(url, window.location.href);
    next.searchParams.set('link_context', '1');
    if (fy > 0) {
      next.searchParams.set('fy', String(fy));
    }
    if (ver > 0) {
      next.searchParams.set('ver', String(ver));
    }
    next.searchParams.set('scope_dataobject_code', scopeCode);
    if (scopeCode !== '' && scopeName !== '') {
      next.searchParams.set('scope_dataobject_name', scopeName);
    } else {
      next.searchParams.delete('scope_dataobject_name');
    }
    return next;
  };

  return { fy, ver, scopeCode, scopeName, apply };
})();

document.addEventListener("DOMContentLoaded", () => {
  let fy = 0;
  let ver = 0;

  try {
    const pdoc = window.parent && window.parent.document ? window.parent.document : null;
    if (pdoc) {
      const fyEl = pdoc.getElementById('FiscalYearID_hidden');
      const verEl = pdoc.getElementById('VersionID_hidden');
      if (fyEl) {
        fy = parseInt(fyEl.value || '0', 10);
      }
      if (verEl) {
        ver = parseInt(verEl.value || '0', 10);
      }
    }
  } catch (e) {
    // Same-origin is expected; ignore if parent access is unavailable.
  }

  if (!fy) {
    fy = pickerContext.fy;
  }
  if (!ver) {
    ver = pickerContext.ver;
  }

  if (!fy || !ver) {
    console.warn('[WF Picker] Missing FY/VER, skipping status fetches.');
    return;
  }

  const statusMap = {
    "Open": { icon: "bi-circle text-info", label: <?= json_encode(__t('workflow_status_open')) ?> },
    "In Progress": { icon: "bi-hourglass-split text-primary", label: <?= json_encode(__t('workflow_status_in_progress')) ?> },
    "Completed": { icon: "bi-check-circle text-success", label: <?= json_encode(__t('workflow_status_completed')) ?> },
    "Approved": { icon: "bi-hand-thumbs-up text-success", label: <?= json_encode(__t('workflow_status_approved')) ?> },
    "Rejected": { icon: "bi-x-circle text-danger", label: <?= json_encode(__t('workflow_status_rejected')) ?> },
    "Closed": { icon: "bi-lock text-warning", label: <?= json_encode(__t('workflow_status_closed')) ?> },
    "Not Set": { icon: "bi-question-circle text-muted", label: <?= json_encode(__t('workflow_status_not_set')) ?> }
  };

  const updateStatus = (el, code) => {
    const statusUrl = pickerContext.apply('index.php?route=dataobjectworkflow/getStatus');
    statusUrl.searchParams.set('FiscalYearID', String(fy));
    statusUrl.searchParams.set('VersionID', String(ver));
    statusUrl.searchParams.set('DataObjectCode', String(code));

    fetch(statusUrl.pathname + statusUrl.search)
      .then(async (r) => {
        if (!r.ok) {
          return { status: 'Not Set' };
        }
        try {
          return await r.json();
        } catch {
          return { status: 'Not Set' };
        }
      })
      .then(({ status }) => {
        const st = status || 'Not Set';
        const statusMeta = statusMap[st] || statusMap["Not Set"];
        el.innerHTML = `<i class="bi ${statusMeta.icon}" title="${statusMeta.label}" aria-hidden="true"></i>`;
      })
      .catch(() => {
        el.innerHTML = `<i class="bi bi-question-circle text-muted" title="${statusMap["Not Set"].label}" aria-hidden="true"></i>`;
      });
  };

  document.querySelectorAll('[data-status-code]').forEach((el) => {
    updateStatus(el, el.getAttribute('data-status-code'));
  });

  document.addEventListener('children:loaded', (e) => {
    e.detail.container.querySelectorAll('[data-status-code]').forEach((el) => {
      updateStatus(el, el.getAttribute('data-status-code'));
    });
  });
});

function selectCode(code, name) {
  try {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ type: 'dataobject:selected', code: code, name: name }, '*');
      return;
    }
  } catch (e) {}

  const next = pickerContext.apply('index.php?route=dataobjects/select');
  next.searchParams.set('code', code);
  next.searchParams.set('name', name);
  next.searchParams.set('scope_dataobject_code', code);
  if (name) {
    next.searchParams.set('scope_dataobject_name', name);
  } else {
    next.searchParams.delete('scope_dataobject_name');
  }
  next.searchParams.set('return', '<?= h($backUrl) ?>');
  window.location.href = next.pathname + next.search;
}

document.addEventListener('click', async function (ev) {
  const t = ev.target.closest('.toggle');
  if (!t) return;

  const code = t.getAttribute('data-code');
  const loaded = t.getAttribute('data-loaded') === '1';
  const pane = document.getElementById('children-' + code);
  const icon = t.querySelector('i');

  if (!loaded) {
    try {
      const childUrl = pickerContext.apply('index.php?route=dataobjects/children');
      childUrl.searchParams.set('parent', code);
      childUrl.searchParams.set('fy', String(pickerContext.fy || 0));
      const res = await fetch(childUrl.pathname + childUrl.search);
      const json = await res.json();
      pane.innerHTML = '';
      (json.items || []).forEach(function (item) {
        const row = document.createElement('div');
        row.className = 'node-row py-1';
        row.setAttribute('data-code', item.code || '');
        row.innerHTML =
          (item.hasChildren
            ? '<span class="toggle me-2" data-code="'+item.code+'" data-loaded="0"><i class="bi bi-caret-right-square"></i></span>'
            : '<i class="bi bi-dot me-2"></i>') +
          '<button class="btn btn-sm btn-outline-success" onclick="selectCode(\''+String(item.code||'').replace(/'/g,"\\'")+'\', \''+String(item.name||'').replace(/'/g,"\\'")+'\')"><i class="bi bi-check2-circle"></i></button>' +
          '<span class="ms-2 fw-semibold">'+(item.name||'')+'</span>' +
          '<span class="ms-2 text-muted">('+(item.code||'')+')</span>' +
          '<span class="ms-2" data-status-code="'+item.code+'"><i class="bi bi-circle text-secondary"></i></span>' +
          (item.hasChildren ? '<div class="children d-none" id="children-'+item.code+'"></div>' : '');
        pane.appendChild(row);
      });
      t.setAttribute('data-loaded', '1');

      const evt = new CustomEvent('children:loaded', { detail: { container: pane } });
      document.dispatchEvent(evt);
    } catch (e) {
      pane.innerHTML = '<div class="text-danger small">Load failed</div>';
    }
  }

  const isHidden = pane.classList.contains('d-none');
  if (isHidden) {
    pane.classList.remove('d-none');
    if (icon) icon.className = 'bi bi-caret-down-square';
  } else {
    pane.classList.add('d-none');
    if (icon) icon.className = 'bi bi-caret-right-square';
  }
});

(function () {
  const selected = <?= json_encode($selected ?? '') ?>;
  const selectedPath = <?= isset($selectedPath) ? json_encode($selectedPath) : '[]' ?>;
  const tree = document.getElementById('tree');
  if (!tree || !selected) return;

  const rootRow = tree.querySelector('.node-row[data-code="'+CSS.escape(selected)+'"]');
  if (rootRow) {
    rootRow.classList.add('active');
    rootRow.scrollIntoView({block:'center'});
    return;
  }

  function ensureExpanded(code) {
    return new Promise((resolve) => {
      const toggle = tree.querySelector('.toggle[data-code="'+CSS.escape(code)+'"]');
      const pane   = document.getElementById('children-' + code);
      if (!toggle || !pane) { resolve(); return; }

      const needClick = (toggle.getAttribute('data-loaded') !== '1') || pane.classList.contains('d-none');
      if (!needClick) { resolve(); return; }

      let done = false;
      const obs = new MutationObserver(() => {
        if (!done && toggle.getAttribute('data-loaded') === '1' && !pane.classList.contains('d-none')) {
          done = true; obs.disconnect(); resolve();
        }
      });
      obs.observe(pane, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });

      setTimeout(() => { if (!done) { done = true; obs.disconnect(); resolve(); } }, 1200);

      toggle.dispatchEvent(new MouseEvent('click', { bubbles:true }));
    });
  }

  (async function openPath() {
    try {
      for (const anc of selectedPath) {
        await ensureExpanded(anc);
      }
      const selRow = tree.querySelector('.node-row[data-code="'+CSS.escape(selected)+'"]');
      if (selRow) {
        selRow.classList.add('active');
        selRow.scrollIntoView({block:'center'});
      }
    } catch (e) {
      /* no-op */
    }
  })();
})();
</script>

<?php if ($isIframe): ?>
</div>
</body>
</html>
<?php endif; ?>
