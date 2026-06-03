<?php
declare(strict_types=1);
?>

<div class="p-3">
  <h5 class="mb-3"><i class="bi bi-people me-2"></i>Help – Users List</h5>

  <p class="text-muted">
    This screen allows administrators to manage all system users. From here you can search, filter, edit, and export
    user accounts.
  </p>

  <hr>

  <h6>Main Features</h6>
  <ul>
    <li><strong>Search & Filter:</strong> Use the search box to find users by username, email, or role.</li>
    <li><strong>Pagination:</strong> Results are split into pages (default: 50 per page). Use navigation controls at the bottom.</li>
    <li><strong>Export:</strong> 
      <ul>
        <li><i class="bi bi-file-earmark-excel"></i> Export filtered results to Excel</li>
        <li><i class="bi bi-file-earmark-pdf"></i> Export filtered results to PDF</li>
      </ul>
    </li>
    <li><strong>User Actions:</strong>
      <ul>
        <li><i class="bi bi-pencil-square"></i> Edit user details</li>
        <li><i class="bi bi-unlock"></i> Unlock a locked user</li>
        <li><i class="bi bi-person-x"></i> Delete or disable an account</li>
        <li><i class="bi bi-shield-lock"></i> Assign roles & permissions</li>
      </ul>
    </li>
  </ul>

  <hr>

  <h6>Tips</h6>
  <ul>
    <li>Only administrators with <code>USERS_ADMIN</code> permission can edit or delete users.</li>
    <li>Use filters before exporting to ensure only relevant results are included in Excel/PDF outputs.</li>
    <li>If a user is locked out due to failed logins, use the <strong>Unlock</strong> button to restore access.</li>
  </ul>

  <p class="text-muted small mt-4">
    For additional guidance, see the <strong>System Administration Guide</strong> or contact the system administrator.
  </p>
</div>
