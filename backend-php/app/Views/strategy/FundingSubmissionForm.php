<?php
declare(strict_types=1);
/** @var array|null $submission */
/** @var array $dataObjectOptions */
/** @var array $submissionTypeOptions */
/** @var array $priorityOptions */
/** @var bool $workflowInstalled */
/** @var bool $attachmentsInstalled */
/** @var array $attachments */
/** @var string $scopeDataObjectCode */
/** @var string $scopeDataObjectName */
if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
$submission = is_array($submission ?? null) ? $submission : null;
$attachments = is_array($attachments ?? null) ? $attachments : [];
$currentStatus = strtoupper(trim((string) ($submission['SubmissionStatusCode'] ?? 'DRAFT')));
$scopeDataObjectCode = trim((string) ($scopeDataObjectCode ?? ''));
$scopeDataObjectName = trim((string) ($scopeDataObjectName ?? ''));
$effectiveDataObjectCode = $scopeDataObjectCode !== '' ? $scopeDataObjectCode : trim((string) ($submission['DataObjectCode'] ?? ''));
$effectiveDataObjectName = $scopeDataObjectName !== '' ? $scopeDataObjectName : trim((string) ($submission['DataObjectName'] ?? ''));
?>
<div class="container-fluid py-3">
  <style>
    .container-fluid.py-3 {
      font-size: .95rem;
    }
    .lodgement-shell {
      background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
      border: 1px solid #d9e6f2;
      border-radius: 1.15rem;
      box-shadow: 0 .45rem 1.35rem rgba(43, 63, 87, 0.06);
    }
    .lodgement-hero {
      background: linear-gradient(135deg, #eef6ff 0%, #f8fbff 100%);
      border: 1px solid #dce8f3;
      padding: 1.3rem 1.35rem;
    }
    .lodgement-section-title {
      font-size: .72rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #6c757d;
      font-weight: 700;
      margin-bottom: .85rem;
    }
    .lodgement-meta-chip {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .45rem .75rem;
      border-radius: 999px;
      background: #ffffff;
      border: 1px solid #d7e5f4;
      color: #425466;
      font-size: .84rem;
      font-weight: 600;
    }
    .lodgement-panel {
      background: #fff;
      border: 1px solid #e6edf5;
      border-radius: 1rem;
      padding: 1.05rem 1.1rem;
      box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05);
    }
    .lodgement-attachments-panel {
      background: linear-gradient(180deg, #fffdf6 0%, #ffffff 100%);
      border: 1px solid #efe2b2;
      border-radius: 1rem;
      padding: 1.05rem 1.1rem;
      box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05);
    }
    .lodgement-page-hero {
      background: linear-gradient(135deg, #f3f9ff 0%, #ffffff 100%);
      border: 1px solid #dce8f3;
      border-radius: 1.2rem;
      padding: 1.25rem 1.35rem;
      box-shadow: 0 .45rem 1.35rem rgba(43, 63, 87, 0.06);
    }
    .lodgement-page-eyebrow {
      font-size: .72rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #6c757d;
      font-weight: 700;
      margin-bottom: .45rem;
    }
    .lodgement-page-title {
      font-size: 1.45rem;
      line-height: 1.2;
      letter-spacing: -.02em;
    }
    .lodgement-page-subtext {
      font-size: .9rem;
      max-width: 54rem;
    }
    .lodgement-summary-card {
      background: #fff;
      border: 1px solid #e4ebf2;
      border-radius: 1rem;
      padding: 1.05rem 1.1rem;
      height: 100%;
      box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05);
    }
    .lodgement-summary-label {
      font-size: .7rem;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: #7a8796;
      font-weight: 700;
      margin-bottom: .35rem;
    }
    .lodgement-summary-value {
      font-size: .95rem;
      font-weight: 700;
      color: #203040;
    }
    .lodgement-form .form-control,
    .lodgement-form .form-select {
      font-size: .875rem;
    }
    .lodgement-form textarea.form-control {
      min-height: 8rem;
    }
    .lodgement-form .lodgement-notes-panel textarea.form-control {
      min-height: 12rem;
    }
  </style>

  <div class="lodgement-page-hero d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
    <div>
      <div class="lodgement-page-eyebrow">Funding Lodgement Header</div>
      <h1 class="lodgement-page-title mb-2"><?= $submission ? 'Edit Funding Lodgement' : 'New Funding Lodgement' ?></h1>
      <div class="text-muted lodgement-page-subtext">Capture the request clearly here, establish the DataScope ownership, and shape the package before it moves into formal submission and review.</div>
    </div>
    <a href="index.php?route=strategy-submissions/lodgements" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>

  <?php if (!$workflowInstalled): ?>
    <div class="alert alert-warning">Run <code>create_tblSbFundingSubmission.sql</code> to enable funding submissions.</div>
  <?php endif; ?>
  <?php if ($workflowInstalled && empty($attachmentsInstalled)): ?>
    <div class="alert alert-warning">Run the updated <code>create_tblSbFundingSubmission.sql</code> script to enable funding submission attachments.</div>
  <?php endif; ?>
  <?php if ($scopeDataObjectCode === ''): ?>
    <div class="alert alert-warning">Select a DataScope in the menu header before creating or editing a funding lodgement.</div>
  <?php endif; ?>

  <div class="card shadow-sm lodgement-shell">
    <div class="card-body p-4">
      <form method="post" action="index.php?route=strategy-submissions/save" enctype="multipart/form-data" class="lodgement-form">
        <input type="hidden" name="StrategicFundingSubmissionID" value="<?= (int) ($submission['StrategicFundingSubmissionID'] ?? 0) ?>">
        <input type="hidden" name="DataObjectCode" value="<?= h($effectiveDataObjectCode) ?>">

        <div class="lodgement-hero rounded-4 p-4 mb-4">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
              <div class="lodgement-section-title mb-2">Lodgement Overview</div>
              <div class="lodgement-page-title fs-5 mb-1"><?= $submission ? h((string) ($submission['RequestTitle'] ?? 'Untitled Lodgement')) : 'New Funding Lodgement' ?></div>
              <div class="text-muted lodgement-page-subtext">Use the header to establish ownership, classify the request, and explain the case before detailed funding item review begins.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <span class="lodgement-meta-chip">Status: <?= h($currentStatus) ?></span>
              <?php if ($effectiveDataObjectCode !== ''): ?>
                <span class="lodgement-meta-chip">Scope: <?= h($effectiveDataObjectCode) ?><?php if ($effectiveDataObjectName !== ''): ?> / <?= h($effectiveDataObjectName) ?><?php endif; ?></span>
              <?php endif; ?>
              <?php if (!empty($submission['DataObjectWorkflowStatus'])): ?>
                <span class="lodgement-meta-chip">DataScope Status: <?= h((string) ($submission['DataObjectWorkflowStatus'] ?? '')) ?></span>
              <?php endif; ?>
              <?php if (!empty($submission['PriorityCode'])): ?>
                <span class="lodgement-meta-chip">Priority: <?= h((string) ($submission['PriorityCode'] ?? '')) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-6 col-xl-3">
            <div class="lodgement-summary-card">
              <div class="lodgement-summary-label">System DataScope</div>
              <div class="lodgement-summary-value"><?= $effectiveDataObjectCode !== '' ? h($effectiveDataObjectCode) : 'Not selected' ?></div>
              <?php if ($effectiveDataObjectName !== ''): ?>
                <div class="text-muted small mt-1"><?= h($effectiveDataObjectName) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <div class="lodgement-summary-card">
              <div class="lodgement-summary-label">Submission Type</div>
              <div class="lodgement-summary-value"><?= h((string) ($submission['SubmissionTypeCode'] ?? 'NEW_SPENDING')) ?></div>
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <div class="lodgement-summary-card">
              <div class="lodgement-summary-label">Priority</div>
              <div class="lodgement-summary-value"><?= h((string) ($submission['PriorityCode'] ?? 'MEDIUM')) ?></div>
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <div class="lodgement-summary-card">
              <div class="lodgement-summary-label">Scope Workflow Status</div>
              <div class="lodgement-summary-value"><?= !empty($submission['DataObjectWorkflowStatus']) ? h((string) ($submission['DataObjectWorkflowStatus'] ?? '')) : 'Not set' ?></div>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-xl-8">
            <div class="lodgement-panel mb-4">
              <div class="lodgement-section-title">Core Details</div>
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Request Title</label>
                  <input type="text" name="RequestTitle" class="form-control form-control-lg" required<?= $scopeDataObjectCode === '' ? ' disabled' : '' ?> value="<?= h((string) ($submission['RequestTitle'] ?? '')) ?>" placeholder="Enter a clear title for this funding request">
                </div>
                <div class="col-md-6">
                  <label class="form-label">System DataScope</label>
                  <input type="text" class="form-control" readonly value="<?= h($effectiveDataObjectCode !== '' ? $effectiveDataObjectCode . ($effectiveDataObjectName !== '' ? ' / ' . $effectiveDataObjectName : '') : 'Select a DataScope in the menu header') ?>">
                  <div class="form-text">This lodgement uses the DataScope currently selected in the system header.</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Submission Type</label>
                  <select name="SubmissionTypeCode" class="form-select" required<?= $scopeDataObjectCode === '' ? ' disabled' : '' ?>>
                    <?php foreach ($submissionTypeOptions as $option): ?>
                      <option value="<?= h((string) $option['code']) ?>" <?= ((string) ($submission['SubmissionTypeCode'] ?? 'NEW_SPENDING')) === (string) $option['code'] ? 'selected' : '' ?>>
                        <?= h((string) $option['label']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Priority</label>
                  <select name="PriorityCode" class="form-select" required<?= $scopeDataObjectCode === '' ? ' disabled' : '' ?>>
                    <?php foreach ($priorityOptions as $option): ?>
                      <option value="<?= h((string) $option['code']) ?>" <?= ((string) ($submission['PriorityCode'] ?? 'MEDIUM')) === (string) $option['code'] ? 'selected' : '' ?>>
                        <?= h((string) $option['label']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="lodgement-panel lodgement-notes-panel">
              <div class="lodgement-section-title">Narrative</div>
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Request Notes</label>
                  <textarea name="RequestNotes" class="form-control" rows="7"<?= $scopeDataObjectCode === '' ? ' disabled' : '' ?> placeholder="Summarize the rationale, context, and intended result of this lodgement."><?= h((string) ($submission['RequestNotes'] ?? '')) ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-4">
            <div class="lodgement-attachments-panel">
              <div class="lodgement-section-title">Attachments</div>
              <p class="text-muted mb-3">Upload supporting files as part of the lodgement package. Files upload immediately after selection.</p>
              <label class="form-label">Add Attachment Files</label>
              <input type="file" name="SubmissionAttachments[]" id="SubmissionAttachments" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg"<?= empty($attachmentsInstalled) || $scopeDataObjectCode === '' ? ' disabled' : '' ?>>
              <div class="form-text">Allowed types: PDF, Word, Excel, CSV, TXT, PNG, JPG. Maximum 10 MB per file.</div>
              <div id="SubmissionAttachmentStatus" class="small mt-2 text-muted"></div>

              <?php if ($submission !== null): ?>
                <hr class="my-4">
                <div class="fw-semibold mb-2">Existing Attachments</div>
                <div id="FundingSubmissionAttachmentList">
                  <?php
                  $submissionId = (int) ($submission['StrategicFundingSubmissionID'] ?? 0);
                  require __DIR__ . '/_FundingSubmissionAttachmentList.php';
                  ?>
                </div>
              <?php else: ?>
                <div class="small text-muted mt-4">Save the lodgement once and you will be able to see the attachment list here.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary"<?= $scopeDataObjectCode === '' ? ' disabled' : '' ?>>Save Lodgement</button>
          <a href="index.php?route=strategy-submissions/lodgements" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php if (!empty($attachmentsInstalled)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[action="index.php?route=strategy-submissions/save"]');
    const input = document.getElementById('SubmissionAttachments');
    const status = document.getElementById('SubmissionAttachmentStatus');
    const attachmentList = document.getElementById('FundingSubmissionAttachmentList');
    if (!form || !input || !status) {
        return;
    }

    const setStatus = function (message, isError) {
        status.textContent = message;
        status.classList.toggle('text-danger', !!isError);
        status.classList.toggle('text-muted', !isError);
    };

    const wireDeleteForms = function () {
        if (!attachmentList) {
            return;
        }

        const deleteForms = attachmentList.querySelectorAll('.js-funding-attachment-delete');
        deleteForms.forEach(function (deleteForm) {
            deleteForm.addEventListener('submit', function (event) {
                event.preventDefault();
                if (typeof window.confirm === 'function' && !window.confirm('Remove this attachment?')) {
                    return;
                }

                const deleteData = new FormData(deleteForm);
                setStatus('Removing attachment...', false);

                fetch(deleteForm.action, {
                    method: 'POST',
                    body: deleteData,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok || !data.ok) {
                                throw new Error(data.error || 'Attachment delete failed.');
                            }
                            return data;
                        });
                    })
                    .then(function (data) {
                        if (attachmentList && typeof data.attachments_html === 'string') {
                            attachmentList.innerHTML = data.attachments_html;
                            wireDeleteForms();
                        }
                        setStatus('Attachment removed.', false);
                    })
                    .catch(function (error) {
                        setStatus(error.message || 'Attachment delete failed.', true);
                    });
            });
        });
    };

    input.addEventListener('change', function () {
        if (!input.files || input.files.length === 0) {
            return;
        }

        const uploadData = new FormData();
        const fields = [
            'StrategicFundingSubmissionID',
            'RequestTitle',
            'DataObjectCode',
            'SubmissionTypeCode',
            'PriorityCode',
            'RequestNotes'
        ];

        fields.forEach(function (name) {
            const field = form.querySelector('[name="' + name + '"]');
            if (field) {
                uploadData.append(name, field.value || '');
            }
        });

        Array.from(input.files).forEach(function (file) {
            uploadData.append('SubmissionAttachments[]', file);
        });

        input.disabled = true;
        setStatus('Uploading attachment(s)...', false);

        fetch('index.php?route=strategy-submissions/upload-attachment', {
            method: 'POST',
            body: uploadData,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.ok) {
                        throw new Error(data.error || 'Attachment upload failed.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                const idField = form.querySelector('[name="StrategicFundingSubmissionID"]');
                if (idField) {
                    idField.value = String(data.submission_id || 0);
                }

                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('route', 'strategy-submissions/form');
                currentUrl.searchParams.set('id', String(data.submission_id || 0));
                window.history.replaceState({}, '', currentUrl.toString());

                if (attachmentList && typeof data.attachments_html === 'string') {
                    attachmentList.innerHTML = data.attachments_html;
                    wireDeleteForms();
                }

                input.disabled = false;
                input.value = '';
                setStatus('Upload complete.', false);
            })
            .catch(function (error) {
                input.disabled = false;
                input.value = '';
                setStatus(error.message || 'Attachment upload failed.', true);
            });
    });

    wireDeleteForms();
});
</script>
<?php endif; ?>
