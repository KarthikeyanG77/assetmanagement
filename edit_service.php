<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

if (!isset($_GET['id'])) {
    header("Location: manage_service.php");
    exit();
}

$id = $_GET['id'];

// Fetch existing data
$stmt = $pdo->prepare("SELECT * FROM service_center WHERE center_id = ?");
$stmt->execute([$id]);
$center = $stmt->fetch();

if (!$center) {
    header("Location: manage_service.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $center_name = $_POST['center_name'];
    $contact_person = $_POST['contact_person'];
    $contact_email = $_POST['contact_email'];
    $phone = $_POST['phone'];
    $alt_phone = $_POST['alt_phone'];
    $address = $_POST['address'];

    $stmt = $pdo->prepare("UPDATE service_center SET center_name = ?, contact_person = ?, contact_email = ?, 
                          phone = ?, alt_phone = ?, address = ? WHERE center_id = ?");
    $stmt->execute([$center_name, $contact_person, $contact_email, $phone, $alt_phone, $address, $id]);

    header("Location: manage_service.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .form-container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
            justify-content: space-between;
            align-items: center;
        }
        .back-btn {
            margin-right: 15px;
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
        <div class="form-container">
            <div class="page-header">
                <h1><i class="fas fa-tools me-2"></i>Edit Service Center</h1>
                <a href="manage_service.php" class="btn btn-secondary back-btn">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>

            <form method="post">
                <input type="hidden" name="id" value="<?php echo $center['center_id']; ?>">

                <div class="mb-3">
                    <label for="center_name" class="form-label">Center Name:</label>
                    <input type="text" class="form-control" id="center_name" name="center_name" 
                           value="<?php echo htmlspecialchars($center['center_name']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="contact_person" class="form-label">Contact Person:</label>
                    <input type="text" class="form-control" id="contact_person" name="contact_person" 
                           value="<?php echo htmlspecialchars($center['contact_person']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="contact_email" class="form-label">Contact Email:</label>
                    <input type="email" class="form-control" id="contact_email" name="contact_email" 
                           value="<?php echo htmlspecialchars($center['contact_email']); ?>" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Phone:</label>
                        <input type="text" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($center['phone']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="alt_phone" class="form-label">Alternate Phone:</label>
                        <input type="text" class="form-control" id="alt_phone" name="alt_phone" 
                               value="<?php echo htmlspecialchars($center['alt_phone']); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Address:</label>
                    <textarea class="form-control" id="address" name="address" rows="4"><?php echo htmlspecialchars($center['address']); ?></textarea>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Service Center
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>