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

// Prevent self-deletion
if ($emp_id == $user_id) {
    header("Location: manage_emp.php?error=selfdelete");
    exit();
}

// Check if employee exists
$stmt = $conn->prepare("SELECT emp_name FROM employee WHERE emp_id = ?");
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    header("Location: manage_emp.php");
    exit();
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this employee has any assets assigned
    $stmt = $conn->prepare("SELECT COUNT(*) as asset_count FROM assets WHERE assigned_to = ?");
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $asset_count = $result->fetch_assoc()['asset_count'];

    if ($asset_count > 0) {
        header("Location: manage_emp.php?error=hasassets");
        exit();
    }

    // Delete employee
    $stmt = $conn->prepare("DELETE FROM employee WHERE emp_id = ?");
    $stmt->bind_param("i", $emp_id);
    if ($stmt->execute()) {
        header("Location: manage_emp.php?success=deleted");
        exit();
    } else {
        header("Location: manage_emp.php?error=deletefailed");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Employee - ICT IT Asset Management System</title>
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
                <h5 class="mb-0">Delete Employee</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <h5>Are you sure you want to delete this employee?</h5>
                    <p><strong>Employee Name:</strong> <?php echo htmlspecialchars($employee['emp_name']); ?></p>
                    <p>This action cannot be undone.</p>
                </div>
                
                <form method="post">
                    <div class="d-flex justify-content-between">
                        <a href="manage_emp.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-danger">Confirm Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>