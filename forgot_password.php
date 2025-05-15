<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Include config file
require_once 'config.php';

// Default password
$default_password = 'ABCD';
$hashed_default_password = password_hash($default_password, PASSWORD_DEFAULT);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        try {
            // Check if email exists
            $query = "SELECT emp_id, emp_name FROM employee WHERE email = ?";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Database error. Please try again later.");
            }
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Update password to default
                $update_query = "UPDATE employee SET password = ?, first_login = 1 WHERE email = ?";
                $update_stmt = $conn->prepare($update_query);
                
                if (!$update_stmt) {
                    throw new Exception("Database error. Please try again later.");
                }
                
                $update_stmt->bind_param("ss", $hashed_default_password, $email);
                $update_stmt->execute();
                
                // In a real application, you would send an email here
                // For this example, we'll just show the default password
                $success = "Your password has been reset to the default: <strong>ABCD</strong>. Please login and change it immediately.";
                
                // Store email in session for optional verification
                $_SESSION['reset_email'] = $email;
            } else {
                $error = "No account found with that email address";
            }
        } catch (Exception $e) {
            $error = "An error occurred. Please try again later.";
            error_log("Forgot Password Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ICT IT Asset Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .forgot-container {
            background-color: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
        }
        
        .forgot-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .forgot-header i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .forgot-header h2 {
            color: var(--dark-color);
            margin: 0;
            font-weight: 600;
        }
        
        .forgot-header p {
            color: #7f8c8d;
            margin: 0.5rem 0 0;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 38px;
            color: #95a5a6;
        }
        
        .btn-submit {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            background-color: var(--secondary-color);
        }
        
        .error-message {
            color: #e74c3c;
            background-color: #fde8e8;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #e74c3c;
        }
        
        .success-message {
            color: #27ae60;
            background-color: #e8f8f0;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #27ae60;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <i class="fas fa-unlock-alt"></i>
            <h2>Forgot Password</h2>
            <p>Enter your email to reset your password</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required 
                       placeholder="Enter your registered email">
                <i class="fas fa-envelope input-icon"></i>
            </div>
            
            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane me-2"></i> Reset Password
            </button>
            
            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left me-2"></i> Back to login
            </a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>