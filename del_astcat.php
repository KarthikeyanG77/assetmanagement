<?php
require_once 'config.php';
require_once 'session_helper.php';
redirect_if_not_logged_in();
require_once 'header.php';
// Check if category ID is provided
if (!isset($_GET['id'])) {
    header("Location: manage_astcat.php");
    exit;
}

$categoryId = (int)$_GET['id'];

try {
    // Check if category is being used in locations table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM location WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $usageCount = $stmt->fetchColumn();

    if ($usageCount > 0) {
        $_SESSION['error'] = "Cannot delete category - it is being used in one or more locations!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM asset_category WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['message'] = "Category deleted successfully!";
        } else {
            $_SESSION['error'] = "Category not found or already deleted!";
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error deleting category: " . $e->getMessage();
}

header("Location: manage_astcat.php");
exit;
?>