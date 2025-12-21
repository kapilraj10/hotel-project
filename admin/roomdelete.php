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
  // Hide the linked tables_info row from public/admin pickers by setting is_public=0
  // (do not hard-delete tables_info here to preserve historical references).
  if ($r && !empty($r['tables_info_id'])) {
    try {
      $up = $pdo->prepare('UPDATE tables_info SET is_public=0 WHERE id=?');
      $up->execute([$r['tables_info_id']]);
    } catch (Throwable $__ignored) {
      // ignore failures — best-effort
    }
  }
  if ($r && !empty($r['image'])) { @unlink(__DIR__ . '/../uploads/rooms/' . $r['image']); }
}
header('Location: rooms.php'); exit;
