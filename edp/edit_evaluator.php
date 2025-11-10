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

if (!isset($_GET['id'])) {
    header('Location: evaluator_manage.php');
    exit();
}
$id = $_GET['id'];
$evaluator = $user->getById($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => $_POST['name'],
        'username' => $_POST['username'],
        'role' => $_POST['role'],
        'department' => $_POST['department'],
        'password' => $_POST['password'] ?? ''
    ];
    $user->update($id, $data);
    header('Location: evaluator_manage.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Evaluator</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid">
            <h3>Edit Evaluator</h3>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($evaluator['name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($evaluator['username']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role" required>
                        <option value="">Select Role</option>
                        <?php $roles = ['president' => 'President', 'vice_president' => 'Vice President', 'dean' => 'Dean', 'principal' => 'Principal', 'subject_coordinator' => 'Subject Coordinator', 'chairperson' => 'Chairperson'];
                        foreach($roles as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php if($evaluator['role'] == $key) echo 'selected'; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Department</label>
                    <select class="form-select" name="department">
                        <option value="">Select Department/Category</option>
                        <?php $departments = [
                            'CTE' => 'College of Teacher Education',
                            'CAS' => 'College of Arts and Sciences',
                            'CCJE' => 'College of Criminal Justice Education',
                            'CBM' => 'College of Business Management',
                            'CCIS' => 'College of Computing and Information Sciences',
                            'CTHM' => 'College of Tourism and Hospitality Management',
                            'BASIC ED' => 'BASIC ED (Nursery, Kindergarten, Elementary, Junior High School)',
                            'SHS' => 'Senior High School (SHS)'
                        ];
                        foreach($departments as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php if($evaluator['department'] == $key) echo 'selected'; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password (leave blank to keep current)</label>
                    <input type="password" class="form-control" name="password">
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="users.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
                            if(role === 'president' || role === 'vice_president') {
                                departmentGroup.style.display = 'none';
                            } else if(role === 'dean' || role === 'principal' || role === 'subject_coordinator' || role === 'chairperson') {
    <script src="../assets/js/main.js"></script>
</body>
</html>
