<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'manual_checkin') {
        try {
            // Get form data
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $purpose = sanitize($_POST['purpose'] ?? '');
            $person_to_meet = sanitize($_POST['person_to_meet'] ?? '');
            $department = sanitize($_POST['department'] ?? '');
            $id_proof_type = sanitize($_POST['id_proof_type'] ?? '');
            $id_proof_number = sanitize($_POST['id_proof_number'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            
            // Validate required fields
            $required_fields = ['name', 'phone', 'purpose', 'person_to_meet'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }
            
            // Validate phone number
            if (!preg_match('/^\d{10}$/', $phone)) {
                throw new Exception('Phone number must be 10 digits');
            }
            
            // Check if visitor exists
            $visitor_id = null;
            if (!empty($email)) {
                $stmt = $db->prepare("SELECT id FROM visitors WHERE email = :email");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $visitor_id = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
                }
            }
            
            // Create visitor record if doesn't exist
            if (!$visitor_id) {
                $stmt = $db->prepare("INSERT INTO visitors 
                    (name, email, phone, id_proof_type, id_proof_number) 
                    VALUES (:name, :email, :phone, :id_proof_type, :id_proof_number)");
                
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':id_proof_type', $id_proof_type);
                $stmt->bindParam(':id_proof_number', $id_proof_number);
                $stmt->execute();
                
                $visitor_id = $db->lastInsertId();
            }
            
            // Create visit record with immediate check-in
            $qr_code = generateUniqueQRCode();
            $visit_date = date('Y-m-d');
            
            $stmt = $db->prepare("INSERT INTO visits 
                (visitor_id, purpose, person_to_meet, department, visit_date, qr_code, status, 
                actual_arrival, checked_in_by, notes) 
                VALUES (:visitor_id, :purpose, :person_to_meet, :department, :visit_date, :qr_code, 
                'checked_in', CURRENT_TIMESTAMP, :user_id, :notes)");
            
            $stmt->bindParam(':visitor_id', $visitor_id);
            $stmt->bindParam(':purpose', $purpose);
            $stmt->bindParam(':person_to_meet', $person_to_meet);
            $stmt->bindParam(':department', $department);
            $stmt->bindParam(':visit_date', $visit_date);
            $stmt->bindParam(':qr_code', $qr_code);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':notes', $notes);
            $stmt->execute();
            
            $visit_id = $db->lastInsertId();
            
            // Log activity
            logActivity($db, $_SESSION['user_id'], $visitor_id, $visit_id, 
                       'manual_check_in', 'Manual check-in: ' . $name);
            
            $message = 'Visitor checked in manually successfully!';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get today's manual entries
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT v.*, vis.name, vis.email, vis.phone 
    FROM visits v 
    LEFT JOIN visitors vis ON v.visitor_id = vis.id 
    WHERE DATE(v.actual_arrival) = :today AND v.checked_in_by = :user_id
    ORDER BY v.actual_arrival DESC
");
$stmt->bindParam(':today', $today);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$manual_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Entry - VMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="scanner.php">
                <i class="fas fa-qrcode"></i> Gate Scanner
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="scanner.php">
                    <i class="fas fa-qrcode"></i> QR Scanner
                </a>
                <a class="nav-link" href="../admin/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Manual Entry Form -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-edit"></i> Manual Visitor Entry</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="manual_checkin">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           pattern="[0-9]{10}" maxlength="10" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="id_proof_type" class="form-label">ID Proof Type</label>
                                    <select class="form-select" id="id_proof_type" name="id_proof_type">
                                        <option value="">Select ID Type</option>
                                        <option value="aadhar">Aadhar Card</option>
                                        <option value="pan">PAN Card</option>
                                        <option value="driving">Driving License</option>
                                        <option value="passport">Passport</option>
                                        <option value="voter">Voter ID</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="id_proof_number" class="form-label">ID Proof Number</label>
                                    <input type="text" class="form-control" id="id_proof_number" name="id_proof_number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department" name="department">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="purpose" class="form-label">Purpose of Visit *</label>
                                <textarea class="form-control" id="purpose" name="purpose" rows="2" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="person_to_meet" class="form-label">Person to Meet *</label>
                                <input type="text" class="form-control" id="person_to_meet" name="person_to_meet" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" 
                                          placeholder="Any additional information..."></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus"></i> Check In Visitor
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Today's Manual Entries -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Today's Manual Entries</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="badge bg-primary fs-6"><?php echo count($manual_entries); ?> Entries</span>
                        </div>
                        
                        <?php if (!empty($manual_entries)): ?>
                            <div class="list-group">
                                <?php foreach ($manual_entries as $entry): ?>
                                    <div class="list-group-item">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($entry['name']); ?></h6>
                                        <p class="mb-1 small text-muted">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($entry['phone']); ?>
                                        </p>
                                        <p class="mb-1 small text-muted">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($entry['person_to_meet']); ?>
                                        </p>
                                        <p class="mb-0 small text-muted">
                                            <i class="fas fa-clock"></i> <?php echo formatDateTime($entry['actual_arrival']); ?>
                                        </p>
                                        <p class="mb-0 small">
                                            Status: <?php echo getStatusBadge($entry['status']); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No manual entries today</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function() {
            const value = this.value.replace(/\D/g, '');
            if (value.length <= 10) {
                this.value = value;
            } else {
                this.value = value.substring(0, 10);
            }
        });
        
        // Clear alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
