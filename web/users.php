<?php
require 'layout.php';
require 'db.php';
if (!$is_admin) die("Access Denied");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $email = $_POST['email'];
        $role = $_POST['role'];
        $uid = 'U-' . time() . rand(10,99);
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (user_id, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$uid, $email, $hash, $role]);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$_POST['user_id']]);
    }
    header("Location: users.php");
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>

<h1>Manage Users</h1>
<div class="content-columns">
  <div class="panel">
    <h2>Add New User</h2>
    <form method="POST" style="margin-top:1rem;">
      <input type="hidden" name="action" value="create">
      <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
      <div class="form-group">
        <label>Password</label>
        <div style="position:relative;">
          <input type="password" id="passInput" name="password" class="form-control" style="padding-right:40px;" required>
          <button type="button" onclick="togglePass()" style="position:absolute; right:5px; top:5px; width:30px; background:none; border:none; color:#555; padding:5px; font-size:12px; cursor:pointer;">👁</button>
        </div>
      </div>
      <div class="form-group"><label>Role</label>
        <select name="role" class="form-control">
          <option value="RANGER">Ranger</option>
          <option value="ADMIN">Admin</option>
        </select>
      </div>
      <button type="submit" class="btn-primary">Create User</button>
    </form>
  </div>
  <script>
    function togglePass() {
      let ipt = document.getElementById('passInput');
      ipt.type = (ipt.type === "password") ? "text" : "password";
    }
  </script>
  
  <div class="panel">
    <h2>System Users</h2>
    <table class="data-table" style="margin-top:1rem;">
      <thead><tr><th>User ID</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['user_id']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= htmlspecialchars($u['role']) ?></td>
          <td>
            <?php if($u['user_id'] !== $_SESSION['user_id']): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
              <button type="submit" class="btn-danger" style="padding:0.2rem 0.5rem; font-size:0.8rem;">Delete</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body></html>
