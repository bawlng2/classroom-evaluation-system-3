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
$teachers = $teacher->getAllTeachers('active');
// Handle deactivate action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_id'], $_POST['action']) && $_POST['action'] === 'deactivate') {
    $teacher->updateStatus($_POST['teacher_id'], 'inactive');
    header('Location: teachers_manage.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid">
            <h3>Manage Teachers (Edit/Deactivate)</h3>
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
                                    <th style="vertical-align: middle;">
                                        <span style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                            Actions
                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTeacherModal" style="font-size:0.95em; padding: 0.25rem 0.75rem; line-height: 1;">
                                                <i class="fas fa-plus"></i> Add
                                            </button>
                                        </span>
                                    </th>
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
                            <input type="text" class="form-control" name="department" required>
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
                                        <span class="badge bg-success">Active</span>
                                    </td>
                                    <td>
                                        <a href="edit_teacher.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                        <form method="POST" action="" style="display:inline;">
                                            <input type="hidden" name="teacher_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" class="btn btn-sm btn-warning">Deactivate</button>
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
