<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Report parameters
$report_type = $_GET['type'] ?? 'summary';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$department = $_GET['department'] ?? '';

// Get departments for filter
$stmt = $db->prepare("SELECT DISTINCT department FROM visits WHERE department IS NOT NULL AND department != '' ORDER BY department");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate report data based on type
$report_data = [];
$chart_data = [];

switch ($report_type) {
    case 'summary':
        // Summary statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_visits,
                COUNT(DISTINCT visitor_id) as unique_visitors,
                COUNT(CASE WHEN status = 'checked_in' THEN 1 END) as currently_inside,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected
            FROM visits 
            WHERE DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$date_from, $date_to]);
        $report_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Daily visits for chart
        $stmt = $db->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as visits 
            FROM visits 
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute([$date_from, $date_to]);
        $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'visitors':
        // Visitor statistics
        $stmt = $db->prepare("
            SELECT 
                v.visitor_id,
                vis.name,
                vis.email,
                COUNT(*) as total_visits,
                MAX(v.created_at) as last_visit,
                COUNT(CASE WHEN v.status = 'checked_in' THEN 1 END) as current_visits
            FROM visits v
            LEFT JOIN visitors vis ON v.visitor_id = vis.id
            WHERE DATE(v.created_at) BETWEEN ? AND ?
            GROUP BY v.visitor_id, vis.name, vis.email
            ORDER BY total_visits DESC
            LIMIT 20
        ");
        $stmt->execute([$date_from, $date_to]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'departments':
        // Department statistics
        $stmt = $db->prepare("
            SELECT 
                department,
                COUNT(*) as total_visits,
                COUNT(DISTINCT visitor_id) as unique_visitors,
                AVG(TIMESTAMPDIFF(MINUTE, actual_arrival, actual_departure)) as avg_duration
            FROM visits 
            WHERE DATE(created_at) BETWEEN ? AND ? AND department IS NOT NULL AND department != ''
            GROUP BY department
            ORDER BY total_visits DESC
        ");
        $stmt->execute([$date_from, $date_to]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'peak_hours':
        // Peak hours analysis
        $stmt = $db->prepare("
            SELECT 
                HOUR(actual_arrival) as hour,
                COUNT(*) as visits
            FROM visits 
            WHERE DATE(actual_arrival) BETWEEN ? AND ? AND actual_arrival IS NOT NULL
            GROUP BY HOUR(actual_arrival)
            ORDER BY hour
        ");
        $stmt->execute([$date_from, $date_to]);
        $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'duration':
        // Visit duration analysis
        $stmt = $db->prepare("
            SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(MINUTE, actual_arrival, actual_departure) <= 30 THEN '0-30 min'
                    WHEN TIMESTAMPDIFF(MINUTE, actual_arrival, actual_departure) <= 60 THEN '30-60 min'
                    WHEN TIMESTAMPDIFF(MINUTE, actual_arrival, actual_departure) <= 120 THEN '1-2 hours'
                    WHEN TIMESTAMPDIFF(MINUTE, actual_arrival, actual_departure) <= 240 THEN '2-4 hours'
                    ELSE '4+ hours'
                END as duration_range,
                COUNT(*) as visits
            FROM visits 
            WHERE DATE(actual_arrival) BETWEEN ? AND ? 
            AND actual_arrival IS NOT NULL 
            AND actual_departure IS NOT NULL
            GROUP BY duration_range
            ORDER BY MIN(TIMESTAMPDIFF(MINUTE, actual_arrival, actual_departure))
        ");
        $stmt->execute([$date_from, $date_to]);
        $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - VMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <h2><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>
            <div class="btn-group">
                <a href="export.php?type=summary&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                   class="btn btn-outline-success">
                    <i class="fas fa-file-csv"></i> Export Summary
                </a>
                <a href="export.php?type=visitors&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                   class="btn btn-outline-success">
                    <i class="fas fa-file-csv"></i> Export Visitors
                </a>
                <a href="export.php?type=visits&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                   class="btn btn-outline-success">
                    <i class="fas fa-file-csv"></i> Export Visits
                </a>
                <a href="export.php?type=activity&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                   class="btn btn-outline-success">
                    <i class="fas fa-file-csv"></i> Export Activity
                </a>
            </div>
        </div>
        <!-- Report Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="type">
                            <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Summary</option>
                            <option value="visitors" <?php echo $report_type === 'visitors' ? 'selected' : ''; ?>>Top Visitors</option>
                            <option value="departments" <?php echo $report_type === 'departments' ? 'selected' : ''; ?>>Department Stats</option>
                            <option value="peak_hours" <?php echo $report_type === 'peak_hours' ? 'selected' : ''; ?>>Peak Hours</option>
                            <option value="duration" <?php echo $report_type === 'duration' ? 'selected' : ''; ?>>Visit Duration</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sync"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Report -->
        <?php if ($report_type === 'summary'): ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <i class="fas fa-calendar-check fa-2x text-primary mb-3"></i>
                        <div class="stats-number"><?php echo $report_data['total_visits']; ?></div>
                        <div class="stats-label">Total Visits</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <i class="fas fa-users fa-2x text-success mb-3"></i>
                        <div class="stats-number"><?php echo $report_data['unique_visitors']; ?></div>
                        <div class="stats-label">Unique Visitors</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <i class="fas fa-door-open fa-2x text-warning mb-3"></i>
                        <div class="stats-number"><?php echo $report_data['currently_inside']; ?></div>
                        <div class="stats-label">Currently Inside</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <i class="fas fa-clock fa-2x text-info mb-3"></i>
                        <div class="stats-number"><?php echo $report_data['pending']; ?></div>
                        <div class="stats-label">Pending</div>
                    </div>
                </div>
            </div>

            <!-- Daily Visits Chart -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Daily Visits</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyVisitsChart" height="100"></canvas>
                </div>
            </div>

        <!-- Top Visitors Report -->
        <?php elseif ($report_type === 'visitors'): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Top Visitors</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Visitor Name</th>
                                    <th>Email</th>
                                    <th>Total Visits</th>
                                    <th>Last Visit</th>
                                    <th>Currently Inside</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $visitor): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($visitor['name']); ?></td>
                                        <td><?php echo htmlspecialchars($visitor['email']); ?></td>
                                        <td><?php echo $visitor['total_visits']; ?></td>
                                        <td><?php echo formatDateTime($visitor['last_visit']); ?></td>
                                        <td>
                                            <?php echo $visitor['current_visits'] > 0 ? 
                                                '<span class="badge bg-success">Yes</span>' : 
                                                '<span class="badge bg-secondary">No</span>'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <!-- Department Statistics -->
        <?php elseif ($report_type === 'departments'): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Department Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Total Visits</th>
                                    <th>Unique Visitors</th>
                                    <th>Avg Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $dept): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                        <td><?php echo $dept['total_visits']; ?></td>
                                        <td><?php echo $dept['unique_visitors']; ?></td>
                                        <td>
                                            <?php 
                                            if ($dept['avg_duration']) {
                                                $hours = floor($dept['avg_duration'] / 60);
                                                $minutes = $dept['avg_duration'] % 60;
                                                echo $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <!-- Peak Hours Chart -->
        <?php elseif ($report_type === 'peak_hours'): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Peak Hours Analysis</h5>
                </div>
                <div class="card-body">
                    <canvas id="peakHoursChart" height="100"></canvas>
                </div>
            </div>

        <!-- Duration Analysis -->
        <?php elseif ($report_type === 'duration'): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Visit Duration Analysis</h5>
                </div>
                <div class="card-body">
                    <canvas id="durationChart" height="100"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($report_type === 'summary' && !empty($chart_data)): ?>
        <script>
            const dailyCtx = document.getElementById('dailyVisitsChart').getContext('2d');
            new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($chart_data, 'date')); ?>,
                    datasets: [{
                        label: 'Daily Visits',
                        data: <?php echo json_encode(array_column($chart_data, 'visits')); ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>

    <?php if ($report_type === 'peak_hours' && !empty($chart_data)): ?>
        <script>
            const peakCtx = document.getElementById('peakHoursChart').getContext('2d');
            new Chart(peakCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($h) { return $h . ':00'; }, array_column($chart_data, 'hour'))); ?>,
                    datasets: [{
                        label: 'Visits by Hour',
                        data: <?php echo json_encode(array_column($chart_data, 'visits')); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>

    <?php if ($report_type === 'duration' && !empty($chart_data)): ?>
        <script>
            const durationCtx = document.getElementById('durationChart').getContext('2d');
            new Chart(durationCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($chart_data, 'duration_range')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($chart_data, 'visits')); ?>,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 205, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(153, 102, 255, 0.2)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
