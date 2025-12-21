<?php
// Redirect duplicate create page to the canonical roomcreate.php
require_once __DIR__ . '/../auth.php';
require_admin();
header('Location: roomcreate.php');
exit;
?>