<?php
require_once '../auth/session-check.php';
if(!in_array($_SESSION['role'], ['president', 'vice_president'])) {
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

// Use the leader's department from session to limit visible teachers
$leader_department = isset($_SESSION['department']) ? $_SESSION['department'] : null;
$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

if ($role === 'president') {
    // President can see all active teachers grouped by department
    $allTeachersStmt = $teacher->getAllTeachers('active');
    $teachers_by_department = [];
    while ($t = $allTeachersStmt->fetch(PDO::FETCH_ASSOC)) {
        $dept = $t['department'] ?? 'Unassigned';
        if (!isset($teachers_by_department[$dept])) {
            $teachers_by_department[$dept] = [];
        }
        $teachers_by_department[$dept][] = $t;
    }
} elseif ($leader_department) {
    // Vice President or leader limited to own department
    $teachers = $teacher->getActiveByDepartment($leader_department);
} else {
    $teachers = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaders Dashboard - Manage Teachers</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Leaders Dashboard</h3>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> (<?php echo ucfirst(str_replace('_',' ',$_SESSION['role'])); ?>)</span>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php
            // Get statistics similar to EDP dashboard
            $total_teachers = $teacher->getTotalTeachers();
            $total_evaluators = $user->getTotalEvaluators();

            $presidents = $user->getUsersByRole('president')->rowCount();
            $vice_presidents = $user->getUsersByRole('vice_president')->rowCount();
            $deans = $user->getUsersByRole('dean')->rowCount();
            $principals = $user->getUsersByRole('principal')->rowCount();
            $chairpersons = $user->getUsersByRole('chairperson')->rowCount();
            $coordinators = $user->getUsersByRole('subject_coordinator')->rowCount();
            ?>

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

            <!-- Quick Actions and Evaluators Summary -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="teachers.php" class="btn btn-primary">
                                    <i class="fas fa-users me-2"></i>View Teachers
                                </a>
                                <a href="../evaluators/evaluation.php" class="btn btn-outline-primary">
                                    <i class="fas fa-clipboard-check me-2"></i>New Evaluation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Evaluators Summary</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
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
</body>
</html>
