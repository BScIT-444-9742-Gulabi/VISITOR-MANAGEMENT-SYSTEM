<?php
/**
 * Approval API Endpoint
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    if (!$data) {
        throw new Exception('Invalid JSON data');
    }

    $visit_id = $data['visit_id'] ?? '';
    $action = $data['action'] ?? ''; // approve or reject
    $admin_id = $data['admin_id'] ?? '';
    $notes = $data['notes'] ?? '';

    if (empty($visit_id) || empty($action) || empty($admin_id)) {
        throw new Exception('Visit ID, action, and admin ID are required');
    }

    if (!in_array($action, ['approve', 'reject'])) {
        throw new Exception('Invalid action');
    }

    // Connect to database
    $database = new Database();
    $db = $database->getConnection();

    // Get visit details
    $stmt = $db->prepare("
        SELECT v.*, vis.name, vis.email 
        FROM visits v 
        LEFT JOIN visitors vis ON v.visitor_id = vis.id 
        WHERE v.id = :visit_id
    ");
    $stmt->bindParam(':visit_id', $visit_id);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        throw new Exception('Visit not found');
    }

    $visit = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($visit['status'] !== 'pending') {
        throw new Exception('Visit has already been processed');
    }

    // Update visit status
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    $stmt = $db->prepare("
        UPDATE visits 
        SET status = :status, approved_by = :admin_id, notes = :notes, updated_at = CURRENT_TIMESTAMP 
        WHERE id = :visit_id
    ");
    $stmt->bindParam(':status', $new_status);
    $stmt->bindParam(':admin_id', $admin_id);
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':visit_id', $visit_id);
    $stmt->execute();

    // Log activity
    logActivity($db, $admin_id, $visit['visitor_id'], $visit_id, 
               'visit_' . $action, "Visit {$action}ed: " . $visit['name']);

    // Send email if approved
    $email_sent = false;
    if ($action === 'approve') {
        $email_sent = sendQREmail($db, $visit['email'], $visit['name'], $visit['qr_code'], $visit);
    }

    echo json_encode([
        'success' => true,
        'message' => "Visit {$action}d successfully!",
        'email_sent' => $email_sent
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
