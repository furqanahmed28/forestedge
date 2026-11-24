<?php
// web/api.php
require 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$key = $_GET['key'] ?? '';
if ($key !== 'FE_SECRET_2026') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Invalid or missing API key"]);
    exit;
}

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON payload"]);
    exit;
}

$type = $data['type'] ?? '';
$cluster_id = $data['cluster_id'] ?? '01';
// Node config IDs directly map to node_id in db
$node_id = $data['node_id'] ?? 'unknown'; 
$timestamp = date('Y-m-d H:i:s');

try {
    if ($type === 'heartbeat') {
        $stmt = $pdo->prepare("UPDATE nodes SET last_heartbeat = :ts, is_active = 1 WHERE node_id = :nid");
        $stmt->execute(['ts' => $timestamp, 'nid' => $node_id]);
    } 
    elseif ($type === 'telemetry') {
        // Only trigger DB inserts if it's a critical event (Not 'nothing')
        // We also update heartbeat on telemetry
        $stmt = $pdo->prepare("UPDATE nodes SET last_heartbeat = :ts, is_active = 1 WHERE node_id = :nid");
        $stmt->execute(['ts' => $timestamp, 'nid' => $node_id]);

        $inf = $data['inference'] ?? [];
        $env = $data['env_data'] ?? [];
        
        $problem = strtoupper($inf['Problem'] ?? 'NOTHING');
        $criticality = strtoupper($inf['Criticality'] ?? 'NONE');
        
        if ($problem !== 'NOTHING') {
            $gen_time = intval($data['generated_at'] ?? time());
            $event_id = strtoupper($node_id) . date('YmdHi', $gen_time);
            
            // Protect against very rapid duplicate collisions
            $event_id .= '-' . rand(10, 99);
            $valid_events = ['FIRE', 'DISEASE', 'OTHER'];
            if (!in_array($problem, $valid_events)) {
                $problem = 'OTHER';
            }
            
            $generated_at = date('Y-m-d H:i:s', intval($data['generated_at'] ?? time()));
            
            $stmt = $pdo->prepare("INSERT INTO events 
                (event_id, node_id, cluster_id, event_type, criticality, temperature, humidity, llm_summary, captured_at, generated_at, received_at) 
                VALUES (:eid, :nid, :cid, :evtype, :crit, :temp, :hum, :summ, :cap, :gena, :recva)");
                
            $stmt->execute([
                'eid' => $event_id,
                'nid' => $node_id,
                'cid' => $cluster_id,
                'evtype' => $problem,
                'crit' => $criticality,
                'temp' => $env['temperature'] ?? null,
                'hum' => $env['humidity'] ?? null,
                'summ' => $inf['Description'] ?? '',
                'cap' => $timestamp,
                'gena' => $generated_at,
                'recva' => $timestamp
            ]);
        }
    }
    
    echo json_encode(["status" => "success"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
