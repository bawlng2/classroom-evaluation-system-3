<?php
require_once '../auth/session-check.php';
if(!in_array($_SESSION['role'], ['president', 'vice_president'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Teacher.php';

$database = new Database();
$db = $database->getConnection();
$teacher = new Teacher($db);

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$leader_department = isset($_SESSION['department']) ? $_SESSION['department'] : null;

if ($role === 'president') {
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
    <title>Leaders - Teachers</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Teachers</h3>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> (<?php echo ucfirst(str_replace('_',' ',$_SESSION['role'])); ?>)</span>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Active Teachers</h5>
                    <input type="text" id="teacherSearch" class="form-control w-auto" placeholder="Search teachers...">
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <?php if ($role === 'president'): ?>
                            <?php foreach ($teachers_by_department as $dept => $deptTeachers): ?>
                                <h6 class="mt-3"><?php echo htmlspecialchars($dept); ?></h6>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Department</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $counter = 1; foreach ($deptTeachers as $row): ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                                <td>
                                                    <a href="evaluation.php?teacher_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">Evaluate</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($teachers && $teachers->rowCount() > 0): ?>
                                        <?php $counter = 1; while($row = $teachers->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                            <td>
                                                <a href="evaluation.php?teacher_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">Evaluate</a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No teachers found for your department.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
