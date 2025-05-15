<?php
session_start();
require_once 'config.php';
require_once 'session_helper.php';
redirect_if_not_logged_in();

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

// Handle asset type deletion
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    try {
        $typeId = $_POST['type_id'];
        
        // First check if there are any assets using this type
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM asset WHERE type_id = ?");
        $checkStmt->bind_param("i", $typeId);
        $checkStmt->execute();
        $checkStmt->bind_result($assetCount);
        $checkStmt->fetch();
        $checkStmt->close();
        
        if ($assetCount > 0) {
            $error = "Cannot delete asset type - there are assets assigned to this type!";
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM asset_type WHERE type_id = ?");
            $deleteStmt->bind_param("i", $typeId);
            $deleteStmt->execute();
            
            if ($deleteStmt->affected_rows > 0) {
                $_SESSION['message'] = "Asset type deleted successfully!";
                header("Location: manage_astty.php");
                exit;
            } else {
                $error = "Asset type not found or already deleted!";
            }
            $deleteStmt->close();
        }
    } catch (Exception $e) {
        $error = "Error deleting asset type: " . $e->getMessage();
    }
}

// Get asset type details for confirmation
$typeDetails = [];
if (isset($_GET['id'])) {
    $typeId = $_GET['id'];
    $stmt = $conn->prepare("SELECT type_id, type_name FROM asset_type WHERE type_id = ?");
    $stmt->bind_param("i", $typeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $typeDetails = $result->fetch_assoc();
    } else {
        $error = "Asset type not found!";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Asset Type</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .container { 
            max-width: 600px; 
            margin-top: 50px; 
        }
        .card { 
            border-radius: 10px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        }
        .form-label { 
            font-weight: 500; 
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
        .top-navigation {
            position: absolute;
            top: 20px;
            left: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .back-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 1.1rem;
        }
        .back-btn:hover {
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <!-- Top Navigation with Back Button -->
    <div class="top-navigation">
        <a href="manage_astty.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Asset Types
        </a>
    </div>

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
                <h3 class="mb-0">Delete Asset Type</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if (empty($typeDetails)): ?>
                    <div class="alert alert-warning">No asset type selected or asset type not found.</div>
                    <a href="manage_astty.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Asset Types
                    </a>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Are you sure you want to delete this asset type?
                    </div>
                    
                    <div class="mb-4">
                        <h5>Asset Type Details</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>Type ID</th>
                                <td><?= htmlspecialchars($typeDetails['type_id']) ?></td>
                            </tr>
                            <tr>
                                <th>Type Name</th>
                                <td><?= htmlspecialchars($typeDetails['type_name']) ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <form method="post">
                        <input type="hidden" name="type_id" value="<?= htmlspecialchars($typeDetails['type_id']) ?>">
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="manage_astty.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" name="delete" class="btn btn-danger">
                                <i class="fas fa-trash-alt"></i> Confirm Delete
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>