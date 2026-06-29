<?php
declare(strict_types=1);
?>
<div class="container-fluid p-3">
  <h5><i class="bi bi-person me-2"></i>User Form Help</h5>
  <p>
    This page allows administrators to <strong>create</strong> or <strong>edit user accounts</strong>.
    The form is divided into multiple tabs depending on whether the user already exists.
  </p>

  <h6><i class="bi bi-shield-check me-2"></i>Access Readiness</h6>
  <p>
    Existing users show an access readiness check near the top of the screen. A user is ready when they have
    at least one assigned role and effective Data Object Code access for the current fiscal year.
  </p>

  <h6><i class="bi bi-pencil-square me-2"></i>Edit Tab</h6>
  <ul>
    <li><strong>Username</strong> – required, unique login identifier.</li>
    <li><strong>Email</strong> – optional, must be valid if entered.</li>
    <li><strong>Onboarding</strong> – when creating a user, choose whether to queue a welcome invite email or set a temporary password manually. The invite email uses the <code>USER_WELCOME_INVITE</code> Email Template.</li>
    <li><strong>First/Last Name</strong> – personal details of the user.</li>
    <li><strong>Display Name</strong> – shown in application UIs.</li>
    <li><strong>Phone / Department / Job Title</strong> – additional optional details.</li>
    <li><strong>Checkboxes</strong>:
      <ul>
        <li><em>Enabled</em> – toggle whether account is active.</li>
        <li><em>Force Password Reset</em> – require reset at next login.</li>
        <li><em>Must Change Password</em> – similar, but enforced immediately.</li>
      </ul>
    </li>
    <li><strong>Notes</strong> – free-text comments.</li>
    <li><strong>Save</strong> – remember that changes only apply once you press save.</li>
  </ul>

  <h6><i class="bi bi-card-list me-2"></i>Details Tab</h6>
  <p>
    Displays read-only system information about the user:
    ID, login history, failed attempts, and audit metadata (created/updated).
  </p>

  <h6><i class="bi bi-people me-2"></i>Roles Tab</h6>
  <p>
    Assign roles to control what the user can access. Each role maps to one or more permissions.
    Tick the checkboxes and press <em>Save Roles</em> to apply changes.
  </p>

  <h6><i class="bi bi-shield-lock me-2"></i>Data Object Access Tab</h6>
  <p>
    Review the user's direct Data Object Code grants and the effective access inherited from included child codes
    for the current fiscal year.
  </p>

  <h6><i class="bi bi-shield-lock me-2"></i>Account & Access Tab</h6>
  <p>
    Shows embedded account access details, including password reset and lock status.
  </p>

  <hr>
  <p class="text-muted small">
    <i class="bi bi-info-circle me-1"></i>
    Tip: Use the <strong>Export PDF</strong> button in the header to save a snapshot of the user’s profile.
  </p>
</div>
