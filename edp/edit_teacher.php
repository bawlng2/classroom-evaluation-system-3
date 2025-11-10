<?php
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

if (!isset($_GET['id'])) {
    header('Location: teachers_manage.php');
    exit();
}
$id = $_GET['id'];
$teacherData = $teacher->getById($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => $_POST['name'],
        'department' => $_POST['department'],
        'password' => $_POST['password'] ?? ''
    ];
    $teacher->update($id, $data);
    header('Location: teachers_manage.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teacher</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid">
            <h3>Edit Teacher</h3>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($teacherData['name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Department</label>
                    <input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars($teacherData['department']); ?>">
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="teachers.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
