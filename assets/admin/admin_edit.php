<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();

// ensure admins table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: admins.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$admin = $stmt->fetch();
if (!$admin) { header('Location: admins.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || !filter_var($username, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email username is required';
    if (!$errors) {
        try {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ust = $pdo->prepare('UPDATE admins SET username = ?, password = ? WHERE id = ?');
                $ust->execute([$username, $hash, $id]);
            } else {
                $ust = $pdo->prepare('UPDATE admins SET username = ? WHERE id = ?');
                $ust->execute([$username, $id]);
            }
            // if a users row exists with previous username, update it to keep sync
            $prev = $admin['username'];
            $ucheck = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $ucheck->execute([$prev]);
            $ur = $ucheck->fetch();
            if ($ur) {
                $uup = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
                $uup->execute([$username, $ur['id']]);
            }

            header('Location: admins.php'); exit;
        } catch (PDOException $ex) { $errors[] = 'Failed to update admin: ' . $ex->getMessage(); }
    }
}

$page_title = 'Edit Admin'; include __DIR__ . '/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3>Edit Admin</h3>
  <a class="btn btn-secondary" href="admins.php">Back</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars(implode('\n', $errors)); ?></div>
<?php endif; ?>

<form method="post">
  <div class="mb-3">
    <label class="form-label">Username (email)</label>
    <input name="username" class="form-control" value="<?php echo htmlspecialchars($admin['username'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Password (leave blank to keep)</label>
    <input name="password" type="password" class="form-control">
  </div>
  <div>
    <button class="btn btn-primary">Save</button>
  </div>
</form>

<?php include __DIR__ . '/admin_footer.php';
