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

$errors = [];
$prefill_email = trim($_GET['email'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if ($name === '') $errors[] = 'Name is required';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if ($password === '' || strlen($password) < 6) $errors[] = 'Password is required (min 6 chars)';

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare('INSERT INTO users (name,email,phone,password,role) VALUES (?,?,?,?,?)');
            $stmt->execute([$name,$email,$phone,$hash,$role]);
      // if role is admin, ensure admins table has this user (username = email)
      if ($role === 'admin') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
          id INT AUTO_INCREMENT PRIMARY KEY,
          username VARCHAR(100) NOT NULL UNIQUE,
          password VARCHAR(255) NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ast = $pdo->prepare('INSERT INTO admins (username,password) VALUES (?,?) ON DUPLICATE KEY UPDATE password=VALUES(password)');
        $ast->execute([$email, $hash]);
      }
      header('Location: users.php'); exit;
        } catch (PDOException $ex) {
            $errors[] = 'Failed to create user: ' . $ex->getMessage();
        }
    }
}

$page_title = 'Create User'; include __DIR__ . '/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3>Create User</h3>
  <a class="btn btn-secondary" href="users.php">Back</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars(implode('\n', $errors)); ?></div>
<?php endif; ?>

<form method="post">
  <div class="mb-3">
    <label class="form-label">Name</label>
    <input name="name" class="form-control" value="<?php echo htmlspecialchars($name ?? '') ?>">
  </div>
    <div class="mb-3">
    <label class="form-label">Email</label>
    <input name="email" class="form-control" value="<?php echo htmlspecialchars($email ?? $prefill_email ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Phone</label>
    <input name="phone" class="form-control" value="<?php echo htmlspecialchars($phone ?? '') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Password</label>
    <input name="password" type="password" class="form-control">
    <div class="form-text">Min length 6</div>
  </div>
  <div class="mb-3">
    <label class="form-label">Role</label>
    <select name="role" class="form-select">
      <option value="user" <?php if (($role ?? '')==='user') echo 'selected'; ?>>User</option>
      <option value="staff" <?php if (($role ?? '')==='staff') echo 'selected'; ?>>Staff</option>
      <option value="manager" <?php if (($role ?? '')==='manager') echo 'selected'; ?>>Manager</option>
  <!-- Admin role creation via this form is disabled; use Admins management -->
    </select>
  </div>
  <div>
    <button class="btn btn-primary">Create</button>
  </div>
</form>

<?php include __DIR__ . '/admin_footer.php';
