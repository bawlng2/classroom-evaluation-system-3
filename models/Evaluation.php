<?php
class Evaluation {
    private $conn;
    private $table_name = "evaluations";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get total number of evaluations
    public function getTotalEvaluations() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    // Get evaluations for reporting
    public function getEvaluationsForReport($evaluator_id = null, $academic_year = '', $semester = '', $teacher_id = '') {
        // Build base query
        $query = "SELECT e.*, t.name as teacher_name, u.name as evaluator_name,
                         COUNT(ai.id) as ai_count
                  FROM " . $this->table_name . " e
                  JOIN teachers t ON e.teacher_id = t.id
                  JOIN users u ON e.evaluator_id = u.id
                  LEFT JOIN ai_recommendations ai ON e.id = ai.evaluation_id
                  ";

        $where = [];
        $params = [];

        // If an evaluator_id is provided, filter by evaluator; otherwise return all evaluations (useful for leaders)
        if (!empty($evaluator_id)) {
            $where[] = 'e.evaluator_id = :evaluator_id';
            $params[':evaluator_id'] = $evaluator_id;
        }

        if (!empty($academic_year)) {
            $where[] = 'e.academic_year = :academic_year';
            $params[':academic_year'] = $academic_year;
        }

        if (!empty($semester)) {
            $where[] = 'e.semester = :semester';
            $params[':semester'] = $semester;
        }

        if (!empty($teacher_id)) {
            $where[] = 'e.teacher_id = :teacher_id';
            $params[':teacher_id'] = $teacher_id;
        }

        if (count($where) > 0) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        $query .= " GROUP BY e.id ORDER BY e.observation_date DESC";

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt;
    }

    // Get department statistics
    public function getDepartmentStats($department, $academic_year = '', $semester = '') {
        $query = "SELECT 
                    COUNT(*) as total_evaluations,
                    COALESCE(AVG(e.overall_avg), 0) as avg_rating,
                    COUNT(DISTINCT e.teacher_id) as teachers_evaluated,
                    COUNT(ai.id) as ai_recommendations,
                    SUM(CASE WHEN e.overall_avg >= 4.6 THEN 1 ELSE 0 END) as excellent_ratings,
                    SUM(CASE WHEN e.overall_avg < 2.9 THEN 1 ELSE 0 END) as needs_improvement
                  FROM " . $this->table_name . " e
                  JOIN teachers t ON e.teacher_id = t.id
                  LEFT JOIN ai_recommendations ai ON e.id = ai.evaluation_id
                  WHERE t.department = :department";
        
        $params = [':department' => $department];
        
        if (!empty($academic_year)) {
            $query .= " AND e.academic_year = :academic_year";
            $params[':academic_year'] = $academic_year;
        }
        
        if (!empty($semester)) {
            $query .= " AND e.semester = :semester";
            $params[':semester'] = $semester;
        }
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure all values are set and formatted
        if ($stats) {
            $stats['avg_rating'] = round($stats['avg_rating'], 1);
        } else {
            $stats = [
                'total_evaluations' => 0,
                'avg_rating' => 0,
                'teachers_evaluated' => 0,
                'ai_recommendations' => 0,
                'excellent_ratings' => 0,
                'needs_improvement' => 0
            ];
        }
        
        return $stats;
    }

