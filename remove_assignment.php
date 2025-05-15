<?php
require_once 'config.php';
session_start();

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['emp_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user's designation
$user_designation = getUserDesignation($conn, $_SESSION['emp_id']);
$is_admin = in_array($user_designation, ['Lead', 'Sr SA', 'IT_Admin', 'Lab_incharge']);

if (!$is_admin) {
    die("You don't have permission to access this page");
}

// Get current user's name
$currentUserName = "User";
$query = "SELECT emp_name FROM employee WHERE emp_id = ?";
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $_SESSION['emp_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $currentUserName = $user['emp_name'];
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assignment_id'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }

    $assignment_id = $_POST['assignment_id'];
    
    // Soft delete (set is_active to 0) instead of actual deletion
    $stmt = $conn->prepare("UPDATE faculty_incharge SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $assignment_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Assignment removed successfully";
    } else {
        $_SESSION['error'] = "Error removing assignment: " . $conn->error;
    }
}

header("Location: assign_faculty_incharge.php");
exit();
?>