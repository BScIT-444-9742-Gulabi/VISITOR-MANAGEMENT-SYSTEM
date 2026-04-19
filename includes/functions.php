<?php
/**
 * Helper Functions for Visitor Management System
 */

require_once '../config/config.php';

// Generate unique QR code
function generateUniqueQRCode() {
    return 'VMS_' . uniqid() . '_' . time();
}

// Log activity
function logActivity($db, $user_id, $visitor_id, $visit_id, $action, $description) {
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs 
            (user_id, visitor_id, visit_id, action, description, ip_address, user_agent) 
            VALUES (:user_id, :visitor_id, :visit_id, :action, :description, :ip_address, :user_agent)");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':visitor_id', $visitor_id);
        $stmt->bindParam(':visit_id', $visit_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Send admin notification email
function sendAdminNotification($db, $visitor_data, $visit_id, $visitor_id) {
    try {
        require_once '../vendor/PHPMailer/src/PHPMailer.php';
        require_once '../vendor/PHPMailer/src/SMTP.php';
        require_once '../vendor/PHPMailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = EMAIL_USERNAME;
        $mail->Password   = EMAIL_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress(EMAIL_USERNAME, 'Admin');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New Visitor Registration - ' . $visitor_data['name'];
        
        $approval_link = QR_CODE_BASE_URL . 'admin/approve.php?id=' . $visit_id;
        
        $mail->Body = "
            <h2>New Visitor Registration</h2>
            <p>A new visitor has registered and needs your approval:</p>
            <table border='1' cellpadding='10' style='border-collapse: collapse;'>
                <tr><td><strong>Name:</strong></td><td>{$visitor_data['name']}</td></tr>
                <tr><td><strong>Email:</strong></td><td>{$visitor_data['email']}</td></tr>
                <tr><td><strong>Phone:</strong></td><td>{$visitor_data['phone']}</td></tr>
                <tr><td><strong>Purpose:</strong></td><td>{$visitor_data['purpose']}</td></tr>
                <tr><td><strong>Person to Meet:</strong></td><td>{$visitor_data['person_to_meet']}</td></tr>
                <tr><td><strong>Visit Date:</strong></td><td>{$visitor_data['visit_date']}</td></tr>
                <tr><td><strong>Department:</strong></td><td>{$visitor_data['department']}</td></tr>
            </table>
            <p><a href='{$approval_link}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Approve Visit</a></p>
            <p>Or copy this link: {$approval_link}</p>
            <hr>
            <p><small>This is an automated message from Visitor Management System</small></p>
        ";
        
        $mail->AltBody = "
            New Visitor Registration\n\n
            Name: {$visitor_data['name']}\n
            Email: {$visitor_data['email']}\n
            Phone: {$visitor_data['phone']}\n
            Purpose: {$visitor_data['purpose']}\n
            Person to Meet: {$visitor_data['person_to_meet']}\n
            Visit Date: {$visitor_data['visit_date']}\n
            Department: {$visitor_data['department']}\n\n
            Approve here: {$approval_link}
        ";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Admin notification email failed: " . $e->getMessage());
        return false;
    }
}

// Send QR code email to visitor
function sendQREmail($db, $visitor_email, $visitor_name, $qr_code, $visit_data) {
    try {
        require_once '../vendor/PHPMailer/src/PHPMailer.php';
        require_once '../vendor/PHPMailer/src/SMTP.php';
        require_once '../vendor/PHPMailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = EMAIL_USERNAME;
        $mail->Password   = EMAIL_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($visitor_email, $visitor_name);
        
        // Generate QR code image
        $qr_image_url = QR_CODE_BASE_URL . 'api/generate_qr.php?code=' . $qr_code;
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Visit QR Code - Approved';
        
        $mail->Body = "
            <h2>Visit Approved - QR Code Generated</h2>
            <p>Dear {$visitor_name},</p>
            <p>Your visit request has been approved. Please use the QR code below for entry and exit.</p>
            <div style='text-align: center; margin: 20px 0;'>
                <img src='{$qr_image_url}' alt='QR Code' style='max-width: 200px; border: 2px solid #007bff; padding: 10px;'>
            </div>
            <p><strong>Visit Details:</strong></p>
            <table border='1' cellpadding='10' style='border-collapse: collapse;'>
                <tr><td><strong>Date:</strong></td><td>{$visit_data['visit_date']}</td></tr>
                <tr><td><strong>Person to Meet:</strong></td><td>{$visit_data['person_to_meet']}</td></tr>
                <tr><td><strong>Purpose:</strong></td><td>{$visit_data['purpose']}</td></tr>
                <tr><td><strong>QR Code:</strong></td><td>{$qr_code}</td></tr>
            </table>
            <p><strong>Important:</strong></p>
            <ul>
                <li>Please arrive on time</li>
                <li>Show this QR code at the gate</li>
                <li>QR code expires in " . QR_CODE_EXPIRY_HOURS . " hours</li>
            </ul>
            <hr>
            <p><small>This is an automated message from Visitor Management System</small></p>
        ";
        
        $mail->AltBody = "
            Visit Approved - QR Code Generated\n\n
            Dear {$visitor_name},\n\n
            Your visit request has been approved. Please use the QR code for entry and exit.\n\n
            Visit Details:\n
            Date: {$visit_data['visit_date']}\n
            Person to Meet: {$visit_data['person_to_meet']}\n
            Purpose: {$visit_data['purpose']}\n
            QR Code: {$qr_code}\n\n
            Please arrive on time and show this QR code at the gate.\n
            QR code expires in " . QR_CODE_EXPIRY_HOURS . " hours.\n\n
            Thank you!
        ";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("QR email failed: " . $e->getMessage());
        return false;
    }
}

// Generate QR code image
function generateQRImage($qr_code) {
    // This would use a QR code library like php-qrcode
    // For now, return a placeholder URL
    return "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";
}

// Validate session and user role
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../admin/login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: ../admin/login.php');
        exit;
    }
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Format date/time
function formatDateTime($datetime) {
    if (!$datetime) return 'N/A';
    $date = new DateTime($datetime);
    return $date->format('d M Y h:i A');
}

// Get status badge HTML
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning">Pending</span>',
        'approved' => '<span class="badge bg-info">Approved</span>',
        'checked_in' => '<span class="badge bg-success">Checked In</span>',
        'checked_out' => '<span class="badge bg-secondary">Checked Out</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
}

// Get visitor statistics
function getVisitorStats($db) {
    $stats = [];
    
    // Today's visitors
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $stats['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Pending approvals
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Currently inside
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE status = 'checked_in'");
    $stmt->execute();
    $stats['inside'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total visitors
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visitors");
    $stmt->execute();
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    return $stats;
}
?>
