<?php
// Simple PDO database connection wrapper for the Hotel project
// Usage: require 'db.php'; $pdo = get_db();

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = '127.0.0.1';
    $port = 3306; // MariaDB/XAMPP MySQL default
    $db   = 'hotel_db';
    // Created a dedicated DB user for the project
    $user = 'hotel_user';
    $pass = 'changeme';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // In production, hide detailed errors and log them instead
        http_response_code(500);
        echo "Database connection failed: " . htmlspecialchars($e->getMessage());
        exit;
    }

    return $pdo;
}

