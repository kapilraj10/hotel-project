<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) {
	// prevent deleting a tables_info row that is linked to a room
	$linked = $pdo->prepare('SELECT COUNT(*) AS cnt FROM rooms WHERE tables_info_id = ?');
	$linked->execute([$id]); $l = $linked->fetch();
	if ($l && $l['cnt'] > 0) {
		// redirect back with an error flag so UI can show a message
		header('Location: tables.php?error=linked'); exit;
	}
	$stmt = $pdo->prepare('DELETE FROM tables_info WHERE id=?'); $stmt->execute([$id]);
}
header('Location: tables.php'); exit;
