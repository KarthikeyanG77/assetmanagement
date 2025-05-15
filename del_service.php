<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include config file
require_once 'config.php';

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user's name from database
$currentUserName = "User";
$user_id = $_SESSION['user_id'];
$query = "SELECT emp_name FROM employee WHERE emp_id = ?";

if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $currentUserName = $user['emp_name'];
    }
    $stmt->close();
}

// Check if ID parameter exists
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No service center specified for deletion.";
    header("Location: manage_service.php");
    exit();
}

$centerId = (int)$_GET['id'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("DELETE FROM service_center WHERE center_id = ?");
        $stmt->bind_param("i", $centerId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $_SESSION['message'] = "Service center deleted successfully!";
        } else {
            $_SESSION['error'] = "Service center not found or already deleted!";
        }
        $stmt->close();
        
        header("Location: manage_service.php");
        exit();
    } catch (Exception $e) {
        $error = "Error deleting service center: " . $e->getMessage();
    }
}

// Fetch service center details for confirmation
$centerDetails = [];
$query = "SELECT center_name FROM service_center WHERE center_id = ?";
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $centerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Service center not found!";
        header("Location: manage_service.php");
        exit();
    }
    
    $centerDetails = $result->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Service Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container { 
            max-width: 600px; 
            margin-top: 50px; 
        }
        .card { 
            border-radius: 10px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            border: none;
        }
        .user-info {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-icon {
            width: 40px;
            height: 40px;
            background-color: #0d6efd;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logout-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
        }
        .logout-btn:hover {
            color: #0d6efd;
        }
        .warning-icon {
            color: #dc3545;
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- User Info Section -->
    <div class="user-info">
        <div class="user-icon">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <span><?php echo htmlspecialchars($currentUserName); ?></span>
            <form action="logout.php" method="post" style="display: inline;">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Delete Service Center</h3>
                    <a href="manage_service.php" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body text-center">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h4>Confirm Deletion</h4>
                <p class="lead">Are you sure you want to delete the service center:</p>
                <p class="h5 mb-4"><strong><?= htmlspecialchars($centerDetails['center_name']) ?></strong>?</p>
                <p class="text-muted">This action cannot be undone.</p>

                <form method="post">
                    <input type="hidden" name="center_id" value="<?= $centerId ?>">
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                        <a href="manage_service.php" class="btn btn-secondary me-md-2">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> Confirm Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>