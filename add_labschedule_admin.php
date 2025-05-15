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

// Get current user's details from database
$currentUser = [];
$user_id = $_SESSION['user_id'];
$query = "SELECT emp_name, designation FROM employee WHERE emp_id = ?";

if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $currentUser = $result->fetch_assoc();
    }
    $stmt->close();
}

// Check if user has appropriate role (Lead, Sr SA, SA, IT_Admin)
$allowed_roles = ['Lead', 'Sr SA', 'SA', 'IT_Admin'];
if (!in_array($currentUser['designation'], $allowed_roles)) {
    header("Location: dashboard.php");
    exit();
}

// Initialize variables
$lab_id = $day_of_week = $time_from = $time_to = $subject_code = $subject_name = '';
$faculty_name = $faculty_email = $class_group = $semester = $academic_year = '';
$success = $error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $lab_id = $_POST['lab_id'] ?? '';
    $day_of_week = $_POST['day_of_week'] ?? '';
    $time_from = $_POST['time_from'] ?? '';
    $time_to = $_POST['time_to'] ?? '';
    $subject_code = $_POST['subject_code'] ?? '';
    $subject_name = $_POST['subject_name'] ?? '';
    $faculty_name = $_POST['faculty_name'] ?? '';
    $faculty_email = $_POST['faculty_email'] ?? '';
    $class_group = $_POST['class_group'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $academic_year = $_POST['academic_year'] ?? '';
    $created_by = $_SESSION['user_id'];
    
    // Combine times
    $time_slot = $time_from . '-' . $time_to;
    
    // Validate required fields
    if (empty($lab_id) || empty($day_of_week) || empty($time_from) || empty($time_to) || empty($faculty_name)) {
        $error = "Please fill in all required fields.";
    } elseif ($time_from >= $time_to) {
        $error = "End time must be after start time.";
    } else {
        // Check for overlapping schedules
        $check_query = "SELECT * FROM lab_schedule 
                       WHERE lab_id = ? AND day_of_week = ? 
                       AND (
                           (? BETWEEN SUBSTRING_INDEX(time_slot, '-', 1) AND SUBSTRING_INDEX(time_slot, '-', -1)) OR
                           (? BETWEEN SUBSTRING_INDEX(time_slot, '-', 1) AND SUBSTRING_INDEX(time_slot, '-', -1)) OR
                           (SUBSTRING_INDEX(time_slot, '-', 1) BETWEEN ? AND ?) OR
                           (SUBSTRING_INDEX(time_slot, '-', -1) BETWEEN ? AND ?)
                       )";
        
        $stmt = $conn->prepare($check_query);
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("isssssss", $lab_id, $day_of_week, $time_from, $time_to, $time_from, $time_to, $time_from, $time_to);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "This time slot overlaps with an existing booking.";
            } else {
                // Insert new schedule
                $insert_query = "INSERT INTO lab_schedule 
                                (lab_id, day_of_week, time_slot, subject_code, subject_name, 
                                 faculty_name, faculty_email, class_group, semester, academic_year, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($insert_query);
                if ($stmt === false) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("isssssssssi", $lab_id, $day_of_week, $time_slot, $subject_code, 
                                      $subject_name, $faculty_name, $faculty_email, $class_group, 
                                      $semester, $academic_year, $created_by);
                    
                    if ($stmt->execute()) {
                        $success = "Lab schedule requested successfully! Status: Pending Approval";
                        // Clear form on success
                        $lab_id = $day_of_week = $time_from = $time_to = $subject_code = $subject_name = '';
                        $faculty_name = $faculty_email = $class_group = $semester = $academic_year = '';
                    } else {
                        $error = "Error adding lab schedule: " . $stmt->error;
                    }
                }
            }
        }
    }
}

// Get all lab locations
$labs = [];
$query = "SELECT location_id, location_name FROM location WHERE category_id = 1 ORDER BY location_name";
$result = $conn->query($query);
if ($result) {
    $labs = $result->fetch_all(MYSQLI_ASSOC);
}

// Get current schedules
$schedules = [];
$query = "SELECT ls.*, l.location_name, e.emp_name as created_by_name 
          FROM lab_schedule ls
          JOIN location l ON ls.lab_id = l.location_id
          JOIN employee e ON ls.created_by = e.emp_id
          ORDER BY l.location_name, ls.day_of_week, ls.time_slot";
$result = $conn->query($query);
if ($result) {
    $schedules = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Schedule Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing styles */
    </style>
</head>
<body>
    <!-- User Info Section -->
    <div class="user-info">
        <div class="user-icon">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <span><?php echo htmlspecialchars($currentUser['emp_name']); ?> (<?php echo htmlspecialchars($currentUser['designation']); ?>)</span>
            <form action="logout.php" method="post" style="display: inline;">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Back Button -->
        <a href="dashboard.php" class="btn btn-secondary back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <h2>Lab Schedule Management</h2>
        
        <!-- Success/Error Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Add New Schedule</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="scheduleForm">
                            <!-- Form fields remain the same -->
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Current Schedules</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($schedules)): ?>
                            <p>No schedules found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Lab</th>
                                            <th>Day</th>
                                            <th>Time</th>
                                            <th>Subject</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($schedule['location_name']); ?></td>
                                                <td><?php echo htmlspecialchars($schedule['day_of_week']); ?></td>
                                                <td><?php echo htmlspecialchars($schedule['time_slot']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($schedule['subject_code']); ?><br>
                                                    <small><?php echo htmlspecialchars($schedule['subject_name']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        switch($schedule['status'] ?? 'Pending') {
                                                            case 'Approved': echo 'success'; break;
                                                            case 'Rejected': echo 'danger'; break;
                                                            default: echo 'warning';
                                                        }
                                                    ?>">
                                                        <?php echo htmlspecialchars($schedule['status'] ?? 'Pending'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side validation
        document.getElementById('scheduleForm').addEventListener('submit', function(e) {
            const fromTime = document.getElementById('time_from').value;
            const toTime = document.getElementById('time_to').value;
            
            if (fromTime && toTime && fromTime >= toTime) {
                alert('End time must be after start time');
                e.preventDefault();
            }
        });

        // Auto-adjust "To" time options
        document.getElementById('time_from').addEventListener('change', function() {
            const fromTime = this.value;
            const toSelect = document.getElementById('time_to');
            
            if (fromTime) {
                Array.from(toSelect.options).forEach(option => {
                    option.disabled = option.value && option.value <= fromTime;
                });
            }
        });
    </script>
</body>
</html>