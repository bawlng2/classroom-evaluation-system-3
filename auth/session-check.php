<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$current_file = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'];

// Check if user is accessing correct directory based on role
if($user_role === 'edp' && strpos($_SERVER['REQUEST_URI'], '/edp/') === false) {
    header("Location: ../edp/dashboard.php");
    exit();
}

// Leaders (President, Vice President) should access the leaders area
if(in_array($user_role, ['president', 'vice_president']) && strpos($_SERVER['REQUEST_URI'], '/leaders/') === false) {
    header("Location: ../leaders/dashboard.php");
    exit();
}

// Department-level administrators
if (
    in_array($user_role, ['dean', 'principal', 'chairperson', 'subject_coordinator']) &&
    strpos($_SERVER['REQUEST_URI'], '/evaluators/') === false &&
    basename($_SERVER['PHP_SELF']) !== 'dashboard.php'
) {
    header("Location: ../evaluators/dashboard.php");
    exit();
}
?>