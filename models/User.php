<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $username;
    public $password;
    public $name;
    public $role;
    public $department;
    public $status;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get users by role and department
    public function getUsersByRoleAndDepartment($role, $department, $status = null) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE role = :role AND department = :department";
        if ($status) {
            $query .= " AND status = :status";
        }
        $query .= " ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':department', $department);
        if ($status) {
            $stmt->bindParam(':status', $status);
        }
        $stmt->execute();
        return $stmt;
    }

    // Login method
    public function login() {
        // Add debug logging
        error_log("Attempting login with username: " . $this->username . ", role: " . $this->role);
        // Prepare query
        $query = "SELECT id, username, password, name, role, department, status 
                  FROM " . $this->table_name . " 
                  WHERE username = :username 
                  AND status = 'active' 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        // Sanitize and bind parameters
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $stmt->bindParam(':username', $this->username);
        // Execute query
        if($stmt->execute()) {
            // Check if user exists
            if($stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                // Add debug logging
                error_log("Found user in database. DB Role: " . $row['role'] . ", Requested Role: " . $this->role);
                // Verify password and role
                if(password_verify($this->password, $row['password']) && strtolower($row['role']) === strtolower($this->role)) {
                    // Set user properties
                    $this->id = $row['id'];
                    $this->name = $row['name'];
                    $this->department = $row['department'];
                    $this->status = $row['status'];
                    return true;
                }
            }
        }
        return false;
    }

    // Get user by ID
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get all users
    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Create new user
    public function create($data) {
        // Check if username already exists
        $checkQuery = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE username = :username";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(':username', $data['username']);
        $checkStmt->execute();
        if ($checkStmt->fetchColumn() > 0) {
            // Username exists
            return 'exists';
        }

        $query = "INSERT INTO " . $this->table_name . " 
                  (username, password, name, role, department, status, created_at) 
                  VALUES (:username, :password, :name, :role, :department, 'active', NOW())";
        $stmt = $this->conn->prepare($query);
        // Hash password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':role', $data['role']);
        $stmt->bindParam(':department', $data['department']);
        return $stmt->execute();
    }

    // Update user
    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . " 
                  SET name = :name, role = :role, department = :department";
        
        // Add password update if provided
        if(!empty($data['password'])) {
            $query .= ", password = :password";
        }
        
        $query .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':role', $data['role']);
        $stmt->bindParam(':department', $data['department']);
        $stmt->bindParam(':id', $id);
        
        if(!empty($data['password'])) {
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt->bindParam(':password', $hashed_password);
        }
        
        return $stmt->execute();
    }

    // Delete user (soft delete)
    public function delete($id) {
        $query = "UPDATE " . $this->table_name . " SET status = 'inactive' WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Check if username exists
    public function usernameExists($username) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Get total users count
    public function getTotalUsers() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    // Get active sessions count (simplified)
    public function getActiveSessions() {
        $query = "SELECT COUNT(DISTINCT user_id) as active_sessions FROM audit_logs 
                  WHERE action = 'LOGIN' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['active_sessions'] ?? 0;
    }

    // Get total users by role
    public function getTotalUsersByRole($role) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE role = :role";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role', $role);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    // Get total number of evaluators (all roles except EDP)
    public function getTotalEvaluators() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
                 WHERE role IN ('president', 'vice_president', 'dean', 'principal', 
                              'chairperson', 'subject_coordinator') AND status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    // Get users by role
    public function getUsersByRole($role, $status = null) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE role = :role";
        if ($status) {
            $query .= " AND status = :status";
        }
        $query .= " ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role', $role);
        if ($status) {
            $stmt->bindParam(':status', $status);
        }
        $stmt->execute();
        return $stmt;
    }

    // Update user status
    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " 
                 SET status = :status, updated_at = NOW() 
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    // Get recent activities
    public function getRecentActivities($limit = 10) {
        $query = "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>