<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 day
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Include config file
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Validate session security
if (!isset($_SESSION['ip_address']) || !isset($_SESSION['user_agent']) || 
    $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || 
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

// Get current user's details
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

// Check if user has admin privileges
$is_admin = in_array($user_designation, ['Lead', 'Sr SA', 'IT_Admin']);
if (!$is_admin) {
    header("Location: manage_astcat.php");
    exit();
}

// Initialize variables
$category_id = $category_name = "";
$error = "";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate category ID
    if (empty(trim($_POST["category_id"]))) {
        $error = "Please enter a category ID.";
    } else {
        $category_id = trim($_POST["category_id"]);
    }
    
    // Validate category name
    if (empty(trim($_POST["category_name"]))) {
        $error = "Please enter a category name.";
    } else {
        $category_name = trim($_POST["category_name"]);
    }
    
    // Check input errors before updating in database
    if (empty($error)) {
        // First check if category with this name already exists (excluding current category)
        $check_sql = "SELECT category_id FROM asset_category WHERE category_name = ? AND category_id != ?";
        if ($check_stmt = $conn->prepare($check_sql)) {
            $check_stmt->bind_param("si", $category_name, $category_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $error = "A category with this name already exists.";
            }
            $check_stmt->close();
        }
        
        if (empty($error)) {
            $sql = "UPDATE asset_category SET category_name = ? WHERE category_id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("si", $param_category_name, $param_category_id);
                
                // Set parameters
                $param_category_name = $category_name;
                $param_category_id = $category_id;
                
                // Attempt to execute the prepared statement
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Asset category updated successfully";
                    header("Location: manage_astcat.php");
                    exit();
                } else {
                    $error = "Something went wrong. Please try again later.";
                }
                
                // Close statement
                $stmt->close();
            }
        }
    }
} else {
    // Check existence of id parameter before processing further
    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
        // Get URL parameter
        $category_id = trim($_GET["id"]);
        
        // Prepare a select statement
        $sql = "SELECT * FROM asset_category WHERE category_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("i", $param_id);
            
            // Set parameters
            $param_id = $category_id;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    /* Fetch result row as an associative array */
                    $row = $result->fetch_array(MYSQLI_ASSOC);
                    
                    // Retrieve individual field value
                    $category_name = $row["category_name"];
                } else {
                    // URL doesn't contain valid id
                    header("Location: error.php");
                    exit();
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            
            // Close statement
            $stmt->close();
        }
    } else {
        // URL doesn't contain id parameter
        header("Location: error.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Asset Category - ICT IT Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 60px;
        }
        
        .container {
            max-width: 600px;
            margin-top: 20px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            border-top: 4px solid var(--primary-color);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-danger {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .user-info {
            position: fixed;
            top: 10px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: white;
            padding: 8px 15px;
            border-radius: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .user-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
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
            transition: color 0.3s;
        }
        
        .logout-btn:hover {
            color: var(--accent-color);
        }
        
        .back-btn-container {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- User Info Section - Fixed at top right -->
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
        <!-- Back Button -->
        <div class="back-btn-container">
            <a href="manage_astcat.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Asset Categories
            </a>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Edit Asset Category</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
                    
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" 
                               value="<?php echo htmlspecialchars($category_name); ?>" required>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update
                        </button>
                        <a href="manage_astcat.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>