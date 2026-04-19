<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = getVisitorStats($db);

// Get recent visits
$stmt = $db->prepare("
    SELECT v.*, vis.name, vis.email, vis.phone, vis.address 
    FROM visits v 
    LEFT JOIN visitors vis ON v.visitor_id = vis.id 
    ORDER BY v.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending approvals
$stmt = $db->prepare("
    SELECT v.*, vis.name, vis.email, vis.phone 
    FROM visits v 
    LEFT JOIN visitors vis ON v.visitor_id = vis.id 
    WHERE v.status = 'pending' 
    ORDER BY v.created_at ASC
");
$stmt->execute();
$pending_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VMS</title>
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
                        <a class="nav-link active" href="dashboard.php">
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

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <p class="mb-0">Welcome back, <?php echo $_SESSION['username']; ?>!</p>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <i class="fas fa-calendar-day fa-2x text-primary mb-3"></i>
                    <div class="stats-number"><?php echo $stats['today']; ?></div>
                    <div class="stats-label">Today's Visitors</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                    <div class="stats-number"><?php echo $stats['pending']; ?></div>
                    <div class="stats-label">Pending Approvals</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <i class="fas fa-door-open fa-2x text-success mb-3"></i>
                    <div class="stats-number"><?php echo $stats['inside']; ?></div>
                    <div class="stats-label">Currently Inside</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <i class="fas fa-users fa-2x text-info mb-3"></i>
                    <div class="stats-number"><?php echo $stats['total']; ?></div>
                    <div class="stats-label">Total Visitors</div>
                </div>
            </div>
        </div>

        <!-- Pending Approvals -->
        <?php if (!empty($pending_visits)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Pending Approvals</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Purpose</th>
                                        <th>Visit Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_visits as $visit): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($visit['name']); ?></td>
                                        <td><?php echo htmlspecialchars($visit['email']); ?></td>
                                        <td><?php echo htmlspecialchars($visit['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($visit['purpose']); ?></td>
                                        <td><?php echo htmlspecialchars($visit['visit_date']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="approve.php?id=<?php echo $visit['id']; ?>&action=approve" 
                                                   class="btn btn-success" onclick="return confirm('Approve this visit?')">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                                <a href="approve.php?id=<?php echo $visit['id']; ?>&action=reject" 
                                                   class="btn btn-danger" onclick="return confirm('Reject this visit?')">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                                <a href="visit_details.php?id=<?php echo $visit['id']; ?>" 
                                                   class="btn btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Visitor</th>
                                        <th>Email</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_visits as $visit): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($visit['name']); ?></td>
                                        <td><?php echo htmlspecialchars($visit['email']); ?></td>
                                        <td><?php echo htmlspecialchars($visit['purpose']); ?></td>
                                        <td><?php echo getStatusBadge($visit['status']); ?></td>
                                        <td><?php echo formatDateTime($visit['created_at']); ?></td>
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
                        
                        <?php if (empty($recent_visits)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No recent activity found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
