<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-heart-pulse me-2"></i>Intelligence Engine Health</h3>
      <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/index">Dashboard</a>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <div class="border rounded p-3 bg-white mb-3">
          <div class="small text-muted">Configured Engine URL</div>
          <div class="fw-semibold"><?= h((string) $engineUrl) ?></div>
        </div>
        <button id="intelHealthBtn" class="btn btn-primary" type="button">
          <span class="spinner-border spinner-border-sm me-1 d-none" id="intelHealthSpinner" role="status" aria-hidden="true"></span>
          <i class="bi bi-heart-pulse me-1" id="intelHealthIcon"></i>
          <span id="intelHealthButtonText">Run Health Check</span>
        </button>

        <div id="intelHealthResult" class="mt-3 border rounded p-3 bg-white">
          <div class="text-muted">Not checked yet.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('intelHealthBtn');
    const result = document.getElementById('intelHealthResult');
    const spinner = document.getElementById('intelHealthSpinner');
    const icon = document.getElementById('intelHealthIcon');
    const buttonText = document.getElementById('intelHealthButtonText');
    if (!btn || !result) return;
    btn.addEventListener('click', async function () {
        const fd = new FormData();
        fd.append('_csrf', <?= json_encode((string) $csrf) ?>);
        btn.disabled = true;
        spinner.classList.remove('d-none');
        icon.classList.add('d-none');
        buttonText.textContent = 'Checking...';
        result.className = 'mt-3 border rounded p-3 bg-white';
        result.innerHTML = '<div class="text-muted">Checking engine...</div>';
        try {
            const response = await fetch('index.php?route=intelligence/health-check', { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await response.json();
            renderHealth(data);
        } catch (error) {
            result.className = 'mt-3 border rounded p-3 bg-white';
            result.innerHTML = '<div class="text-danger fw-semibold">Health check failed.</div>';
        } finally {
            btn.disabled = false;
            spinner.classList.add('d-none');
            icon.classList.remove('d-none');
            buttonText.textContent = 'Run Health Check';
        }
    });

    function renderHealth(data) {
        const response = data && data.response && typeof data.response === 'object' ? data.response : {};
        const capabilities = Array.isArray(response.capabilities) ? response.capabilities : [];
        const statusClass = data.ok ? 'success' : 'danger';
        const statusText = data.ok ? 'Engine is available' : (data.message || 'Engine is unavailable');
        const badges = capabilities.length
            ? capabilities.map(function (capability) {
                return '<span class="badge text-bg-light border me-1 mb-1">' + escapeHtml(String(capability)) + '</span>';
            }).join('')
            : '<span class="text-muted">No capabilities returned.</span>';
        result.innerHTML = ''
            + '<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">'
            + '<div><div class="fw-semibold text-' + statusClass + '">' + escapeHtml(statusText) + '</div>'
            + '<div class="small text-muted">Response time: ' + escapeHtml(String(data.duration_ms || 0)) + ' ms</div></div>'
            + '<span class="badge text-bg-' + statusClass + '">' + (data.ok ? 'OK' : 'ERROR') + '</span>'
            + '</div>'
            + '<div class="row g-3 mb-3">'
            + summaryCell('Service', response.service || '')
            + summaryCell('Version', response.version || '')
            + summaryCell('Timestamp UTC', response.timestamp_utc || '')
            + '</div>'
            + '<div class="small text-muted mb-1">Capabilities</div>'
            + '<div class="mb-3">' + badges + '</div>'
            + '<button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#intelHealthRaw">Raw Response</button>'
            + '<div class="collapse mt-2" id="intelHealthRaw"><pre class="small bg-light border rounded p-2 mb-0 text-break">'
            + escapeHtml(JSON.stringify(data, null, 2))
            + '</pre></div>';
    }

    function summaryCell(label, value) {
        return '<div class="col-md-4"><div class="border rounded p-2 h-100">'
            + '<div class="small text-muted">' + escapeHtml(label) + '</div>'
            + '<div class="fw-semibold text-break">' + escapeHtml(String(value || '')) + '</div>'
            + '</div></div>';
    }

    function escapeHtml(value) {
        return value.replace(/[&<>"']/g, function (ch) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[ch];
        });
    }
});
</script>
