<?php
require_once '../auth/session-check.php';
if(!in_array($_SESSION['role'], ['dean', 'principal', 'chairperson', 'subject_coordinator'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Teacher.php';

$database = new Database();
$db = $database->getConnection();

$teacher = new Teacher($db);

// Handle form actions
// Handle form actions
if($_POST) {
    if(isset($_POST['add_teacher'])) {
        // Validate data
        $validation_errors = $teacher->validate($_POST);
        
        if (empty($validation_errors)) {
            // Check if teacher already exists
            if ($teacher->existsInDepartment($_POST['name'], $_SESSION['department'])) {
                $_SESSION['error'] = "A teacher with this name already exists in the department.";
            } else {
                $result = $teacher->create([
                    'name' => $_POST['name'],
                    'department' => $_SESSION['department'],
                    'email' => $_POST['email'],
                    'phone' => $_POST['phone']
                ]);
                
                if($result) {
                    $_SESSION['success'] = "Teacher added successfully!";
                } else {
                    $_SESSION['error'] = "Failed to add teacher. Please try again.";
                }
            }
        } else {
            $_SESSION['error'] = implode("<br>", $validation_errors);
        }
    }
    elseif(isset($_POST['update_teacher'])) {
        // Validate data
        $validation_errors = $teacher->validate($_POST);
        
        if (empty($validation_errors)) {
            $result = $teacher->update($_POST['teacher_id'], [
                'name' => $_POST['name'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone']
            ]);
            
            if($result) {
                $_SESSION['success'] = "Teacher updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update teacher. Please try again.";
            }
        } else {
            $_SESSION['error'] = implode("<br>", $validation_errors);
        }
    }
    elseif(isset($_POST['toggle_status'])) {
        $teacher_id = $_POST['teacher_id'];
        $result = $teacher->toggleStatus($teacher_id);
        
        if($result) {
            $current_teacher = $teacher->getById($teacher_id);
            $action = $current_teacher['status'] == 'active' ? 'activated' : 'deactivated';
            $_SESSION['success'] = "Teacher {$action} successfully!";
        } else {
            $_SESSION['error'] = "Failed to update teacher status. Please try again.";
        }
    }
    
    header("Location: teachers.php");
    exit();
}

// Get teachers for current department
$teachers = $teacher->getByDepartment($_SESSION['department']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - <?php echo $_SESSION['department']; ?></title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Manage Teachers - <?php echo $_SESSION['department']; ?></h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="fas fa-plus me-2"></i>Add New Teacher
                </button>
            </div>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Teachers List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <!-- In the table body section of teachers.php -->
                            <tbody>
                                <?php if($teachers->rowCount() > 0): ?>
                                <?php $counter = 1; ?>
                                <?php while($teacher_row = $teachers->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($teacher_row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher_row['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($teacher_row['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $teacher_row['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($teacher_row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary edit-teacher" 
                                                data-teacher-id="<?php echo $teacher_row['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($teacher_row['name']); ?>"
                                                data-email="<?php echo htmlspecialchars($teacher_row['email']); ?>"
                                                data-phone="<?php echo htmlspecialchars($teacher_row['phone']); ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="teacher_id" value="<?php echo $teacher_row['id']; ?>">
                                            <?php if($teacher_row['status'] == 'active'): ?>
                                            <button type="submit" name="toggle_status" 
                                                    class="btn btn-sm btn-warning"
                                                    onclick="return confirm('Are you sure you want to deactivate this teacher? They will not be available for new evaluations.')">
                                                <i class="fas fa-pause"></i> Deactivate
                                            </button>
                                            <?php else: ?>
                                            <button type="submit" name="toggle_status" 
                                                    class="btn btn-sm btn-success"
                                                    onclick="return confirm('Are you sure you want to activate this teacher? They will be available for new evaluations.')">
                                                <i class="fas fa-play"></i> Activate
                                            </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h5>No Teachers Found</h5>
                                        <p class="text-muted">Add your first teacher to get started with evaluations.</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                                            <i class="fas fa-plus me-2"></i>Add First Teacher
                                        </button>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Teacher Modal -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Teacher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle me-2"></i>
                                Teacher will be automatically assigned to <?php echo $_SESSION['department']; ?> department.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_teacher" class="btn btn-primary">Add Teacher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Teacher Modal -->
    <div class="modal fade" id="editTeacherModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Teacher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="teacher_id" id="edit_teacher_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_teacher" class="btn btn-primary">Update Teacher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Edit Teacher Modal
        document.querySelectorAll('.edit-teacher').forEach(button => {
            button.addEventListener('click', function() {
                const teacherId = this.getAttribute('data-teacher-id');
                const name = this.getAttribute('data-name');
                const email = this.getAttribute('data-email');
                const phone = this.getAttribute('data-phone');
                
                document.getElementById('edit_teacher_id').value = teacherId;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_email').value = email;
                document.getElementById('edit_phone').value = phone;
                
                const editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
                editModal.show();
            });
        });

        // Clear add modal when closed
        document.getElementById('addTeacherModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('name').value = '';
            document.getElementById('email').value = '';
            document.getElementById('phone').value = '';
        });
    </script>
</body>
</html>