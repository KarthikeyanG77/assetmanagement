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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $centerName = trim($_POST['center_name']);
        $contactPerson = trim($_POST['contact_person']);
        $contactEmail = trim($_POST['contact_email']);
        $phone = trim($_POST['phone']);

        // Basic validation
        if (empty($centerName) || empty($contactPerson)) {
            $error = "Center name and contact person are required!";
        } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format!";
        } else {
            $stmt = $conn->prepare("INSERT INTO service_center (center_name, contact_person, contact_email, phone) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $centerName, $contactPerson, $contactEmail, $phone);
            $stmt->execute();

            $_SESSION['message'] = "Service center added successfully!";
            header("Location: manage_service.php");
            exit;
        }
    } catch (Exception $e) {
        if ($e->getCode() == 23000) {
            $error = "This service center already exists!";
        } else {
            $error = "Error adding service center: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Service Center</title>
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
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Add New Service Center</h3>
                    <a href="manage_service.php" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label for="center_name" class="form-label">Center Name</label>
                        <input type="text" class="form-control" id="center_name" name="center_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact_person" class="form-label">Contact Person</label>
                        <input type="text" class="form-control" id="contact_person" name="contact_person" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact_email" class="form-label">Contact Email</label>
                        <input type="email" class="form-control" id="contact_email" name="contact_email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="reset" class="btn btn-secondary me-md-2">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Center
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>