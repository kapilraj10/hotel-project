<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db(); $id = $_GET['id'] ?? null; if ($id) { $stmt=$pdo->prepare('DELETE FROM items WHERE id=?'); $stmt->execute([$id]); }
header('Location: items.php'); exit;
