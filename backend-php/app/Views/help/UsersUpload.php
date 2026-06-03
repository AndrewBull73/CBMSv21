<?php
/**
 * Help: Users Upload
 * File: app/Views/help/UsersUpload.php
 */
?>
<div class="p-3">
  <h5><i class="bi bi-upload me-2"></i>User Upload Help</h5>
  <p>
    This page allows administrators to <strong>bulk import users</strong> into the system 
    using an Excel spreadsheet.
  </p>

  <h6><i class="bi bi-file-earmark-excel me-2"></i>Steps to upload users:</h6>
  <ol>
    <li>
      <strong>Download the template</strong>:
      Use the <em>Download Template</em> button to get the correct Excel format.
    </li>
    <li>
      <strong>Fill in user details</strong>:
      Open the Excel file and enter usernames, email addresses, and other required fields.
    </li>
    <li>
      <strong>Save the file</strong>:
      Ensure the file is saved in <code>.xlsx</code> or <code>.xls</code> format.
    </li>
    <li>
      <strong>Upload</strong>:
      Use the <em>Select Excel File</em> field to choose your file and click
      <em>Upload</em>.
    </li>
  </ol>

  <h6><i class="bi bi-info-circle me-2"></i>Notes:</h6>
  <ul>
    <li>The file must match the required template structure.</li>
    <li>Mandatory fields (such as Username and Email) must be completed.</li>
    <li>Validation errors will be shown if any required data is missing or invalid.</li>
    <li>Uploaded users will appear in the <em>User List</em> after processing.</li>
  </ul>

  <p class="mt-3">
    <i class="bi bi-arrow-left-circle me-1"></i>
    Use the <strong>Back</strong> button to return to the User List without uploading.
  </p>
</div>
