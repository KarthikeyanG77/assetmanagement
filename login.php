<?php
session_start();
require_once 'config.php'; // Database connection file

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (!empty($email) && !empty($password)) {
        // Prepare SQL statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT * FROM employee WHERE email = ? AND status = 'Active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $employee = $result->fetch_assoc();
            
            // Verify password (assuming passwords are hashed in the database)
            if (password_verify($password, $employee['password'])) {
                // Password is correct, start a new session
                $_SESSION['user_id'] = $employee['emp_id'];
                $_SESSION['emp_name'] = $employee['emp_name'];
                $_SESSION['email'] = $employee['email'];
                $_SESSION['designation'] = $employee['designation'];
                $_SESSION['department'] = $employee['department'];
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['last_activity'] = time();
                
                // Set success message in session to display after redirect
                $_SESSION['success_message'] = "Login successful! Welcome back, " . $employee['emp_name'] . ".";
                
                // Redirect to appropriate dashboard based on designation
                $dashboard_designations = ['Leas', 'Sr SA', 'SA', 'IT_admin', 'Lab Incharge'];
                $fi_designations = ['HOD', 'FI', 'User'];
                
                if (in_array($employee['designation'], $dashboard_designations)) {
                    header("Location: dashboard.php");
                } elseif (in_array($employee['designation'], $fi_designations)) {
                    header("Location: dashboard_fi.php");
                } else {
                    // Default redirect if designation doesn't match any category
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }
        
        $stmt->close();
    } else {
        $error_message = "Please enter both email and password.";
    }
    
    $conn->close();
}

// Check for success message in session (after redirect)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        
        .login-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            border-color: #4a90e2;
            outline: none;
        }
        
        .error-message {
            color: #e74c3c;
            font-size: 13px;
            margin-bottom: 15px;
            text-align: center;
            padding: 10px;
            background-color: #fde8e8;
            border-radius: 4px;
        }
        
        .success-message {
            color: #2ecc71;
            font-size: 13px;
            margin-bottom: 15px;
            text-align: center;
            padding: 10px;
            background-color: #e8f8f0;
            border-radius: 4px;
        }
        
        .login-button {
            width: 100%;
            padding: 12px;
            background-color: #4a90e2;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .login-button:hover {
            background-color: #357abd;
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }
        
        .forgot-password a {
            color: #4a90e2;
            text-decoration: none;
            font-size: 13px;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .company-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .company-logo img {
            max-width: 150px;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="company-logo">
            <!-- Replace with your company logo -->
            <img src="logo.png" alt="Company Logo">
        </div>
        
        <div class="login-header">
            <h1>Employee Login</h1>
            <p>Enter your credentials to access your account</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            
            <button type="submit" class="login-button">Login</button>
        </form>
        
        <div class="forgot-password">
            <a href="forgot-password.php">Forgot your password?</a>
        </div>
    </div>
</body>
</html>