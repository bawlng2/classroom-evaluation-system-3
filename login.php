<?php
session_start();
// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    if(in_array($_SESSION['role'], ['president', 'vice_president'])) {
        header("Location: leaders/dashboard.php");
    } else {
        header("Location: evaluators/dashboard.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AI Classroom Evaluation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
        }
        
        .login-body {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            padding: 40px 30px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            border: none;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 8px;
        }
        
        .form-control {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--secondary) 0%, #2980b9 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 12px 15px;
        }
        
        .system-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .system-info h5 {
            margin-bottom: 15px;
        }
        
        .system-info ul {
            padding-left: 20px;
            margin-bottom: 0;
        }
        
        .system-info li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2><i class="fas fa-robot me-2"></i>AI Classroom Evaluation</h2>
                <p>SMCC Quality Management System</p>
            </div>
            
            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <form action="auth/login-process.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">
                        <i class="fas fa-user me-2"></i>Username
                    </label>
                    <input type="text" class="form-control" id="username" name="username" required 
                           placeholder="Enter your username">
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <input type="password" class="form-control" id="password" name="password" required 
                           placeholder="Enter your password">
                </div>
                
                <div class="mb-4">
                    <label for="role" class="form-label">
                        <i class="fas fa-user-tag me-2"></i>Role
                    </label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="">Select Your Role</option>
                        <option value="edp">EDP</option>
                        <option value="president">President</option>
                        <option value="vice_president">Vice President</option>
                        <option value="dean">Dean</option>
                        <option value="principal">Principal</option>
                        <option value="chairperson">Chairperson</option>
                        <option value="subject_coordinator">Subject Coordinator</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login w-100 mb-4">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const role = document.getElementById('role').value;
            if (!role) {
                e.preventDefault();
                alert('Please select your role');
                return false;
            }
        });
    </script>
</body>
</html>