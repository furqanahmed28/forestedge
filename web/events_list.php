<?php
require 'layout.php';
require 'db.php';

$stmt = $pdo->query("SELECT * FROM events ORDER BY received_at DESC");
$events = $stmt->fetchAll();
?>

<h1>All Events History</h1>
<div class="panel">
  <table class="data-table">
    <thead>
      <tr>
        <th>Received</th>
        <th>Event ID</th>
        <th>Cluster</th>
        <th>Node</th>
        <th>Type</th>
        <th>Criticality</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($events as $e): 
        $crit = htmlspecialchars($e['criticality']);
        $crit_cls = 'crit-' . strtolower($crit);
        $time_str = date('M d, H:i', strtotime($e['received_at']));
      ?>
      <tr style="cursor:pointer;" onclick="window.location='event_detail.php?id=<?= htmlspecialchars($e['event_id']) ?>'">
        <td><?= $time_str ?></td>
        <td><?= htmlspecialchars($e['event_id']) ?></td>
        <td><?= htmlspecialchars($e['cluster_id']) ?></td>
        <td><?= htmlspecialchars($e['node_id']) ?></td>
        <td><strong><?= htmlspecialchars($e['event_type']) ?></strong></td>
        <td class="<?= $crit_cls ?>"><?= $crit ?></td>
        <td>
            <?php if($e['is_acknowledged'] == 0): ?>
                <span style="color:#c62828; font-weight:bold;">Active</span>
            <?php else: ?>
                <span style="color:#2E7D32; font-weight:bold;">Resolved</span>
            <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($events)): ?>
        <tr><td colspan="7">No events recorded.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
