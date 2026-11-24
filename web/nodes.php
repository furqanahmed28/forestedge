<?php
require 'layout.php';
require 'db.php';
if (!$is_admin) die("Access Denied");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO nodes (node_id, cluster_id, hardware_mac, node_type, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['node_id'], $_POST['cluster_id'], $_POST['hardware_mac'], 
            $_POST['node_type'], $_POST['latitude'], $_POST['longitude']
        ]);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM nodes WHERE node_id = ?");
        $stmt->execute([$_POST['node_id']]);
    }
    header("Location: nodes.php");
    exit;
}

$nodes = $pdo->query("SELECT * FROM nodes ORDER BY cluster_id, node_id")->fetchAll();
$clusters = $pdo->query("SELECT cluster_id FROM clusters")->fetchAll();
?>

<h1>Manage Nodes</h1>
<div class="content-columns">
  <div class="panel">
    <h2>Register New Node</h2>
    <form method="POST" style="margin-top:1rem;">
      <input type="hidden" name="action" value="create">
      <div class="form-group"><label>Node ID (e.g. N03C01)</label><input type="text" name="node_id" class="form-control" required></div>
      <div class="form-group"><label>Cluster ID</label>
        <select name="cluster_id" class="form-control" required>
            <?php foreach($clusters as $c): ?>
            <option value="<?= $c['cluster_id'] ?>"><?= $c['cluster_id'] ?></option>
            <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Hardware MAC</label><input type="text" name="hardware_mac" class="form-control" required></div>
      <div class="form-group"><label>Node Type</label>
        <select name="node_type" class="form-control">
            <option value="LEAF">LEAF</option>
            <option value="SUBMASTER">SUBMASTER</option>
            <option value="MASTER">MASTER</option>
        </select>
      </div>
      <div class="form-group"><label>Latitude</label><input type="number" step="0.000001" name="latitude" class="form-control" required></div>
      <div class="form-group"><label>Longitude</label><input type="number" step="0.000001" name="longitude" class="form-control" required></div>
      <button type="submit" class="btn-primary">Register Node</button>
    </form>
  </div>
  
  <div class="panel" style="height:600px; overflow-y:auto;">
    <h2>Connected Hardware</h2>
    <table class="data-table" style="margin-top:1rem;">
      <thead><tr><th>Node ID</th><th>Cluster</th><th>Type</th><th>Lat, Lng</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($nodes as $n): ?>
        <tr>
          <td><?= htmlspecialchars($n['node_id']) ?></td>
          <td><?= htmlspecialchars($n['cluster_id']) ?></td>
          <td><?= htmlspecialchars($n['node_type']) ?></td>
          <td style="font-size:0.8rem;"><?= htmlspecialchars($n['latitude']) ?>, <?= htmlspecialchars($n['longitude']) ?></td>
          <td>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete node?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="node_id" value="<?= $n['node_id'] ?>">
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
