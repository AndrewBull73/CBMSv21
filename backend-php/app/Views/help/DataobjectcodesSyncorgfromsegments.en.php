<?php declare(strict_types=1);
?>

<div class="help-content">
  <div class="small text-uppercase text-muted fw-semibold mb-1">Helper Instructions</div>
  <h5 class="mb-2">Load Data Object Codes from Segment Values</h5>

  <p class="text-muted">
    Use this screen to preview and load organisational Data Object Codes from the current fiscal year segment values.
  </p>

  <ul class="mb-3">
    <li>Confirm the root code and root name. For the whole-of-government root, use <code>GOV</code> and <code>Government</code> unless your configuration uses a different label.</li>
    <li>Review the type mapping. Data object types with <code>SegmentNo</code> set are loaded from matching segment values. The type with blank <code>SegmentNo</code> is used as the root level.</li>
    <li>Check rejected rows before loading. Rejections usually mean a missing code, duplicate code, missing parent link, or a parent Data Object Code that is not available.</li>
    <li>Choose <strong>Load DataObjectCodes</strong> only after the preview counts and rejected rows look correct.</li>
  </ul>

  <div class="alert alert-light border small mb-0">
    Loading updates <code>tblDataObjectCodes</code> for the active fiscal year and rebuilds <code>tblDataObjectTree</code>. It does not reload segment values; resolve segment parent links first if parent relationships are missing.
  </div>
</div>
