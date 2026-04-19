<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Search functionality
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get visitors with pagination
$query = "SELECT * FROM visitors";
$count_query = "SELECT COUNT(*) as total FROM visitors";
$params = [];

if (!empty($search)) {
    $query .= " WHERE name LIKE :search OR email LIKE :search OR phone LIKE :search";
    $count_query .= " WHERE name LIKE :search OR email LIKE :search OR phone LIKE :search";
    $search_param = "%{$search}%";
    $params = [$search_param, $search_param, $search_param];
}

$query .= " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";

$stmt = $db->prepare($query);
if (!empty($search)) {
    $stmt->bindParam(':search', $search_param);
}
$stmt->execute();
$visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_stmt = $db->prepare($count_query);
if (!empty($search)) {
    $count_stmt->bindParam(':search', $search_param);
}
$count_stmt->execute();
$total_visitors = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_visitors / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitors Management - VMS</title>
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
                        <a class="nav-link active" href="visitors.php">
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
            <h2><i class="fas fa-users"></i> Visitors Management</h2>
            <div class="d-flex">
                <form method="GET" class="d-flex me-2">
                    <input type="text" class="form-control me-2" name="search" 
                           placeholder="Search visitors..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                <a href="../index.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> New Registration
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <i class="fas fa-users fa-2x text-primary mb-3"></i>
                    <div class="stats-number"><?php echo $total_visitors; ?></div>
                    <div class="stats-label">Total Visitors</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <i class="fas fa-calendar-day fa-2x text-success mb-3"></i>
                    <div class="stats-number">
                        <?php 
                        $today = date('Y-m-d');
                        $stmt = $db->prepare("SELECT COUNT(DISTINCT visitor_id) as count FROM visits WHERE DATE(created_at) = ?");
                        $stmt->execute([$today]);
                        echo $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        ?>
                    </div>
                    <div class="stats-label">Today's Visitors</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <i class="fas fa-door-open fa-2x text-warning mb-3"></i>
                    <div class="stats-number">
                        <?php 
                        $stmt = $db->prepare("SELECT COUNT(DISTINCT visitor_id) as count FROM visits WHERE status = 'checked_in'");
                        $stmt->execute();
                        echo $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        ?>
                    </div>
                    <div class="stats-label">Currently Inside</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <i class="fas fa-calendar-week fa-2x text-info mb-3"></i>
                    <div class="stats-number">
                        <?php 
                        $week = date('Y-m-d', strtotime('-7 days'));
                        $stmt = $db->prepare("SELECT COUNT(DISTINCT visitor_id) as count FROM visits WHERE DATE(created_at) >= ?");
                        $stmt->execute([$week]);
                        echo $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        ?>
                    </div>
                    <div class="stats-label">This Week</div>
                </div>
            </div>
        </div>

        <!-- Visitors Table -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">All Visitors</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>ID Proof</th>
                                <th>Registered</th>
                                <th>Total Visits</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visitors as $visitor): ?>
                                <tr>
                                    <td><?php echo $visitor['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($visitor['name']); ?></strong>
                                        <?php if ($visitor['address']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($visitor['address'], 0, 50)); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($visitor['email']); ?></td>
                                    <td><?php echo htmlspecialchars($visitor['phone']); ?></td>
                                    <td>
                                        <?php if ($visitor['id_proof_type']): ?>
                                            <?php echo htmlspecialchars(ucfirst($visitor['id_proof_type'])); ?>
                                            <br><small><?php echo htmlspecialchars($visitor['id_proof_number']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDateTime($visitor['created_at']); ?></td>
                                    <td>
                                        <?php 
                                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE visitor_id = ?");
                                        $stmt->execute([$visitor['id']]);
                                        echo $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="visitor_details.php?id=<?php echo $visitor['id']; ?>" 
                                               class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="visits.php?visitor_id=<?php echo $visitor['id']; ?>" 
                                               class="btn btn-outline-info">
                                                <i class="fas fa-calendar"></i> Visits
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($visitors)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-users-slash fa-3x mb-3"></i>
                        <p>No visitors found.</p>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
