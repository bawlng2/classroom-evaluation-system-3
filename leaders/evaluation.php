<?php
require_once '../auth/session-check.php';
// Allow evaluators and leaders (president/vice_president) to access evaluation
if(!in_array($_SESSION['role'], ['dean', 'principal', 'chairperson', 'subject_coordinator', 'president', 'vice_president'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Teacher.php';
require_once '../models/Evaluation.php';
require_once '../controllers/EvaluationController.php';

$database = new Database();
$db = $database->getConnection();

$teacher = new Teacher($db);
$evaluation = new Evaluation($db);

// Compute default academic year dynamically (e.g., 2025-2026 in late 2025)
$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
if ($currentMonth >= 7) {
    $defaultAcademicYear = $currentYear . '-' . ($currentYear + 1);
} else {
    $defaultAcademicYear = ($currentYear - 1) . '-' . $currentYear;
}

// Presidents and Vice Presidents can evaluate across all departments
if(in_array($_SESSION['role'], ['president', 'vice_president'])) {
    $teachers = $teacher->getAllTeachers('active');
} else {
    $teachers = $teacher->getActiveByDepartment($_SESSION['department']);
}

// If teacher_id is provided via GET, load that teacher to preselect and open the form
$preselect_teacher = null;
if(isset($_GET['teacher_id']) && is_numeric($_GET['teacher_id'])) {
    $preselect_teacher = $teacher->getById((int)$_GET['teacher_id']);
    // Server-side guard: ensure the current user is authorized to evaluate this teacher
    if ($preselect_teacher) {
        // Presidents and Vice Presidents can evaluate all departments
        if (!in_array($_SESSION['role'], ['president', 'vice_president'])) {
            $teacher_dept = $preselect_teacher['department'] ?? null;
            $user_dept = $_SESSION['department'] ?? null;
            if ($teacher_dept !== $user_dept) {
                // Not authorized to preselect this teacher; clear preselect and show a message
                $_SESSION['error'] = "You are not authorized to evaluate that teacher.";
                $preselect_teacher = null;
            }
        }
    }
}

// Handle form submission
if($_POST && isset($_POST['submit_evaluation'])) {
    $evalController = new EvaluationController($db);
    $result = $evalController->submitEvaluation($_POST, $_SESSION['user_id']);

    if($result['success']) {
        $_SESSION['success'] = "Evaluation submitted successfully!";
        // Redirect to reports with the teacher filter and academic year so the new record is visible
        $ay = urlencode($_POST['academic_year'] ?? '');
        $tid = urlencode($_POST['teacher_id'] ?? '');
        header("Location: ../evaluators/reports.php?teacher_id={$tid}&academic_year={$ay}");
        exit();
    } else {
        $_SESSION['error'] = "Error submitting evaluation: " . $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classroom Evaluation - AI Classroom Evaluation</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Classroom Evaluation</h3>
                <div>
                    <button class="btn btn-secondary" id="backToTeachers">
                        <i class="fas fa-arrow-left me-2"></i> Back to Teachers
                    </button>
                </div>
            </div>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Teacher Selection -->
            <div class="card mb-4" id="teacherSelection">
                <div class="card-header">
                    <h5 class="mb-0">Select Teacher to Evaluate</h5>
                </div>
                <div class="card-body">
                    <?php if($teachers->rowCount() > 0): ?>
                    <div class="list-group" id="teacherList">
                        <?php while($teacher_row = $teachers->fetch(PDO::FETCH_ASSOC)): ?>
                        <div class="list-group-item teacher-item" data-teacher-id="<?php echo $teacher_row['id']; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($teacher_row['name']); ?></h6>
                                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($teacher_row['department']); ?></p>
                                </div>
                                <div>
                                    <span class="badge bg-success">Active</span>
                                    <i class="fas fa-chevron-right ms-2"></i>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No Active Teachers</h5>
                        <p class="text-muted">There are no active teachers in your department to evaluate.</p>
                        <a href="teachers.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Manage Teachers
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Evaluation Form -->
            <div id="evaluationFormContainer" class="d-none">
                <form id="evaluationForm" method="POST">
                    <input type="hidden" name="teacher_id" id="selected_teacher_id">
                    
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">CLASSROOM EVALUATION FORM</h5>
                        </div>
                        <div class="card-body">
                            <!-- PART 1: Faculty Information -->
                            <div class="evaluation-section">
                                <h5>PART 1: Faculty Information</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Name of Faculty:</label>
                                        <input type="text" class="form-control" id="facultyName" name="faculty_name">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Academic Year:</label>
                                        <input type="text" class="form-control" id="academicYear" name="academic_year" value="<?php echo htmlspecialchars($defaultAcademicYear); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Semester:</label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="semester" id="semester1" value="1st" checked required>
                                                <label class="form-check-label" for="semester1">1st</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="semester" id="semester2" value="2nd">
                                                <label class="form-check-label" for="semester2">2nd</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Department:</label>
                                        <input type="text" class="form-control" id="department" name="department">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Subject/Time of Observation:</label>
                                        <input type="text" class="form-control" id="subjectTime" name="subject_observed" placeholder="e.g., Mathematics 9:00-10:30 AM" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Date of Observation:</label>
                                        <input type="date" class="form-control" id="observationDate" name="observation_date" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Type of Classroom Observation:</label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="observation_type" id="formal" value="Formal" checked required>
                                                <label class="form-check-label" for="formal">Formal</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="observation_type" id="informal" value="Informal">
                                                <label class="form-check-label" for="informal">Informal</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- PART 2: Mandatory Requirements -->
                            <div class="evaluation-section">
                                <h5>PART 2: Mandatory Requirements for Teachers</h5>
                                <p>Check if presented to the observer.</p>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="seatPlan" name="seat_plan" value="1">
                                            <label class="form-check-label" for="seatPlan">Seat Plan</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="courseSyllabi" name="course_syllabi" value="1">
                                            <label class="form-check-label" for="courseSyllabi">Course Syllabi</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="others" name="others_requirements" value="1">
                                            <label class="form-check-label" for="others">Others</label>
                                            <input type="text" class="form-control mt-1" id="othersSpecify" name="others_specify" placeholder="Please specify">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Rating Scale -->
                            <div class="rating-scale">
                                <h6>Rating Scale:</h6>
                                <div class="rating-scale-item">
                                    <span>5 - Excellent</span>
                                    <span>Greatly exceeds standards</span>
                                </div>
                                <div class="rating-scale-item">
                                    <span>4 - Very Satisfactory</span>
                                    <span>More than meets standards</span>
                                </div>
                                <div class="rating-scale-item">
                                    <span>3 - Satisfactory</span>
                                    <span>Meets standards</span>
                                </div>
                                <div class="rating-scale-item">
                                    <span>2 - Below Satisfactory</span>
                                    <span>Falls below standards</span>
                                </div>
                                <div class="rating-scale-item">
                                    <span>1 - Needs Improvement</span>
                                    <span>Barely meets expectations</span>
                                </div>
                            </div>
                            
                            <!-- PART 3: Domains of Teaching Performance -->
                            <div class="evaluation-section">
                                <h5>PART 3: Domains of Teaching Performance</h5>
                                
                                <!-- Communications Competence -->
                                <div class="mb-4">
                                    <h6>Communications Competence</h6>
                                    <table class="table table-bordered evaluation-table">
                                        <thead>
                                            <tr>
                                                <th width="70%">Indicator</th>
                                                <th width="6%">5</th>
                                                <th width="6%">4</th>
                                                <th width="6%">3</th>
                                                <th width="6%">2</th>
                                                <th width="6%">1</th>
                                                <th width="10%">Comments</th>
                                            </tr>
                                        </thead>
                                        <tbody id="communicationsCompetence">
                                            <tr>
                                                <td>Uses an audible voice that can be heard at the back of the room.</td>
                                                <td><input type="radio" name="communications0" value="5" required></td>
                                                <td><input type="radio" name="communications0" value="4"></td>
                                                <td><input type="radio" name="communications0" value="3"></td>
                                                <td><input type="radio" name="communications0" value="2"></td>
                                                <td><input type="radio" name="communications0" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="communications_comment0" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Speaks fluently in the language of instruction.</td>
                                                <td><input type="radio" name="communications1" value="5" required></td>
                                                <td><input type="radio" name="communications1" value="4"></td>
                                                <td><input type="radio" name="communications1" value="3"></td>
                                                <td><input type="radio" name="communications1" value="2"></td>
                                                <td><input type="radio" name="communications1" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="communications_comment1" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Facilitates a dynamic discussion.</td>
                                                <td><input type="radio" name="communications2" value="5" required></td>
                                                <td><input type="radio" name="communications2" value="4"></td>
                                                <td><input type="radio" name="communications2" value="3"></td>
                                                <td><input type="radio" name="communications2" value="2"></td>
                                                <td><input type="radio" name="communications2" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="communications_comment2" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Uses engaging non-verbal cues (facial expression, gestures).</td>
                                                <td><input type="radio" name="communications3" value="5" required></td>
                                                <td><input type="radio" name="communications3" value="4"></td>
                                                <td><input type="radio" name="communications3" value="3"></td>
                                                <td><input type="radio" name="communications3" value="2"></td>
                                                <td><input type="radio" name="communications3" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="communications_comment3" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Uses words & expressions suited to the level of the students.</td>
                                                <td><input type="radio" name="communications4" value="5" required></td>
                                                <td><input type="radio" name="communications4" value="4"></td>
                                                <td><input type="radio" name="communications4" value="3"></td>
                                                <td><input type="radio" name="communications4" value="2"></td>
                                                <td><input type="radio" name="communications4" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="communications_comment4" placeholder="Comments"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div class="text-end">
                                        <strong>Average: <span id="communicationsAverage">0.0</span></strong>
                                    </div>
                                </div>
                                
                                <!-- Management and Presentation of the Lesson -->
                                <div class="mb-4">
                                    <h6>Management and Presentation of the Lesson</h6>
                                    <table class="table table-bordered evaluation-table">
                                        <thead>
                                            <tr>
                                                <th width="70%">Indicator</th>
                                                <th width="6%">5</th>
                                                <th width="6%">4</th>
                                                <th width="6%">3</th>
                                                <th width="6%">2</th>
                                                <th width="6%">1</th>
                                                <th width="10%">Comments</th>
                                            </tr>
                                        </thead>
                                        <tbody id="managementPresentation">
                                            <tr>
                                                <td>The TILO (Topic Intended Learning Outcomes) are clearly presented.</td>
                                                <td><input type="radio" name="management0" value="5" required></td>
                                                <td><input type="radio" name="management0" value="4"></td>
                                                <td><input type="radio" name="management0" value="3"></td>
                                                <td><input type="radio" name="management0" value="2"></td>
                                                <td><input type="radio" name="management0" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="management_comment0" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Recall and connects previous lessons to the new lessons.</td>
                                                <td><input type="radio" name="management1" value="5" required></td>
                                                <td><input type="radio" name="management1" value="4"></td>
                                                <td><input type="radio" name="management1" value="3"></td>
                                                <td><input type="radio" name="management1" value="2"></td>
                                                <td><input type="radio" name="management1" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="management_comment1" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>The topic/lesson is introduced in an interesting & engaging way.</td>
                                                <td><input type="radio" name="management2" value="5" required></td>
                                                <td><input type="radio" name="management2" value="4"></td>
                                                <td><input type="radio" name="management2" value="3"></td>
                                                <td><input type="radio" name="management2" value="2"></td>
                                                <td><input type="radio" name="management2" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="management_comment2" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Uses current issues, real life & local examples to enrich class discussion.</td>
                                                <td><input type="radio" name="management3" value="5" required></td>
                                                <td><input type="radio" name="management3" value="4"></td>
                                                <td><input type="radio" name="management3" value="3"></td>
                                                <td><input type="radio" name="management3" value="2"></td>
                                                <td><input type="radio" name="management3" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="management_comment3" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Focuses class discussion on key concepts of the lesson.</td>
                                                <td><input type="radio" name="management4" value="5" required></td>
                                                <td><input type="radio" name="management4" value="4"></td>
                                                <td><input type="radio" name="management4" value="3"></td>
                                                <td><input type="radio" name="management4" value="2"></td>
                                                <td><input type="radio" name="management4" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="management_comment4" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Encourages active participation among students and ask questions about the topic.</td>
                                                <td><input type="radio" name="management5" value="5" required></td>
                                                <td><input type="radio" name="management5" value="4"></td>
                                                <td><input type="radio" name="management5" value="3"></td>
                                                <td><input type="radio" name="management5" value="2"></td>
                                                <td><input type="radio" name="management5" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="management_comment5" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Uses current instructional strategies and resources.</td>
                                                <td><input type="radio" name="management6" value="5" required></td>
                                                <td><input type="radio" name="management6" value="4"></td>
                                                <td><input type="radio" name="management6" value="3"></td>
                                                <td><input type="radio" name="management6" value="2"></td>
                                                <td><input type="radio" name="management6" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="management_comment6" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Designs teaching aids that facilitate understanding of key concepts.</td>
                                                <td><input type="radio" name="management7" value="5" required></td>
                                                <td><input type="radio" name="management7" value="4"></td>
                                                <td><input type="radio" name="management7" value="3"></td>
                                                <td><input type="radio" name="management7" value="2"></td>
                                                <td><input type="radio" name="management7" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="management_comment7" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Adapts teaching approach in the light of student feedback and reactions.</td>
                                                <td><input type="radio" name="management8" value="5" required></td>
                                                <td><input type="radio" name="management8" value="4"></td>
                                                <td><input type="radio" name="management8" value="3"></td>
                                                <td><input type="radio" name="management8" value="2"></td>
                                                <td><input type="radio" name="management8" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="management_comment8" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Aids students using thought provoking questions (Art of Questioning).</td>
                                                <td><input type="radio" name="management9" value="5" required></td>
                                                <td><input type="radio" name="management9" value="4"></td>
                                                <td><input type="radio" name="management9" value="3"></td>
                                                <td><input type="radio" name="management9" value="2"></td>
                                                <td><input type="radio" name="management9" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="management_comment9" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Integrate the institutional core values to the lessons.</td>
                                                <td><input type="radio" name="management10" value="5" required></td>
                                                <td><input type="radio" name="management10" value="4"></td>
                                                <td><input type="radio" name="management10" value="3"></td>
                                                <td><input type="radio" name="management10" value="2"></td>
                                                <td><input type="radio" name="management10" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="management_comment10" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Conduct the lesson using the principle of SMART</td>
                                                <td><input type="radio" name="management11" value="5" required></td>
                                                <td><input type="radio" name="management11" value="4"></td>
                                                <td><input type="radio" name="management11" value="3"></td>
                                                <td><input type="radio" name="management11" value="2"></td>
                                                <td><input type="radio" name="management11" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="management_comment11" placeholder="Comments"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div class="text-end">
                                        <strong>Average: <span id="managementAverage">0.0</span></strong>
                                    </div>
                                </div>
                                
                                <!-- Assessment of Students' Learning -->
                                <div class="mb-4">
                                    <h6>Assessment of Students' Learning</h6>
                                    <table class="table table-bordered evaluation-table">
                                        <thead>
                                            <tr>
                                                <th width="70%">Indicator</th>
                                                <th width="6%">5</th>
                                                <th width="6%">4</th>
                                                <th width="6%">3</th>
                                                <th width="6%">2</th>
                                                <th width="6%">1</th>
                                                <th width="10%">Comments</th>
                                            </tr>
                                        </thead>
                                        <tbody id="assessmentLearning">
                                            <tr>
                                                <td>Monitors students' understanding on key concepts discussed.</td>
                                                <td><input type="radio" name="assessment0" value="5" required></td>
                                                <td><input type="radio" name="assessment0" value="4"></td>
                                                <td><input type="radio" name="assessment0" value="3"></td>
                                                <td><input type="radio" name="assessment0" value="2"></td>
                                                <td><input type="radio" name="assessment0" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="assessment_comment0" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Uses assessment tool that relates specific course competencies stated in the syllabus.</td>
                                                <td><input type="radio" name="assessment1" value="5" required></td>
                                                <td><input type="radio" name="assessment1" value="4"></td>
                                                <td><input type="radio" name="assessment1" value="3"></td>
                                                <td><input type="radio" name="assessment1" value="2"></td>
                                                <td><input type="radio" name="assessment1" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="assessment_comment1" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Design test/quarter/assignments and other assessment tasks that are corrector-based.</td>
                                                <td><input type="radio" name="assessment2" value="5" required></td>
                                                <td><input type="radio" name="assessment2" value="4"></td>
                                                <td><input type="radio" name="assessment2" value="3"></td>
                                                <td><input type="radio" name="assessment2" value="2"></td>
                                                <td><input type="radio" name="assessment2" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="assessment_comment2" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Introduces varied activities that will answer the differentiated needs to the learners with varied learning style.</td>
                                                <td><input type="radio" name="assessment3" value="5" required></td>
                                                <td><input type="radio" name="assessment3" value="4"></td>
                                                <td><input type="radio" name="assessment3" value="3"></td>
                                                <td><input type="radio" name="assessment3" value="2"></td>
                                                <td><input type="radio" name="assessment3" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="assessment_comment3" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Conducts normative assessment before evaluating and grading the learner's performance outcome.</td>
                                                <td><input type="radio" name="assessment4" value="5" required></td>
                                                <td><input type="radio" name="assessment4" value="4"></td>
                                                <td><input type="radio" name="assessment4" value="3"></td>
                                                <td><input type="radio" name="assessment4" value="2"></td>
                                                <td><input type="radio" name="assessment4" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="assessment_comment4" placeholder="Comments"></td>
                                            </tr>
                                            <tr>
                                                <td>Monitors the formative assessment results and find ways to ensure learning for the learners.</td>
                                                <td><input type="radio" name="assessment5" value="5" required></td>
                                                <td><input type="radio" name="assessment5" value="4"></td>
                                                <td><input type="radio" name="assessment5" value="3"></td>
                                                <td><input type="radio" name="assessment5" value="2"></td>
                                                <td><input type="radio" name="assessment5" value="1"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="assessment_comment5" placeholder="Comments"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div class="text-end">
                                        <strong>Average: <span id="assessmentAverage">0.0</span></strong>
                                    </div>
                                </div>
                                
                                <!-- Overall Rating -->
                                <div class="mb-4">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Overall Rating Interpretation</h6>
                                            <div class="rating-scale">
                                                <div class="rating-scale-item">
                                                    <span>4.6-5.0</span>
                                                    <span>Excellent</span>
                                                </div>
                                                <div class="rating-scale-item">
                                                    <span>3.6-4.5</span>
                                                    <span>Very Satisfactory</span>
                                                </div>
                                                <div class="rating-scale-item">
                                                    <span>2.9-3.5</span>
                                                    <span>Satisfactory</span>
                                                </div>
                                                <div class="rating-scale-item">
                                                    <span>1.8-2.5</span>
                                                    <span>Below Satisfactory</span>
                                                </div>
                                                <div class="rating-scale-item">
                                                    <span>1.0-1.5</span>
                                                    <span>Needs Improvement</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-center p-4">
                                                <h4>Total Average</h4>
                                                <div class="display-4 text-primary" id="totalAverage">0.0</div>
                                                <h5 id="ratingInterpretation">Not Rated</h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                

                                
                                <!-- Strengths and Areas for Improvement -->
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <label class="form-label">STRENGTHS</label>
                                        <textarea class="form-control" id="strengths" name="strengths" rows="3" placeholder="List the teacher's strengths observed during the evaluation"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">AREAS FOR IMPROVEMENT</label>
                                        <textarea class="form-control" id="improvementAreas" name="improvement_areas" rows="3" placeholder="List areas where the teacher can improve"></textarea>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label">RECOMMENDATIONS</label>
                                        <textarea class="form-control" id="recommendations" name="recommendations" rows="3" placeholder="Provide specific recommendations for improvement"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">AGREEMENT</label>
                                        <textarea class="form-control" id="agreement" name="agreement" rows="3" placeholder="State agreement or additional notes"></textarea>
                                    </div>
                                </div>
                                
                                <!-- Agreement Section -->
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="border p-3">
                                            <h6>Rater/Observer</h6>
                                            <p class="small">I certify that this classroom evaluation represents my best judgment.</p>
                                            <div class="mb-3">
                                                <label class="form-label">Signature over printed name</label>
                                                <input type="text" class="form-control" id="raterSignature" name="rater_signature" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Date</label>
                                                <input type="date" class="form-control" id="raterDate" name="rater_date" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="border p-3">
                                            <h6>Faculty</h6>
                                            <p class="small">I certify that this evaluation result has been discussed with me during the post conference/debriefing.</p>
                                            <div class="mb-3">
                                                <label class="form-label">Signature of Faculty over printed name</label>
                                                <input type="text" class="form-control" id="facultySignature" name="faculty_signature" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Date</label>
                                                <input type="date" class="form-control" id="facultyDate" name="faculty_date" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Form Details -->
                                <div class="mt-4 p-3 bg-light">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Form Code No.</strong>
                                            <p>FM-DPM-SMCC-QM-02</p>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Name Status</strong>
                                            <p>: 01</p>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Revision No.</strong>
                                            <p>: 01</p>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Date Effective</strong>
                                            <p>: 21 September 2023</p>
                                        </div>
                                    </div>
                                    <div>
                                        <strong>Argument By</strong>
                                        <p>: President</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="form-actions mt-4">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="button" class="btn btn-secondary" id="saveDraft">
                                            <i class="fas fa-save me-2"></i> Save Draft
                                        </button>
                                    </div>
                                    <div>
                                        <button type="submit" class="btn btn-success me-2" name="submit_evaluation">
                                            <i class="fas fa-check me-2"></i> Submit Evaluation
                                        </button>
                                        <button type="button" class="btn btn-primary" id="downloadPDF">
                                            <i class="fas fa-download me-2"></i> Download as PDF
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Set current date for forms
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const observationDate = document.getElementById('observationDate');
            const raterDate = document.getElementById('raterDate');
            const facultyDate = document.getElementById('facultyDate');
            
            if (observationDate) observationDate.value = today;
            if (raterDate) raterDate.value = today;
            if (facultyDate) facultyDate.value = today;
            
            // Initialize teacher selection
            initializeTeacherSelection();

            // If a teacher_id param is provided in the URL (leaders link), auto-start evaluation
            const urlParams = new URLSearchParams(window.location.search);
            const preselectTeacher = urlParams.get('teacher_id');
            if (preselectTeacher) {
                // If the teacher list is present, start evaluation immediately
                startEvaluation(preselectTeacher);
            }
        });

        function initializeTeacherSelection() {
            // Teacher selection
            document.querySelectorAll('.teacher-item').forEach(item => {
                item.addEventListener('click', function() {
                    const teacherId = this.getAttribute('data-teacher-id');
                    startEvaluation(teacherId);
                });
            });

            // Back to teachers button
            document.getElementById('backToTeachers').addEventListener('click', function() {
                showTeacherSelection();
            });

            // Rating change listeners
            document.addEventListener('change', function(e) {
                if (e.target.type === 'radio' && e.target.name.includes('communications') || 
                    e.target.name.includes('management') || 
                    e.target.name.includes('assessment')) {
                    calculateAverages();
                }
            });

            // Save draft button
            document.getElementById('saveDraft').addEventListener('click', function() {
                saveEvaluationDraft();
            });

            // Download PDF button
            document.getElementById('downloadPDF').addEventListener('click', function() {
                exportToPDF();
            });
        }

        function startEvaluation(teacherId) {
            document.getElementById('teacherSelection').classList.add('d-none');
            document.getElementById('evaluationFormContainer').classList.remove('d-none');
            document.getElementById('selected_teacher_id').value = teacherId;
            
            // Set current date
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('observationDate').value = today;
            document.getElementById('raterDate').value = today;
            document.getElementById('facultyDate').value = today;
        }

        function showTeacherSelection() {
            document.getElementById('teacherSelection').classList.remove('d-none');
            document.getElementById('evaluationFormContainer').classList.add('d-none');
        }
                function calculateAverages() {
            // Communications average
            let commTotal = 0;
            let commCount = 0;
            
            for (let i = 0; i < 5; i++) {
                const selected = document.querySelector(`input[name="communications${i}"]:checked`);
                if (selected) {
                    commTotal += parseInt(selected.value);
                    commCount++;
                }
            }
            
            const commAvg = commCount > 0 ? (commTotal / commCount).toFixed(1) : '0.0';
            document.getElementById('communicationsAverage').textContent = commAvg;
            
            // Management average
            let mgmtTotal = 0;
            let mgmtCount = 0;
            
            for (let i = 0; i < 12; i++) {
                const selected = document.querySelector(`input[name="management${i}"]:checked`);
                if (selected) {
                    mgmtTotal += parseInt(selected.value);
                    mgmtCount++;
                }
            }
            
            const mgmtAvg = mgmtCount > 0 ? (mgmtTotal / mgmtCount).toFixed(1) : '0.0';
            document.getElementById('managementAverage').textContent = mgmtAvg;
            
            // Assessment average
            let assessTotal = 0;
            let assessCount = 0;
            
            for (let i = 0; i < 6; i++) {
                const selected = document.querySelector(`input[name="assessment${i}"]:checked`);
                if (selected) {
                    assessTotal += parseInt(selected.value);
                    assessCount++;
                }
            }
            
            const assessAvg = assessCount > 0 ? (assessTotal / assessCount).toFixed(1) : '0.0';
            document.getElementById('assessmentAverage').textContent = assessAvg;
            
            // Overall average
            const totalCount = commCount + mgmtCount + assessCount;
            const totalSum = commTotal + mgmtTotal + assessTotal;
            const overallAvg = totalCount > 0 ? (totalSum / totalCount).toFixed(1) : '0.0';
            
            document.getElementById('totalAverage').textContent = overallAvg;
            
            // Rating interpretation
            let interpretation = '';
            let interpretationClass = '';
            const numericAvg = parseFloat(overallAvg);
            
            if (numericAvg >= 4.6) {
                interpretation = 'Excellent';
                interpretationClass = 'text-success';
            } else if (numericAvg >= 3.6) {
                interpretation = 'Very Satisfactory';
                interpretationClass = 'text-primary';
            } else if (numericAvg >= 2.9) {
                interpretation = 'Satisfactory';
                interpretationClass = 'text-info';
            } else if (numericAvg >= 1.8) {
                interpretation = 'Below Satisfactory';
                interpretationClass = 'text-warning';
            } else if (numericAvg >= 1.0) {
                interpretation = 'Needs Improvement';
                interpretationClass = 'text-danger';
            } else {
                interpretation = 'Not Rated';
                interpretationClass = 'text-muted';
            }
            
            const ratingElement = document.getElementById('ratingInterpretation');
            ratingElement.textContent = interpretation;
            ratingElement.className = interpretationClass;
            
            return {
                communications: parseFloat(commAvg),
                management: parseFloat(mgmtAvg),
                assessment: parseFloat(assessAvg),
                overall: parseFloat(overallAvg)
            };
        }



        function saveEvaluationDraft() {
            if (validateForm(true)) {
                if (confirm('Save evaluation as draft? You can continue and submit later.')) {
                    // Show loading state
                    const saveBtn = document.getElementById('saveDraft');
                    const originalText = saveBtn.innerHTML;
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
                    saveBtn.disabled = true;
                    
                    // Simulate API call
                    setTimeout(() => {
                        alert('Draft saved successfully! You can continue editing or submit later.');
                        saveBtn.innerHTML = originalText;
                        saveBtn.disabled = false;
                    }, 1000);
                }
            } else {
                alert('Please complete all required ratings before saving draft.');
            }
        }

        function exportToPDF() {
            const teacherName = document.getElementById('facultyName').value;
            const overallRating = document.getElementById('ratingInterpretation').textContent;
            const totalAverage = document.getElementById('totalAverage').textContent;
            
            if (!teacherName || teacherName === '') {
                alert('Please select a teacher and complete the evaluation form first.');
                return;
            }
            
            if (confirm(`Generate PDF report for ${teacherName}?\nOverall Rating: ${overallRating} (${totalAverage})`)) {
                // Show loading state
                const pdfBtn = document.getElementById('downloadPDF');
                const originalText = pdfBtn.innerHTML;
                pdfBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';
                pdfBtn.disabled = true;
                
                // Simulate PDF generation
                setTimeout(() => {
                    alert(`PDF report for "${teacherName}" has been generated!\n\nIn a real implementation, this would:\n• Create a formatted PDF document\n• Include all evaluation data and ratings\n• Add AI recommendations\n• Download automatically`);
                    
                    pdfBtn.innerHTML = originalText;
                    pdfBtn.disabled = false;
                    
                    // In real implementation, you would trigger actual PDF download
                    // window.open('../controllers/export.php?type=pdf&evaluation_data=' + encodeURIComponent(JSON.stringify(getFormData())), '_blank');
                }, 1500);
            }
        }

        function validateForm(isDraft = false) {
            let isValid = true;
            const requiredFields = document.querySelectorAll('[required]');
            const errorFields = [];
            
            // Check required fields
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    errorFields.push(field.name || field.id);
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Check if at least some ratings are provided (for draft, require at least one category)
            if (!isDraft) {
                const communicationsRatings = document.querySelectorAll('input[name^="communications"]:checked');
                const managementRatings = document.querySelectorAll('input[name^="management"]:checked');
                const assessmentRatings = document.querySelectorAll('input[name^="assessment"]:checked');
                
                if (communicationsRatings.length === 0 && managementRatings.length === 0 && assessmentRatings.length === 0) {
                    alert('Please provide ratings for at least one evaluation category.');
                    isValid = false;
                }
            }
            
            // Check ratings completeness (for submission)
            if (!isDraft) {
                const categories = ['communications', 'management', 'assessment'];
                const expectedCounts = { communications: 5, management: 12, assessment: 6 };
                
                for (const category of categories) {
                    const ratings = document.querySelectorAll(`input[name^="${category}"]:checked`);
                    if (ratings.length > 0 && ratings.length < expectedCounts[category]) {
                        if (confirm(`You have only completed ${ratings.length} out of ${expectedCounts[category]} items in ${category.replace('communications', 'Communications').replace('management', 'Management').replace('assessment', 'Assessment')}. Continue anyway?`)) {
                            // User chose to continue with incomplete category
                        } else {
                            isValid = false;
                            break;
                        }
                    }
                }
            }
            
            if (!isValid && !isDraft) {
                alert('Please complete all required fields before submitting.');
                // Scroll to first error
                if (errorFields.length > 0) {
                    const firstError = document.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                }
            }
            
            return isValid;
        }

        function getFormData() {
            const formData = {
                teacher_id: document.getElementById('selected_teacher_id').value,
                faculty_name: document.getElementById('facultyName').value,
                academic_year: document.getElementById('academicYear').value,
                semester: document.querySelector('input[name="semester"]:checked')?.value,
                department: document.getElementById('department').value,
                subject_observed: document.getElementById('subjectTime').value,
                observation_date: document.getElementById('observationDate').value,
                observation_type: document.querySelector('input[name="observation_type"]:checked')?.value,
                seat_plan: document.getElementById('seatPlan').checked ? 1 : 0,
                course_syllabi: document.getElementById('courseSyllabi').checked ? 1 : 0,
                others_requirements: document.getElementById('others').checked ? 1 : 0,
                others_specify: document.getElementById('othersSpecify').value,
                strengths: document.getElementById('strengths').value,
                improvement_areas: document.getElementById('improvementAreas').value,
                recommendations: document.getElementById('recommendations').value,
                rater_signature: document.getElementById('raterSignature').value,
                rater_date: document.getElementById('raterDate').value,
                faculty_signature: document.getElementById('facultySignature').value,
                faculty_date: document.getElementById('facultyDate').value,
                ratings: {}
            };
            
            // Collect all ratings
            ['communications', 'management', 'assessment'].forEach(category => {
                formData.ratings[category] = {};
                const count = category === 'communications' ? 5 : category === 'management' ? 12 : 6;
                
                for (let i = 0; i < count; i++) {
                    const rating = document.querySelector(`input[name="${category}${i}"]:checked`);
                    const comment = document.querySelector(`input[name="${category}_comment${i}"]`);
                    
                    if (rating) {
                        formData.ratings[category][i] = {
                            rating: rating.value,
                            comment: comment ? comment.value : ''
                        };
                    }
                }
            });
            
            // Add calculated averages
            const averages = calculateAverages();
            formData.averages = averages;
            
            return formData;
        }

        // Form submission handler
        document.getElementById('evaluationForm').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('button[name="submit_evaluation"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            submitBtn.disabled = true;
            
            // Allow form to submit normally
            return true;
        });

        // Auto-save functionality (optional)
        let autoSaveTimeout;
        function setupAutoSave() {
            const inputs = document.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    clearTimeout(autoSaveTimeout);
                    autoSaveTimeout = setTimeout(() => {
                        if (validateForm(true)) {
                            console.log('Auto-saving draft...');
                            // In real implementation, call saveEvaluationDraft() or make AJAX call
                        }
                    }, 3000); // Save 3 seconds after last change
                });
            });
        }

        // Initialize auto-save when form is shown
        function initializeEvaluationForm() {
            setupAutoSave();
            calculateAverages();
            generateAIRecommendations();
        }

        // Enhanced teacher selection with search
        function setupTeacherSearch() {
            const teacherSearch = document.createElement('div');
            teacherSearch.className = 'mb-3';
            teacherSearch.innerHTML = `
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="teacherSearch" placeholder="Search teachers...">
                </div>
            `;
            
            const teacherList = document.getElementById('teacherList');
            if (teacherList) {
                teacherList.parentNode.insertBefore(teacherSearch, teacherList);
                
                const searchInput = document.getElementById('teacherSearch');
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const teacherItems = document.querySelectorAll('.teacher-item');
                    
                    teacherItems.forEach(item => {
                        const teacherName = item.querySelector('h6').textContent.toLowerCase();
                        if (teacherName.includes(searchTerm)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
        }

        // Initialize everything when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeTeacherSelection();
            setupTeacherSearch();

            <?php if(isset($preselect_teacher) && $preselect_teacher): ?>
            // Preselect teacher passed from server and open the evaluation form
            (function() {
                var teacher = <?php echo json_encode($preselect_teacher); ?>;
                // Try to find the teacher item in the list and trigger click
                var teacherItem = document.querySelector('.teacher-item[data-teacher-id="' + teacher.id + '"]');
                if (teacherItem) {
                    teacherItem.click();
                } else {
                    // If not found in the list (e.g., different query), populate form fields directly
                    var selectionCard = document.getElementById('teacherSelection');
                    var formContainer = document.getElementById('evaluationFormContainer');
                    if (selectionCard && formContainer) {
                        selectionCard.classList.add('d-none');
                        formContainer.classList.remove('d-none');
                    }
                    var selectedInput = document.getElementById('selected_teacher_id');
                    if (selectedInput) selectedInput.value = teacher.id;
                    var facultyInput = document.getElementById('facultyName');
                    if (facultyInput) facultyInput.value = teacher.name;
                    var deptInput = document.getElementById('department');
                    if (deptInput) deptInput.value = teacher.department;
                    // Initialize form behaviors
                    initializeEvaluationForm();
                }
            })();
            <?php endif; ?>
        });
    </script>
</body>
</html>