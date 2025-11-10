<?php
require_once '../auth/session-check.php';
if($_SESSION['role'] != 'edp') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';
require_once '../models/User.php';
$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$roles = ['president', 'vice_president', 'dean', 'principal', 'subject_coordinator', 'chairperson'];
$evaluators = [];
foreach ($roles as $role) {
    $evaluators[$role] = $user->getUsersByRole($role, 'active');
}
// Handle deactivate action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action']) && $_POST['action'] === 'deactivate') {
    $user->updateStatus($_POST['user_id'], 'inactive');
    header('Location: evaluator_manage.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Evaluators</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid">
            <h3>Manage Evaluators (Edit/Deactivate)</h3>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th style="vertical-align: middle;">
                                        <span style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                            Actions
                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEvaluatorModal" style="font-size:0.95em; padding: 0.25rem 0.75rem; line-height: 1;">
                                                <i class="fas fa-plus"></i> Add
                                            </button>
                                        </span>
                                    </th>
                                </tr>
                            </thead>
    <!-- Add Evaluator Modal -->
    <div class="modal fade" id="addEvaluatorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Evaluator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="users.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="roleSelect" required>
                                <option value="">Select Role</option>
                                <option value="president">President</option>
                                <option value="vice_president">Vice President</option>
                                <option value="dean">Dean</option>
                                <option value="principal">Principal</option>
                                <option value="subject_coordinator">Subject Coordinator</option>
                                <option value="chairperson">Chairperson</option>
                            </select>
                        </div>
                        <div class="mb-3" id="departmentDiv" style="display:none;">
                            <label class="form-label">Department/Category</label>
                            <select class="form-select" name="department" id="departmentSelect">
                                <option value="">Select Department/Category</option>
                                <option value="CTE">College of Teacher Education</option>
                                <option value="BSED">Bachelor of Secondary Education</option>
                                <option value="CAS">College of Arts and Sciences</option>
                                <option value="CCJE">College of Criminal Justice Education</option>
                                <option value="CBM">College of Business Management</option>
                                <option value="CCIS">College of Computing and Information Sciences</option>
                                <option value="CTHM">College of Tourism and Hospitality Management</option>
                                <option value="BASIC ED">BASIC ED (Nursery, Kindergarten, Elementary, Junior High School)</option>
                                <option value="SHS">Senior High School (SHS)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    // Show/hide department/category based on role
    document.addEventListener('DOMContentLoaded', function() {
        var roleSelect = document.getElementById('roleSelect');
        var departmentDiv = document.getElementById('departmentDiv');
        var departmentSelect = document.getElementById('departmentSelect');
        function toggleDepartment() {
            var role = roleSelect.value;
            if(role === 'dean' || role === 'principal' || role === 'subject_coordinator' || role === 'chairperson') {
                departmentDiv.style.display = '';
                departmentSelect.required = true;
            } else {
                departmentDiv.style.display = 'none';
                departmentSelect.required = false;
                departmentSelect.value = '';
            }
        }
        roleSelect.addEventListener('change', toggleDepartment);
        toggleDepartment();
    });
    </script>
                            <tbody>
                                <?php 
                                $counter = 1;
                                foreach ($roles as $role) {
                                    while($row = $evaluators[$role]->fetch(PDO::FETCH_ASSOC)):
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td>
                                        <span class="badge bg-success">Active</span>
                                    </td>
                                    <td>
                                        <a href="edit_evaluator.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                        <form method="POST" action="" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" class="btn btn-sm btn-warning">Deactivate</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
