<?php
session_start();

// Log the logout
if(isset($_SESSION['user_id'])) {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Verify that the user still exists to avoid FK constraint errors
    $checkUser = $db->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
    $checkUser->bindParam(':id', $_SESSION['user_id']);
    $checkUser->execute();

    if($checkUser->rowCount() > 0) {
        $log_query = "INSERT INTO audit_logs (user_id, action, description, ip_address) 
                     VALUES (:user_id, 'LOGOUT', 'User logged out of the system', :ip_address)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $log_stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $log_stmt->execute();
    } else {
        // User not found (maybe deleted) — skip logging to avoid FK violation
        error_log("Logout: user_id " . $_SESSION['user_id'] . " not found, skipping audit log.");
    }
}

// Destroy all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: ../login.php");
exit();
?>