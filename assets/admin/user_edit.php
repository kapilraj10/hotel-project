<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();

// ensure users table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(200) NOT NULL UNIQUE,
  phone VARCHAR(50),
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: users.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { header('Location: users.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if ($name === '') $errors[] = 'Name is required';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';

  if (!$errors) {
    try {
      if ($password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ust = $pdo->prepare('UPDATE users SET name=?,email=?,phone=?,password=?,role=? WHERE id=?');
        $ust->execute([$name,$email,$phone,$hash,$role,$id]);
      } else {
        $ust = $pdo->prepare('UPDATE users SET name=?,email=?,phone=?,role=? WHERE id=?');
        $ust->execute([$name,$email,$phone,$role,$id]);
      }

      // Sync admins table when role changes to/from admin or email changed
      $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      // Fetch current stored password hash from users table
      $pstmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
      $pstmt->execute([$id]);
      $rowp = $pstmt->fetch();
      $stored_hash = $rowp['password'] ?? null;

      // If role is admin, ensure admins table has username=email and password hash
      if ($role === 'admin') {
        if ($stored_hash) {
          $ast = $pdo->prepare('INSERT INTO admins (username,password) VALUES (?,?) ON DUPLICATE KEY UPDATE password=VALUES(password)');
          $ast->execute([$email, $stored_hash]);
        }
      } else {
        // if role is not admin, remove from admins table if exists (matching previous username or current email)
        $dst = $pdo->prepare('DELETE FROM admins WHERE username = ?');
        $dst->execute([$email]);
      }

      header('Location: users.php'); exit;
    } catch (PDOException $ex) {
      $errors[] = 'Failed to update user: ' . $ex->getMessage();
    }
  }
}

$page_title = 'Edit User'; include __DIR__ . '/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3>Edit User</h3>
  <a class="btn btn-secondary" href="users.php">Back</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars(implode('\n', $errors)); ?></div>
<?php endif; ?>

<form method="post">
  <div class="mb-3">
    <label class="form-label">Name</label>
    <input name="name" class="form-control" value="<?php echo htmlspecialchars($user['name'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Email</label>
    <input name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Phone</label>
    <input name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Password (leave blank to keep)</label>
    <input name="password" type="password" class="form-control">
  </div>
  <div class="mb-3">
    <label class="form-label">Role</label>
    <select name="role" class="form-select">
      <option value="user" <?php if (($user['role'] ?? '')==='user') echo 'selected'; ?>>User</option>
      <option value="staff" <?php if (($user['role'] ?? '')==='staff') echo 'selected'; ?>>Staff</option>
      <option value="manager" <?php if (($user['role'] ?? '')==='manager') echo 'selected'; ?>>Manager</option>
    </select>
  </div>
  <div>
    <button class="btn btn-primary">Save</button>
  </div>
</form>

<?php include __DIR__ . '/admin_footer.php';
