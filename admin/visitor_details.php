<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

$visitor_id = $_GET['id'] ?? '';

if (empty($visitor_id)) {
    header('Location: visitors.php');
    exit;
}

// Get visitor details
$stmt = $db->prepare("SELECT * FROM visitors WHERE id = :visitor_id");
$stmt->bindParam(':visitor_id', $visitor_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header('Location: visitors.php');
    exit;
}

$visitor = $stmt->fetch(PDO::FETCH_ASSOC);

// Get visitor's visits
$stmt = $db->prepare("
    SELECT v.*, u1.username as approved_by_name, u2.username as checked_in_by_name, u3.username as checked_out_by_name
    FROM visits v 
    LEFT JOIN users u1 ON v.approved_by = u1.id
    LEFT JOIN users u2 ON v.checked_in_by = u2.id
    LEFT JOIN users u3 ON v.checked_out_by = u3.id
    WHERE v.visitor_id = :visitor_id 
    ORDER BY v.created_at DESC
");
$stmt->bindParam(':visitor_id', $visitor_id);
$stmt->execute();
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get visitor statistics
$total_visits = count($visits);
$approved_visits = 0;
$checked_in_visits = 0;
$rejected_visits = 0;

foreach ($visits as $visit) {
    if ($visit['status'] === 'approved') $approved_visits++;
    elseif ($visit['status'] === 'checked_in') $checked_in_visits++;
    elseif ($visit['status'] === 'rejected') $rejected_visits++;
}

// Get activity logs for this visitor
$stmt = $db->prepare("
    SELECT al.*, u.username 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    WHERE al.visitor_id = :visitor_id 
    ORDER BY al.created_at DESC
    LIMIT 10
");
$stmt->bindParam(':visitor_id', $visitor_id);
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Details - VMS</title>
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="visitors.php">
                            <i class="fas fa-users"></i> Visitors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="visits.php">
                            <i class="fas fa-calendar-check"></i> Visits
                        </a>
                    </li>
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
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-user"></i> Visitor Details</h2>
            <a href="visitors.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back to Visitors
            </a>
        </div>

        <div class="row">
            <!-- Visitor Information -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-id-card"></i> Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Visitor ID:</strong></td>
                                        <td>#<?php echo $visitor['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Full Name:</strong></td>
                                        <td><?php echo htmlspecialchars($visitor['name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email Address:</strong></td>
                                        <td><?php echo htmlspecialchars($visitor['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Phone Number:</strong></td>
                                        <td><?php echo htmlspecialchars($visitor['phone']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Address:</strong></td>
                                        <td><?php echo htmlspecialchars($visitor['address'] ?: 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>ID Proof Type:</strong></td>
                                        <td><?php echo htmlspecialchars(ucfirst($visitor['id_proof_type'] ?: 'N/A')); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>ID Proof Number:</strong></td>
                                        <td><?php echo htmlspecialchars($visitor['id_proof_number'] ?: 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Registered On:</strong></td>
                                        <td><?php echo formatDateTime($visitor['created_at']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visit History -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Visit History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($visits)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Visit ID</th>
                                            <th>Purpose</th>
                                            <th>Person to Meet</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($visits as $visit): ?>
                                            <tr>
                                                <td>#<?php echo $visit['id']; ?></td>
                                                <td><?php echo htmlspecialchars(substr($visit['purpose'], 0, 30)); ?>...</td>
                                                <td><?php echo htmlspecialchars($visit['person_to_meet']); ?></td>
                                                <td><?php echo htmlspecialchars($visit['visit_date']); ?></td>
                                                <td><?php echo getStatusBadge($visit['status']); ?></td>
                                                <td>
                                                    <a href="visit_details.php?id=<?php echo $visit['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                <p>No visits recorded for this visitor.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Statistics -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Visit Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="stats-number"><?php echo $total_visits; ?></div>
                                <div class="stats-label">Total Visits</div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="stats-number"><?php echo $approved_visits; ?></div>
                                <div class="stats-label">Approved</div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="stats-number"><?php echo $checked_in_visits; ?></div>
                                <div class="stats-label">Checked In</div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="stats-number"><?php echo $rejected_visits; ?></div>
                                <div class="stats-label">Rejected</div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="text-center">
                            <a href="../index.php?email=<?php echo urlencode($visitor['email']); ?>" 
                               class="btn btn-success btn-sm">
                                <i class="fas fa-plus"></i> New Visit
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($activities)): ?>
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
