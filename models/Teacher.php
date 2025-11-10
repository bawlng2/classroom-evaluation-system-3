<?php
class Teacher {
    private $conn;
    private $table_name = "teachers";

    public $id;
    public $name;
    public $department;
    public $email;
    public $phone;
    public $status;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all teachers by department
    public function getByDepartment($department) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE department = :department ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department', $department);
        $stmt->execute();
        return $stmt;
    }

    // Get active teachers by department
    public function getActiveByDepartment($department) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE department = :department AND status = 'active' ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department', $department);
        $stmt->execute();
        return $stmt;
    }

    // Get teacher by ID
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    // Create new teacher
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                 (name, department, email, phone, status, created_at) 
                 VALUES (:name, :department, :email, :phone, 'active', NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':department', $data['department']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':phone', $data['phone']);
        
        return $stmt->execute();
    }

    // Update teacher
    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . " 
                 SET name = :name, email = :email, phone = :phone, updated_at = NOW() 
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':phone', $data['phone']);
        
        return $stmt->execute();
    }

    // Toggle teacher status (active/inactive)
    public function toggleStatus($id) {
        // Get current status
        $current_teacher = $this->getById($id);
        if (!$current_teacher) {
            return false;
        }
        
        $new_status = $current_teacher['status'] == 'active' ? 'inactive' : 'active';
        
        $query = "UPDATE " . $this->table_name . " 
                 SET status = :status, updated_at = NOW() 
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    // Get total number of teachers
    public function getTotalTeachers() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    // Get all teachers
    public function getAllTeachers($status = null) {
        $query = "SELECT * FROM " . $this->table_name;
        if (!is_null($status)) {
            $query .= " WHERE status = :status";
        }
        $query .= " ORDER BY department, name";
        $stmt = $this->conn->prepare($query);
        if (!is_null($status)) {
            $stmt->bindParam(':status', $status);
        }
        $stmt->execute();
        return $stmt;
    }

    // Update teacher status
    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " 
                 SET status = :status, updated_at = NOW() 
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    // Check if teacher already exists in department
    public function existsInDepartment($name, $department) {
        $query = "SELECT id FROM " . $this->table_name . " 
                 WHERE name = :name AND department = :department 
                 LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':department', $department);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    // Validate teacher data
    public function validate($data) {
        $errors = [];

        // Name validation
        if (empty(trim($data['name']))) {
            $errors[] = "Teacher name is required.";
        } elseif (strlen(trim($data['name'])) < 2) {
            $errors[] = "Teacher name must be at least 2 characters long.";
        }

        // Email validation (optional)
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }

        // Phone validation (optional)
        if (!empty($data['phone']) && !preg_match('/^[\d\s\-\+\(\)]{10,}$/', $data['phone'])) {
            $errors[] = "Invalid phone number format.";
        }

        return $errors;
    }

    // Get teacher statistics
    public function getTeacherStats($teacher_id) {
        require_once 'Evaluation.php';
        $evaluation = new Evaluation($this->conn);
        return $evaluation->getTeacherStats($teacher_id);
    }

    // Search teachers by name
    public function searchByName($name, $department) {
        $query = "SELECT * FROM " . $this->table_name . " 
                 WHERE department = :department 
                 AND name LIKE :name 
                 ORDER BY name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department', $department);
        $search_term = '%' . $name . '%';
        $stmt->bindParam(':name', $search_term);
        $stmt->execute();
        
        return $stmt;
    }

    // Get teachers with evaluation count
    public function getWithEvaluationCount($department) {
        $query = "SELECT t.*, COUNT(e.id) as evaluation_count 
                 FROM " . $this->table_name . " t 
                 LEFT JOIN evaluations e ON t.id = e.teacher_id 
                 WHERE t.department = :department 
                 GROUP BY t.id 
                 ORDER BY t.name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department', $department);
        $stmt->execute();
        
        return $stmt;
    }
    // Add this method to get teacher count by department
public function getCountByDepartment($department) {
    $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
              WHERE department = :department AND status = 'active'";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}
}
?>