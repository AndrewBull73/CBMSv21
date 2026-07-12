<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$foundationInstalled = (bool) ($foundationInstalled ?? false);
$context = is_array($context ?? null) ? $context : [];
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-stars me-2"></i>Ask CBMS Assistant</h3>
        <div class="small text-muted mt-1">Answers are limited to approved CBMS knowledge documents.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=ai-knowledge/admin" class="btn btn-sm btn-outline-secondary"><i class="bi bi-database me-1"></i>Knowledge Base</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> before using the assistant.</div>
      <?php else: ?>
        <form id="aiAskForm" class="mb-3">
          <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
          <input type="hidden" name="module" value="<?= h((string) ($context['Module'] ?? '')) ?>">
          <input type="hidden" name="screen" value="<?= h((string) ($context['Screen'] ?? '')) ?>">
          <label for="aiQuestion" class="form-label">Question</label>
          <div class="input-group">
            <textarea id="aiQuestion" name="question" class="form-control" rows="3" required placeholder="How do I submit a budget?"></textarea>
            <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Ask</button>
          </div>
          <div class="form-text">
            FY <?= h((string) ($context['FiscalYearID'] ?? 0)) ?>,
            Version <?= h((string) ($context['VersionID'] ?? 0)) ?>,
            Screen <?= h((string) ($context['Screen'] ?? 'current')) ?>
          </div>
        </form>

        <div id="aiAnswerPanel" class="border rounded bg-white p-3 d-none">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <h4 class="h5 mb-0">Answer</h4>
            <div id="aiDuration" class="small text-muted"></div>
          </div>
          <div id="aiAnswer" class="mb-3" style="white-space: pre-wrap;"></div>
          <div id="aiProviderWarning" class="alert alert-warning py-2 d-none"></div>
          <h5 class="h6">Referenced Documents</h5>
          <div id="aiSources" class="list-group list-group-flush mb-3"></div>
          <div id="aiFeedback" class="d-flex gap-2 align-items-center d-none">
            <button type="button" class="btn btn-sm btn-outline-success" data-helpful="1"><i class="bi bi-hand-thumbs-up me-1"></i>Helpful</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-helpful="0"><i class="bi bi-hand-thumbs-down me-1"></i>Not Helpful</button>
            <span class="small text-muted" id="aiFeedbackStatus"></span>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('aiAskForm');
    if (!form) return;
    const panel = document.getElementById('aiAnswerPanel');
    const answerEl = document.getElementById('aiAnswer');
    const sourcesEl = document.getElementById('aiSources');
    const durationEl = document.getElementById('aiDuration');
    const warningEl = document.getElementById('aiProviderWarning');
    const feedbackEl = document.getElementById('aiFeedback');
    const feedbackStatus = document.getElementById('aiFeedbackStatus');
    let questionId = 0;

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        panel.classList.remove('d-none');
        answerEl.textContent = 'Searching approved CBMS documentation...';
        sourcesEl.innerHTML = '';
        durationEl.textContent = '';
        warningEl.classList.add('d-none');
        feedbackEl.classList.add('d-none');

        let data;
        try {
            const controller = new AbortController();
            const timeoutId = window.setTimeout(function () { controller.abort(); }, 30000);
            const response = await fetch('index.php?route=ai-knowledge/answer', {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                signal: controller.signal
            });
            window.clearTimeout(timeoutId);
            const text = await response.text();
            try {
                data = JSON.parse(text);
            } catch (error) {
                throw new Error(response.ok ? 'The assistant returned an unreadable response.' : 'The assistant request failed with HTTP ' + response.status + '.');
            }
        } catch (error) {
            answerEl.textContent = error.name === 'AbortError'
                ? 'The assistant request timed out after 30 seconds.'
                : (error.message || 'The assistant request failed.');
            return;
        }
        if (!data.ok) {
            answerEl.textContent = data.error || 'The assistant could not answer the question.';
            return;
        }
        questionId = data.question_id || 0;
        answerEl.textContent = data.answer || '';
        durationEl.textContent = data.duration_ms ? (data.duration_ms + ' ms') : '';
        if (data.provider_error) {
            warningEl.textContent = data.provider_error;
            warningEl.classList.remove('d-none');
        }
        const sources = Array.isArray(data.sources) ? data.sources : [];
        sourcesEl.innerHTML = sources.length ? '' : '<div class="text-muted small">No source documents were used.</div>';
        sources.forEach(function (source) {
            const item = document.createElement('div');
            item.className = 'list-group-item px-0';
            const title = escapeHtml(source.label + ' - ' + source.title);
            const href = source.url ? escapeHtml(source.url) : '';
            item.innerHTML = '<div class="fw-semibold">' + (href ? '<a href="' + href + '">' + title + '</a>' : title) + '</div>'
                + '<div class="small text-muted">Chunk ' + escapeHtml(String(source.chunk_number || '')) + (source.module ? ' - ' + escapeHtml(source.module) : '') + '</div>';
            sourcesEl.appendChild(item);
        });
        feedbackEl.classList.remove('d-none');
        feedbackStatus.textContent = '';
    });

    feedbackEl.querySelectorAll('button[data-helpful]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const fd = new FormData();
            fd.append('_csrf', form.querySelector('input[name="_csrf"]').value);
            fd.append('question_id', String(questionId));
            fd.append('helpful', button.getAttribute('data-helpful') || '');
            await fetch('index.php?route=ai-knowledge/feedback', { method: 'POST', body: fd, credentials: 'same-origin' });
            feedbackStatus.textContent = 'Feedback saved.';
        });
    });

    function escapeHtml(value) {
        return value.replace(/[&<>"']/g, function (ch) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[ch];
        });
    }
});
</script>
