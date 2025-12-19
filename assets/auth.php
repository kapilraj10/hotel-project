<?php
require_once __DIR__ . '/db_rest.php';
session_start();

function admin_check(): bool {
    return !empty($_SESSION['admin_id']);
}

function require_admin() {
    if (!admin_check()) {
        header('Location: /hotel/assets/admin/login.php');
        exit;
    }
}

function admin_login($username, $password): bool {
    $pdo = get_rest_db();
    $stmt = $pdo->prepare('SELECT id, password FROM admins WHERE username = :u LIMIT 1');
    $stmt->execute(['u' => $username]);
    $row = $stmt->fetch();
    if ($row && password_verify($password, $row['password'])) {
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_username'] = $username;
        return true;
    }
    return false;
}

function admin_logout() {
    session_unset();
    session_destroy();
}
