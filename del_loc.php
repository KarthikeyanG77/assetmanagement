<?php
require_once 'config.php';
require_once 'session_helper.php';
redirect_if_not_logged_in();
require_once 'header.php';
if (!isset($_GET['id'])) {
    header("Location: manage_loc.php");
    exit;
}

try {
    // Check if location is used in any assets before deleting
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ast WHERE LocationID = ?");
    $stmt->execute([$_GET['id']]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $_SESSION['error'] = "Cannot delete location - it is assigned to one or more assets!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM location WHERE location_id = ?");
        $stmt->execute([$_GET['id']]);
        $_SESSION['message'] = "Location deleted successfully!";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error deleting location: " . $e->getMessage();
}

header("Location: manage_loc.php");
exit;
?>