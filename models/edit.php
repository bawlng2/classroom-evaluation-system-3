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

$teacher_id = $_GET['id'] ?? 0;
$teacher_data = $teacher->getById($teacher_id);

if(!$teacher_data) {
    $_SESSION['error'] = "Teacher not found.";
    header("Location: teachers.php");
    exit();
}

// Handle form submission
if($_POST && isset($_POST['update_teacher'])) {
    $validation_errors = $teacher->validate($_POST);
    
    if (empty($validation_errors)) {
        $result = $teacher->update($teacher_id, [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone']
        ]);
        
        if($result) {
            $_SESSION['success'] = "Teacher updated successfully!";
            header("Location: teachers.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to update teacher. Please try again.";
        }
    } else {
        $_SESSION['error'] = implode("<br>", $validation_errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teacher - <?php echo $_SESSION['department']; ?></title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Edit Teacher</h3>
                <a href="teachers.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Teachers
                </a>
            </div>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Teacher Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($teacher_data['name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department" 
                                           value="<?php echo htmlspecialchars($teacher_data['department']); ?>" readonly>
                                    <small class="text-muted">Department cannot be changed</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($teacher_data['email'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($teacher_data['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <div>
                                        <span class="badge bg-<?php echo $teacher_data['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($teacher_data['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Created</label>
                                    <div>
                                        <?php echo date('M j, Y', strtotime($teacher_data['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="update_teacher" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Teacher
                            </button>
                            <a href="teachers.php" class="btn btn-secondary">Cancel</a>
                            
                            <?php if($teacher_data['status'] == 'active'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
                                <button type="submit" name="toggle_status" 
                                        class="btn btn-warning float-end"
                                        onclick="return confirm('Are you sure you want to deactivate this teacher? They will not be available for new evaluations.')">
                                    <i class="fas fa-pause me-2"></i>Deactivate Teacher
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
                                <button type="submit" name="toggle_status" 
                                        class="btn btn-success float-end"
                                        onclick="return confirm('Are you sure you want to activate this teacher? They will be available for new evaluations.')">
                                    <i class="fas fa-play me-2"></i>Activate Teacher
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Evaluation History -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Evaluation History</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Fetch evaluations directly using PDO in case Evaluation::getByTeacher() is not defined
                    $query = "SELECT e.*, u.name AS evaluator_name
                              FROM evaluations e
                              LEFT JOIN users u ON e.evaluator_id = u.id
                              WHERE e.teacher_id = :teacher_id
                              ORDER BY e.observation_date DESC";
                    $stmt = $db->prepare($query);
                    $stmt->execute([':teacher_id' => $teacher_id]);
                    $teacher_evaluations = $stmt;
                    ?>
                    
                    <?php if($teacher_evaluations->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Overall Rating</th>
                                    <th>Evaluator</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($eval = $teacher_evaluations->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($eval['observation_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($eval['subject_observed']); ?></td>
                                    <td><?php echo htmlspecialchars($eval['observation_type']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $eval['overall_avg'] >= 4.6 ? 'success' : 
                                                             ($eval['overall_avg'] >= 3.6 ? 'primary' : 
                                                             ($eval['overall_avg'] >= 2.9 ? 'info' : 'warning')); ?>">
                                            <?php echo number_format($eval['overall_avg'], 1); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($eval['evaluator_name']); ?></td>
                                    <td>
                                        <a href="evaluation-view.php?id=<?php echo $eval['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <h5>No Evaluations Yet</h5>
                        <p class="text-muted">This teacher hasn't been evaluated yet.</p>
                        <a href="evaluation.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Start Evaluation
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>