<?php
session_start();
require_once 'config.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Lead', 'Sr SA', 'IT_Admin'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required = ['asset_name', 'type_id'];
    $errors = [];
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst($field) . " is required";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        header("Location: add_asset.php");
        exit();
    }
    
    // Process asset data with prepared statements
    try {
        $stmt = $conn->prepare("INSERT INTO asset (...) VALUES (...)");
        // Bind all parameters
        // Execute statement
        
        $_SESSION['success_message'] = "Asset added successfully!";
        header("Location: assets.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: add_asset.php");
        exit();
    }
} else {
    header("Location: add_asset.php");
    exit();
}
?>