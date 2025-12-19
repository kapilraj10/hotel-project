<?php
require_once __DIR__ . '/db_rest.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    $stmt = $pdo->prepare('SELECT id, password, role, permissions FROM admins WHERE username = :u LIMIT 1');
    $stmt->execute(['u' => $username]);
    $row = $stmt->fetch();
    if ($row && password_verify($password, $row['password'])) {
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_username'] = $username;
        // store role for access checks
        $_SESSION['admin_role'] = $row['role'] ?? 'admin';
        $_SESSION['admin_permissions'] = $row['permissions'] ?? '';
        return true;
    }
    return false;
}

function admin_logout() {
    session_unset();
    session_destroy();
}

// Check if current admin has the given role (or is superadmin)
function admin_has_role(string $role): bool {
    if (!admin_check()) return false;
    $r = $_SESSION['admin_role'] ?? '';
    if ($r === 'superadmin') return true;
    return $r === $role;
}

// Require a minimum role; redirects if not satisfied
function require_role(string $role) {
    if (!admin_check() || !admin_has_role($role)) {
        header('Location: /hotel/assets/admin/forbidden.php');
        exit;
    }
}
