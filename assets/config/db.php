<?php
if (file_exists(__DIR__ . './config.php')) {
    require_once __DIR__ . './config.php';
} else {
    // fallback defaults (adjust as needed)
    define('DB_HOST','127.0.0.1');
    define('DB_PORT',3306);
    define('DB_NAME','restaurant_db');
    define('DB_USER','hotel_user');
    define('DB_PASS','changeme');
}

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, defined('DB_PORT')?DB_PORT:3306, DB_NAME);
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    } catch (PDOException $e) {
        http_response_code(500);
        echo 'DB Connection error: ' . htmlspecialchars($e->getMessage());
        exit;
    }
    return $pdo;
}
