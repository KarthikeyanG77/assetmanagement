<?php
require_once 'config.php';
require_once 'header.php';
if (!isset($_GET['id'])) {
    header("Location: manage_dpt.php");
    exit();
}

try {
    // Check if department exists
    $stmt = $pdo->prepare("SELECT * FROM dpt WHERE DepartmentID = ?");
    $stmt->execute([$_GET['id']]);
    $department = $stmt->fetch();
    
    if (!$department) {
        $_SESSION['error'] = "Department not found!";
        header("Location: manage_dpt.php");
        exit();
    }
    
    // Check if department is being used
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM emp WHERE Department = ?");
    $stmt->execute([$department['DepartmentName']]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $_SESSION['error'] = "Cannot delete department - it is assigned to employees!";
        header("Location: manage_dpt.php");
        exit();
    }
    
    // Delete department
    $stmt = $pdo->prepare("DELETE FROM dpt WHERE DepartmentID = ?");
    $stmt->execute([$_GET['id']]);
    
    $_SESSION['message'] = "Department deleted successfully!";
} catch (PDOException $e) {
    $_SESSION['error'] = "Error deleting department: " . $e->getMessage();
}

header("Location: manage_dpt.php");
exit();
?>