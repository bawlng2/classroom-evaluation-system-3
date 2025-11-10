<?php
if (isset($_GET['action']) && $_GET['action'] === 'get_teacher' && isset($_GET['id'])) {
    require_once '../config/database.php';
    require_once '../models/Teacher.php';
    $db = (new Database())->getConnection();
    $teacherModel = new Teacher($db);
    $teacher = $teacherModel->getById($_GET['id']);
    if ($teacher) {
        echo json_encode(['success' => true, 'teacher' => [
            'name' => $teacher['name'],
            'department' => $teacher['department']
        ]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    }
    exit();
}


// --- AJAX handler for get_teacher action ---
if (isset($_GET['action']) && $_GET['action'] === 'get_teacher' && isset($_GET['id'])) {
    require_once '../config/database.php';
    require_once '../models/Teacher.php';
    $db = (new Database())->getConnection();
    $teacherModel = new Teacher($db);
    $teacher = $teacherModel->getById($_GET['id']);
    if ($teacher) {
        echo json_encode(['success' => true, 'teacher' => [
            'name' => $teacher['name'],
            'department' => $teacher['department']
        ]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    }
    exit();
}

class EvaluationController {
    private $db;
    private $evaluationModel;
    private $aiController;

    public function __construct($database) {
        $this->db = $database;
        $this->evaluationModel = new Evaluation($database);
        $this->aiController = new AIController($database);
    }

    public function submitEvaluation($postData, $evaluatorId) {
        try {
            // Authorization: ensure evaluator is allowed to evaluate this teacher
            if (!isset($postData['teacher_id']) || !is_numeric($postData['teacher_id'])) {
                throw new Exception('Invalid teacher specified.');
            }
            $teacherId = (int)$postData['teacher_id'];
            // Load teacher to check department
            require_once '../models/Teacher.php';
            $teacherModel = new Teacher($this->db);
            $teacherData = $teacherModel->getById($teacherId);
            if (!$teacherData) {
                throw new Exception('Teacher not found.');
            }
            // Fetch evaluator role and department from users table
            $userStmt = $this->db->prepare('SELECT role, department FROM users WHERE id = ?');
            $userStmt->execute([$evaluatorId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                throw new Exception('Evaluator not found.');
            }
            // Presidents and Vice Presidents can evaluate across departments
            if (!in_array($user['role'], ['president', 'vice_president'])) {
                $evaluatorDept = $user['department'];
                $teacherDept = $teacherData['department'] ?? null;
                if ($evaluatorDept !== $teacherDept) {
                    throw new Exception('You are not authorized to evaluate this teacher.');
                }
            }

            // Start transaction
            $this->db->beginTransaction();

            // 1. Create evaluation record
            $evaluationId = $this->createEvaluationRecord($postData, $evaluatorId);
            
            if (!$evaluationId) {
                throw new Exception("Failed to create evaluation record");
            }

            // 2. Save evaluation details (ratings and comments)
            $this->saveEvaluationDetails($evaluationId, $postData);

            // 3. Calculate averages
            $this->calculateAndUpdateAverages($evaluationId);

            // 4. Generate AI recommendations
            $this->aiController->generateRecommendations($evaluationId);

            // 5. Update evaluation with qualitative data
            $this->updateQualitativeData($evaluationId, $postData);

            // Commit transaction
            $this->db->commit();

            return [
                'success' => true,
                'evaluation_id' => $evaluationId,
                'message' => 'Evaluation submitted successfully!'
            ];

        } catch (Exception $e) {
            // Rollback transaction on error
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function createEvaluationRecord($data, $evaluatorId) {
        $query = "INSERT INTO evaluations 
                  (teacher_id, evaluator_id, academic_year, semester, 
                   subject_observed, observation_time, observation_date, 
                   observation_type, seat_plan, course_syllabi, 
                   others_requirements, others_specify, status) 
                  VALUES (:teacher_id, :evaluator_id, :academic_year, :semester, 
                          :subject_observed, :observation_time, :observation_date, 
                          :observation_type, :seat_plan, :course_syllabi, 
                          :others_requirements, :others_specify, 'completed')";

        $stmt = $this->db->prepare($query);
        
        $stmt->bindParam(':teacher_id', $data['teacher_id']);
        $stmt->bindParam(':evaluator_id', $evaluatorId);
        $stmt->bindParam(':academic_year', $data['academic_year']);
        $stmt->bindParam(':semester', $data['semester']);
        $stmt->bindParam(':subject_observed', $data['subject_observed']);
        $stmt->bindParam(':observation_time', $data['observation_time']);
        $stmt->bindParam(':observation_date', $data['observation_date']);
        $stmt->bindParam(':observation_type', $data['observation_type']);
        $stmt->bindParam(':seat_plan', $data['seat_plan']);
        $stmt->bindParam(':course_syllabi', $data['course_syllabi']);
        $stmt->bindParam(':others_requirements', $data['others_requirements']);
        $stmt->bindParam(':others_specify', $data['others_specify']);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    private function saveEvaluationDetails($evaluationId, $data) {
        // Save communications criteria
        for ($i = 0; $i < 5; $i++) {
            if (isset($data["communications{$i}"])) {
                $this->saveCriterion($evaluationId, 'communications', $i, $data["communications{$i}"], $data["communications_comment{$i}"] ?? '');
            }
        }

        // Save management criteria
        for ($i = 0; $i < 12; $i++) {
            if (isset($data["management{$i}"])) {
                $this->saveCriterion($evaluationId, 'management', $i, $data["management{$i}"], $data["management_comment{$i}"] ?? '');
            }
        }

        // Save assessment criteria
        for ($i = 0; $i < 6; $i++) {
            if (isset($data["assessment{$i}"])) {
                $this->saveCriterion($evaluationId, 'assessment', $i, $data["assessment{$i}"], $data["assessment_comment{$i}"] ?? '');
            }
        }
    }

    private function saveCriterion($evaluationId, $category, $index, $rating, $comment) {
        // Get criterion text from evaluation_criteria table
        $criterionQuery = "SELECT criterion_text FROM evaluation_criteria 
                          WHERE category = :category AND criterion_index = :index";
        $criterionStmt = $this->db->prepare($criterionQuery);
        $criterionStmt->bindParam(':category', $category);
        $criterionStmt->bindParam(':index', $index);
        $criterionStmt->execute();
        $criterion = $criterionStmt->fetch(PDO::FETCH_ASSOC);

        $query = "INSERT INTO evaluation_details 
                  (evaluation_id, category, criterion_index, criterion_text, rating, comments) 
                  VALUES (:evaluation_id, :category, :criterion_index, :criterion_text, :rating, :comments)";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':evaluation_id', $evaluationId);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':criterion_index', $index);
        $stmt->bindParam(':criterion_text', $criterion['criterion_text']);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':comments', $comment);

        return $stmt->execute();
    }

    private function calculateAndUpdateAverages($evaluationId) {
        // Use the stored procedure
        $stmt = $this->db->prepare("CALL CalculateAverages(?)");
        $stmt->execute([$evaluationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function updateQualitativeData($evaluationId, $data) {
        $query = "UPDATE evaluations 
                  SET strengths = :strengths, 
                      improvement_areas = :improvement_areas,
                      recommendations = :recommendations,
                      rater_signature = :rater_signature,
                      rater_date = :rater_date,
                      faculty_signature = :faculty_signature,
                      faculty_date = :faculty_date
                  WHERE id = :evaluation_id";

        $stmt = $this->db->prepare($query);
        
        $stmt->bindParam(':strengths', $data['strengths']);
        $stmt->bindParam(':improvement_areas', $data['improvement_areas']);
        $stmt->bindParam(':recommendations', $data['recommendations']);
        $stmt->bindParam(':rater_signature', $data['rater_signature']);
        $stmt->bindParam(':rater_date', $data['rater_date']);
        $stmt->bindParam(':faculty_signature', $data['faculty_signature']);
        $stmt->bindParam(':faculty_date', $data['faculty_date']);
        $stmt->bindParam(':evaluation_id', $evaluationId);

        return $stmt->execute();
    }

    public function getEvaluationById($evaluationId) {
        $query = "SELECT e.*, t.name as teacher_name, t.department, 
                         u.name as evaluator_name 
                  FROM evaluations e
                  JOIN teachers t ON e.teacher_id = t.id
                  JOIN users u ON e.evaluator_id = u.id
                  WHERE e.id = :evaluation_id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':evaluation_id', $evaluationId);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>