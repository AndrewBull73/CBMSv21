<?php
declare(strict_types=1);
/** @var array $attachments */
/** @var int $submissionId */
if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
$attachments = is_array($attachments ?? null) ? $attachments : [];
$submissionId = (int) ($submissionId ?? 0);
?>
<?php if ($attachments === []): ?>
  <div class="text-muted small">No attachments uploaded yet.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>File</th>
          <th class="text-end">Size</th>
          <th>Uploaded</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($attachments as $attachment): ?>
          <tr>
            <td><?= h((string) ($attachment['OriginalFileName'] ?? '')) ?></td>
            <td class="text-end"><?= h(number_format(((int) ($attachment['FileSizeBytes'] ?? 0)) / 1024, 1)) ?> KB</td>
            <td><?= h((string) ($attachment['CreatedDate'] ?? '')) ?></td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <a href="index.php?route=strategy-submissions/download-attachment&id=<?= (int) ($attachment['StrategicFundingSubmissionAttachmentID'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">Download</a>
                <form method="post" action="index.php?route=strategy-submissions/delete-attachment" class="js-funding-attachment-delete">
                  <input type="hidden" name="id" value="<?= (int) ($attachment['StrategicFundingSubmissionAttachmentID'] ?? 0) ?>">
                  <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
