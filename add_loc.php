<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start secure session
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

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

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
    header("Location: manage_loc.php");
    exit();
}

// Initialize variables
$location_name = "";
$category_id = "";
$error = "";

// Fetch all categories
$categories = [];
$query = "SELECT * FROM asset_category ORDER BY category_name";
$result = $conn->query($query);
if ($result) {
    $categories = $result->fetch_all(MYSQLI_ASSOC);
}

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate location name
    if (empty(trim($_POST["location_name"]))) {
        $error = "Please enter a location name.";
    } else {
        $location_name = trim($_POST["location_name"]);
    }
    
    // Validate category
    if (empty(trim($_POST["category_id"]))) {
        $error = "Please select a category.";
    } else {
        $category_id = trim($_POST["category_id"]);
    }
    
    // Check input errors before inserting in database
    if (empty($error)) {
        $sql = "INSERT INTO location (location_name, category_id) VALUES (?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("si", $param_location_name, $param_category_id);
            
            $param_location_name = $location_name;
            $param_category_id = $category_id;
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Location added successfully!";
                header("Location: manage_loc.php");
                exit();
            } else {
                $error = "Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Location - ICT IT Asset Management System</title>
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
            max-width: 800px;
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
        
        .admin-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .back-btn-container {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- User Info Section - Fixed at top right -->
    <div class="user-info">
        <div class="user-icon">
            <i class="fas fa-user"></i>
            <?php if ($is_admin): ?>
                <span class="admin-badge">A</span>
            <?php endif; ?>
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
            <a href="manage_loc.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Locations
            </a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Add New Location</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label for="location_name" class="form-label">Location Name</label>
                        <input type="text" class="form-control" id="location_name" name="location_name" 
                               value="<?php echo htmlspecialchars($location_name); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>" 
                                    <?php echo ($category_id == $category['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Location
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>