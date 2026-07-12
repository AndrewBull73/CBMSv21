<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$summary = is_array($summary ?? null) ? $summary : [];
$csrf = (string) ($csrf ?? csrf_token());
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-database me-2"></i>AI Knowledge Base</h3>
        <div class="small text-muted mt-1">Manage approved documents, chunks, and assistant audit logs.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=ai-knowledge/ask" class="btn btn-sm btn-outline-secondary"><i class="bi bi-stars me-1"></i>Ask</a>
        <a href="index.php?route=ai-knowledge/documents" class="btn btn-sm btn-outline-secondary"><i class="bi bi-files me-1"></i>Documents</a>
        <a href="index.php?route=ai-knowledge/upload" class="btn btn-sm btn-primary"><i class="bi bi-upload me-1"></i>Upload</a>
        <a href="index.php?route=ai-knowledge/usage" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bar-chart-line me-1"></i>Usage</a>
        <a href="index.php?route=ai-knowledge/logs" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clock-history me-1"></i>Logs</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> to install the AI assistant tables.</div>
      <?php else: ?>
        <div class="row row-cols-1 row-cols-md-4 g-3">
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Active Documents</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['document_count'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Active Chunks</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['chunk_count'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Questions 7 Days</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['question_count_7d'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Latest Question</div><div class="fw-semibold"><?= h((string) ($summary['latest_question_at'] ?? 'None')) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Tokens 7 Days</div><div class="fs-4 fw-semibold"><?= h(number_format((int) ($summary['token_count_7d'] ?? 0))) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Avg Response 7 Days</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['avg_response_ms_7d'] ?? 0)) ?> ms</div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Provider Warnings 7 Days</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['provider_error_count_7d'] ?? 0)) ?></div></div></div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-lg-6">
            <div class="border rounded p-3 bg-white h-100">
              <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <div>
                  <h4 class="h6 mb-1">Provider Health</h4>
                  <div class="small text-muted">Checks whether the OpenAI provider can currently answer.</div>
                </div>
                <button id="aiProviderHealthBtn" class="btn btn-sm btn-outline-primary" type="button">
                  <i class="bi bi-heart-pulse me-1"></i>Check
                </button>
              </div>
              <div id="aiProviderHealthResult" class="small text-muted">Not checked yet.</div>
            </div>
          </div>
          <div class="col-lg-6">
            <form class="border rounded p-3 bg-white h-100" method="post" action="index.php?route=ai-knowledge/bulk-index-help">
              <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div>
                  <h4 class="h6 mb-1">Bulk Index Help Files</h4>
                  <div class="small text-muted">Indexes current CBMS help pages and replaces older copies.</div>
                </div>
                <button class="btn btn-sm btn-outline-primary" type="submit">
                  <i class="bi bi-files me-1"></i>Index Help
                </button>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const button = document.getElementById('aiProviderHealthBtn');
    const result = document.getElementById('aiProviderHealthResult');
    if (!button || !result) return;

    button.addEventListener('click', async function () {
        const fd = new FormData();
        fd.append('_csrf', <?= json_encode($csrf) ?>);
        button.disabled = true;
        result.className = 'small text-muted';
        result.textContent = 'Checking provider...';
        try {
            const response = await fetch('index.php?route=ai-knowledge/provider-health', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await response.json();
            result.className = data.ok ? 'small text-success' : 'small text-danger';
            result.textContent = data.message || (data.ok ? 'Provider is available.' : 'Provider is unavailable.');
        } catch (error) {
            result.className = 'small text-danger';
            result.textContent = 'Health check failed.';
        } finally {
            button.disabled = false;
        }
    });
});
</script>