    // Get admin statistics
    public function getAdminStats($admin_id) {
        $query = "SELECT 
                    COUNT(*) as completed_evaluations,
                    COUNT(ai.id) as ai_recommendations
                  FROM " . $this->table_name . " e
                  LEFT JOIN ai_recommendations ai ON e.id = ai.evaluation_id
                  WHERE e.evaluator_id = :admin_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':admin_id', $admin_id);
        $stmt->execute();
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            $stats = [
                'completed_evaluations' => 0,
                'ai_recommendations' => 0
            ];
        }
        
        return $stats;
    }

    // Get recent evaluations
    public function getRecentEvaluations($evaluator_id, $limit = 5) {
        $query = "SELECT e.*, t.name as teacher_name
                  FROM " . $this->table_name . " e
                  JOIN teachers t ON e.teacher_id = t.id
                  WHERE e.evaluator_id = :evaluator_id
                  ORDER BY e.observation_date DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evaluator_id', $evaluator_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }

    // Get evaluations by teacher
    public function getByTeacher($teacher_id, $limit = null) {
        $query = "SELECT e.*, u.name as evaluator_name 
                  FROM " . $this->table_name . " e 
                  JOIN users u ON e.evaluator_id = u.id 
                  WHERE e.teacher_id = :teacher_id 
                  ORDER BY e.observation_date DESC";
        
        if ($limit) {
            $query .= " LIMIT :limit";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacher_id);
        
        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt;
    }

    // Get teacher statistics
    public function getTeacherStats($teacher_id) {
        $query = "SELECT 
                    COUNT(*) as total_evaluations,
                    COALESCE(AVG(e.overall_avg), 0) as avg_rating,
                    COALESCE(AVG(e.communications_avg), 0) as comm_avg,
                    COALESCE(AVG(e.management_avg), 0) as mgmt_avg,
                    COALESCE(AVG(e.assessment_avg), 0) as assess_avg,
                    COUNT(ai.id) as ai_recommendations
                  FROM " . $this->table_name . " e
                  LEFT JOIN ai_recommendations ai ON e.id = ai.evaluation_id
                  WHERE e.teacher_id = :teacher_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->execute();
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure all values are set and formatted
        if ($stats) {
            $stats['avg_rating'] = round($stats['avg_rating'], 1);
            $stats['comm_avg'] = round($stats['comm_avg'], 1);
            $stats['mgmt_avg'] = round($stats['mgmt_avg'], 1);
            $stats['assess_avg'] = round($stats['assess_avg'], 1);
        } else {
            $stats = [
                'total_evaluations' => 0,
                'avg_rating' => 0,
                'comm_avg' => 0,
                'mgmt_avg' => 0,
                'assess_avg' => 0,
                'ai_recommendations' => 0
            ];
        }
        
        return $stats;
    }

    // Get evaluation by ID
    public function getEvaluationById($evaluation_id) {
        $query = "SELECT e.*, t.name as teacher_name, t.department, 
                         u.name as evaluator_name 
                  FROM " . $this->table_name . " e
                  JOIN teachers t ON e.teacher_id = t.id
                  JOIN users u ON e.evaluator_id = u.id
                  WHERE e.id = :evaluation_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evaluation_id', $evaluation_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    // Get AI recommendations for evaluation
    public function getAIRecommendations($evaluation_id) {
        $query = "SELECT * FROM ai_recommendations 
                  WHERE evaluation_id = :evaluation_id 
                  ORDER BY 
                    CASE priority 
                        WHEN 'high' THEN 1 
                        WHEN 'medium' THEN 2 
                        WHEN 'low' THEN 3 
                    END";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evaluation_id', $evaluation_id);
        $stmt->execute();
        
        return $stmt;
    }

    // Create new evaluation
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                 (teacher_id, evaluator_id, academic_year, semester, 
                  subject_observed, observation_date, observation_type,
                  seat_plan, course_syllabi, others_requirements, others_specify,
                  communications_avg, management_avg, assessment_avg, overall_avg,
                  strengths, improvement_areas, recommendations,
                  rater_signature, rater_date, faculty_signature, faculty_date,
                  status, created_at) 
                 VALUES 
                 (:teacher_id, :evaluator_id, :academic_year, :semester,
                  :subject_observed, :observation_date, :observation_type,
                  :seat_plan, :course_syllabi, :others_requirements, :others_specify,
                  :communications_avg, :management_avg, :assessment_avg, :overall_avg,
                  :strengths, :improvement_areas, :recommendations,
                  :rater_signature, :rater_date, :faculty_signature, :faculty_date,
                  'completed', NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':teacher_id', $data['teacher_id']);
        $stmt->bindParam(':evaluator_id', $data['evaluator_id']);
        $stmt->bindParam(':academic_year', $data['academic_year']);
        $stmt->bindParam(':semester', $data['semester']);
        $stmt->bindParam(':subject_observed', $data['subject_observed']);
        $stmt->bindParam(':observation_date', $data['observation_date']);
        $stmt->bindParam(':observation_type', $data['observation_type']);
        $stmt->bindParam(':seat_plan', $data['seat_plan']);
        $stmt->bindParam(':course_syllabi', $data['course_syllabi']);
        $stmt->bindParam(':others_requirements', $data['others_requirements']);
        $stmt->bindParam(':others_specify', $data['others_specify']);
        $stmt->bindParam(':communications_avg', $data['communications_avg']);
        $stmt->bindParam(':management_avg', $data['management_avg']);
        $stmt->bindParam(':assessment_avg', $data['assessment_avg']);
        $stmt->bindParam(':overall_avg', $data['overall_avg']);
        $stmt->bindParam(':strengths', $data['strengths']);
        $stmt->bindParam(':improvement_areas', $data['improvement_areas']);
        $stmt->bindParam(':recommendations', $data['recommendations']);
        $stmt->bindParam(':rater_signature', $data['rater_signature']);
        $stmt->bindParam(':rater_date', $data['rater_date']);
        $stmt->bindParam(':faculty_signature', $data['faculty_signature']);
        $stmt->bindParam(':faculty_date', $data['faculty_date']);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Save evaluation details (ratings and comments)
    public function saveEvaluationDetails($evaluation_id, $category, $criterion_index, $rating, $comment, $criterion_text) {
        $query = "INSERT INTO evaluation_details 
                  (evaluation_id, category, criterion_index, criterion_text, rating, comments) 
                  VALUES (:evaluation_id, :category, :criterion_index, :criterion_text, :rating, :comments)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evaluation_id', $evaluation_id);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':criterion_index', $criterion_index);
        $stmt->bindParam(':criterion_text', $criterion_text);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':comments', $comment);
        
        return $stmt->execute();
    }

    // Calculate averages for an evaluation
    public function calculateAverages($evaluation_id) {
        // Calculate communications average
        $comm_query = "SELECT AVG(rating) as avg_rating 
                      FROM evaluation_details 
                      WHERE evaluation_id = :evaluation_id AND category = 'communications'";
        $comm_stmt = $this->conn->prepare($comm_query);
        $comm_stmt->bindParam(':evaluation_id', $evaluation_id);
        $comm_stmt->execute();
        $comm_avg = $comm_stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'] ?? 0;

        // Calculate management average
        $mgmt_query = "SELECT AVG(rating) as avg_rating 
                      FROM evaluation_details 
                      WHERE evaluation_id = :evaluation_id AND category = 'management'";
        $mgmt_stmt = $this->conn->prepare($mgmt_query);
        $mgmt_stmt->bindParam(':evaluation_id', $evaluation_id);
        $mgmt_stmt->execute();
        $mgmt_avg = $mgmt_stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'] ?? 0;

        // Calculate assessment average
        $assess_query = "SELECT AVG(rating) as avg_rating 
                        FROM evaluation_details 
                        WHERE evaluation_id = :evaluation_id AND category = 'assessment'";
        $assess_stmt = $this->conn->prepare($assess_query);
        $assess_stmt->bindParam(':evaluation_id', $evaluation_id);
        $assess_stmt->execute();
        $assess_avg = $assess_stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'] ?? 0;

        // Calculate overall average
        $overall_avg = ($comm_avg + $mgmt_avg + $assess_avg) / 3;

        // Update evaluation with calculated averages
        $update_query = "UPDATE " . $this->table_name . " 
                        SET communications_avg = :comm_avg,
                            management_avg = :mgmt_avg,
                            assessment_avg = :assess_avg,
                            overall_avg = :overall_avg
                        WHERE id = :evaluation_id";
        
        $update_stmt = $this->conn->prepare($update_query);
        $update_stmt->bindParam(':comm_avg', $comm_avg);
        $update_stmt->bindParam(':mgmt_avg', $mgmt_avg);
        $update_stmt->bindParam(':assess_avg', $assess_avg);
        $update_stmt->bindParam(':overall_avg', $overall_avg);
        $update_stmt->bindParam(':evaluation_id', $evaluation_id);
        
        return $update_stmt->execute();
    }

    // Get evaluation criteria
    public function getEvaluationCriteria($category = '') {
        $query = "SELECT * FROM evaluation_criteria";
        
        if (!empty($category)) {
            $query .= " WHERE category = :category";
        }
        
        $query .= " ORDER BY category, criterion_index";
        
        $stmt = $this->conn->prepare($query);
        
        if (!empty($category)) {
            $stmt->bindParam(':category', $category);
        }
        
        $stmt->execute();
        return $stmt;
    }

    // Get evaluation details by evaluation ID
    public function getEvaluationDetails($evaluation_id) {
        $query = "SELECT * FROM evaluation_details 
                  WHERE evaluation_id = :evaluation_id 
                  ORDER BY category, criterion_index";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evaluation_id', $evaluation_id);
        $stmt->execute();
        
        return $stmt;
    }

    // Delete evaluation
    public function delete($evaluation_id) {
        // First delete related records
        $this->conn->beginTransaction();
        
        try {
            // Delete AI recommendations
            $ai_query = "DELETE FROM ai_recommendations WHERE evaluation_id = :evaluation_id";
            $ai_stmt = $this->conn->prepare($ai_query);
            $ai_stmt->bindParam(':evaluation_id', $evaluation_id);
            $ai_stmt->execute();
            
            // Delete evaluation details
            $details_query = "DELETE FROM evaluation_details WHERE evaluation_id = :evaluation_id";
            $details_stmt = $this->conn->prepare($details_query);
            $details_stmt->bindParam(':evaluation_id', $evaluation_id);
            $details_stmt->execute();
            
            // Delete evaluation
            $eval_query = "DELETE FROM " . $this->table_name . " WHERE id = :evaluation_id";
            $eval_stmt = $this->conn->prepare($eval_query);
            $eval_stmt->bindParam(':evaluation_id', $evaluation_id);
            $result = $eval_stmt->execute();
            
            $this->conn->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // Get evaluation summary for dashboard
    public function getEvaluationSummary($evaluator_id, $time_period = 'month') {
        $date_condition = "";
        
        switch ($time_period) {
            case 'week':
                $date_condition = "AND e.observation_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $date_condition = "AND e.observation_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'year':
                $date_condition = "AND e.observation_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }
        
        $query = "SELECT 
                    COUNT(*) as total_evaluations,
                    AVG(e.overall_avg) as average_rating,
                    COUNT(DISTINCT e.teacher_id) as unique_teachers,
                    MIN(e.observation_date) as first_evaluation,
                    MAX(e.observation_date) as last_evaluation
                  FROM " . $this->table_name . " e
                  WHERE e.evaluator_id = :evaluator_id
                  $date_condition";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evaluator_id', $evaluator_id);
        $stmt->execute();
        
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($summary) {
            $summary['average_rating'] = round($summary['average_rating'], 1);
        } else {
            $summary = [
                'total_evaluations' => 0,
                'average_rating' => 0,
                'unique_teachers' => 0,
                'first_evaluation' => null,
                'last_evaluation' => null
            ];
        }
        
        return $summary;
    }

    // Check if evaluation exists for teacher in current period
    public function hasEvaluationInPeriod($teacher_id, $academic_year, $semester, $evaluation_type = '') {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE teacher_id = :teacher_id 
                  AND academic_year = :academic_year 
                  AND semester = :semester";
        
        if (!empty($evaluation_type)) {
            $query .= " AND observation_type = :evaluation_type";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->bindParam(':academic_year', $academic_year);
        $stmt->bindParam(':semester', $semester);
        
        if (!empty($evaluation_type)) {
            $stmt->bindParam(':evaluation_type', $evaluation_type);
        }
        
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
    public function getSuperAdminStats() {
    $stats = [];
    
    // Total evaluations
    $query = "SELECT COUNT(*) as total_evaluations FROM " . $this->table_name;
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    $stats['total_evaluations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_evaluations'] ?? 0;
    
    // Average rating
    $query = "SELECT COALESCE(AVG(overall_avg), 0) as avg_rating FROM " . $this->table_name;
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    $stats['avg_rating'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'] ?? 0, 1);
    
    // Total teachers
    $query = "SELECT COUNT(*) as total_teachers FROM teachers WHERE status = 'active'";
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    $stats['total_teachers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_teachers'] ?? 0;
    
    // Total departments
    $query = "SELECT COUNT(DISTINCT department) as total_departments FROM teachers";
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    $stats['total_departments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_departments'] ?? 0;
    
    // AI recommendations
    $query = "SELECT COUNT(*) as ai_recommendations FROM ai_recommendations";
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    $stats['ai_recommendations'] = $stmt->fetch(PDO::FETCH_ASSOC)['ai_recommendations'] ?? 0;
    
    // Recent evaluations (last 30 days)
    $query = "SELECT COUNT(*) as recent_evaluations FROM " . $this->table_name . " 
              WHERE observation_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    $stats['recent_evaluations'] = $stmt->fetch(PDO::FETCH_ASSOC)['recent_evaluations'] ?? 0;
    
    // Department-wise statistics
    $query = "SELECT t.department, 
                     COUNT(e.id) as eval_count,
                     COALESCE(AVG(e.overall_avg), 0) as avg_rating
              FROM teachers t
              LEFT JOIN " . $this->table_name . " e ON t.id = e.teacher_id
              GROUP BY t.department
              ORDER BY eval_count DESC";
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    $stats['department_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}
// Add this method to get recent evaluations for superadmin
public function getRecentEvaluationsSuperAdmin($limit = 5) {
    $query = "SELECT e.*, t.name as teacher_name, t.department
              FROM " . $this->table_name . " e
              JOIN teachers t ON e.teacher_id = t.id
              ORDER BY e.observation_date DESC
              LIMIT :limit";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt;
}
}
?>