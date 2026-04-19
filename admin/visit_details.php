<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

$visit_id = $_GET['id'] ?? '';
$message = '';
$error = '';

if (empty($visit_id)) {
    header('Location: visits.php');
    exit;
}

// Get visit details
$stmt = $db->prepare("
    SELECT v.*, vis.name, vis.email, vis.phone, vis.address, vis.id_proof_type, vis.id_proof_number,
           u1.username as approved_by_name, u2.username as checked_in_by_name, u3.username as checked_out_by_name
    FROM visits v 
    LEFT JOIN visitors vis ON v.visitor_id = vis.id 
    LEFT JOIN users u1 ON v.approved_by = u1.id
    LEFT JOIN users u2 ON v.checked_in_by = u2.id
    LEFT JOIN users u3 ON v.checked_out_by = u3.id
    WHERE v.id = :visit_id
");
$stmt->bindParam(':visit_id', $visit_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    $error = 'Visit not found';
} else {
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get activity logs for this visit
if (isset($visit)) {
    $stmt = $db->prepare("
        SELECT al.*, u.username 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE al.visit_id = :visit_id 
        ORDER BY al.created_at DESC
    ");
    $stmt->bindParam(':visit_id', $visit_id);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($visit)) {
    $action = $_POST['action'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    try {
        if ($action === 'approve' && $visit['status'] === 'pending') {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'approved', approved_by = :user_id, notes = :notes, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :visit_id
            ");
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':visit_id', $visit_id);
            $stmt->execute();
            
            // Send QR email
            sendQREmail($db, $visit['email'], $visit['name'], $visit['qr_code'], $visit);
            
            logActivity($db, $_SESSION['user_id'], $visit['visitor_id'], $visit_id, 
                       'visit_approved', 'Visit approved: ' . $visit['name']);
            
            $message = 'Visit approved successfully!';
            
            // Refresh visit data
            $stmt = $db->prepare("SELECT * FROM visits WHERE id = :visit_id");
            $stmt->bindParam(':visit_id', $visit_id);
            $stmt->execute();
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } elseif ($action === 'reject' && $visit['status'] === 'pending') {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'rejected', approved_by = :user_id, notes = :notes, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :visit_id
            ");
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':visit_id', $visit_id);
            $stmt->execute();
            
            logActivity($db, $_SESSION['user_id'], $visit['visitor_id'], $visit_id, 
                       'visit_rejected', 'Visit rejected: ' . $visit['name']);
            
            $message = 'Visit rejected successfully!';
            $visit['status'] = 'rejected';
            
        } elseif ($action === 'cancel') {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP 
                WHERE id = :visit_id
            ");
            $stmt->bindParam(':visit_id', $visit_id);
            $stmt->execute();
            
            logActivity($db, $_SESSION['user_id'], $visit['visitor_id'], $visit_id, 
                       'visit_cancelled', 'Visit cancelled: ' . $visit['name']);
            
            $message = 'Visit cancelled successfully!';
            $visit['status'] = 'cancelled';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Details - VMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-building"></i> VMS Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="visits.php">
                    <i class="fas fa-arrow-left"></i> Back to Visits
                </a>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users-cog"></i> Users
                    </a>
                </li>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <!-- Visit Details -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-info-circle"></i> Visit Details</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($visit)): ?>
                            <!-- Status Badge -->
                            <div class="mb-3">
                                <span class="fs-6">Status: <?php echo getStatusBadge($visit['status']); ?></span>
                            </div>
                            
                            <!-- Visitor Information -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5><i class="fas fa-user"></i> Visitor Information</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Name:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['name']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['email']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Phone:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['phone']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Address:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['address'] ?: 'N/A'); ?></td>
                                        </tr>
                                        <?php if ($visit['id_proof_type']): ?>
                                        <tr>
                                            <td><strong>ID Proof:</strong></td>
                                            <td><?php echo htmlspecialchars(ucfirst($visit['id_proof_type'])); ?> - <?php echo htmlspecialchars($visit['id_proof_number']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h5><i class="fas fa-calendar"></i> Visit Details</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Purpose:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['purpose']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Person to Meet:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['person_to_meet']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Department:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['department'] ?: 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Visit Date:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['visit_date']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Expected Arrival:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['expected_arrival'] ?: 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Expected Departure:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['expected_departure'] ?: 'N/A'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Timing Information -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5><i class="fas fa-clock"></i> Timing Information</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Registration:</strong></td>
                                            <td><?php echo formatDateTime($visit['created_at']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Actual Arrival:</strong></td>
                                            <td><?php echo $visit['actual_arrival'] ? formatDateTime($visit['actual_arrival']) : 'Not checked in'; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Actual Departure:</strong></td>
                                            <td><?php echo $visit['actual_departure'] ? formatDateTime($visit['actual_departure']) : 'Not checked out'; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>QR Code:</strong></td>
                                            <td>
                                                <code><?php echo htmlspecialchars($visit['qr_code']); ?></code>
                                                <br>
                                                <small class="text-muted">Expires: <?php echo formatDateTime($visit['qr_expiry']); ?></small>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h5><i class="fas fa-user-shield"></i> Processed By</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Approved By:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['approved_by_name'] ?: 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Checked In By:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['checked_in_by_name'] ?: 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Checked Out By:</strong></td>
                                            <td><?php echo htmlspecialchars($visit['checked_out_by_name'] ?: 'N/A'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Notes -->
                            <?php if ($visit['notes']): ?>
                            <div class="mb-4">
                                <h5><i class="fas fa-sticky-note"></i> Notes</h5>
                                <div class="alert alert-info">
                                    <?php echo htmlspecialchars($visit['notes']); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Actions -->
                            <?php if ($visit['status'] === 'pending'): ?>
                                <div class="d-flex justify-content-between">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="notes" value="">
                                        <button type="submit" class="btn btn-success" onclick="return confirm('Approve this visit?')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="notes" value="">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this visit?')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                    
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="notes" value="">
                                        <button type="submit" class="btn btn-warning" onclick="return confirm('Cancel this visit?')">
                                            <i class="fas fa-ban"></i> Cancel
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- QR Code -->
                <?php if (isset($visit) && $visit['qr_code']): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-qrcode"></i> QR Code</h5>
                        </div>
                        <div class="card-body text-center">
                            <img src="../api/generate_qr.php?code=<?php echo urlencode($visit['qr_code']); ?>" 
                                 alt="QR Code" class="img-fluid mb-2" style="max-width: 200px;">
                            <p class="small text-muted mb-0"><?php echo htmlspecialchars($visit['qr_code']); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Activity Log -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Activity Log</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($activities) && !empty($activities)): ?>
                            <div class="timeline">
                                <?php foreach ($activities as $activity): ?>
                                    <div class="timeline-item mb-3">
                                        <small class="text-muted"><?php echo formatDateTime($activity['created_at']); ?></small>
                                        <div>
                                            <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                                            <?php if ($activity['username']): ?>
                                                by <?php echo htmlspecialchars($activity['username']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($activity['description']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($activity['description']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No activity recorded</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
