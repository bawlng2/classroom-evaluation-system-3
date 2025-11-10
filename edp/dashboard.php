<?php
require_once '../auth/session-check.php';
if($_SESSION['role'] != 'edp') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Teacher.php';
require_once '../models/Evaluation.php';
require_once '../models/User.php';

$database = new Database();
$db = $database->getConnection();

$teacher = new Teacher($db);
$evaluation = new Evaluation($db);
$user = new User($db);

// Get statistics
$total_teachers = $teacher->getTotalTeachers();
$total_evaluators = $user->getTotalEvaluators(); // Total users who can evaluate


// Get users by role for the summary
$presidents = $user->getUsersByRole('president')->rowCount();
$vice_presidents = $user->getUsersByRole('vice_president')->rowCount();
$deans = $user->getUsersByRole('dean')->rowCount();
$principals = $user->getUsersByRole('principal')->rowCount();
$chairpersons = $user->getUsersByRole('chairperson')->rowCount();
$coordinators = $user->getUsersByRole('subject_coordinator')->rowCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDP Dashboard - AI Classroom Evaluation</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>EDP Dashboard</h3>
                <span>Welcome, <?php echo $_SESSION['name']; ?></span>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-6">
                    <div class="dashboard-stat stat-1">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <div class="number"><?php echo $total_teachers; ?></div>
                        <div>Total Teachers</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="dashboard-stat stat-2">
                        <i class="fas fa-user-tie"></i>
                        <div class="number"><?php echo $total_evaluators; ?></div>
                        <div>Total Evaluators</div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="users.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Manage Evaluators
                                </a>
                                <a href="teachers.php" class="btn btn-outline-primary">
                                    <i class="fas fa-users me-2"></i>Manage Teachers
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Evaluators Summary -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Evaluators Summary</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Presidents
                                    <span class="badge bg-primary rounded-pill"><?php echo $presidents; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Vice Presidents
                                    <span class="badge bg-primary rounded-pill"><?php echo $vice_presidents; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Deans
                                    <span class="badge bg-primary rounded-pill"><?php echo $deans; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Principals
                                    <span class="badge bg-primary rounded-pill"><?php echo $principals; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Chairpersons
                                    <span class="badge bg-primary rounded-pill"><?php echo $chairpersons; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Subject Coordinators
                                    <span class="badge bg-primary rounded-pill"><?php echo $coordinators; ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>