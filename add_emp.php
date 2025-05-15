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

require_once 'config.php';

// Security checks (same as dashboard.php)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['ip_address']) || !isset($_SESSION['user_agent']) || 
    $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || 
    $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    header("Location: login.php?security=1");
    exit();
}

// Check admin privileges
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT designation FROM employee WHERE emp_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!in_array($user['designation'], ['Lead', 'Sr SA', 'IT_Admin'])) {
    header("Location: dashboard.php");
    exit();
}

// Form processing
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_name = trim($_POST['emp_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $designation = $_POST['designation'];
    $department = trim($_POST['department']);
    $status = $_POST['status'];

    // Validation
    if (empty($emp_name)) $errors[] = "Employee name is required";
    if (empty($email)) $errors[] = "Email is required";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($password)) $errors[] = "Password is required";
    elseif (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if (empty($department)) $errors[] = "Department is required";

    if (empty($errors)) {
        // Check if email exists
        $stmt = $conn->prepare("SELECT emp_id FROM employee WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already exists";
        } else {
            // Insert new employee
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO employee (emp_name, email, password, designation, department, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $emp_name, $email, $hashed_password, $designation, $department, $status);
            if ($stmt->execute()) {
                $success = true;
                // Reset form values
                $_POST = [];
            } else {
                $errors[] = "Error adding employee: " . $conn->error;
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
    <title>Add Employee - ICT IT Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Same styling as dashboard.php */
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            max-width: 800px;
            margin-top: 20px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            border-top: 4px solid var(--primary-color);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
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
        }
    </style>
</head>
<body>
    <!-- User Info Section (same as dashboard.php) -->
    <div class="user-info">
        <div class="user-icon">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <span><?php echo htmlspecialchars($_SESSION['emp_name'] ?? 'User'); ?></span>
            <form action="logout.php" method="post" style="display: inline;">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Add New Employee</h5>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">Employee added successfully!</div>
                    <a href="manage_emp.php" class="btn btn-primary">Back to Employee List</a>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label for="emp_name" class="form-label">Employee Name *</label>
                            <input type="text" class="form-control" id="emp_name" name="emp_name" 
                                   value="<?php echo htmlspecialchars($_POST['emp_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="designation" class="form-label">Designation *</label>
                            <select class="form-select" id="designation" name="designation" required>
                                <option value="">Select Designation</option>
                                <option value="Lead" <?php echo (($_POST['designation'] ?? '') === 'Lead') ? 'selected' : ''; ?>>Lead</option>
                                <option value="Sr SA" <?php echo (($_POST['designation'] ?? '') === 'Sr SA') ? 'selected' : ''; ?>>Sr SA</option>
                                <option value="SA" <?php echo (($_POST['designation'] ?? '') === 'SA') ? 'selected' : ''; ?>>SA</option>
                                <option value="IT_Admin" <?php echo (($_POST['designation'] ?? '') === 'IT_Admin') ? 'selected' : ''; ?>>IT Admin</option>
                                <option value="Lab_incharge" <?php echo (($_POST['designation'] ?? '') === 'Lab_incharge') ? 'selected' : ''; ?>>Lab Incharge</option>
                                <option value="HOD" <?php echo (($_POST['designation'] ?? '') === 'HOD') ? 'selected' : ''; ?>>HOD</option>
                                <option value="FI" <?php echo (($_POST['designation'] ?? '') === 'FI') ? 'selected' : ''; ?>>FI</option>
                                <option value="User" <?php echo (($_POST['designation'] ?? '') === 'User') ? 'selected' : ''; ?>>User</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="department" class="form-label">Department *</label>
                            <input type="text" class="form-control" id="department" name="department" 
                                   value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Active" <?php echo (($_POST['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo (($_POST['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="manage_emp.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Add Employee</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>