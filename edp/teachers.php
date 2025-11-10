<?php
$departments = [
    'CTE' => 'College of Teacher Education',
    
    'CAS' => 'College of Arts and Sciences',
    'CCJE' => 'College of Criminal Justice Education',
    'CBM' => 'College of Business Management',
    'CCIS' => 'College of Computing and Information Sciences',
    'CTHM' => 'College of Tourism and Hospitality Management',
    'BASIC ED' => 'BASIC ED (Nursery, Kindergarten, Elementary, Junior High School)',
    'SHS' => 'Senior High School (SHS)'
];
$selected_department = isset($_GET['department']) ? $_GET['department'] : '';
require_once '../auth/session-check.php';
if($_SESSION['role'] != 'edp') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Teacher.php';

$database = new Database();
$db = $database->getConnection();
$teacher = new Teacher($db);

// Handle teacher deactivation
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if($_POST['action'] == 'deactivate') {
        if($teacher->updateStatus($_POST['teacher_id'], 'inactive')) {
            $_SESSION['success'] = "Teacher deactivated successfully.";
        } else {
            $_SESSION['error'] = "Failed to deactivate teacher.";
        }
    } elseif($_POST['action'] == 'activate') {
        if($teacher->updateStatus($_POST['teacher_id'], 'active')) {
            $_SESSION['success'] = "Teacher activated successfully.";
        } else {
            $_SESSION['error'] = "Failed to activate teacher.";
        }
    }
    header("Location: teachers.php");
    exit();
}

// Get only active teachers
$teachers = $selected_department ? $teacher->getActiveByDepartment($selected_department) : $teacher->getAllTeachers('active');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - AI Classroom Evaluation</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Manage Teachers</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="fas fa-plus me-2"></i>Add
                </button>
            </div>
    <!-- Add Teacher Modal -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Teacher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department" required>
                                <option value="">Select Department</option>
                                <?php foreach($departments as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Teacher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="get" class="mb-3 d-flex align-items-center">
                <label class="me-2 mb-0">Department:</label>
                <select name="department" class="form-select w-auto me-2" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php if($selected_department == $key) echo 'selected'; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Teachers List</h5>
                        <div class="form-group">
                            <input type="text" id="teacherSearch" class="form-control" placeholder="Search teachers...">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = 1;
                                while($row = $teachers->fetch(PDO::FETCH_ASSOC)): 
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_teacher.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="teacher_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="<?php echo $row['status'] == 'active' ? 'deactivate' : 'activate'; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $row['status'] == 'active' ? 'warning' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $row['status'] == 'active' ? 'user-slash' : 'user-check'; ?>"></i>
                                                <?php echo $row['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
    // Simple search functionality
    document.getElementById('teacherSearch').addEventListener('keyup', function() {
        let searchText = this.value.toLowerCase();
        let tableRows = document.querySelectorAll('tbody tr');
        
        tableRows.forEach(row => {
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        });
    });
    </script>
</body>
</html>