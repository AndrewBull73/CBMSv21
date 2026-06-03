<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$csrf = h(csrf_token());
$fiscalYearId = (int) ($fiscalYearId ?? 0);
$currentMappings = is_array($currentMappings ?? null) ? $currentMappings : [];
$availableSegments = is_array($availableSegments ?? null) ? $availableSegments : [];
$definitions = is_array($definitions ?? null) ? $definitions : [];
$inlineErrors = is_array($inlineErrors ?? null) ? $inlineErrors : [];
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-sliders me-2"></i>Strategic Segment Mapping</h3>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="alert alert-info">
        Configure which CBMS segment drives each strategic dimension for the active fiscal year.
        Current fiscal year:
        <strong><?= $fiscalYearId > 0 ? (int) $fiscalYearId : 'Not set' ?></strong>
      </div>

      <?php if ($inlineErrors !== []): ?>
        <div class="alert alert-warning">
          Some mapping rows still need attention. Review the inline messages below and save again.
        </div>
      <?php endif; ?>

      <?php if ($fiscalYearId <= 0): ?>
        <p class="text-muted mb-0">Select a fiscal year in the context picker before editing mappings.</p>
      <?php else: ?>
        <form method="post" action="index.php?route=strategy-config/save-segment-mapping">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">

          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead class="table-light">
                <tr>
                  <th>Strategic Dimension</th>
                  <th>Configured Segment</th>
                  <th>CBMS Dimension</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($definitions as $definition): ?>
                  <?php
                    $code = (string) ($definition['Code'] ?? '');
                    $row = $currentMappings[$code] ?? [];
                    $rawSelectedSegmentNo = (int) ($row['SegmentNo'] ?? 0);
                    $decisionState = (string) ($row['DecisionState'] ?? '');
                    $selectedDecision = $decisionState !== '' ? $decisionState : ($rawSelectedSegmentNo > 0 ? 'MAPPED' : '');
                    $selectedSegmentNo = $selectedDecision === 'MAPPED' ? $rawSelectedSegmentNo : 0;
                    $rowErrors = is_array($inlineErrors[$code] ?? null) ? $inlineErrors[$code] : [];
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($definition['Label'] ?? $code)) ?></div>
                      <div class="small text-muted"><?= h((string) ($definition['HelpText'] ?? '')) ?></div>
                    </td>
                    <td>
                      <div class="mb-2">
                        <select name="mapping[<?= h($code) ?>][Decision]" class="form-select<?= isset($rowErrors['Decision']) ? ' is-invalid' : '' ?>">
                          <option value="">Select decision</option>
                          <option value="MAPPED" <?= $selectedDecision === 'MAPPED' ? 'selected' : '' ?>>Mapped</option>
                          <option value="NOT_MAPPED" <?= $selectedDecision === 'NOT_MAPPED' ? 'selected' : '' ?>>Not mapped</option>
                        </select>
                        <?php if (isset($rowErrors['Decision'])): ?>
                          <div class="invalid-feedback d-block"><?= h((string) $rowErrors['Decision']) ?></div>
                        <?php endif; ?>
                        <div class="form-text">Choose explicitly whether this dimension is sourced from a CBMS segment or maintained directly in the strategic module.</div>
                      </div>
                      <select name="mapping[<?= h($code) ?>][SegmentNo]" class="form-select<?= isset($rowErrors['SegmentNo']) ? ' is-invalid' : '' ?>">
                        <option value="">Select segment if mapped</option>
                        <?php foreach ($availableSegments as $segment): ?>
                          <?php $segmentNo = (int) ($segment['SegmentNo'] ?? 0); ?>
                          <option value="<?= $segmentNo ?>" <?= $selectedSegmentNo === $segmentNo ? 'selected' : '' ?>>
                            <?= h((string) ($segment['SegmentLabel'] ?? '')) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <?php if (isset($rowErrors['SegmentNo'])): ?>
                        <div class="invalid-feedback d-block"><?= h((string) $rowErrors['SegmentNo']) ?></div>
                      <?php endif; ?>
                      <div class="form-text">Pick a segment only when the decision is `Mapped`.</div>
                    </td>
                    <td>
                      <?php
                        $cbmsDimension = '';
                        foreach ($availableSegments as $segment) {
                            if ((int) ($segment['SegmentNo'] ?? 0) === $selectedSegmentNo) {
                                $cbmsDimension = (string) ($segment['CBMSDimension'] ?? '');
                                break;
                            }
                        }
                      ?>
                      <span class="small text-muted"><?= h($cbmsDimension) ?></span>
                    </td>
                    <td>
                      <input
                        type="text"
                        name="mapping[<?= h($code) ?>][Notes]"
                        class="form-control"
                        value="<?= h((string) ($row['Notes'] ?? '')) ?>"
                        placeholder="Optional implementation note">
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Save Mapping</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
