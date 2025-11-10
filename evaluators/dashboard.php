<?php
require_once '../auth/session-check.php';
// Only allow department evaluators; redirect president/vp to leaders dashboard
if(!in_array($_SESSION['role'], ['dean', 'principal', 'chairperson', 'subject_coordinator'])) {
    if(in_array($_SESSION['role'], ['president', 'vice_president'])) {
        header("Location: ../leaders/dashboard.php");
        exit();
    } else {
        header("Location: ../login.php");
        exit();
    }
}

require_once '../config/database.php';
require_once '../models/Teacher.php';
require_once '../models/Evaluation.php';

$database = new Database();
$db = $database->getConnection();

$teacher = new Teacher($db);
$evaluation = new Evaluation($db);

$department_teachers = $teacher->getByDepartment($_SESSION['department']);
$stats = $evaluation->getAdminStats($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AI Classroom Evaluation</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Admin Dashboard - <?php echo $_SESSION['department']; ?></h3>
                <span>Welcome, <?php echo $_SESSION['name']; ?></span>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-4">
                    <div class="dashboard-stat stat-1">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <div class="number"><?php echo $department_teachers->rowCount(); ?></div>
                        <div>Teachers</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-stat stat-2">
                        <i class="fas fa-clipboard-check"></i>
                        <div class="number"><?php echo $stats['completed_evaluations']; ?></div>
                        <div>Completed Evaluations</div>
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
                            <a href="evaluation.php" class="btn btn-primary me-2">
                                <i class="fas fa-plus me-2"></i>New Evaluation
                            </a>
                            <a href="teachers.php" class="btn btn-outline-primary me-2">
                                <i class="fas fa-users me-2"></i>Manage Teachers
                            </a>
                            <a href="reports.php" class="btn btn-outline-primary">
                                <i class="fas fa-chart-bar me-2"></i>View Reports
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Evaluations</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $recent_evals = $evaluation->getRecentEvaluations($_SESSION['user_id'], 5);
                            if($recent_evals->rowCount() > 0):
                            ?>
                            <div class="list-group">
                                <?php while($eval = $recent_evals->fetch(PDO::FETCH_ASSOC)): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($eval['teacher_name']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($eval['observation_date'])); ?> | 
                                                <?php echo htmlspecialchars($eval['subject_observed']); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <span class="badge bg-<?php 
                                                echo $eval['overall_avg'] >= 4.6 ? 'success' : 
                                                    ($eval['overall_avg'] >= 3.6 ? 'primary' : 
                                                    ($eval['overall_avg'] >= 2.9 ? 'info' : 'warning')); ?>">
                                                <?php echo number_format($eval['overall_avg'], 1); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-muted text-center py-3">
                                <i class="fas fa-clipboard-list fa-2x mb-3"></i><br>
                                No evaluations yet. <a href="evaluation.php">Start your first evaluation</a>.
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>