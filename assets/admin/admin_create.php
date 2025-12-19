<?php
require_once __DIR__ . '/../auth.php'; require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();

// Allow initial bootstrap: if there are no admins yet, allow creating the first superadmin
$countAdmins = 0;
try {
  $r = $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
  $countAdmins = (int)$r;
} catch (Throwable $e) {
  // table may not exist yet
  $countAdmins = 0;
}

if ($countAdmins > 0) {
  // require logged-in superadmin for further admin creation
  require_admin(); require_role('superadmin');
}

// ensure admins table exists with role/permissions
$pdo->exec("CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'admin',
  permissions TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'admin');
    $permissions = trim($_POST['permissions'] ?? '');

    if ($username === '' || !filter_var($username, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email username is required';
    if ($password === '' || strlen($password) < 8) $errors[] = 'Password is required (min 8 chars)';

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare('INSERT INTO admins (username,password,role,permissions) VALUES (?,?,?,?)');
            $stmt->execute([$username, $hash, $role, $permissions]);
            header('Location: admins.php'); exit;
        } catch (PDOException $ex) {
            $errors[] = 'Failed to create admin: ' . $ex->getMessage();
        }
    }
}

$page_title = 'Create Admin'; include __DIR__ . '/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3>Create Admin</h3>
  <a class="btn btn-secondary" href="admins.php">Back</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars(implode('\n', $errors)); ?></div>
<?php endif; ?>

<form method="post">
  <div class="mb-3">
    <label class="form-label">Username (email)</label>
    <input name="username" class="form-control" value="<?php echo htmlspecialchars($username ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Password</label>
    <input name="password" type="password" class="form-control">
    <div class="form-text">Min length 8</div>
  </div>
  <div class="mb-3">
    <label class="form-label">Role</label>
    <select name="role" class="form-select">
      <option value="superadmin">Superadmin</option>
      <option value="admin" selected>Admin</option>
      <option value="manager">Manager</option>
      <option value="staff">Staff</option>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label">Permissions (JSON or comma list)</label>
    <textarea name="permissions" class="form-control" rows="3"><?php echo htmlspecialchars($permissions ?? '') ?></textarea>
  </div>
  <div>
    <button class="btn btn-primary">Create Admin</button>
  </div>
</form>

<?php include __DIR__ . '/admin_footer.php';
