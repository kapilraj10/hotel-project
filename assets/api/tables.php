<?php
require_once __DIR__ . '/../db_rest.php';
header('Content-Type: application/json');
$pdo = get_rest_db();

// Return public restaurant tables by default (exclude rooms).
// If ?type=rooms is provided, return only rooms instead.
try {
    $type = isset($_GET['type']) ? $_GET['type'] : 'tables';
    if ($type === 'rooms') {
        $stmt = $pdo->query("SELECT id, table_number, table_type, status, COALESCE(is_room,0) as is_room FROM tables_info WHERE is_public=1 AND COALESCE(is_room,0)=1 ORDER BY id");
    } else {
        // default: only non-room tables
        $stmt = $pdo->query("SELECT id, table_number, table_type, status, COALESCE(is_room,0) as is_room FROM tables_info WHERE is_public=1 AND COALESCE(is_room,0)=0 ORDER BY id");
    }
    $rows = $stmt->fetchAll();
    echo json_encode(['tables'=>$rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>'DB error','message'=>$e->getMessage()]);
}
