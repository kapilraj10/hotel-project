<?php
require_once __DIR__ . '/../db_rest.php';
header('Content-Type: application/json');
$pdo = get_rest_db();
$items = $pdo->query('SELECT i.*, c.name as category_name FROM items i JOIN categories c ON i.category_id = c.id')->fetchAll();
echo json_encode(['items'=>$items]);
