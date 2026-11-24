<?php
// web/process_event.php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$event_id = $data['event_id'] ?? null;
$action_type = $data['action_type'] ?? 'ACK'; // ACK or FALSE-EVENT
$notes = $data['notes'] ?? '';

if ($event_id) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE events SET is_acknowledged = 1 WHERE event_id = :eid");
        $stmt->execute(['eid' => $event_id]);
        
        $log_id = 'LOG-' . time() . '-' . rand(100, 999);
        $stmt = $pdo->prepare("INSERT INTO event_audit_logs (log_id, event_id, user_id, action_type, notes) VALUES (:lid, :eid, :uid, :act, :notes)");
        $stmt->execute([
            'lid' => $log_id, 
            'eid' => $event_id, 
            'uid' => $_SESSION['user_id'], 
            'act' => $action_type, 
            'notes' => $notes
        ]);
        
        $pdo->commit();
        echo json_encode(["status" => "success"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "missing event_id"]);
}
