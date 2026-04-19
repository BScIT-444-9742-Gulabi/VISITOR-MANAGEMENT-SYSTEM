<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$scan_result = null;

// Handle QR code scan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qr_code = $_POST['qr_code'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if (empty($qr_code) || empty($action)) {
        $error = 'Invalid request';
    } else {
        try {
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
            
            // Process check-in or check-out
            if ($action === 'checkin') {
                if ($visit['status'] !== 'approved') {
                    throw new Exception('Visit must be approved before check-in');
                }
                if ($visit['status'] === 'checked_in') {
                    throw new Exception('Visitor already checked in');
                }
                
                // Update visit
                $stmt = $db->prepare("
                    UPDATE visits 
                    SET status = 'checked_in', actual_arrival = CURRENT_TIMESTAMP, 
                    checked_in_by = :user_id, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = :visit_id
                ");
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':visit_id', $visit['id']);
                $stmt->execute();
                
                $message = 'Visitor checked in successfully!';
                logActivity($db, $_SESSION['user_id'], $visit['visitor_id'], $visit['id'], 
                           'check_in', 'Visitor checked in: ' . $visit['name']);
                
            } elseif ($action === 'checkout') {
                if ($visit['status'] !== 'checked_in') {
                    throw new Exception('Visitor must be checked in first');
                }
                
                // Update visit
                $stmt = $db->prepare("
                    UPDATE visits 
                    SET status = 'checked_out', actual_departure = CURRENT_TIMESTAMP, 
                    checked_out_by = :user_id, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = :visit_id
                ");
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':visit_id', $visit['id']);
                $stmt->execute();
                
                $message = 'Visitor checked out successfully!';
                logActivity($db, $_SESSION['user_id'], $visit['visitor_id'], $visit['id'], 
                           'check_out', 'Visitor checked out: ' . $visit['name']);
            }
            
            $scan_result = $visit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get currently checked-in visitors
$stmt = $db->prepare("
    SELECT v.*, vis.name, vis.email, vis.phone 
    FROM visits v 
    LEFT JOIN visitors vis ON v.visitor_id = vis.id 
    WHERE v.status = 'checked_in' 
    ORDER BY v.actual_arrival DESC
");
$stmt->execute();
$checked_in_visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Scanner - VMS</title>
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
                <a class="nav-link" href="manual_entry.php">
                    <i class="fas fa-edit"></i> Manual Entry
                </a>
                <a class="nav-link" href="../admin/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Scanner Section -->
            <div class="col-md-8">
                <div class="scanner-container">
                    <h4 class="mb-4"><i class="fas fa-qrcode"></i> QR Code Scanner</h4>
                    
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
                    
                    <div class="text-center mb-4">
                        <div class="border rounded p-4 bg-light">
                            <i class="fas fa-qrcode fa-5x text-muted mb-3"></i>
                            <p class="text-muted">Position QR code within the frame</p>
                            <p class="small">Or enter QR code manually below</p>
                        </div>
                    </div>
                    
                    <!-- Manual QR Code Entry -->
                    <form method="POST" class="mb-4">
                        <div class="row">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="qr_code" 
                                       placeholder="Enter QR code manually" required>
                            </div>
                            <div class="col-md-4">
                                <div class="btn-group w-100">
                                    <button type="submit" name="action" value="checkin" 
                                            class="btn btn-success">
                                        <i class="fas fa-sign-in-alt"></i> Check In
                                    </button>
                                    <button type="submit" name="action" value="checkout" 
                                            class="btn btn-warning">
                                        <i class="fas fa-sign-out-alt"></i> Check Out
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <?php if ($scan_result): ?>
                        <div class="scanner-result">
                            <h5><i class="fas fa-user-check"></i> Scan Result</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($scan_result['name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($scan_result['email']); ?></p>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($scan_result['phone']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Purpose:</strong> <?php echo htmlspecialchars($scan_result['purpose']); ?></p>
                                    <p><strong>Person to Meet:</strong> <?php echo htmlspecialchars($scan_result['person_to_meet']); ?></p>
                                    <p><strong>Status:</strong> <?php echo getStatusBadge($scan_result['status']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Currently Inside Section -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-door-open"></i> Currently Inside</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="badge bg-success fs-6"><?php echo count($checked_in_visitors); ?> Visitors</span>
                        </div>
                        
                        <?php if (!empty($checked_in_visitors)): ?>
                            <div class="list-group">
                                <?php foreach ($checked_in_visitors as $visitor): ?>
                                    <div class="list-group-item">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($visitor['name']); ?></h6>
                                        <p class="mb-1 small text-muted">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($visitor['person_to_meet']); ?>
                                        </p>
                                        <p class="mb-0 small text-muted">
                                            <i class="fas fa-clock"></i> <?php echo formatDateTime($visitor['actual_arrival']); ?>
                                        </p>
                                        
                                        <!-- Quick Check Out -->
                                        <form method="POST" class="mt-2">
                                            <input type="hidden" name="qr_code" value="<?php echo htmlspecialchars($visitor['qr_code']); ?>">
                                            <input type="hidden" name="action" value="checkout">
                                            <button type="submit" class="btn btn-sm btn-warning" 
                                                    onclick="return confirm('Check out this visitor?')">
                                                <i class="fas fa-sign-out-alt"></i> Check Out
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No visitors currently inside</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus on QR code input
        document.addEventListener('DOMContentLoaded', function() {
            const qrInput = document.querySelector('input[name="qr_code"]');
            if (qrInput) {
                qrInput.focus();
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
