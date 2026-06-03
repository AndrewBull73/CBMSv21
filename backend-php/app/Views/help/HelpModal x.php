<?php
declare(strict_types=1);

/** @var string|null $route */
/** @var string|null $viewFile */

// Safety defaults
$route    = $route    ?? 'unknown';
$viewFile = $viewFile ?? __DIR__ . '/Default.php';

// Format a friendly title (e.g. "users/list" → "Users / List")
$title = ucwords(str_replace(['-', '_'], ' ', str_replace('/', ' / ', $route)));
?>
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="helpModalLabel">
          <i class="bi bi-question-circle me-2"></i>
          Help – <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if (is_file($viewFile)): ?>
          <?php require $viewFile; ?>
        <?php else: ?>
          <p class="text-muted">No help is available for this screen yet.</p>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-1"></i> Close x
        </button>
        <button type="button" class="btn btn-outline-primary" onclick="printHelpContent()">
          <i class="bi bi-printer me-1"></i> Print
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function printHelpContent() {
  const modalBody = document.querySelector('#helpModal .modal-body');
  if (!modalBody) return;

  const printWindow = window.open('', '_blank', 'width=900,height=650');
  printWindow.document.write(`
    <html>
      <head>
        <title>Help</title>
        <link href="assets/css/bootstrap.min.css" rel="stylesheet">
        <style>
          body { font-family: Arial, sans-serif; padding: 20px; }
        </style>
      </head>
      <body>
        ${modalBody.innerHTML}
      </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
}
</script>
