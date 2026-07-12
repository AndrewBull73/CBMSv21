<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$scenario = is_array($scenario ?? null) ? $scenario : [];
$activeRun = is_array($activeRun ?? null) ? $activeRun : null;
$previewRun = is_array($previewRun ?? null) ? $previewRun : null;
$testData = is_array($testData ?? null) ? $testData : [];
$runContext = is_array($runContext ?? null) ? $runContext : [];
$recentRuns = is_array($recentRuns ?? null) ? $recentRuns : [];
$resolvedVerificationQueries = is_array($resolvedVerificationQueries ?? null) ? $resolvedVerificationQueries : [];
$storageReady = (bool) ($storageReady ?? false);
$createTableScript = (string) ($createTableScript ?? '');
$createAttachmentTableScript = (string) ($createAttachmentTableScript ?? 'backend-php/config/sql/create_tblScreenTestRunAttachment.sql');
$targetUrl = (string) ($targetUrl ?? 'index.php?route=home/index');
$captureEnabled = (bool) ($captureEnabled ?? false);
$captureStorageReady = (bool) ($captureStorageReady ?? false);
$pendingAttachments = array_values(is_array($pendingAttachments ?? null) ? $pendingAttachments : []);
$scenarioId = (string) ($scenario['id'] ?? '');
$scenarioTitle = (string) ($scenario['title'] ?? $scenarioId);
$baselineContext = is_array($scenario['baseline_context'] ?? null) ? $scenario['baseline_context'] : [];
$prerequisites = array_values(is_array($scenario['prerequisites'] ?? null) ? $scenario['prerequisites'] : []);
$steps = array_values(is_array($scenario['steps'] ?? null) ? $scenario['steps'] : []);
$expectedVisible = array_values(is_array($scenario['expected_visible'] ?? null) ? $scenario['expected_visible'] : []);
$expectedData = array_values(is_array($scenario['expected_data'] ?? null) ? $scenario['expected_data'] : []);
$resetScripts = array_values(is_array($scenario['reset_scripts'] ?? null) ? $scenario['reset_scripts'] : []);
$attemptNo = (int) ($activeRun['attempt_no'] ?? $previewRun['attempt_no'] ?? 0);
$startedAt = trim((string) ($activeRun['started_at'] ?? ''));
$durationLabel = '';
if ($startedAt !== '') {
    $from = strtotime($startedAt . ' UTC') ?: strtotime($startedAt);
    $to = time();
    if ($from !== false && $to >= $from) {
        $seconds = $to - $from;
        $minutes = intdiv($seconds, 60);
        $hours = intdiv($minutes, 60);
        if ($hours > 0) {
            $durationLabel = $hours . 'h ' . ($minutes % 60) . 'm';
        } elseif ($minutes > 0) {
            $durationLabel = $minutes . 'm ' . ($seconds % 60) . 's';
        } else {
            $durationLabel = $seconds . 's';
        }
    }
}
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-clipboard-check me-2"></i><?= h($scenarioTitle) ?></h3>
    </div>
    <div class="card-body">

      <div class="alert alert-info">
        <?= __t('screen_tests_runner_intro') ?>
      </div>

      <?php
      $testingQuickLinksMode = 'tester';
      require __DIR__ . '/_TestingQuickLinks.php';
      $testingHelperTitle = 'How to run this test script';
      $testingHelperItems = [
          'Review the prerequisites, test data, steps, and expected results before opening the target screen.',
          'Use <strong>Start Test Run</strong> before recording a result so the attempt number, duration, and evidence are captured correctly.',
          'Save <strong>Passed</strong>, <strong>Failed</strong>, or <strong>Blocked</strong> with clear notes and a defect reference when follow-up is needed.',
      ];
      require __DIR__ . '/_TestingHelperInstructions.php';
      ?>

      <div class="d-flex gap-2 flex-wrap mb-3">
        <a href="index.php?route=screen-tests/scenarios" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrow-left me-1"></i><?= __t('screen_tests_back_to_catalogue') ?>
        </a>
        <a href="index.php?route=screen-tests/summary&scenario_code=<?= urlencode($scenarioId) ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-table me-1"></i><?= __t('screen_tests_view_results') ?>
        </a>
        <a href="<?= h($targetUrl) ?>" class="btn btn-sm btn-primary"><?= __t('screen_tests_open_target_screen') ?></a>
        <a href="<?= h($targetUrl) ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"><?= __t('screen_tests_open_target_new_window') ?></a>
      </div>

      <?php if (!$storageReady): ?>
        <div class="alert alert-warning">
          <div class="fw-semibold mb-1"><?= __t('screen_tests_storage_session_only') ?></div>
          <div class="small"><?= __t('screen_tests_storage_session_only_help', ['script' => $createTableScript]) ?></div>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <div class="col-lg-7">
          <div class="card shadow-sm mb-3">
            <div class="card-header">
              <h5 class="mb-0"><?= __t('screen_tests_scenario_details') ?></h5>
            </div>
            <div class="card-body">
              <div class="small text-muted mb-1"><?= __t('screen_tests_purpose_label') ?></div>
              <div class="mb-3"><?= h((string) ($scenario['purpose'] ?? '')) ?></div>

              <div class="row g-2 small mb-3">
                <div class="col-sm-4">
                  <div class="text-muted"><?= __t('screen_tests_filter_module') ?></div>
                  <div class="fw-semibold"><?= h((string) ($scenario['module'] ?? '')) ?></div>
                </div>
                <div class="col-sm-4">
                  <div class="text-muted"><?= __t('screen_tests_target_screen') ?></div>
                  <div class="fw-semibold"><?= h((string) ($scenario['target_label'] ?? $scenario['target_route'] ?? '')) ?></div>
                </div>
                <div class="col-sm-4">
                  <div class="text-muted"><?= __t('training_attempt_label') ?></div>
                  <div class="fw-semibold"><?= h((string) max(0, $attemptNo)) ?></div>
                </div>
              </div>

              <?php if ($baselineContext !== []): ?>
                <div class="mb-3">
                  <div class="fw-semibold mb-2"><?= __t('screen_tests_baseline_context') ?></div>
                  <div class="row g-2 small">
                    <?php foreach ($baselineContext as $label => $value): ?>
                      <div class="col-sm-6">
                        <div class="text-muted"><?= h((string) $label) ?></div>
                        <div><?= h((string) $value) ?></div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($prerequisites !== []): ?>
                <div class="mb-3">
                  <div class="fw-semibold mb-2"><?= __t('screen_tests_prerequisites') ?></div>
                  <ul class="mb-0 ps-3">
                    <?php foreach ($prerequisites as $item): ?>
                      <li><?= h((string) $item) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <div class="mb-3">
                <div class="fw-semibold mb-2"><?= __t('screen_tests_test_data') ?></div>
                <?php if ($testData === []): ?>
                  <div class="small text-muted"><?= __t('screen_tests_no_test_data') ?></div>
                <?php else: ?>
                  <div class="row g-2 small">
                    <?php foreach ($testData as $sampleKey => $sampleVal): ?>
                      <?php $sampleLabel = trim((string) preg_replace('/(?<!^)([A-Z])/', ' $1', (string) $sampleKey)); ?>
                      <div class="col-sm-6">
                        <div class="text-muted"><?= h($sampleLabel) ?></div>
                        <div><code><?= h((string) $sampleVal) ?></code></div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <div class="fw-semibold mb-2"><?= __t('screen_tests_steps') ?></div>
                <div class="table-responsive">
                  <table class="table align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th><?= __t('training_step_label') ?></th>
                        <th><?= __t('training_instruction_label') ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($steps as $step): ?>
                        <tr>
                          <td class="fw-semibold"><?= h((string) ($step['number'] ?? '')) ?></td>
                          <td>
                            <div class="fw-semibold"><?= h((string) ($step['title'] ?? '')) ?></div>
                            <div class="small text-muted"><?= h((string) ($step['instruction'] ?? '')) ?></div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <?php if ($expectedVisible !== []): ?>
                <div class="mb-3">
                  <div class="fw-semibold mb-2"><?= __t('screen_tests_expected_visible') ?></div>
                  <ul class="mb-0 ps-3">
                    <?php foreach ($expectedVisible as $item): ?>
                      <li><?= h((string) $item) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <?php if ($expectedData !== []): ?>
                <div class="mb-3">
                  <div class="fw-semibold mb-2"><?= __t('screen_tests_expected_data') ?></div>
                  <ul class="mb-0 ps-3">
                    <?php foreach ($expectedData as $item): ?>
                      <li><?= h((string) $item) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <?php if ($resetScripts !== []): ?>
                <div class="mb-3">
                  <div class="fw-semibold mb-2"><?= __t('screen_tests_reset_scripts') ?></div>
                  <ul class="mb-0 ps-3">
                    <?php foreach ($resetScripts as $item): ?>
                      <li>
                        <code><?= h((string) ($item['path'] ?? '')) ?></code>
                        <?php if (trim((string) ($item['note'] ?? '')) !== ''): ?>
                          <span class="text-muted">- <?= h((string) ($item['note'] ?? '')) ?></span>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <div>
                <div class="fw-semibold mb-2"><?= __t('screen_tests_verification_queries') ?></div>
                <?php if ($resolvedVerificationQueries === []): ?>
                  <div class="small text-muted"><?= __t('screen_tests_no_verification_query') ?></div>
                <?php else: ?>
                  <?php foreach ($resolvedVerificationQueries as $query): ?>
                    <pre class="bg-light border rounded p-2 small mb-2"><code><?= h((string) $query) ?></code></pre>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card shadow-sm mb-3">
            <div class="card-header">
              <h5 class="mb-0"><?= __t('screen_tests_active_run') ?></h5>
            </div>
            <div class="card-body">
              <?php if ($activeRun === null): ?>
                <div class="small text-muted mb-3"><?= __t('screen_tests_no_active_run') ?></div>
                <form method="post" action="index.php?route=screen-tests/start">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                  <button type="submit" class="btn btn-sm btn-primary"><?= __t('screen_tests_start_run') ?></button>
                </form>
              <?php else: ?>
                <div class="small text-muted mb-1"><?= __t('training_attempt_label') ?></div>
                <div class="fw-semibold mb-3"><?= h((string) max(0, $attemptNo)) ?></div>

                <div class="small text-muted mb-1"><?= __t('screen_tests_started_at') ?></div>
                <div class="fw-semibold mb-3"><?= h($startedAt !== '' ? $startedAt : 'n/a') ?></div>

                <div class="small text-muted mb-1"><?= __t('screen_tests_duration') ?></div>
                <div class="fw-semibold mb-3"><?= h($durationLabel !== '' ? $durationLabel : '0s') ?></div>

                <form method="post" action="index.php?route=screen-tests/start">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary"><?= __t('screen_tests_restart_run') ?></button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <div class="card shadow-sm mb-3">
            <div class="card-header">
              <h5 class="mb-0"><?= __t('screen_tests_record_result') ?></h5>
            </div>
            <div class="card-body">
              <?php if ($activeRun === null): ?>
                <div class="small text-muted"><?= __t('screen_tests_start_run_before_saving') ?></div>
              <?php else: ?>
                <?php if ($captureEnabled && $captureStorageReady): ?>
                  <div class="border rounded p-3 bg-light mb-3">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                      <div class="fw-semibold"><?= __t('screen_tests_capture_section_title') ?></div>
                      <div class="d-flex gap-2 flex-wrap">
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-primary"
                          id="screenTestCaptureButton"
                          data-scenario-id="<?= h($scenarioId) ?>"
                          data-csrf="<?= h(csrf_token()) ?>"
                        >
                          <i class="bi bi-camera me-1"></i><?= __t('screen_tests_capture_button') ?>
                        </button>
                        <label class="btn btn-sm btn-outline-secondary mb-0">
                          <i class="bi bi-paperclip me-1"></i><?= __t('screen_tests_attachment_button') ?>
                          <input
                            type="file"
                            id="screenTestAttachmentInput"
                            class="d-none"
                            accept=".png,.jpg,.jpeg,.webp,.pdf,.txt"
                            data-scenario-id="<?= h($scenarioId) ?>"
                            data-csrf="<?= h(csrf_token()) ?>"
                          >
                        </label>
                      </div>
                    </div>
                    <div class="small text-muted mb-2"><?= __t('screen_tests_capture_help') ?></div>
                    <div class="small text-muted mb-2"><?= __t('screen_tests_attachment_help') ?></div>
                    <div id="screenTestCaptureStatus" class="small text-muted mb-2"></div>
                    <div id="screenTestCaptureList">
                      <?php if ($pendingAttachments === []): ?>
                        <div class="small text-muted" data-empty-state="1"><?= __t('screen_tests_capture_none_yet') ?></div>
                      <?php else: ?>
                        <?php foreach ($pendingAttachments as $attachment): ?>
                          <div class="small border rounded px-2 py-1 mb-2 bg-white">
                            <div class="fw-semibold"><?= h((string) ($attachment['name'] ?? 'Screenshot')) ?></div>
                            <div class="text-muted"><?= h((string) ($attachment['captured_at'] ?? '')) ?><?php if (trim((string) ($attachment['size_label'] ?? '')) !== ''): ?> · <?= h((string) ($attachment['size_label'] ?? '')) ?><?php endif; ?></div>
                          </div>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php elseif ($captureEnabled && !$captureStorageReady): ?>
                  <div class="small text-muted mb-1"><?= __t('screen_tests_capture_storage_required') ?></div>
                  <div class="small text-muted mb-3"><?= __t('screen_tests_attachment_storage_help', ['script' => $createAttachmentTableScript]) ?></div>
                <?php endif; ?>

                <form method="post" action="index.php?route=screen-tests/save-result">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">

                  <div class="mb-2">
                    <label for="screenTestResult" class="form-label"><?= __t('screen_tests_result_label') ?></label>
                    <select id="screenTestResult" name="run_result" class="form-select">
                      <option value="passed"><?= __t('screen_tests_result_passed') ?></option>
                      <option value="failed"><?= __t('screen_tests_result_failed') ?></option>
                      <option value="blocked"><?= __t('screen_tests_result_blocked') ?></option>
                    </select>
                  </div>

                  <div class="mb-2">
                    <label for="screenTestVerification" class="form-label"><?= __t('screen_tests_verification_label') ?></label>
                    <select id="screenTestVerification" name="verification_status" class="form-select">
                      <option value="not_run"><?= __t('screen_tests_verification_not_run') ?></option>
                      <option value="manual_pass"><?= __t('screen_tests_verification_passed') ?></option>
                      <option value="manual_fail"><?= __t('screen_tests_verification_failed') ?></option>
                    </select>
                  </div>

                  <div class="mb-2">
                    <label for="screenTestOutcomeSummary" class="form-label"><?= __t('screen_tests_outcome_summary') ?></label>
                    <input type="text" id="screenTestOutcomeSummary" name="outcome_summary" class="form-control" maxlength="250">
                  </div>

                  <div class="mb-2">
                    <label for="screenTestDefectRef" class="form-label"><?= __t('screen_tests_defect_reference') ?></label>
                    <input type="text" id="screenTestDefectRef" name="defect_reference" class="form-control" maxlength="120">
                  </div>

                  <div class="mb-3">
                    <label for="screenTestNotes" class="form-label"><?= __t('screen_tests_tester_notes') ?></label>
                    <textarea id="screenTestNotes" name="tester_notes" rows="4" class="form-control"></textarea>
                  </div>

                  <button type="submit" class="btn btn-sm btn-success"><?= __t('screen_tests_save_result') ?></button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <div class="card shadow-sm">
            <div class="card-header">
              <h5 class="mb-0"><?= __t('screen_tests_recent_results_title') ?></h5>
            </div>
            <div class="card-body">
              <?php if ($recentRuns === []): ?>
                <div class="small text-muted"><?= __t('screen_tests_no_recent_runs') ?></div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th><?= __t('training_attempt_label') ?></th>
                        <th><?= __t('screen_tests_result_label') ?></th>
                        <th><?= __t('screen_tests_verification_label') ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($recentRuns as $row): ?>
                        <?php
                        $result = strtolower((string) ($row['RunResult'] ?? ''));
                        $verification = strtolower((string) ($row['VerificationStatus'] ?? ''));
                        $resultLabel = match ($result) {
                            'passed' => __t('screen_tests_result_passed'),
                            'failed' => __t('screen_tests_result_failed'),
                            'blocked' => __t('screen_tests_result_blocked'),
                            default => __t('screen_tests_status_not_run'),
                        };
                        $verificationLabel = match ($verification) {
                            'manual_pass' => __t('screen_tests_verification_passed'),
                            'manual_fail' => __t('screen_tests_verification_failed'),
                            default => __t('screen_tests_verification_not_run'),
                        };
                        ?>
                        <tr>
                          <td><?= h((string) ($row['AttemptNo'] ?? '')) ?></td>
                          <td><?= h($resultLabel) ?></td>
                          <td><?= h($verificationLabel) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($activeRun !== null && $captureEnabled && $captureStorageReady): ?>
