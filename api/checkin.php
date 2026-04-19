<?php
/**
 * Check-in API Endpoint
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

    $qr_code = $data['qr_code'] ?? '';
    $user_id = $data['user_id'] ?? '';

    if (empty($qr_code)) {
        throw new Exception('QR code is required');
    }

    // Connect to database
    $database = new Database();
    $db = $database->getConnection();

    // Find visit by QR code
    $stmt = $db->prepare("
        SELECT v.*, vis.name, vis.email, vis.phone 
        FROM visits v 
        LEFT JOIN visitors vis ON v.visitor_id = vis.id 
        WHERE v.qr_code = :qr_code
    ");
    $stmt->bindParam(':qr_code', $qr_code);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        throw new Exception('Invalid QR code or visit not found');
    }

    $visit = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check QR code expiry
    if (strtotime($visit['qr_expiry']) < time()) {
        throw new Exception('QR code has expired');
    }

    // Check if visit is approved
    if ($visit['status'] !== 'approved') {
        throw new Exception('Visit must be approved before check-in');
    }

    // Check if already checked in
    if ($visit['status'] === 'checked_in') {
        throw new Exception('Visitor already checked in');
    }

    // Update visit status
    $stmt = $db->prepare("
        UPDATE visits 
        SET status = 'checked_in', actual_arrival = CURRENT_TIMESTAMP, 
        checked_in_by = :user_id, updated_at = CURRENT_TIMESTAMP 
        WHERE id = :visit_id
    ");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':visit_id', $visit['id']);
    $stmt->execute();

    // Log activity
    logActivity($db, $user_id, $visit['visitor_id'], $visit['id'], 'check_in', 'Visitor checked in: ' . $visit['name']);

    echo json_encode([
        'success' => true,
        'message' => 'Check-in successful!',
        'visit' => $visit
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
