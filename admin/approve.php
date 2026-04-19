<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

$visit_id = $_GET['id'] ?? '';
$action = $_GET['action'] ?? '';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visit_id = $_POST['visit_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($visit_id) || empty($action)) {
        $error = 'Invalid request';
    } else {
        try {
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
                SET status = :status, approved_by = :approved_by, notes = :notes, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :visit_id
            ");
            $stmt->bindParam(':status', $new_status);
            $stmt->bindParam(':approved_by', $_SESSION['user_id']);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':visit_id', $visit_id);
            $stmt->execute();
            
            // Log activity
            logActivity($db, $_SESSION['user_id'], $visit['visitor_id'], $visit_id, 
                       'visit_' . $action, "Visit {$action}ed: " . $visit['name']);
            
            if ($action === 'approve') {
                // Send QR code email to visitor
                $qr_sent = sendQREmail($db, $visit['email'], $visit['name'], $visit['qr_code'], $visit);
                $message = 'Visit approved successfully! QR code email sent to visitor.';
            } else {
                $message = 'Visit rejected successfully.';
            }
            
            // Redirect to dashboard
            header('Location: dashboard.php?message=' . urlencode($message));
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get visit details for display
if (!empty($visit_id) && empty($error)) {
    try {
        $stmt = $db->prepare("
            SELECT v.*, vis.name, vis.email, vis.phone, vis.address, vis.id_proof_type, vis.id_proof_number 
            FROM visits v 
            LEFT JOIN visitors vis ON v.visitor_id = vis.id 
            WHERE v.id = :visit_id
        ");
        $stmt->bindParam(':visit_id', $visit_id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            $error = 'Visit not found';
        } else {
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $error = 'Failed to load visit details';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Visit - VMS</title>
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
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
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
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-user-check"></i> 
                            <?php echo ucfirst($action ?? 'Review'); ?> Visit Request
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <div class="text-center">
                                <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                            </div>
                        <?php elseif (isset($visit)): ?>
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

                            <?php if ($visit['notes']): ?>
                            <div class="mb-4">
                                <h5><i class="fas fa-sticky-note"></i> Additional Notes</h5>
                                <p><?php echo htmlspecialchars($visit['notes']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($visit['status'] === 'pending'): ?>
                                <!-- Approval Form -->
                                <form method="POST">
                                    <input type="hidden" name="visit_id" value="<?php echo $visit['id']; ?>">
                                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Admin Notes (Optional)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                  placeholder="Add any notes or instructions..."></textarea>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <?php if ($action === 'approve'): ?>
                                            Upon approval, a QR code will be generated and sent to the visitor's email address.
                                        <?php else: ?>
                                            The visitor will be notified that their visit request has been rejected.
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <a href="dashboard.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Cancel
                                        </a>
                                        
                                        <div>
                                            <?php if ($action === 'approve'): ?>
                                                <button type="submit" class="btn btn-success btn-lg">
                                                    <i class="fas fa-check"></i> Approve Visit
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-danger btn-lg">
                                                    <i class="fas fa-times"></i> Reject Visit
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    This visit has already been <?php echo $visit['status']; ?>.
                                </div>
                                <div class="text-center">
                                    <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