<script>
(function () {
  const button = document.getElementById('screenTestCaptureButton');
  const fileInput = document.getElementById('screenTestAttachmentInput');
  const statusNode = document.getElementById('screenTestCaptureStatus');
  const listNode = document.getElementById('screenTestCaptureList');
  if (!button || !fileInput || !statusNode || !listNode) {
    return;
  }

  const mediaDevices = navigator.mediaDevices;
  if (!mediaDevices || typeof mediaDevices.getDisplayMedia !== 'function') {
    statusNode.textContent = <?= json_encode(__t('screen_tests_capture_browser_unsupported')) ?>;
    button.disabled = true;
    return;
  }

  const setStatus = function (message, isError) {
    statusNode.textContent = message || '';
    statusNode.className = isError ? 'small text-danger mb-2' : 'small text-muted mb-2';
  };

  const renderAttachments = function (attachments) {
    listNode.innerHTML = '';
    if (!Array.isArray(attachments) || attachments.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'small text-muted';
      empty.textContent = <?= json_encode(__t('screen_tests_capture_none_yet')) ?>;
      listNode.appendChild(empty);
      return;
    }

    attachments.forEach(function (attachment) {
      const wrapper = document.createElement('div');
      wrapper.className = 'small border rounded px-2 py-1 mb-2 bg-white';

      const title = document.createElement('div');
      title.className = 'fw-semibold';
      title.textContent = String(attachment.name || 'Screenshot');

      const meta = document.createElement('div');
      meta.className = 'text-muted';
      const metaParts = [];
      if (attachment.captured_at) {
        metaParts.push(String(attachment.captured_at));
      }
      if (attachment.size_label) {
        metaParts.push(String(attachment.size_label));
      }
      meta.textContent = metaParts.join(' · ');

      wrapper.appendChild(title);
      wrapper.appendChild(meta);
      listNode.appendChild(wrapper);
    });
  };

  const captureFrame = async function () {
    const stream = await mediaDevices.getDisplayMedia({ video: true, audio: false });
    try {
      const video = document.createElement('video');
      video.style.position = 'fixed';
      video.style.left = '-99999px';
      video.muted = true;
      video.srcObject = stream;
      document.body.appendChild(video);

      await video.play();
      await new Promise(function (resolve) {
        if (video.readyState >= 2) {
          resolve();
          return;
        }
        video.onloadedmetadata = function () { resolve(); };
      });

      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth || 1280;
      canvas.height = video.videoHeight || 720;
      const context = canvas.getContext('2d');
      context.drawImage(video, 0, 0, canvas.width, canvas.height);

      const blob = await new Promise(function (resolve, reject) {
        canvas.toBlob(function (value) {
          if (value) {
            resolve(value);
          } else {
            reject(new Error('Capture failed.'));
          }
        }, 'image/png');
      });

      video.pause();
      video.remove();
      return blob;
    } finally {
      stream.getTracks().forEach(function (track) { track.stop(); });
    }
  };

  const uploadAttachmentFile = async function (file) {
    const formData = new FormData();
    formData.append('_csrf', fileInput.getAttribute('data-csrf') || '');
    formData.append('scenario_id', fileInput.getAttribute('data-scenario-id') || '');
    formData.append('attachment', file);

    const response = await fetch('index.php?route=screen-tests/upload-attachment', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    const payload = await response.json().catch(function () { return {}; });
    if (!response.ok || !payload.ok) {
      throw new Error(String(payload.message || <?= json_encode(__t('screen_tests_attachment_upload_failed')) ?>));
    }

    renderAttachments(payload.attachments || []);
    setStatus(String(payload.message || <?= json_encode(__t('screen_tests_attachment_saved')) ?>), false);
  };

  button.addEventListener('click', async function () {
    button.disabled = true;
    fileInput.disabled = true;
    setStatus(<?= json_encode(__t('screen_tests_capture_in_progress')) ?>, false);

    try {
      const blob = await captureFrame();
      const formData = new FormData();
      formData.append('_csrf', button.getAttribute('data-csrf') || '');
      formData.append('scenario_id', button.getAttribute('data-scenario-id') || '');
      formData.append('screenshot', blob, 'screen-test-capture.png');

      const response = await fetch('index.php?route=screen-tests/capture-screenshot', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const payload = await response.json().catch(function () { return {}; });
      if (!response.ok || !payload.ok) {
        throw new Error(String(payload.message || <?= json_encode(__t('screen_tests_capture_upload_failed')) ?>));
      }

      renderAttachments(payload.attachments || []);
      setStatus(String(payload.message || <?= json_encode(__t('screen_tests_capture_saved')) ?>), false);
    } catch (error) {
      const message = error && error.message ? error.message : <?= json_encode(__t('screen_tests_capture_upload_failed')) ?>;
      setStatus(String(message), true);
    } finally {
      button.disabled = false;
      fileInput.disabled = false;
    }
  });

  fileInput.addEventListener('change', async function () {
    const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    if (!file) {
      return;
    }

    button.disabled = true;
    fileInput.disabled = true;
    setStatus(<?= json_encode(__t('screen_tests_attachment_uploading')) ?>, false);

    try {
      await uploadAttachmentFile(file);
    } catch (error) {
      const message = error && error.message ? error.message : <?= json_encode(__t('screen_tests_attachment_upload_failed')) ?>;
      setStatus(String(message), true);
    } finally {
      fileInput.value = '';
      button.disabled = false;
      fileInput.disabled = false;
    }
  });
})();
</script>
<?php endif; ?>
