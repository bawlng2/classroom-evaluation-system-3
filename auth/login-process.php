
<?php
session_start();
require_once '../config/database.php';
require_once '../models/User.php';

if($_POST) {
    $database = new Database();
    $db = $database->getConnection();
    
    $user = new User($db);
    $user->username = $_POST['username'];
    $user->password = $_POST['password'];
    $user->role = $_POST['role'];
    
    // Debug information
    error_log("Login attempt - Username: " . $user->username . ", Role: " . $user->role);
    
    // Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Add debug logging
error_log("Login attempt - Username: " . $_POST['username'] . ", Role: " . $_POST['role']);

if($user->login()) {
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['role'] = strtolower($user->role); // force lowercase for consistency
        $_SESSION['department'] = $user->department;
        $_SESSION['name'] = $user->name;
            
        // Log the login
        $log_query = "INSERT INTO audit_logs (user_id, action, description, ip_address) 
                     VALUES (:user_id, 'LOGIN', 'User logged into the system', :ip_address)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->bindParam(':user_id', $user->id);
        $log_stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $log_stmt->execute();
        
        $role = strtolower($user->role);
        if($role == 'edp') {
            header("Location: ../edp/dashboard.php");
        } elseif(in_array($role, ['president', 'vice_president'])) {
            header("Location: ../leaders/dashboard.php");
        } elseif(in_array($role, ['dean', 'principal', 'chairperson', 'subject_coordinator'])) {
            header("Location: ../evaluators/dashboard.php");
        } else {
            header("Location: ../evaluators/dashboard.php");
        }
        exit();
    } else {
        $_SESSION['error'] = "Invalid username, password or role selection. Please try again.";
        header("Location: ../login.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: ../login.php");
    exit();
}
?>