<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if log_id is provided
if (!isset($_GET['log_id'])) {
    header("Location: manage_servicelog.php");
    exit();
}

$log_id = $_GET['log_id'];

// Handle deletion
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    try {
        $stmt = $pdo->prepare("DELETE FROM service_log WHERE log_id = ?");
        $stmt->execute([$log_id]);
        
        header("Location: manage_servicelog.php?deleted=1");
        exit();
    } catch (PDOException $e) {
        die("Error deleting service log: " . $e->getMessage());
    }
} else {
    // Fetch service log details for confirmation
    try {
        $stmt = $pdo->prepare("SELECT sl.*, a.asset_name, sc.center_name 
                              FROM service_log sl
                              JOIN asset a ON sl.asset_id = a.asset_id
                              JOIN service_center sc ON sl.center_id = sc.center_id
                              WHERE sl.log_id = ?");
        $stmt->execute([$log_id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$log) {
            header("Location: manage_servicelog.php?error=notfound");
            exit();
        }
    } catch (PDOException $e) {
        die("Error fetching service log: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Service Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card-header {
            background-color: #dc3545;
            color: white;
        }
        .back-btn {
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <a href="manage_servicelog.php" class="back-btn">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <h2 class="mb-0"><i class="fas fa-trash-alt me-2"></i>Delete Service Log</h2>
                <div style="width: 40px;"></div>
            </div>
            
            <div class="card-body">
                <div class="alert alert-danger">
                    <h4 class="alert-heading">Warning!</h4>
                    <p>You are about to delete the following service log. This action cannot be undone.</p>
                </div>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Service Log Details</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>Asset:</strong> <?= htmlspecialchars($log['asset_name']) ?></li>
                            <li class="list-group-item"><strong>Service Center:</strong> <?= htmlspecialchars($log['center_name']) ?></li>
                            <li class="list-group-item"><strong>Service Date:</strong> <?= htmlspecialchars($log['service_date']) ?></li>
                            <li class="list-group-item"><strong>Status:</strong> <?= htmlspecialchars($log['status']) ?></li>
                            <li class="list-group-item"><strong>Issue Description:</strong> <?= htmlspecialchars($log['issue_description']) ?></li>
                        </ul>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="manage_servicelog.php" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                    <a href="del_servicelog.php?log_id=<?= $log_id ?>&confirm=yes" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> Confirm Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>