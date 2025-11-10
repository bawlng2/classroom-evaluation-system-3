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
            error_log('[DEBUG] EvaluationController::submitEvaluation called. POST: ' . print_r($postData, true) . ' | Evaluator ID: ' . $evaluatorId);
            // Authorization: ensure evaluator is allowed to evaluate this teacher
            if (!isset($postData['teacher_id']) || !is_numeric($postData['teacher_id'])) {
                error_log('[DEBUG] EvaluationController: Invalid teacher_id in POST.');
                throw new Exception('Invalid teacher specified.');
            }
            $teacherId = (int)$postData['teacher_id'];
            // Load teacher to check department
            require_once '../models/Teacher.php';
            $teacherModel = new Teacher($this->db);
            $teacherData = $teacherModel->getById($teacherId);
            if (!$teacherData) {
                error_log('[DEBUG] EvaluationController: Teacher not found for ID ' . $teacherId);
                throw new Exception('Teacher not found.');
            }
            // Fetch evaluator role and department from users table
            $userStmt = $this->db->prepare('SELECT role, department FROM users WHERE id = ?');
            $userStmt->execute([$evaluatorId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                error_log('[DEBUG] EvaluationController: Evaluator not found for ID ' . $evaluatorId);
                throw new Exception('Evaluator not found.');
            }
            // Presidents and Vice Presidents can evaluate across departments
            if (!in_array($user['role'], ['president', 'vice_president'])) {
                $evaluatorDept = $user['department'];
                $teacherDept = $teacherData['department'] ?? null;
                if ($evaluatorDept !== $teacherDept) {
                    error_log('[DEBUG] EvaluationController: Evaluator department mismatch. Evaluator: ' . $evaluatorDept . ', Teacher: ' . $teacherDept);
                    throw new Exception('You are not authorized to evaluate this teacher.');
                }
            }

            // Start transaction
            $this->db->beginTransaction();

            // 1. Create evaluation record
            $evaluationId = $this->createEvaluationRecord($postData, $evaluatorId);
            error_log('[DEBUG] EvaluationController: createEvaluationRecord returned ID: ' . print_r($evaluationId, true));
            if (!$evaluationId) {
                error_log('[DEBUG] EvaluationController: Failed to create evaluation record.');
                throw new Exception("Failed to create evaluation record");
            }

            // 2. Save evaluation details (ratings and comments)
            $this->saveEvaluationDetails($evaluationId, $postData);
            error_log('[DEBUG] EvaluationController: saveEvaluationDetails completed.');

            // 3. Calculate averages (wrap in try/catch so missing stored-proc doesn't break entire flow)
            try {
                $this->calculateAndUpdateAverages($evaluationId);
                error_log('[DEBUG] EvaluationController: calculateAndUpdateAverages completed.');
            } catch (Exception $e) {
                error_log('[EvaluationController] calculateAndUpdateAverages failed: ' . $e->getMessage());
            }

            // 4. Generate AI recommendations (guarded)
            try {
                if ($this->aiController) {
                    $this->aiController->generateRecommendations($evaluationId);
                    error_log('[DEBUG] EvaluationController: AI recommendations generated.');
                }
            } catch (Exception $e) {
                error_log('[EvaluationController] AI generateRecommendations failed: ' . $e->getMessage());
            }

            // 5. Update evaluation with qualitative data
            $this->updateQualitativeData($evaluationId, $postData);
            error_log('[DEBUG] EvaluationController: updateQualitativeData completed.');

            // Commit transaction
            $this->db->commit();
            error_log('[DEBUG] EvaluationController: Transaction committed. Evaluation complete.');
            return [
                'success' => true,
                'evaluation_id' => $evaluationId,
                'message' => 'Evaluation submitted successfully!'
            ];
        } catch (Exception $e) {
            error_log('[DEBUG] EvaluationController: Exception thrown: ' . $e->getMessage());
            // Rollback transaction on error
            if ($this->db) {
                try { $this->db->rollBack(); } catch (Exception $__) {}
            }
            // Log detailed error for debugging (not shown to user)
            error_log('[EvaluationController] submitEvaluation error: ' . $e->getMessage());
            error_log($e->getTraceAsString());
            $userMessage = 'An internal error occurred while submitting the evaluation.';
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $userMessage = $e->getMessage();
            }
            return [
                'success' => false,
                'message' => $userMessage
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
        // Defensive defaults for optional/missing fields
        $teacher_id = isset($data['teacher_id']) ? $data['teacher_id'] : null;
        $academic_year = isset($data['academic_year']) ? $data['academic_year'] : null;
        $semester = isset($data['semester']) ? $data['semester'] : null;
        $subject_observed = isset($data['subject_observed']) ? $data['subject_observed'] : null;
        $observation_time = isset($data['observation_time']) ? $data['observation_time'] : null; // form may omit this
        $observation_date = isset($data['observation_date']) ? $data['observation_date'] : null;
        $observation_type = isset($data['observation_type']) ? $data['observation_type'] : null;
        $seat_plan = isset($data['seat_plan']) ? $data['seat_plan'] : 0;
        $course_syllabi = isset($data['course_syllabi']) ? $data['course_syllabi'] : 0;
        $others_requirements = isset($data['others_requirements']) ? $data['others_requirements'] : 0;
        $others_specify = isset($data['others_specify']) ? $data['others_specify'] : null;

        $stmt->bindValue(':teacher_id', $teacher_id, is_null($teacher_id) ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':evaluator_id', $evaluatorId, PDO::PARAM_INT);
        $stmt->bindValue(':academic_year', $academic_year, $academic_year === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':semester', $semester, $semester === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':subject_observed', $subject_observed, $subject_observed === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':observation_time', $observation_time, $observation_time === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':observation_date', $observation_date, $observation_date === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':observation_type', $observation_type, $observation_type === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':seat_plan', $seat_plan, PDO::PARAM_INT);
        $stmt->bindValue(':course_syllabi', $course_syllabi, PDO::PARAM_INT);
        $stmt->bindValue(':others_requirements', $others_requirements, PDO::PARAM_INT);
        $stmt->bindValue(':others_specify', $others_specify, $others_specify === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        } else {
            $err = $stmt->errorInfo();
            error_log('[EvaluationController] createEvaluationRecord failed: ' . json_encode($err));
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
        $criterion_text = '';
        if ($criterion && isset($criterion['criterion_text'])) {
            $criterion_text = $criterion['criterion_text'];
        } else {
            // Log missing criterion mapping to help debugging
            error_log("[EvaluationController] Missing criterion text for category={$category} index={$index}");
        }

        $query = "INSERT INTO evaluation_details 
                  (evaluation_id, category, criterion_index, criterion_text, rating, comments) 
                  VALUES (:evaluation_id, :category, :criterion_index, :criterion_text, :rating, :comments)";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':evaluation_id', $evaluationId, PDO::PARAM_INT);
        $stmt->bindValue(':category', $category, PDO::PARAM_STR);
        $stmt->bindValue(':criterion_index', $index, PDO::PARAM_INT);
        $stmt->bindValue(':criterion_text', $criterion_text, PDO::PARAM_STR);
        $stmt->bindValue(':rating', $rating, PDO::PARAM_INT);
        $stmt->bindValue(':comments', $comment, PDO::PARAM_STR);

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