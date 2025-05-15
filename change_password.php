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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch user details from database
$query = "SELECT emp_id, emp_name, email, designation FROM employee WHERE emp_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found, logout
    header("Location: logout.php");
    exit();
}

$user = $result->fetch_assoc();
$user_name = $user['emp_name'];
$user_email = $user['email'];
$user_designation = $user['designation'];

// Check if password change is required (first login) or user requested change
$require_password_change = isset($_SESSION['require_password_change']) || isset($_GET['change']);

// Define allowed designations for dashboard.php
$allowed_designations = ['lead', 'sr sa', 'sa', 'it_admin', 'lab incharge'];
$is_allowed_designation = in_array(strtolower($user_designation), $allowed_designations);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($new_password)) {
        $error = "New password is required!";
    } elseif (empty($confirm_password)) {
        $error = "Confirm password is required!";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirm password do not match!";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long!";
    } else {
        // If not first login, verify current password
        if (!$require_password_change) {
            if (empty($current_password)) {
                $error = "Current password is required!";
            } else {
                // Verify current password
                $verify_query = "SELECT password FROM employee WHERE emp_id = ?";
                $verify_stmt = $conn->prepare($verify_query);
                $verify_stmt->bind_param("i", $user_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result->num_rows === 1) {
                    $user_data = $verify_result->fetch_assoc();
                    if (!password_verify($current_password, $user_data['password'])) {
                        $error = "Current password is incorrect!";
                    }
                } else {
                    $error = "User not found!";
                }
            }
        }
        
        // If no errors, proceed with password change
        if (empty($error)) {
            try {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password in database
                $update_query = "UPDATE employee SET password = ?, first_login = 0 WHERE emp_id = ?";
                $update_stmt = $conn->prepare($update_query);
                
                if (!$update_stmt) {
                    throw new Exception("Database error. Please try again later.");
                }
                
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                $update_stmt->execute();
                
                // Clear session variables if this was a required password change
                if ($require_password_change) {
                    unset($_SESSION['require_password_change']);
                }
                
                // Set success message
                $success = "Password changed successfully!";
                
            } catch (Exception $e) {
                $error = "Failed to update password. Please try again.";
                error_log("Password change error: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - ICT IT Asset Management</title>
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
        
        .change-container {
            background-color: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
            position: relative;
        }
        
        .user-info {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: white;
            padding: 8px 15px;
            border-radius: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .user-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s;
            padding: 0;
            margin-left: 10px;
        }
        
        .logout-btn:hover {
            color: #e74c3c;
        }
        
        .user-name {
            font-weight: 500;
        }
        
        .change-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .change-header i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .change-header h2 {
            color: var(--dark-color);
            margin: 0;
            font-weight: 600;
        }
        
        .change-header p {
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
        
        .btn-change {
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
        
        .btn-change:hover {
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
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #7f8c8d;
        }
        
        .strength-weak {
            color: #e74c3c;
        }
        
        .strength-medium {
            color: #f39c12;
        }
        
        .strength-strong {
            color: #27ae60;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="change-container">
        <!-- User Info Section -->
        <div class="user-info">
            <div class="user-icon">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
            <form action="logout.php" method="post">
                <button type="submit" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        </div>
        
        <div class="change-header">
            <i class="fas fa-key"></i>
            <h2><?php echo $require_password_change ? 'Set New Password' : 'Change Password'; ?></h2>
            <p><?php echo $require_password_change ? 'You must change your default password' : 'Update your account password'; ?></p>
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
                <?php echo htmlspecialchars($success); ?>
                <?php if ($require_password_change): ?>
                    <p>Redirecting to dashboard...</p>
                    <script>
                        setTimeout(function() {
                            window.location.href = "<?php echo $is_allowed_designation ? 'dashboard.php' : 'dashboard_fi.php'; ?>";
                        }, 2000);
                    </script>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <?php if (!$require_password_change): ?>
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required 
                           placeholder="Enter current password">
                    <i class="fas fa-lock input-icon"></i>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required 
                       placeholder="Enter new password (min 8 characters)">
                <i class="fas fa-lock input-icon"></i>
                <div class="password-strength" id="password-strength">
                    Password strength: <span id="strength-text">None</span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required 
                       placeholder="Confirm new password">
                <i class="fas fa-lock input-icon"></i>
            </div>
            
            <button type="submit" class="btn-change">
                <i class="fas fa-save me-2"></i> <?php echo $require_password_change ? 'Set Password' : 'Change Password'; ?>
            </button>
            
            <?php if (!$require_password_change): ?>
                <a href="<?php echo $is_allowed_designation ? 'dashboard.php' : 'dashboard_fi.php'; ?>" class="back-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            <?php endif; ?>
        </form>
    </div>

    <script>
        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthText = document.getElementById('strength-text');
            const strength = checkPasswordStrength(password);
            
            strengthText.textContent = strength.text;
            strengthText.className = strength.class;
        });
        
        function checkPasswordStrength(password) {
            let strength = 0;
            
            // Length >= 8
            if (password.length >= 8) strength++;
            
            // Contains lowercase
            if (password.match(/[a-z]/)) strength++;
            
            // Contains uppercase
            if (password.match(/[A-Z]/)) strength++;
            
            // Contains number
            if (password.match(/[0-9]/)) strength++;
            
            // Contains special char
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            if (strength <= 2) {
                return { text: "Weak", class: "strength-weak" };
            } else if (strength <= 4) {
                return { text: "Medium", class: "strength-medium" };
            } else {
                return { text: "Strong", class: "strength-strong" };
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>