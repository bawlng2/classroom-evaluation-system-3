<?php
require_once '../auth/session-check.php';
if($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Teacher.php';

$database = new Database();
$db = $database->getConnection();

$teacher = new Teacher($db);

// Handle teacher status toggle
if($_POST && isset($_POST['toggle_status'])) {
    $teacher_id = $_POST['teacher_id'];
    $result = $teacher->toggleStatus($teacher_id);
    
    if($result) {
        $current_teacher = $teacher->getById($teacher_id);
        $action = $current_teacher['status'] == 'active' ? 'activated' : 'deactivated';
        $_SESSION['success'] = "Teacher {$action} successfully!";
    } else {
        $_SESSION['error'] = "Failed to update teacher status. Please try again.";
    }
    
    // Redirect back to previous page
    $redirect_url = $_POST['redirect_url'] ?? 'teachers.php';
    header("Location: $redirect_url");
    exit();
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: teachers.php");
    exit();
}
?>