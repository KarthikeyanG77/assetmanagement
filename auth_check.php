<?php
// Start session securely
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

// Validate session security
if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || 
    $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    session_unset();
    session_destroy();
    header("Location: login.php?security=1");
    exit();
}

// Check for session timeout (30 minutes)
$inactive = 1800;
if (isset($_SESSION['last_activity'])) {
    $session_life = time() - $_SESSION['last_activity'];
    if ($session_life > $inactive) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
}
$_SESSION['last_activity'] = time();

// Get current user's designation and name
$user_designation = null;
$currentUserName = "User";
$user_id = $_SESSION['user_id'];
$query = "SELECT designation, emp_name FROM employee WHERE emp_id = ?";

if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_designation = $user['designation'];
        $currentUserName = $user['emp_name'];
        $_SESSION['designation'] = $user_designation;
        $_SESSION['emp_name'] = $currentUserName;
    }
    $stmt->close();
}

// Define user roles with all necessary variables
$admin_designations = ['Lead', 'Sr SA', 'SA', 'IT_Admin', 'Lab incharge'];
$fi_designations = ['FI'];
$hod_designations = ['HOD'];

$is_admin = in_array($user_designation, $admin_designations);
$is_fi = in_array($user_designation, $fi_designations);
$is_hod = in_array($user_designation, $hod_designations);
$is_regular_user = !$is_admin && !$is_fi && !$is_hod;

// Store role flags in session for easy access
$_SESSION['is_admin'] = $is_admin;
$_SESSION['is_fi'] = $is_fi;
$_SESSION['is_hod'] = $is_hod;
$_SESSION['is_regular_user'] = $is_regular_user;
?>