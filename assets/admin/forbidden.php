<?php
require_once __DIR__ . '/../auth.php';
require_admin();
$page_title = 'Forbidden'; include __DIR__ . '/admin_header.php';
?>
<div class="alert alert-danger">You do not have permission to access this page.</div>
<a class="btn btn-secondary" href="dashboard.php">Back to Dashboard</a>
<?php include __DIR__ . '/admin_footer.php';
