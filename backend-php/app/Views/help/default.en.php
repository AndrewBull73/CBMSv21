<?php
/**
 * Help: Default / Global Layout + Home Dashboard
 * File: app/Views/help/Default.php
 */
?>
<div class="p-3">
  <h5><i class="bi bi-info-circle me-2"></i>CBMSv21 – General Help</h5>
  <p>
    This page provides a general overview of the <strong>CBMSv21 application layout</strong> 
    and how to use its core navigation features.  
    It also includes help for the <strong>Home dashboard</strong>, which is the first screen you see after login.
  </p>

  <h6><i class="bi bi-layout-text-window-reverse me-2"></i>Top Navigation Bar</h6>
  <ul>
    <li><strong><i class="bi bi-list me-1"></i> Menu</strong> – Opens the left-hand navigation sidebar.</li>
    <li><strong><i class="bi bi-calendar3 me-1"></i> Fiscal Year</strong> – 
        Select the active fiscal year used for data entry and reporting.</li>
    <li><strong><i class="bi bi-layers me-1"></i> Version</strong> – 
        Choose the data version within the selected Fiscal Year (e.g., Draft, Approved, Final).</li>
    <li><strong><i class="bi bi-diagram-3 me-1"></i> Data Scope</strong> – 
        Choose the current Data Object scope for operations (e.g., department or entity).</li>
    <li><strong>🌐 Language</strong> – Switch between available interface languages (English, Français, Español, etc.).</li>
    <li><strong><i class="bi bi-person-circle me-1"></i> User Section</strong> – 
        Shows your username and provides quick links to Account, Logout, and Help.</li>
  </ul>

  <h6><i class="bi bi-list me-2"></i>Sidebar Menu (Navigation)</h6>
  <p>
    The sidebar menu (offcanvas) provides access to the system’s modules and functions.
    The available menu items depend on your assigned <strong>roles and permissions</strong>.
  </p>

  <h6><i class="bi bi-bell me-2"></i>Flash Messages</h6>
  <p>
    Messages (success, warning, error, info) are displayed at the top of the content area.
    They automatically dismiss after a few seconds for informational messages.
  </p>

  <h6><i class="bi bi-file-earmark-text me-2"></i>Main Content Area</h6>
  <p>
    The main section displays the content of the page you are currently working on, such as 
    User List, Rates, System Settings, or Workflow.
  </p>

  <h6><i class="bi bi-house-door me-2"></i>Home Dashboard</h6>
  <ul>
    <li><strong>Welcome message</strong> – Greets the logged-in user by name (or “Guest” if not logged in).</li>
    <li><strong>My Open Tasks</strong> – Shows a summary of tasks assigned to you that are still open.</li>
    <li>The task list is embedded in an <code>iframe</code> for convenience. Use the 
        <strong>View All</strong> button in the card header to open the full workflow list with filters applied.</li>
  </ul>

  <h6><i class="bi bi-question-circle me-2"></i>Help Button</h6>
  <p>
    Clicking the <strong>Help</strong> button (top right of the navbar) will open a 
    context-sensitive help modal.  
    If a dedicated help file exists for the screen you are on (e.g., User List, User Form),
    it will be shown. Otherwise, this default help page appears.
  </p>

  <hr>
  <p class="text-muted small">
    <i class="bi bi-lightbulb me-1"></i>
    Tip: Use the Help button on any page to see guidance tailored to that screen.
  </p>
</div>
