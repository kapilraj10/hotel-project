<?php
require_once __DIR__ . '/../auth.php';
admin_logout();
header('Location: /hotel/admin/login.php');
exit;
