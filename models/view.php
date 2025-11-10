<?php
require_once '../auth/session-check.php';
if($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Teacher.php';
require_once '../models/Evaluation.php';

$database = new Database();
$db = $database->getConnection();

$teacher = new Teacher($db);
$evaluation = new Evaluation($db);

$teacher_id = $_GET['id'] ?? 0;
$teacher_data = $teacher->getById($teacher_id);

if(!$teacher_data) {
    $_SESSION['error'] = "Teacher not found.";
    header("Location: teachers.php");
    exit();
}

// Get teacher's evaluation statistics
$teacher_stats = $evaluation->getTeacherStats($teacher_id);
$recent_evaluations = $evaluation->getByTeacher($teacher_id, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile - <?php echo $_SESSION['department']; ?></title>
    <?php include '../includes/header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Teacher Profile</h3>
                <div>
                    <a href="edit.php?id=<?php echo $teacher_id; ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-edit me-2"></i>Edit
                    </a>
                    <a href="teachers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Teachers
                    </a>
                </div>
            </div>

            <!-- Teacher Information -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Teacher Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <div class="avatar-placeholder bg-secondary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                     style="width: 80px; height: 80px; font-size: 2rem;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h4><?php echo htmlspecialchars($teacher_data['name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($teacher_data['department']); ?></p>
                                <span class="badge bg-<?php echo $teacher_data['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($teacher_data['status']); ?>
                                </span>
                            </div>
                            
                            <div class="teacher-details">
                                <div class="detail-item mb-3">
                                    <strong><i class="fas fa-envelope me-2"></i>Email:</strong>
                                    <p class="mb-0"><?php echo $teacher_data['email'] ? htmlspecialchars($teacher_data['email']) : '<span class="text-muted">Not provided</span>'; ?></p>
                                </div>
                                <div class="detail-item mb-3">
                                    <strong><i class="fas fa-phone me-2"></i>Phone:</strong>
                                    <p class="mb-0"><?php echo $teacher_data['phone'] ? htmlspecialchars($teacher_data['phone']) : '<span class="text-muted">Not provided</span>'; ?></p>
                                </div>
                                <div class="detail-item">
                                    <strong><i class="fas fa-calendar me-2"></i>Member Since:</strong>
                                    <p class="mb-0"><?php echo date('F j, Y', strtotime($teacher_data['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="dashboard-stat stat-1">
                                <i class="fas fa-clipboard-check"></i>
                                <div class="number"><?php echo $teacher_stats['total_evaluations']; ?></div>
                                <div>Total Evaluations</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="dashboard-stat stat-2">
                                <i class="fas fa-chart-line"></i>
                                <div class="number"><?php echo number_format($teacher_stats['avg_rating'], 1); ?></div>
                                <div>Average Rating</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="dashboard-stat stat-3">
                                <i class="fas fa-robot"></i>
                                <div class="number"><?php echo $teacher_stats['ai_recommendations']; ?></div>
                                <div>AI Recommendations</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rating Distribution Chart -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Performance Overview</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="performanceChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Evaluations -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Evaluations</h5>
                </div>
                <div class="card-body">
                    <?php if($recent_evaluations->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Communications</th>
                                    <th>Management</th>
                                    <th>Assessment</th>
                                    <th>Overall</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($eval = $recent_evaluations->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($eval['observation_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($eval['subject_observed']); ?></td>
                                    <td><?php echo htmlspecialchars($eval['observation_type']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $eval['communications_avg'] >= 4.0 ? 'success' : ($eval['communications_avg'] >= 3.0 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($eval['communications_avg'], 1); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $eval['management_avg'] >= 4.0 ? 'success' : ($eval['management_avg'] >= 3.0 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($eval['management_avg'], 1); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $eval['assessment_avg'] >= 4.0 ? 'success' : ($eval['assessment_avg'] >= 3.0 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($eval['assessment_avg'], 1); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $eval['overall_avg'] >= 4.6 ? 'success' : 
                                                             ($eval['overall_avg'] >= 3.6 ? 'primary' : 
                                                             ($eval['overall_avg'] >= 2.9 ? 'info' : 'warning')); ?>">
                                            <?php echo number_format($eval['overall_avg'], 1); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $rating = 'Needs Improvement';
                                        if($eval['overall_avg'] >= 4.6) $rating = 'Excellent';
                                        elseif($eval['overall_avg'] >= 3.6) $rating = 'Very Satisfactory';
                                        elseif($eval['overall_avg'] >= 2.9) $rating = 'Satisfactory';
                                        elseif($eval['overall_avg'] >= 1.8) $rating = 'Below Satisfactory';
                                        echo $rating;
                                        ?>
                                    </td>
                                    <td>
                                        <a href="evaluation-view.php?id=<?php echo $eval['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <h5>No Evaluations Yet</h5>
                        <p class="text-muted">This teacher hasn't been evaluated yet.</p>
                        <a href="evaluation.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Start Evaluation
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        // Performance Chart
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Communications', 'Management', 'Assessment'],
                datasets: [{
                    label: 'Average Rating',
                    data: [
                        <?php echo number_format($teacher_stats['comm_avg'], 1); ?>,
                        <?php echo number_format($teacher_stats['mgmt_avg'], 1); ?>,
                        <?php echo number_format($teacher_stats['assess_avg'], 1); ?>
                    ],
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
                        max: 5,
                        title: {
                            display: true,
                            text: 'Rating'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Average Ratings by Category'
                    }
                }
            }
        });
    </script>

    <style>
        .avatar-placeholder {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .teacher-details {
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
        }
        .detail-item {
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        .detail-item:last-child {
            border-bottom: none;
        }
    </style>
</body>
</html>