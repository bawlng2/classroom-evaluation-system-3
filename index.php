<?php
session_start();

// Redirect to login if not authenticated, otherwise to appropriate dashboard
if(isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
	$role = $_SESSION['role'];
    
	if($role === 'edp') {
		header("Location: edp/dashboard.php");
	} 
	elseif(in_array($role, ['president', 'vice_president'])) {
		header("Location: leaders/dashboard.php");
	}
	else {
		header("Location: admin/dashboard.php");
	}
	exit();
} else {
	header("Location: login.php");
	exit();
}