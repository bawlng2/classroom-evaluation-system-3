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
require_once '../models/User.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'create':
                $role = $_POST['role'];
                $department = '';
                // Only require department/category for these roles
                if (in_array($role, ['dean', 'principal', 'subject_coordinator', 'chairperson'])) {
                        $department = $_POST['department'] ?? ''; // Original line retained for context
                }
                    $departments = [
                        'CTE' => 'College of Teacher Education',
                        'CAS' => 'College of Arts and Sciences',
                        'CCJE' => 'College of Criminal Justice Education',
                        'CBM' => 'College of Business Management',
                        'CCIS' => 'College of Computing and Information Sciences',
                        'CTHM' => 'College of Tourism and Hospitality Management',
                        'BASIC_ED' => 'BASIC ED (Nursery, Kindergarten, Elementary, Junior High School)',
                        'SHS' => 'Senior High School (SHS)'
                    ];
                    // If BASIC ED is selected, always store as 'BASIC ED' in the database
                    if ($department === 'BASIC_ED') {
                        $department = 'BASIC ED';
                    }
                    $data = [
                        'username' => $_POST['username'],
                        'password' => $_POST['password'],
                        'name' => $_POST['name'],
                        'role' => $role,
                        'department' => $department
                    ];
                $createResult = $user->create($data);
                if($createResult === true) {
                    $_SESSION['success'] = ucfirst(str_replace('_',' ',$role)) . " account created successfully.";
                } elseif($createResult === 'exists') {
                    $_SESSION['error'] = "Username already exists. Please choose a different username.";
                } else {
                    $_SESSION['error'] = "Failed to create " . str_replace('_',' ',$role) . " account.";
                }
                break;

            case 'deactivate':
                if($user->updateStatus($_POST['user_id'], 'inactive')) {
                    $_SESSION['success'] = "Dean account deactivated successfully.";
                } else {
                    $_SESSION['error'] = "Failed to deactivate dean account.";
                }
                break;

            case 'activate':
                if($user->updateStatus($_POST['user_id'], 'active')) {
                    $_SESSION['success'] = "Dean account activated successfully.";
                } else {
                    $_SESSION['error'] = "Failed to activate dean account.";
                }
                break;
        }
        header("Location: users.php");
        exit();
    }
}

// Get list of evaluators (all roles except EDP)
$roles = ['president', 'vice_president', 'dean', 'principal', 'subject_coordinator', 'chairperson'];
$evaluators = [];
foreach ($roles as $role) {
    if ($selected_department) {
        $evaluators[$role] = $user->getUsersByRoleAndDepartment($role, $selected_department, 'active');
    } else {
        $evaluators[$role] = $user->getUsersByRole($role, 'active');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Deans - AI Classroom Evaluation</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Manage Evaluators</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEvaluatorModal">
                    <i class="fas fa-plus me-2"></i>Add
                </button>
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
                                    <th>Actions</th>
                                </tr>
                            </thead>
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
                                        <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_evaluator.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="<?php echo $row['status'] == 'active' ? 'deactivate' : 'activate'; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $row['status'] == 'active' ? 'warning' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $row['status'] == 'active' ? 'user-slash' : 'user-check'; ?>"></i>
                                                <?php echo $row['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
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

    <!-- Add Evaluator Modal -->
    <div class="modal fade" id="addEvaluatorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Evaluator</h5>
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
                                <?php
                                $departments = [
                                    'CTE' => 'College of Teacher Education',
                                    'BSED' => 'Bachelor of Secondary Education',
                                    'CAS' => 'College of Arts and Sciences',
                                    'CCJE' => 'College of Criminal Justice Education',
                                    'CBM' => 'College of Business Management',
                                    'CCIS' => 'College of Computing and Information Sciences',
                                    'CTHM' => 'College of Tourism and Hospitality Management',
                                    'ELEM' => 'Elementary',
                                    'JHS' => 'Junior High School',
                                    'SHS' => 'Senior High School (SHS)'
                                ];
                                foreach($departments as $key => $value):
                                ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
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
</body>
</html>