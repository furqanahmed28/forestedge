<?php
require 'layout.php';
require 'db.php';

$id = $_GET['id'] ?? null;
if (!$id) die("No ID provided");

$stmt = $pdo->prepare("SELECT e.*, n.latitude, n.longitude, c.region_name FROM events e 
                       JOIN nodes n ON e.node_id = n.node_id 
                       JOIN clusters c ON e.cluster_id = c.cluster_id 
                       WHERE e.event_id = :id");
$stmt->execute(['id' => $id]);
$ev = $stmt->fetch();

if (!$ev) die("Event not found");

$logs_stmt = $pdo->prepare("SELECT l.*, u.email FROM event_audit_logs l JOIN users u ON l.user_id = u.user_id WHERE event_id = :id ORDER BY action_timestamp DESC");
$logs_stmt->execute(['id' => $id]);
$logs = $logs_stmt->fetchAll();
?>

<h1>Event Details: <?= htmlspecialchars($ev['event_id']) ?></h1>
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
  
  <div class="panel">
    <h2>General Information</h2>
    <hr style="margin: 1rem 0; border:0; border-top:1px solid #eee;">
    <p><strong>Type:</strong> <?= htmlspecialchars($ev['event_type']) ?> (<?= htmlspecialchars($ev['criticality']) ?>)</p>
    <p><strong>Node:</strong> <?= htmlspecialchars($ev['node_id']) ?> (Cluster: <?= htmlspecialchars($ev['cluster_id']) ?> - <?= htmlspecialchars($ev['region_name']) ?>)</p>
    <p><strong>Coordinates:</strong> <?= htmlspecialchars($ev['latitude']) ?>, <?= htmlspecialchars($ev['longitude']) ?></p>
    <br>
    <h2>Telemetry</h2>
    <hr style="margin: 1rem 0; border:0; border-top:1px solid #eee;">
    <p><strong>Temperature:</strong> <?= htmlspecialchars($ev['temperature'] ?? 'N/A') ?> °C</p>
    <p><strong>Humidity:</strong> <?= htmlspecialchars($ev['humidity'] ?? 'N/A') ?> %</p>
    <br>
    <h2>LLM Summary</h2>
    <hr style="margin: 1rem 0; border:0; border-top:1px solid #eee;">
    <div style="background:#f9f9f9; padding:15px; border-radius:5px; border:1px solid #ddd;">
      <?= nl2br(htmlspecialchars($ev['llm_summary'])) ?>
    </div>
  </div>
  
  <div>
    <div class="panel" style="margin-bottom: 2rem;">
      <h2>Timeline</h2>
      <hr style="margin: 1rem 0; border:0; border-top:1px solid #eee;">
      <p style="font-size:0.9rem; color:#666;"><strong>Captured:</strong> <?= htmlspecialchars($ev['captured_at']) ?></p>
      <p style="font-size:0.9rem; color:#666;"><strong>Generated:</strong> <?= htmlspecialchars($ev['generated_at']) ?></p>
      <p style="font-size:0.9rem; color:#666;"><strong>Received:</strong> <?= htmlspecialchars($ev['received_at']) ?></p>
      
      <p style="margin-top:1rem;"><strong>Status:</strong> <?= $ev['is_acknowledged'] ? '<span style="color:green">Processed</span>' : '<span style="color:red">Unacknowledged</span>' ?></p>
    </div>
    
    <div class="panel">
      <h2>Audit Logs</h2>
      <hr style="margin: 1rem 0; border:0; border-top:1px solid #eee;">
      <?php if(empty($logs)): ?>
        <p style="color:#777; font-size:0.9rem;">No actions taken yet.</p>
      <?php else: ?>
        <?php foreach($logs as $l): ?>
          <div style="border-left: 3px solid #2E7D32; padding-left: 10px; margin-bottom: 1rem;">
            <p style="font-size:0.8rem; color:#888;"><?= htmlspecialchars($l['action_timestamp']) ?> by <?= htmlspecialchars($l['email']) ?></p>
            <p style="font-weight:bold;"><?= htmlspecialchars($l['action_type']) ?></p>
            <?php if($l['notes']): ?>
              <p style="font-size:0.9rem; background:#eee; padding:5px; border-radius:3px; margin-top:5px;"><?= htmlspecialchars($l['notes']) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>
</body></html>
