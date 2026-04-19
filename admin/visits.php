<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$visitor_filter = $_GET['visitor_id'] ?? '';
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$query = "
    SELECT v.*, vis.name, vis.email, vis.phone 
    FROM visits v 
    LEFT JOIN visitors vis ON v.visitor_id = vis.id 
    WHERE 1=1
";
$count_query = "
    SELECT COUNT(*) as total 
    FROM visits v 
    LEFT JOIN visitors vis ON v.visitor_id = vis.id 
    WHERE 1=1
";
$params = [];

// Apply filters
if (!empty($status_filter)) {
    $query .= " AND v.status = :status";
    $count_query .= " AND v.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($date_filter)) {
    $query .= " AND DATE(v.visit_date) = :date";
    $count_query .= " AND DATE(v.visit_date) = :date";
    $params[':date'] = $date_filter;
}

if (!empty($visitor_filter)) {
    $query .= " AND v.visitor_id = :visitor_id";
    $count_query .= " AND v.visitor_id = :visitor_id";
    $params[':visitor_id'] = $visitor_filter;
}

if (!empty($search)) {
    $query .= " AND (vis.name LIKE :search OR vis.email LIKE :search OR v.purpose LIKE :search OR v.person_to_meet LIKE :search)";
    $count_query .= " AND (vis.name LIKE :search OR vis.email LIKE :search OR v.purpose LIKE :search OR v.person_to_meet LIKE :search)";
    $search_param = "%{$search}%";
    $params[':search'] = $search_param;
}

$query .= " ORDER BY v.created_at DESC LIMIT {$limit} OFFSET {$offset}";

// Execute main query
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_visits = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_visits / $limit);

// Get filter options
$statuses = ['pending', 'approved', 'checked_in', 'checked_out', 'rejected'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visits Management - VMS</title>
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
                        <a class="nav-link active" href="visits.php">
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
            <h2><i class="fas fa-calendar-check"></i> Visits Management</h2>
            <a href="../index.php" class="btn btn-success">
                <i class="fas fa-plus"></i> New Visit
            </a>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                    <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Name, email, purpose..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
                <?php if (!empty($status_filter) || !empty($date_filter) || !empty($search) || !empty($visitor_filter)): ?>
                    <div class="mt-2">
                        <a href="visits.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <?php foreach ($statuses as $status): ?>
                <div class="col-md-2">
                    <div class="stats-card text-center">
                        <div class="stats-number">
                            <?php 
                            $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE status = ?");
                            $stmt->execute([$status]);
                            echo $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                            ?>
                        </div>
                        <div class="stats-label"><?php echo getStatusBadge($status); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Visits Table -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">All Visits</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Visitor</th>
                                <th>Purpose</th>
                                <th>Person to Meet</th>
                                <th>Visit Date</th>
                                <th>Status</th>
                                <th>Entry/Exit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visits as $visit): ?>
                                <tr>
                                    <td><?php echo $visit['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($visit['name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($visit['email']); ?></small>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($visit['phone']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($visit['purpose'], 0, 50)); ?>...</td>
                                    <td>
                                        <?php echo htmlspecialchars($visit['person_to_meet']); ?>
                                        <?php if ($visit['department']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($visit['department']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($visit['visit_date']); ?>
                                        <?php if ($visit['expected_arrival']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($visit['expected_arrival']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo getStatusBadge($visit['status']); ?></td>
                                    <td>
                                        <small>
                                            <?php if ($visit['actual_arrival']): ?>
                                                In: <?php echo formatDateTime($visit['actual_arrival']); ?><br>
                                            <?php endif; ?>
                                            <?php if ($visit['actual_departure']): ?>
                                                Out: <?php echo formatDateTime($visit['actual_departure']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="visit_details.php?id=<?php echo $visit['id']; ?>" 
                                               class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($visit['status'] === 'pending'): ?>
                                                <a href="approve.php?id=<?php echo $visit['id']; ?>&action=approve" 
                                                   class="btn btn-outline-success" onclick="return confirm('Approve this visit?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="approve.php?id=<?php echo $visit['id']; ?>&action=reject" 
                                                   class="btn btn-outline-danger" onclick="return confirm('Reject this visit?')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($visits)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-calendar-times fa-3x mb-3"></i>
                        <p>No visits found.</p>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                        echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; 
                                        echo !empty($date_filter) ? '&date=' . urlencode($date_filter) : ''; 
                                        echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                        echo !empty($visitor_filter) ? '&visitor_id=' . urlencode($visitor_filter) : ''; 
                                    ?>">
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
