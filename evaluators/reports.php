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
// Compute sensible default academic year (e.g., 2025-2026 when current date is in latter half of 2025)
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
// Leaders (president/vice_president) should see all evaluations; other roles see their own
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
                    <button class="btn btn-primary" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-2"></i>Export Excel
                    </button>
                </div>
            </div>

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
                                // Show the default academic year and a few previous years
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
                                <?php while($teacher_row = $teachers->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $teacher_row['id']; ?>" 
                                    <?php echo $teacher_id == $teacher_row['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher_row['name']); ?>
                                </option>
                                <?php endwhile; ?>
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



            <!-- Evaluations Table Restored -->
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
                                    <!-- Removed: Comm, Mgmt, Assess, Overall columns -->
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($evaluations->rowCount() > 0): ?>
                                <?php while($eval = $evaluations->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($eval['teacher_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($eval['observation_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($eval['subject_observed']); ?></td>
                                    <!-- Removed: Comm, Mgmt, Assess, Overall data cells -->
                                    <td>
                                        <a href="evaluation-view.php?id=<?php echo $eval['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                        <h5>No Evaluation Data</h5>
                                        <p class="text-muted">No evaluations found for the selected filters.</p>
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
        // Rating Distribution Chart
        const ratingCtx = document.getElementById('ratingChart').getContext('2d');
        const ratingChart = new Chart(ratingCtx, {
            type: 'doughnut',
            data: {
                labels: ['Excellent (4.6-5.0)', 'Very Satisfactory (3.6-4.5)', 'Satisfactory (2.9-3.5)', 'Below Satisfactory (1.8-2.5)', 'Needs Improvement (1.0-1.5)'],
                datasets: [{
                    data: [12, 8, 5, 2, 1], // Sample data - replace with actual data
                    backgroundColor: [
                        '#28a745',
                        '#007bff',
                        '#17a2b8',
                        '#ffc107',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Category Averages Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: ['Communications', 'Management', 'Assessment'],
                datasets: [{
                    label: 'Average Rating',
                    data: [4.2, 4.0, 3.8], // Sample data - replace with actual data
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(155, 89, 182, 0.8)',
                        'rgba(46, 204, 113, 0.8)'
                    ],
                    borderColor: [
                        'rgb(52, 152, 219)',
                        'rgb(155, 89, 182)',
                        'rgb(46, 204, 113)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5
                    }
                }
            }
        });

        // Export functions
        function exportToPDF() {
            alert('PDF export functionality would be implemented here. This would generate a comprehensive report.');
            // In a real implementation, this would call a PHP script to generate PDF
        }

        function exportToExcel() {
            alert('Excel export functionality would be implemented here. This would download an Excel file of the report.');
            // In a real implementation, this would call a PHP script to generate Excel
        }
        
        // Per-evaluation export helpers
        function exportEvaluationPDF(evaluationId) {
            window.open(`../controllers/export.php?type=pdf&evaluation_id=${evaluationId}&report_type=single`, '_blank');
        }

        function exportEvaluationCSV(evaluationId) {
            window.open(`../controllers/export.php?type=csv&evaluation_id=${evaluationId}&report_type=single`, '_blank');
        }
    </script>
</body>
</html>