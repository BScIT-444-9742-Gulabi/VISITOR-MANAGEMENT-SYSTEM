<?php
/**
 * Visitor Registration API Endpoint
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

    // Validate required fields
    $required_fields = ['name', 'email', 'phone', 'purpose', 'person_to_meet', 'visit_date'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }

    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // Validate phone number
    if (!preg_match('/^\d{10}$/', $data['phone'])) {
        throw new Exception('Phone number must be 10 digits');
    }

    // Validate date
    $visit_date = DateTime::createFromFormat('Y-m-d', $data['visit_date']);
    if (!$visit_date || $visit_date < new DateTime('today')) {
        throw new Exception('Visit date must be today or in the future');
    }

    // Connect to database
    $database = new Database();
    $db = $database->getConnection();

    // Check if visitor already exists
    $check_visitor = $db->prepare("SELECT id FROM visitors WHERE email = :email");
    $check_visitor->bindParam(':email', $data['email']);
    $check_visitor->execute();
    
    $visitor_id = null;
    if ($check_visitor->rowCount() > 0) {
        // Visitor exists, get their ID
        $visitor_id = $check_visitor->fetch(PDO::FETCH_ASSOC)['id'];
        
        // Update visitor information
        $update_visitor = $db->prepare("UPDATE visitors SET 
            name = :name, 
            phone = :phone, 
            address = :address, 
            id_proof_type = :id_proof_type, 
            id_proof_number = :id_proof_number,
            updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id");
        
        $update_visitor->bindParam(':name', $data['name']);
        $update_visitor->bindParam(':phone', $data['phone']);
        $update_visitor->bindParam(':address', $data['address']);
        $update_visitor->bindParam(':id_proof_type', $data['id_proof_type']);
        $update_visitor->bindParam(':id_proof_number', $data['id_proof_number']);
        $update_visitor->bindParam(':id', $visitor_id);
        $update_visitor->execute();
    } else {
        // Create new visitor
        $insert_visitor = $db->prepare("INSERT INTO visitors 
            (name, email, phone, address, id_proof_type, id_proof_number) 
            VALUES (:name, :email, :phone, :address, :id_proof_type, :id_proof_number)");
        
        $insert_visitor->bindParam(':name', $data['name']);
        $insert_visitor->bindParam(':email', $data['email']);
        $insert_visitor->bindParam(':phone', $data['phone']);
        $insert_visitor->bindParam(':address', $data['address']);
        $insert_visitor->bindParam(':id_proof_type', $data['id_proof_type']);
        $insert_visitor->bindParam(':id_proof_number', $data['id_proof_number']);
        $insert_visitor->execute();
        
        $visitor_id = $db->lastInsertId();
    }

    // Create visit record
    $qr_code = generateUniqueQRCode();
    $qr_expiry = date('Y-m-d H:i:s', strtotime('+' . QR_CODE_EXPIRY_HOURS . ' hours'));
    
    $insert_visit = $db->prepare("INSERT INTO visits 
        (visitor_id, purpose, person_to_meet, department, visit_date, expected_arrival, expected_departure, qr_code, qr_expiry, notes) 
        VALUES (:visitor_id, :purpose, :person_to_meet, :department, :visit_date, :expected_arrival, :expected_departure, :qr_code, :qr_expiry, :notes)");
    
    $insert_visit->bindParam(':visitor_id', $visitor_id);
    $insert_visit->bindParam(':purpose', $data['purpose']);
    $insert_visit->bindParam(':person_to_meet', $data['person_to_meet']);
    $insert_visit->bindParam(':department', $data['department']);
    $insert_visit->bindParam(':visit_date', $data['visit_date']);
    $insert_visit->bindParam(':expected_arrival', $data['expected_arrival']);
    $insert_visit->bindParam(':expected_departure', $data['expected_departure']);
    $insert_visit->bindParam(':qr_code', $qr_code);
    $insert_visit->bindParam(':qr_expiry', $qr_expiry);
    $insert_visit->bindParam(':notes', $data['notes']);
    $insert_visit->execute();
    
    $visit_id = $db->lastInsertId();

    // Log activity
    logActivity($db, null, $visitor_id, $visit_id, 'visitor_registered', 'Visitor registered online: ' . $data['name']);

    // Send notification email to admin
    $admin_email_sent = sendAdminNotification($db, $data, $visit_id, $visitor_id);

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! Your request has been submitted for approval.',
        'visit_id' => $visit_id,
        'qr_code' => $qr_code
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
