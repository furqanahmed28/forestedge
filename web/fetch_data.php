<?php
// web/fetch_data.php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

require 'db.php';
header('Content-Type: application/json');

// Auto Offline Check: update nodes if heartbeat older than 120s
$stmt = $pdo->prepare("UPDATE nodes SET is_active = 0 WHERE strftime('%s', 'now') - strftime('%s', last_heartbeat) > 120 AND is_active = 1");
$stmt->execute();

$nodes_stmt = $pdo->query("SELECT node_id as id, cluster_id as cluster, is_active, latitude as lat, longitude as lng FROM nodes");
$nodes = $nodes_stmt->fetchAll();

$mapped_nodes = array_map(function($n) {
    return [
        'id' => $n['id'],
        'cluster' => $n['cluster'],
        'status' => $n['is_active'] ? 'Healthy' : 'Offline',
        'lat' => $n['lat'],
        'lng' => $n['lng']
    ];
}, $nodes);

$events_stmt = $pdo->query("SELECT event_id, node_id, cluster_id, event_type, criticality, llm_summary, received_at FROM events WHERE is_acknowledged = 0 ORDER BY received_at DESC");
$alerts = $events_stmt->fetchAll();

$stats_stmt = $pdo->query("SELECT ROUND(AVG(temperature), 1) as avg_temp, ROUND(AVG(humidity), 1) as avg_hum FROM events");
$stats = $stats_stmt->fetch();

$t_week = $pdo->query("SELECT COUNT(*) FROM events WHERE received_at >= datetime('now', '-7 days')")->fetchColumn();
$t_month = $pdo->query("SELECT COUNT(*) FROM events WHERE received_at >= datetime('now', '-30 days')")->fetchColumn();

// Calculate Average Mins to Acknowledge
$ack_stmt = $pdo->query("
    SELECT ROUND(AVG((julianday(l.action_timestamp) - julianday(e.received_at)) * 24 * 60)) as avg_ack_mins
    FROM events e
    JOIN (
        SELECT event_id, MIN(action_timestamp) as action_timestamp 
        FROM event_audit_logs 
        GROUP BY event_id
    ) l ON e.event_id = l.event_id
");
$ack_res = $ack_stmt->fetch();
$avg_ack = $ack_res['avg_ack_mins'];

echo json_encode([
    "kpi" => [
        "avg_temp" => $stats['avg_temp'] ?? '--',
        "avg_hum" => $stats['avg_hum'] ?? '--',
        "total_weekly" => $t_week ?? 0,
        "total_monthly" => $t_month ?? 0,
        "avg_ack_time" => isset($avg_ack) ? $avg_ack . ' Mins' : '--'
    ],
    "nodes" => $mapped_nodes,
    "alerts" => array_map(function($e) {
        return [
            'alert_id' => $e['event_id'],
            'node_id' => $e['node_id'],
            'cluster_id' => $e['cluster_id'],
            'problem' => $e['event_type'],
            'description' => $e['llm_summary'],
            'criticality' => $e['criticality'],
            'timestamp' => strtotime($e['received_at'])
        ];
    }, $alerts)
]);
