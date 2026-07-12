<?php
declare(strict_types=1);
require __DIR__ . '/../ai/_helpers.php';
$datasets = array_values(is_array($datasets ?? null) ? $datasets : []);
$summary = is_array($summary ?? null) ? $summary : [];
$context = is_array($context ?? null) ? $context : [];
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-graph-up-arrow me-2"></i>AI Dataset Analysis</h3>
        <div class="small text-muted mt-1">Executive-only analysis of approved registered datasets.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="index.php?route=ai-dataset/datasets"><i class="bi bi-database me-1"></i>Datasets</a>
        <a class="btn btn-sm btn-outline-secondary" href="index.php?route=ai-dataset/logs"><i class="bi bi-clock-history me-1"></i>Logs</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> to install the AI dataset analysis tables.</div>
      <?php else: ?>
        <div class="row row-cols-1 row-cols-md-4 g-3 mb-3">
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Active Datasets</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['active_dataset_count'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Queries 7 Days</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['query_count_7d'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Fiscal Context</div><div class="fw-semibold">FY <?= h((string) ($context['FiscalYearID'] ?? 0)) ?> / V <?= h((string) ($context['VersionID'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Latest Query</div><div class="fw-semibold"><?= h((string) ($summary['latest_query_at'] ?? 'None')) ?></div></div></div>
        </div>

        <form id="aiDatasetForm" class="mb-3">
          <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Dataset</label>
              <select class="form-select" name="dataset_id" required>
                <option value="">Select dataset</option>
                <?php foreach ($datasets as $dataset): ?>
                  <option value="<?= h((string) (int) ($dataset['DatasetID'] ?? 0)) ?>"><?= h((string) ($dataset['DatasetName'] ?? '')) ?> (<?= h((string) ($dataset['SensitivityLevel'] ?? '')) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label">Analysis Question</label>
              <textarea class="form-control" name="question" rows="3" required placeholder="Which ministries have the highest variance?"></textarea>
            </div>
          </div>
          <div class="mt-3">
            <div class="small text-muted mb-2">Executive question starters</div>
            <div class="d-flex flex-wrap gap-2" id="aiDatasetExamples">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-question="For fiscal year 2026, group by Segment1 and show the top 10 by total BudgetAmount. Include total BudgetAmount and each Segment1's percentage share of the overall BudgetAmount.">Budget share by Segment1</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-question="For fiscal year 2026, group by EconomicCode and show the top 10 by total ActualAmount.">Top actual expenditure</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-question="For fiscal year 2026, group by ProgramCode and EconomicCode and show the top 10 by total AvailableBalance.">Largest available balances</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-question="For fiscal year 2026 period 12, group by ProgramCode and EconomicCode and show total BudgetAmount, ActualAmount and AvailableBalance.">Period 12 programme check</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-question="For fiscal year 2026, group by Segment1 and EconomicCode and show the top 10 by total ActualAmount. Include percentage share of total ActualAmount.">Actual share by Segment1 and economic</button>
            </div>
          </div>
          <div class="mt-3 d-flex gap-2 align-items-center">
            <button class="btn btn-primary" type="submit" id="aiDatasetSubmit">
              <span class="spinner-border spinner-border-sm me-1 d-none" id="aiDatasetButtonSpinner" role="status" aria-hidden="true"></span>
              <i class="bi bi-lightning-charge me-1" id="aiDatasetButtonIcon"></i>
              <span id="aiDatasetButtonText">Run Analysis</span>
            </button>
            <span class="small text-muted">Only approved columns and server-validated queries can run.</span>
          </div>
        </form>

        <div id="aiDatasetRunning" class="alert alert-info d-none align-items-center justify-content-between gap-3 flex-wrap" role="status" aria-live="polite">
          <div class="d-flex align-items-center gap-2">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <div>
              <div class="fw-semibold">Running approved analysis</div>
              <div class="small">Planning the query, checking approved columns, and calculating results.</div>
            </div>
          </div>
          <div class="fw-semibold text-nowrap" id="aiDatasetElapsed">0s</div>
        </div>

        <div id="aiDatasetResult" class="d-none">
          <div class="alert alert-warning py-2 d-none" id="aiDatasetWarning"></div>
          <div class="border rounded p-3 bg-white mb-3">
            <div class="d-flex justify-content-between gap-2 flex-wrap mb-2">
              <h4 class="h6 mb-0">Analysis Summary</h4>
              <div class="d-flex gap-2 align-items-center flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="aiDatasetExport"><i class="bi bi-download me-1"></i>CSV</button>
                <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="aiDatasetDetailsToggle" data-bs-toggle="collapse" data-bs-target="#aiDatasetDetails"><i class="bi bi-code-square me-1"></i>Details</button>
                <div id="aiDatasetDuration" class="small text-muted"></div>
              </div>
            </div>
            <div id="aiDatasetSummary"></div>
          </div>
          <div class="collapse mb-3" id="aiDatasetDetails">
            <div class="border rounded p-3 bg-light">
              <div class="row g-3">
                <div class="col-md-4">
                  <div class="small text-muted">Plan Source</div>
                  <div class="fw-semibold" id="aiDatasetPlanSource"></div>
                </div>
                <div class="col-md-4">
                  <div class="small text-muted">Rows Returned</div>
                  <div class="fw-semibold" id="aiDatasetRowCount"></div>
                </div>
                <div class="col-md-4">
                  <div class="small text-muted">Model</div>
                  <div class="fw-semibold" id="aiDatasetModel"></div>
                </div>
              </div>
              <div class="mt-3">
                <div class="small text-muted mb-1">Validated SQL</div>
                <pre class="small bg-white border rounded p-2 mb-2 text-break" id="aiDatasetSql"></pre>
                <div class="small text-muted mb-1">Parameters</div>
                <pre class="small bg-white border rounded p-2 mb-0" id="aiDatasetParams"></pre>
              </div>
            </div>
          </div>
          <div class="border rounded p-3 bg-white mb-3 d-none" id="aiDatasetChartWrap">
            <div class="d-flex justify-content-between gap-2 flex-wrap mb-2">
              <h4 class="h6 mb-0" id="aiDatasetChartTitle">Chart</h4>
              <div class="small text-muted">First grouped metric</div>
            </div>
            <canvas id="aiDatasetChart" height="220"></canvas>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle" id="aiDatasetTable"></table>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('aiDatasetForm');
    if (!form) return;
    const result = document.getElementById('aiDatasetResult');
    const warning = document.getElementById('aiDatasetWarning');
    const summary = document.getElementById('aiDatasetSummary');
    const duration = document.getElementById('aiDatasetDuration');
    const table = document.getElementById('aiDatasetTable');
    const running = document.getElementById('aiDatasetRunning');
    const elapsed = document.getElementById('aiDatasetElapsed');
    const submit = document.getElementById('aiDatasetSubmit');
    const buttonSpinner = document.getElementById('aiDatasetButtonSpinner');
    const buttonIcon = document.getElementById('aiDatasetButtonIcon');
    const buttonText = document.getElementById('aiDatasetButtonText');
    const question = form.querySelector('textarea[name="question"]');
    const examples = document.getElementById('aiDatasetExamples');
    const exportButton = document.getElementById('aiDatasetExport');
    const detailsToggle = document.getElementById('aiDatasetDetailsToggle');
    const planSource = document.getElementById('aiDatasetPlanSource');
    const rowCount = document.getElementById('aiDatasetRowCount');
    const model = document.getElementById('aiDatasetModel');
    const sql = document.getElementById('aiDatasetSql');
    const params = document.getElementById('aiDatasetParams');
    const chartWrap = document.getElementById('aiDatasetChartWrap');
    const chartTitle = document.getElementById('aiDatasetChartTitle');
    const chart = document.getElementById('aiDatasetChart');
    const fields = Array.from(form.querySelectorAll('select, textarea, button'));
    let timerId = null;
    let lastRows = [];

    if (examples && question) {
        examples.addEventListener('click', function (event) {
            const button = event.target.closest('button[data-question]');
            if (!button) return;
            question.value = button.getAttribute('data-question') || '';
            question.focus();
        });
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const payload = new FormData(form);
        setRunningState(true);
        result.classList.remove('d-none');
        warning.classList.add('d-none');
        summary.textContent = 'Planning and running approved analysis...';
        duration.textContent = '';
        table.innerHTML = '';
        chartWrap.classList.add('d-none');
        lastRows = [];
        setDetails(null);
        exportButton.classList.add('d-none');
        detailsToggle.classList.add('d-none');
        try {
            const controller = new AbortController();
            const timeoutId = window.setTimeout(function () { controller.abort(); }, 90000);
            const response = await fetch('index.php?route=ai-dataset/analyse', {
                method: 'POST',
                body: payload,
                credentials: 'same-origin',
                signal: controller.signal
            });
            window.clearTimeout(timeoutId);
            const data = await response.json();
            if (!data.ok) {
                summary.textContent = data.error || 'Analysis failed.';
                return;
            }
            if (data.provider_error) {
                warning.textContent = data.provider_error;
                warning.classList.remove('d-none');
            }
            summary.textContent = data.summary || '';
            duration.textContent = data.duration_ms ? data.duration_ms + ' ms' : '';
            lastRows = Array.isArray(data.rows) ? data.rows : [];
            renderTable(lastRows);
            renderChart(lastRows);
            setDetails(data);
            exportButton.classList.toggle('d-none', !lastRows.length);
            detailsToggle.classList.remove('d-none');
        } catch (error) {
            summary.textContent = error.name === 'AbortError' ? 'Analysis timed out after 90 seconds.' : 'Analysis request failed.';
        } finally {
            setRunningState(false);
        }
    });

    exportButton.addEventListener('click', function () {
        if (!lastRows.length) return;
        const csv = rowsToCsv(lastRows);
        const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'ai-dataset-analysis.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    });

    function setRunningState(isRunning) {
        fields.forEach(function (field) {
            field.disabled = isRunning;
        });
        running.classList.toggle('d-none', !isRunning);
        running.classList.toggle('d-flex', isRunning);
        buttonSpinner.classList.toggle('d-none', !isRunning);
        buttonIcon.classList.toggle('d-none', isRunning);
        buttonText.textContent = isRunning ? 'Running...' : 'Run Analysis';

        if (timerId) {
            window.clearInterval(timerId);
            timerId = null;
        }
        if (!isRunning) {
            return;
        }
        const startedAt = Date.now();
        elapsed.textContent = '0s';
        timerId = window.setInterval(function () {
            const seconds = Math.floor((Date.now() - startedAt) / 1000);
            elapsed.textContent = seconds + 's';
        }, 1000);
    }

    function renderTable(rows) {
        if (!rows.length) {
            table.innerHTML = '<tbody><tr><td class="text-muted">No rows returned.</td></tr></tbody>';
            return;
        }
        const columns = Object.keys(rows[0]);
        let html = '<thead class="table-light"><tr>' + columns.map(function (column) {
            return '<th>' + escapeHtml(column) + '</th>';
        }).join('') + '</tr></thead><tbody>';
        rows.forEach(function (row) {
            html += '<tr>' + columns.map(function (column) {
                const formatted = formatCell(column, row[column]);
                const align = formatted.numeric ? ' class="text-end font-monospace"' : '';
                return '<td' + align + '>' + escapeHtml(formatted.value) + '</td>';
            }).join('') + '</tr>';
        });
        table.innerHTML = html + '</tbody>';
    }

    function renderChart(rows) {
        if (!chart || !rows.length) {
            chartWrap.classList.add('d-none');
            return;
        }
        const columns = Object.keys(rows[0]);
        const labelColumn = columns.find(function (column) {
            return !isMetricLikeColumn(String(column).toLowerCase());
        });
        const valueColumn = columns.find(function (column) {
            return isMetricLikeColumn(String(column).toLowerCase()) && parseDisplayNumber(rows[0][column]) !== null;
        });
        if (!labelColumn || !valueColumn) {
            chartWrap.classList.add('d-none');
            return;
        }

        const points = rows.slice(0, 12).map(function (row) {
            return {
                label: String(row[labelColumn] === null || row[labelColumn] === undefined ? '' : row[labelColumn]),
                value: parseDisplayNumber(row[valueColumn]) || 0
            };
        }).filter(function (point) {
            return point.value !== 0;
        });
        if (!points.length) {
            chartWrap.classList.add('d-none');
            return;
        }

        chartWrap.classList.remove('d-none');
        chartTitle.textContent = valueColumn + ' by ' + labelColumn;
        const context = chart.getContext('2d');
        const width = chart.clientWidth || chart.parentElement.clientWidth || 800;
        const height = 220;
        const scale = window.devicePixelRatio || 1;
        chart.width = Math.floor(width * scale);
        chart.height = Math.floor(height * scale);
        context.setTransform(scale, 0, 0, scale, 0, 0);
        context.clearRect(0, 0, width, height);

        const max = Math.max.apply(null, points.map(function (point) { return point.value; }));
        const left = 120;
        const right = 18;
        const top = 10;
        const rowHeight = Math.max(16, Math.floor((height - top - 10) / points.length));
        const barMax = width - left - right;
        context.font = '12px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        points.forEach(function (point, index) {
            const y = top + index * rowHeight;
            const barWidth = max > 0 ? Math.max(1, (point.value / max) * barMax) : 0;
            context.fillStyle = '#475569';
            context.fillText(trimLabel(point.label, 18), 4, y + 12);
            context.fillStyle = '#0d6efd';
            context.fillRect(left, y + 2, barWidth, Math.max(8, rowHeight - 6));
            context.fillStyle = '#334155';
            context.fillText(formatCompact(point.value), left + Math.min(barWidth + 6, barMax - 76), y + 12);
        });
    }

    function isMetricLikeColumn(name) {
        return name.includes('amount')
            || name.includes('balance')
            || name.includes('actual')
            || name.includes('budget')
            || name.includes('expenditure')
            || name.includes('rate')
            || name.includes('pct')
            || name.startsWith('sum_')
            || name.startsWith('avg_')
            || name.startsWith('min_')
            || name.startsWith('max_')
            || name.startsWith('count_');
    }

    function parseDisplayNumber(value) {
        if (value === null || value === undefined || value === '') return null;
        const numericText = String(value).replace(/,/g, '').replace(/%$/, '').trim();
        if (!/^-?\d+(\.\d+)?$/.test(numericText)) return null;
        const number = Number(numericText);
        return Number.isFinite(number) ? number : null;
    }

    function trimLabel(value, limit) {
        return value.length > limit ? value.substring(0, limit - 1) + '...' : value;
    }

    function formatCompact(value) {
        return Number(value).toLocaleString(undefined, {notation: 'compact', maximumFractionDigits: 1});
    }

    function setDetails(data) {
        planSource.textContent = data && data.plan_source ? data.plan_source : '';
        rowCount.textContent = data && data.row_count !== undefined ? String(data.row_count) : '';
        model.textContent = data ? [data.provider || '', data.model || ''].filter(Boolean).join(' / ') : '';
        sql.textContent = data && data.sql ? data.sql : '';
        params.textContent = data && data.params ? JSON.stringify(data.params, null, 2) : '';
    }

    function rowsToCsv(rows) {
        const columns = Object.keys(rows[0] || {});
        const lines = [columns.map(csvEscape).join(',')];
        rows.forEach(function (row) {
            lines.push(columns.map(function (column) {
                return csvEscape(row[column] === null || row[column] === undefined ? '' : String(row[column]));
            }).join(','));
        });
        return lines.join('\r\n');
    }

    function csvEscape(value) {
        if (/[",\r\n]/.test(value)) {
            return '"' + value.replace(/"/g, '""') + '"';
        }
        return value;
    }

    function formatCell(column, value) {
        if (value === null || value === undefined || value === '') {
            return {value: '', numeric: false};
        }
        const name = String(column).toLowerCase();
        if (isCodeLikeColumn(name)) {
            return {value: String(value), numeric: false};
        }
        const text = String(value).trim();
        let numericText = text.replace(/,/g, '');
        if (/^-?\.\d+$/.test(numericText)) {
            numericText = numericText.replace('.', '0.');
        }
        if (!/^-?\d+(\.\d+)?$/.test(numericText)) {
            return {value: text, numeric: false};
        }
        const number = Number(numericText);
        if (!Number.isFinite(number)) {
            return {value: text, numeric: false};
        }
        if (name.includes('rate') || name.includes('pct') || name.includes('percent')) {
            return {value: number.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '%', numeric: true};
        }
        if (name.includes('amount') || name.includes('balance') || name.includes('actual') || name.includes('budget') || name.includes('expenditure') || name.startsWith('sum_') || name.startsWith('avg_') || name.startsWith('min_') || name.startsWith('max_')) {
            return {value: number.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}), numeric: true};
        }
        if (Number.isInteger(number)) {
            return {value: number.toLocaleString(undefined, {maximumFractionDigits: 0}), numeric: true};
        }
        return {value: number.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 4}), numeric: true};
    }

    function isCodeLikeColumn(name) {
        return name.indexOf('segment') === 0
            || name.endsWith('code')
            || name.endsWith('id')
            || ['periodno', 'fiscalyearid', 'budgetversionid', 'versionid'].includes(name);
    }

    function escapeHtml(value) {
        return value.replace(/[&<>"']/g, function (ch) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[ch];
        });
    }
});
</script>
