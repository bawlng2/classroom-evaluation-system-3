<?php
require_once '../auth/session-check.php';
if($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Evaluation.php';

$database = new Database();
$db = $database->getConnection();

$evaluation = new Evaluation($db);

// Corrected line: Fixed $_GET variable and method name
$evaluation_id = $_GET['id'] ?? 0;
$eval_data = $evaluation->getEvaluationById($evaluation_id);

if(!$eval_data) {
    $_SESSION['error'] = "Evaluation not found.";
    header("Location: reports.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Evaluation - <?php echo $_SESSION['department']; ?></title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Evaluation Details</h3>
                <div>
                    <button class="btn btn-secondary" onclick="window.history.back()">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </button>
                    <button class="btn btn-primary ms-2" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                </div>
            </div>

            <!-- Evaluation Details Display -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Evaluation Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Teacher Name:</th>
                                    <td><?php echo htmlspecialchars($eval_data['teacher_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Department:</th>
                                    <td><?php echo htmlspecialchars($eval_data['department']); ?></td>
                                </tr>
                                <tr>
                                    <th>Academic Year:</th>
                                    <td><?php echo htmlspecialchars($eval_data['academic_year']); ?></td>
                                </tr>
                                <tr>
                                    <th>Semester:</th>
                                    <td><?php echo htmlspecialchars($eval_data['semester']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Subject Observed:</th>
                                    <td><?php echo htmlspecialchars($eval_data['subject_observed']); ?></td>
                                </tr>
                                <tr>
                                    <th>Observation Date:</th>
                                    <td><?php echo date('F j, Y', strtotime($eval_data['observation_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Observation Type:</th>
                                    <td><?php echo htmlspecialchars($eval_data['observation_type']); ?></td>
                                </tr>
                                <tr>
                                    <th>Evaluator:</th>
                                    <td><?php echo htmlspecialchars($eval_data['evaluator_name']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ratings Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Rating Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="rating-card">
                                <h6>Communications</h6>
                                <div class="display-4 text-primary"><?php echo number_format($eval_data['communications_avg'], 1); ?></div>
                                <small class="text-muted">Average Rating</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="rating-card">
                                <h6>Management</h6>
                                <div class="display-4 text-info"><?php echo number_format($eval_data['management_avg'], 1); ?></div>
                                <small class="text-muted">Average Rating</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="rating-card">
                                <h6>Assessment</h6>
                                <div class="display-4 text-warning"><?php echo number_format($eval_data['assessment_avg'], 1); ?></div>
                                <small class="text-muted">Average Rating</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="rating-card">
                                <h6>Overall</h6>
                                <div class="display-4 text-success"><?php echo number_format($eval_data['overall_avg'], 1); ?></div>
                                <small class="text-muted">
                                    <?php
                                    $rating = 'Needs Improvement';
                                    if($eval_data['overall_avg'] >= 4.6) $rating = 'Excellent';
                                    elseif($eval_data['overall_avg'] >= 3.6) $rating = 'Very Satisfactory';
                                    elseif($eval_data['overall_avg'] >= 2.9) $rating = 'Satisfactory';
                                    elseif($eval_data['overall_avg'] >= 1.8) $rating = 'Below Satisfactory';
                                    echo $rating;
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Qualitative Assessment -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-star me-2"></i>Strengths</h6>
                        </div>
                        <div class="card-body">
                            <?php echo nl2br(htmlspecialchars($eval_data['strengths'] ?? 'No strengths recorded.')); ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Areas for Improvement</h6>
                        </div>
                        <div class="card-body">
                            <?php echo nl2br(htmlspecialchars($eval_data['improvement_areas'] ?? 'No improvement areas recorded.')); ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Recommendations</h6>
                        </div>
                        <div class="card-body">
                            <?php echo nl2br(htmlspecialchars($eval_data['recommendations'] ?? 'No recommendations recorded.')); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Recommendations -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Export Evaluation</h5>
                </div>
                <div class="card-body">
                    <button class="btn btn-danger me-2" onclick="exportEvaluationPDF(<?php echo $evaluation_id; ?>)">
                        <i class="fas fa-file-pdf me-2"></i>Export as PDF
                    </button>
                    <button class="btn btn-success" onclick="exportEvaluationCSV(<?php echo $evaluation_id; ?>)">
                        <i class="fas fa-file-excel me-2"></i>Export as Excel/CSV
                    </button>
                </div>
            </div>

            <script>
            function exportEvaluationPDF(evaluationId) {
                window.open(`../controllers/export.php?type=pdf&evaluation_id=${evaluationId}&report_type=single`, '_blank');
            }

            function exportEvaluationCSV(evaluationId) {
                window.open(`../controllers/export.php?type=csv&evaluation_id=${evaluationId}&report_type=single`, '_blank');
            }
            </script>
            
            <?php
            // Get AI recommendations for this evaluation
            $ai_recommendations = $evaluation->getAIRecommendations($evaluation_id);
            if($ai_recommendations->rowCount() > 0):
            ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-robot me-2"></i>AI Recommendations</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php while($ai_rec = $ai_recommendations->fetch(PDO::FETCH_ASSOC)): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card border-<?php echo $ai_rec['priority'] == 'high' ? 'danger' : ($ai_rec['priority'] == 'medium' ? 'warning' : 'info'); ?>">
                                <div class="card-header bg-<?php echo $ai_rec['priority'] == 'high' ? 'danger' : ($ai_rec['priority'] == 'medium' ? 'warning' : 'info'); ?> text-white py-2">
                                    <h6 class="mb-0">
                                        <i class="fas fa-<?php echo $ai_rec['priority'] == 'high' ? 'exclamation-triangle' : 'lightbulb'; ?> me-2"></i>
                                        <?php echo htmlspecialchars($ai_rec['area']); ?>
                                        <span class="badge bg-light text-dark float-end"><?php echo ucfirst($ai_rec['priority']); ?> Priority</span>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-0"><?php echo htmlspecialchars($ai_rec['suggestion']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <style>
        .rating-card {
            padding: 20px;
            border-radius: 10px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
        }
        .rating-card h6 {
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
        }
    </style>
    
</body>
</html>