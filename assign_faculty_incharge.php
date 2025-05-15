<?php
require_once 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['emp_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user's designation
$user_designation = getUserDesignation($conn, $_SESSION['emp_id']);
$is_admin = in_array($user_designation, ['Lead', 'Sr SA', 'IT_Admin', 'Lab_incharge']);

if (!$is_admin) {
    header("Location: dashboard.php");
    exit();
}

// Get current user's name
$currentUserName = "User";
$query = "SELECT emp_name FROM employee WHERE emp_id = ?";
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $_SESSION['emp_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $currentUserName = $user['emp_name'];
    }
    $stmt->close();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_faculty'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }

    $lab_id = $_POST['lab_id'];
    $emp_id = $_POST['emp_id'];
    $position = $_POST['position'];

    if (empty($lab_id) || empty($emp_id)) {
        $_SESSION['error'] = "Please select both lab and faculty";
    } else {
        $stmt = $conn->prepare("SELECT id FROM faculty_incharge WHERE lab_id = ? AND emp_id = ? AND is_active = 1");
        $stmt->bind_param("ii", $lab_id, $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $_SESSION['error'] = "This faculty is already assigned to this lab";
        } else {
            $stmt = $conn->prepare("INSERT INTO faculty_incharge (lab_id, emp_id, assigned_date, position) VALUES (?, ?, CURDATE(), ?)");
            $stmt->bind_param("iis", $lab_id, $emp_id, $position);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Faculty assigned successfully";
            } else {
                $_SESSION['error'] = "Error assigning faculty: " . $conn->error;
            }
        }
    }
    header("Location: assign_faculty_incharge.php");
    exit();
}

// Get all active labs
$labs = [];
$result = $conn->query("SELECT location_id, location_name FROM location WHERE category_id = 1 ORDER BY location_name");
if ($result) {
    $labs = $result->fetch_all(MYSQLI_ASSOC);
}

// Get all faculty members
$faculty = [];
$result = $conn->query("SELECT emp_id, emp_name FROM employee WHERE designation IN ('Lead', 'Sr SA', 'SA', 'Lab_incharge') ORDER BY emp_name");
if ($result) {
    $faculty = $result->fetch_all(MYSQLI_ASSOC);
}

// Get current assignments
$assignments = [];
$result = $conn->query("
    SELECT fi.id, l.location_name, e.emp_name, fi.assigned_date, fi.position 
    FROM faculty_incharge fi
    JOIN location l ON fi.lab_id = l.location_id
    JOIN employee e ON fi.emp_id = e.emp_id
    WHERE fi.is_active = 1
    ORDER BY l.location_name, fi.position
");
if ($result) {
    $assignments = $result->fetch_all(MYSQLI_ASSOC);
}

// Check for messages from remove_assignment.php
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;
unset($_SESSION['error']);
unset($_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Same head content as before -->
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

    <div class="dashboard-container">
        <div class="welcome-section mb-4">
            <h2>Assign Faculty In-Charge</h2>
            <p class="text-muted">Manage lab faculty assignments</p>
            <a href="dashboard.php" class="btn btn-secondary mb-3">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Rest of the form remains the same -->
    </div>
</body>
</html>