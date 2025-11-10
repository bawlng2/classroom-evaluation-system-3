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
$teachers = $teacher->getAllTeachers('inactive');
// Handle activate action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_id'], $_POST['action']) && $_POST['action'] === 'activate') {
    $teacher->updateStatus($_POST['teacher_id'], 'active');
    header('Location: deactivated_teachers.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deactivated Teachers</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid">
            <h3>Deactivated Teachers</h3>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Department</th>
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
                                    <td>
                                        <span class="badge bg-secondary">Inactive</span>
                                    </td>
                                    <td>
                                        <form method="POST" action="" style="display:inline;">
                                            <input type="hidden" name="teacher_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="btn btn-sm btn-success">Activate</button>
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
</body>
</html>
