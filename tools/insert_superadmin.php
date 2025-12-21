<?php
// idempotent seeder to insert the superadmin row using project's DB connection
require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();

$username = 'superadmin';
$hash = '$2y$10$jlzsS8e98J7aIEST2BNzDOEEx8DWNBjPIR0plMOzv654rLu4aVa9a';

try {
    // ensure admins table exists with necessary columns
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(100) NOT NULL UNIQUE,
      password VARCHAR(255) NOT NULL,
      role VARCHAR(50) DEFAULT 'admin',
      permissions TEXT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare('INSERT INTO admins (username, password) VALUES (:u, :p) ON DUPLICATE KEY UPDATE username=VALUES(username)');
    $stmt->execute([':u' => $username, ':p' => $hash]);
    echo "Superadmin inserted/updated successfully.\n";
} catch (PDOException $e) {
    echo "Failed to insert superadmin: " . $e->getMessage() . "\n";
    exit(1);
}
