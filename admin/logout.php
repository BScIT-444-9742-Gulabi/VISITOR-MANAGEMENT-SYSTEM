<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

// Log activity before destroying session
if (isset($_SESSION['user_id'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        logActivity($db, $_SESSION['user_id'], null, null, 'logout', 'User logged out: ' . $_SESSION['username']);
    } catch (Exception $e) {
        // Log error but continue with logout
        error_log("Failed to log logout activity: " . $e->getMessage());
    }
}

// Destroy session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header('Location: login.php');
exit;
?>
