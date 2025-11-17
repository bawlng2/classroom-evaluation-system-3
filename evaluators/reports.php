<?php
require_once '../auth/session-check.php';
// Allow department evaluators and leaders (president/vice_president) to access reports
if(!in_array($_SESSION['role'], ['dean', 'principal', 'chairperson', 'subject_coordinator', 'president', 'vice_president'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';
require_once '../models/Evaluation.php';
require_once '../models/Teacher.php';

$database = new Database();
$db = $database->getConnection();

$evaluation = new Evaluation($db);
$teacher = new Teacher($db);

// Get filter parameters
$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
if ($currentMonth >= 7) {
    $defaultAcademicYear = $currentYear . '-' . ($currentYear + 1);
} else {
    $defaultAcademicYear = ($currentYear - 1) . '-' . $currentYear;
}
$academic_year = $_GET['academic_year'] ?? $defaultAcademicYear;
$semester = $_GET['semester'] ?? '';
$teacher_id = $_GET['teacher_id'] ?? '';

// Get evaluations for reporting
$evaluatorFilter = in_array($_SESSION['role'], ['president', 'vice_president']) ? null : $_SESSION['user_id'];
$evaluations = $evaluation->getEvaluationsForReport($evaluatorFilter, $academic_year, $semester, $teacher_id);
$teachers = null;
if (in_array($_SESSION['role'], ['president', 'vice_president'])) {
    // Leaders can pick from all teachers
    $teachers = $teacher->getAllTeachers('active');
} else {
    $teachers = $teacher->getByDepartment($_SESSION['department']);
}

// Calculate statistics
$stats = $evaluation->getDepartmentStats($_SESSION['department'], $academic_year, $semester);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo $_SESSION['department']; ?></title>
    <?php include '../includes/header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Evaluation Reports - <?php echo $_SESSION['department']; ?></h3>
                <div>
                    <button class="btn btn-success me-2" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-2"></i>Export PDF
                    </button>
                    
                </div>
            </div>

            <!-- Success Message -->
            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Report Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="academic_year" class="form-label">Academic Year</label>
                            <select class="form-select" id="academic_year" name="academic_year">
                                <?php
                                $parts = explode('-', $defaultAcademicYear);
                                $startYear = (int)$parts[0];
                                for ($i = 0; $i < 6; $i++) {
                                    $y = $startYear - $i;
                                    $label = $y . '-' . ($y + 1);
                                    $selected = ($academic_year == $label) ? 'selected' : '';
                                    echo '<option value="' . $label . '" ' . $selected . '>' . $label . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="semester" class="form-label">Semester</label>
                            <select class="form-select" id="semester" name="semester">
                                <option value="">All Semesters</option>
                                <option value="1st" <?php echo $semester == '1st' ? 'selected' : ''; ?>>1st Semester</option>
                                <option value="2nd" <?php echo $semester == '2nd' ? 'selected' : ''; ?>>2nd Semester</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="teacher_id" class="form-label">Teacher</label>
                            <select class="form-select" id="teacher_id" name="teacher_id">
                                <option value="">All Teachers</option>
                                <?php 
                                if($teachers) {
                                    while($teacher_row = $teachers->fetch(PDO::FETCH_ASSOC)): 
                                ?>
                                <option value="<?php echo $teacher_row['id']; ?>" 
                                    <?php echo $teacher_id == $teacher_row['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher_row['name']); ?>
                                </option>
                                <?php 
                                    endwhile; 
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics Cards -->
          
            <!-- Evaluations Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Evaluation Details</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Observer</th>
                                    <th>Overall Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($evaluations && $evaluations->rowCount() > 0): ?>
                                <?php while($eval = $evaluations->fetch(PDO::FETCH_ASSOC)): 
                                    // Calculate overall rating for display
                                    $overallRating = number_format($eval['overall_rating'] ?? 0, 1);
                                    $ratingClass = '';
                                    if ($overallRating >= 4.6) $ratingClass = 'text-success';
                                    elseif ($overallRating >= 3.6) $ratingClass = 'text-primary';
                                    elseif ($overallRating >= 2.9) $ratingClass = 'text-info';
                                    elseif ($overallRating >= 1.8) $ratingClass = 'text-warning';
                                    else $ratingClass = 'text-danger';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($eval['teacher_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($eval['observation_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($eval['subject_observed']); ?></td>
                                    <td><?php echo htmlspecialchars($eval['evaluator_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="<?php echo $ratingClass; ?> fw-bold">
                                            <?php echo $overallRating; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="evaluation-view.php?id=<?php echo $eval['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <button onclick="exportEvaluationPDF(<?php echo $eval['id']; ?>)" class="btn btn-sm btn-outline-success me-1">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                        <h5>No Evaluation Data</h5>
                                        <p class="text-muted">No evaluations found for the selected filters.</p>
                                        <a href="evaluation.php" class="btn btn-primary">
                                            Evaluate Teacher
                                        </a>
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

    <?php include '../includes/footer.php'; ?>

    <script>
        // Export functions
        function exportToPDF() {
            const academicYear = document.getElementById('academic_year').value;
            const semester = document.getElementById('semester').value;
            const teacherId = document.getElementById('teacher_id').value;
            
            let url = `../controllers/export.php?type=pdf&report_type=summary&academic_year=${academicYear}`;
            if (semester) url += `&semester=${semester}`;
            if (teacherId) url += `&teacher_id=${teacherId}`;
            
            window.open(url, '_blank');
        }

        function exportToExcel() {
            const academicYear = document.getElementById('academic_year').value;
            const semester = document.getElementById('semester').value;
            const teacherId = document.getElementById('teacher_id').value;
            
            let url = `../controllers/export.php?type=excel&report_type=summary&academic_year=${academicYear}`;
            if (semester) url += `&semester=${semester}`;
            if (teacherId) url += `&teacher_id=${teacherId}`;
            
            window.open(url, '_blank');
        }
        
        // Per-evaluation export
        function exportEvaluationPDF(evaluationId) {
            window.open(`../controllers/export.php?type=pdf&evaluation_id=${evaluationId}&report_type=single`, '_blank');
        }
    </script>
</body>
</html>