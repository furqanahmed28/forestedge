<?php
require 'layout.php';
require 'db.php';
if (!$is_admin) die("Access Denied");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $cid = $_POST['cluster_id'];
        $name = $_POST['region_name'];
        $stmt = $pdo->prepare("INSERT INTO clusters (cluster_id, region_name) VALUES (?, ?)");
        $stmt->execute([$cid, $name]);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM clusters WHERE cluster_id = ?");
        $stmt->execute([$_POST['cluster_id']]);
    }
    header("Location: clusters.php");
    exit;
}

$clusters = $pdo->query("SELECT * FROM clusters ORDER BY created_at DESC")->fetchAll();
?>

<h1>Manage Clusters</h1>
<div class="content-columns">
  <div class="panel">
    <h2>Add New Cluster</h2>
    <form method="POST" style="margin-top:1rem;">
      <input type="hidden" name="action" value="create">
      <div class="form-group"><label>Cluster ID (e.g. 02)</label><input type="text" name="cluster_id" class="form-control" required></div>
      <div class="form-group"><label>Region Name</label><input type="text" name="region_name" class="form-control" required></div>
      <button type="submit" class="btn-primary">Create Cluster</button>
    </form>
  </div>
  
  <div class="panel">
    <h2>Deployments</h2>
    <table class="data-table" style="margin-top:1rem;">
      <thead><tr><th>Cluster ID</th><th>Region</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($clusters as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['cluster_id']) ?></td>
          <td><?= htmlspecialchars($c['region_name']) ?></td>
          <td>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete cluster? Valid only if node empty.');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="cluster_id" value="<?= $c['cluster_id'] ?>">
              <button type="submit" class="btn-danger" style="padding:0.2rem 0.5rem; font-size:0.8rem;">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body></html>
