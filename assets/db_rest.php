<?php
require_once __DIR__ . '/config.php';

function get_rest_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        echo 'DB Connection failed: ' . htmlspecialchars($e->getMessage());
        exit;
    }
    // ensure tables_info has is_public flag (safe to run repeatedly)
    try {
        $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS is_public TINYINT(1) DEFAULT 1");
    } catch (Throwable $e) {
        // ignore if table doesn't exist yet or ALTER not supported
    }
    return $pdo;
}
