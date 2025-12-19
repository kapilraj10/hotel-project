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

// handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action']==='delete') {
    $id = (int)($_POST['user_id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }
    header('Location: users.php'); exit;
}

// handle admin-only deletion (from admins table)
// no admin-extra handling here; users.php only shows users table entries

$users = $pdo->query('SELECT id,name,email,phone,role,created_at FROM users ORDER BY id DESC')->fetchAll();

// fetch admins that are not present in users table (so we can show default admin inserted by install.sql)
// $admins_extra = $pdo->query("SELECT username, created_at FROM admins WHERE username NOT IN (SELECT email FROM users) ORDER BY created_at DESC")->fetchAll();

$page_title = 'Users'; include __DIR__ . '/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3>Users</h3>
  <a class="btn btn-primary" href="user_create.php">Add User</a>
</div>

<div class="table-responsive">
  <table class="table table-striped">
    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= $u['id'] ?></td>
        <td><?= htmlspecialchars($u['name']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= htmlspecialchars($u['phone']) ?></td>
        <td><?= htmlspecialchars($u['role']) ?></td>
        <td><?= htmlspecialchars($u['created_at']) ?></td>
        <td>
          <a class="btn btn-sm btn-secondary" href="user_edit.php?id=<?= $u['id'] ?>">Edit</a>
          <form method="post" style="display:inline-block" onsubmit="return confirm('Delete user?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
            <button class="btn btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>


<?php include __DIR__ . '/admin_footer.php';
