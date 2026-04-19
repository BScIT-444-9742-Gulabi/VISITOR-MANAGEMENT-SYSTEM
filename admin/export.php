<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

$type = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

if (empty($type)) {
    header('Location: reports.php');
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="vms_report_' . $type . '_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

switch ($type) {
    case 'visitors':
        // Export visitors data
        fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Address', 'ID Proof Type', 'ID Proof Number', 'Registered On']);
        
        $stmt = $db->prepare("
            SELECT * FROM visitors 
            WHERE DATE(created_at) BETWEEN ? AND ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$date_from, $date_to]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['email'],
                $row['phone'],
                $row['address'],
                $row['id_proof_type'],
                $row['id_proof_number'],
                $row['created_at']
            ]);
        }
        break;
        
    case 'visits':
        // Export visits data
        fputcsv($output, ['ID', 'Visitor Name', 'Email', 'Phone', 'Purpose', 'Person to Meet', 'Department', 'Visit Date', 'Status', 'Registration Date', 'Actual Arrival', 'Actual Departure']);
        
        $stmt = $db->prepare("
            SELECT v.*, vis.name, vis.email, vis.phone 
            FROM visits v 
            LEFT JOIN visitors vis ON v.visitor_id = vis.id 
            WHERE DATE(v.created_at) BETWEEN ? AND ? 
            ORDER BY v.created_at DESC
        ");
        $stmt->execute([$date_from, $date_to]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['email'],
                $row['phone'],
                $row['purpose'],
                $row['person_to_meet'],
                $row['department'],
                $row['visit_date'],
                $row['status'],
                $row['created_at'],
                $row['actual_arrival'],
                $row['actual_departure']
            ]);
        }
        break;
        
    case 'activity':
        // Export activity logs
        fputcsv($output, ['ID', 'User', 'Visitor', 'Action', 'Description', 'IP Address', 'Date/Time']);
        
        $stmt = $db->prepare("
            SELECT al.*, u.username as user_name, vis.name as visitor_name 
            FROM activity_logs al 
            LEFT JOIN users u ON al.user_id = u.id 
            LEFT JOIN visitors vis ON al.visitor_id = vis.id 
            WHERE DATE(al.created_at) BETWEEN ? AND ? 
            ORDER BY al.created_at DESC
        ");
        $stmt->execute([$date_from, $date_to]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['user_name'] ?: 'System',
                $row['visitor_name'] ?: 'N/A',
                $row['action'],
                $row['description'],
                $row['ip_address'],
                $row['created_at']
            ]);
        }
        break;
        
    case 'summary':
        // Export summary statistics
        fputcsv($output, ['Report Type', 'Date Range', 'Total Visits', 'Unique Visitors', 'Currently Inside', 'Approved', 'Pending', 'Rejected']);
        
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
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        fputcsv($output, [
            'Summary Report',
            "$date_from to $date_to",
            $stats['total_visits'],
            $stats['unique_visitors'],
            $stats['currently_inside'],
            $stats['approved'],
            $stats['pending'],
            $stats['rejected']
        ]);
        
        // Add department breakdown
        fputcsv($output, []);
        fputcsv($output, ['Department Statistics']);
        fputcsv($output, ['Department', 'Total Visits', 'Unique Visitors', 'Average Duration (minutes)']);
        
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
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['department'],
                $row['total_visits'],
                $row['unique_visitors'],
                round($row['avg_duration'], 2)
            ]);
        }
        break;
        
    default:
        header('Location: reports.php');
        exit;
}

fclose($output);
exit;
?>
