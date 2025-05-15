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

// Security checks
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

// Get employee ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_emp.php");
    exit();
}
$emp_id = intval($_GET['id']);

// Fetch employee data
$stmt = $conn->prepare("SELECT * FROM employee WHERE emp_id = ?");
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    header("Location: manage_emp.php");
    exit();
}

// Form processing
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_name = trim($_POST['emp_name']);
    $email = trim($_POST['email']);
    $designation = $_POST['designation'];
    $department = trim($_POST['department']);
    $status = $_POST['status'];
    $change_password = !empty($_POST['new_password']);

    // Validation
    if (empty($emp_name)) $errors[] = "Employee name is required";
    if (empty($email)) $errors[] = "Email is required";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if ($change_password && strlen($_POST['new_password']) < 8) $errors[] = "Password must be at least 8 characters";
    if (empty($department)) $errors[] = "Department is required";

    if (empty($errors)) {
        // Check if email exists for another employee
        $stmt = $conn->prepare("SELECT emp_id FROM employee WHERE email = ? AND emp_id != ?");
        $stmt->bind_param("si", $email, $emp_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already exists for another employee";
        } else {
            // Update employee
            if ($change_password) {
                $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE employee SET emp_name = ?, email = ?, password = ?, designation = ?, department = ?, status = ? WHERE emp_id = ?");
                $stmt->bind_param("ssssssi", $emp_name, $email, $hashed_password, $designation, $department, $status, $emp_id);
            } else {
                $stmt = $conn->prepare("UPDATE employee SET emp_name = ?, email = ?, designation = ?, department = ?, status = ? WHERE emp_id = ?");
                $stmt->bind_param("sssssi", $emp_name, $email, $designation, $department, $status, $emp_id);
            }
            
            if ($stmt->execute()) {
                $success = true;
                // Refresh employee data
                $stmt = $conn->prepare("SELECT * FROM employee WHERE emp_id = ?");
                $stmt->bind_param("i", $emp_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $employee = $result->fetch_assoc();
            } else {
                $errors[] = "Error updating employee: " . $conn->error;
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
    <title>Edit Employee - ICT IT Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Same styling as add_emp.php */
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
                <h5 class="mb-0">Edit Employee: <?php echo htmlspecialchars($employee['emp_name']); ?></h5>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">Employee updated successfully!</div>
                <?php endif; ?>
                
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
                               value="<?php echo htmlspecialchars($employee['emp_name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="change_password" name="change_password">
                            <label class="form-check-label" for="change_password">Change password</label>
                        </div>
                        <input type="password" class="form-control" id="new_password" name="new_password" disabled>
                        <small class="text-muted">Leave blank to keep current password</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="designation" class="form-label">Designation *</label>
                        <select class="form-select" id="designation" name="designation" required>
                            <option value="Lead" <?php echo ($employee['designation'] === 'Lead') ? 'selected' : ''; ?>>Lead</option>
                            <option value="Sr SA" <?php echo ($employee['designation'] === 'Sr SA') ? 'selected' : ''; ?>>Sr SA</option>
                            <option value="SA" <?php echo ($employee['designation'] === 'SA') ? 'selected' : ''; ?>>SA</option>
                            <option value="IT_Admin" <?php echo ($employee['designation'] === 'IT_Admin') ? 'selected' : ''; ?>>IT Admin</option>
                            <option value="Lab_incharge" <?php echo ($employee['designation'] === 'Lab_incharge') ? 'selected' : ''; ?>>Lab Incharge</option>
                            <option value="HOD" <?php echo ($employee['designation'] === 'HOD') ? 'selected' : ''; ?>>HOD</option>
                            <option value="FI" <?php echo ($employee['designation'] === 'FI') ? 'selected' : ''; ?>>FI</option>
                            <option value="User" <?php echo ($employee['designation'] === 'User') ? 'selected' : ''; ?>>User</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="department" class="form-label">Department *</label>
                        <input type="text" class="form-control" id="department" name="department" 
                               value="<?php echo htmlspecialchars($employee['department']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Active" <?php echo ($employee['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo ($employee['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="manage_emp.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable/disable password field based on checkbox
        document.getElementById('change_password').addEventListener('change', function() {
            document.getElementById('new_password').disabled = !this.checked;
            if (this.checked) {
                document.getElementById('new_password').required = true;
            } else {
                document.getElementById('new_password').required = false;
            }
        });
    </script>
</body>
</html>