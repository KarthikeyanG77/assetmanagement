<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CSRF token generation and verification functions
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

// Generate CSRF token for the form
$csrf_token = generateCSRFToken();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user's name and designation
$currentUserName = "User";
$user_designation = null;
$user_id = $_SESSION['user_id'];
$query = "SELECT emp_name, designation FROM employee WHERE emp_id = ?";

if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $currentUserName = $user['emp_name'];
        $user_designation = $user['designation'];
    }
    $stmt->close();
}

// Check admin privileges
$is_admin = in_array($user_designation, ['Lead', 'Sr SA', 'IT_Admin']);
if (!$is_admin) {
    $_SESSION['error_message'] = "You don't have permission to access this page";
    header("Location: manage_ast.php");
    exit();
}

// Check if asset ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "No asset specified";
    header("Location: manage_ast.php");
    exit();
}

$asset_id = (int)$_GET['id'];

// Fetch asset data for confirmation
$asset = [];
$query = "SELECT a.*, at.type_name 
          FROM asset a 
          JOIN asset_type at ON a.type_id = at.type_id 
          WHERE a.asset_id = ?";
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $asset_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Asset not found";
        header("Location: manage_ast.php");
        exit();
    }
    
    $asset = $result->fetch_assoc();
    $stmt->close();
} else {
    $_SESSION['error_message'] = "Database error: " . $conn->error;
    header("Location: manage_ast.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "CSRF token validation failed";
        header("Location: manage_ast.php");
        exit();
    }
    
    $confirm = $_POST['confirm'] ?? '';
    
    if ($confirm === 'yes') {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Delete the asset
            $query = "DELETE FROM asset WHERE asset_id = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("i", $asset_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            // Check if any rows were affected
            if ($stmt->affected_rows === 0) {
                throw new Exception("No asset was deleted - maybe it was already removed?");
            }
            
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success_message'] = "Asset deleted successfully!";
            header("Location: manage_ast.php");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($conn) {
                $conn->rollback();
            }
            $_SESSION['error_message'] = "Error deleting asset: " . $e->getMessage();
            header("Location: del_ast.php?id=" . $asset_id);
            exit();
        }
    } else {
        $_SESSION['info_message'] = "Asset deletion canceled";
        header("Location: manage_ast.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Asset</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .confirmation-container {
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
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
        .page-header {
            margin-bottom: 30px;
            display: flex;
            align-items: center;
        }
        .page-title {
            flex-grow: 1;
            text-align: center;
        }
        .back-btn-container {
            margin-right: 15px;
        }
        .asset-details {
            margin-bottom: 20px;
        }
        .confirmation-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
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
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <div class="back-btn-container">
                <a href="manage_ast.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Assets
                </a>
            </div>
            <h2 class="page-title">Delete Asset</h2>
        </div>
        
        <div class="confirmation-container">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-warning">
                <h4><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</h4>
                <p>Are you sure you want to delete this asset? This action cannot be undone.</p>
                
                <div class="asset-details">
                    <h5>Asset Details</h5>
                    <p><strong>Name:</strong> <?= htmlspecialchars($asset['asset_name']) ?></p>
                    <p><strong>Type:</strong> <?= htmlspecialchars($asset['type_name']) ?></p>
                    <p><strong>Serial No:</strong> <?= htmlspecialchars($asset['serial_no'] ?? 'N/A') ?></p>
                    <p><strong>R No:</strong> <?= htmlspecialchars($asset['r_no'] ?? 'N/A') ?></p>
                </div>
                
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="confirmation-buttons">
                        <button type="submit" name="confirm" value="yes" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-1"></i> Yes, Delete
                        </button>
                        <button type="submit" name="confirm" value="no" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>