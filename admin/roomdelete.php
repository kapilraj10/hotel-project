<?php
// assets/admin/roomdelete.php - handle deletion via POST
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php'; session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: rooms.php'); exit; }
$token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'],$token)) { die('Invalid CSRF'); }
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id) {
  $pdo = get_rest_db();
  $stmt = $pdo->prepare('SELECT tables_info_id,image FROM rooms WHERE id=?'); $stmt->execute([$id]); $r = $stmt->fetch();
  $pdo->prepare('DELETE FROM rooms WHERE id=?')->execute([$id]);
  // Do NOT delete the linked tables_info row here. tables_info is used for booking internals and
  // may be referenced elsewhere; deleting it could remove booking history or other references.
  // If you want to remove the tables_info row as well, do it explicitly from the tables UI.
  if ($r && !empty($r['image'])) { @unlink(__DIR__ . '/../uploads/rooms/' . $r['image']); }
}
header('Location: rooms.php'); exit;
