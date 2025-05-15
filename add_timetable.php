<?php
require_once 'config.php';
require_once 'auth_check.php';

// Define which designations should see dashboard_fi.php
$fi_dashboard_designations = ['HOD', 'FI', 'Users'];
$admin_dashboard_designations = ['Lead', 'Sr SA', 'SA', 'IT_Admin', 'Lab incharge'];

// Get current user's name if not already set in auth_check.php
if (!isset($user_name)) {
    $query = "SELECT emp_name FROM employee WHERE emp_id = ?";
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_name = $user['emp_name'];
        } else {
            $user_name = "User";
        }
        $stmt->close();
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lab_id = $_POST['lab_id'];
    $day_of_week = $_POST['day_of_week'];
    $time_slot = $_POST['time_slot'];
    $subject_code = $_POST['subject_code'];
    $subject_name = $_POST['subject_name'];
    $faculty_name = $_POST['faculty_name'];
    $faculty_email = $_POST['faculty_email'];
    $class_group = $_POST['class_group'];
    $semester = $_POST['semester'];
    $academic_year = $_POST['academic_year'];
    
    $sql = "INSERT INTO lab_schedule (lab_id, day_of_week, time_slot, subject_code, subject_name, 
            faculty_name, faculty_email, class_group, semester, academic_year, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("isssssssssi", $lab_id, $day_of_week, $time_slot, $subject_code, 
                         $subject_name, $faculty_name, $faculty_email, $class_group, 
                         $semester, $academic_year, $user_id);
        
        if ($stmt->execute()) {
            $success = "Timetable entry added successfully!";
        } else {
            $error = "Error adding timetable entry: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get list of labs
$labs = [];
$sql = "SELECT location_id, location_name FROM location WHERE category_id = 1 ORDER BY location_name";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $labs[] = $row;
    }
}

// Determine which dashboard to link back to
$dashboard_link = in_array($user_designation, $fi_dashboard_designations) ? 'dashboard_fi.php' : 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Lab Timetable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
            position: relative;
            min-height: 100vh;
        }
        .form-container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .user-info {
            position: absolute;
            top: 20px;
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
            background-color: #3498db;
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
            padding: 0;
            margin-left: 10px;
        }
        .logout-btn:hover {
            color: #e74c3c;
        }
        .user-name {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- User Info Section -->
    <div class="user-info">
        <div class="user-icon">
            <i class="fas fa-user"></i>
        </div>
        <div class="user-name"><?php echo htmlspecialchars($user_name ?? 'User'); ?></div>
        <form action="logout.php" method="post">
            <button type="submit" class="logout-btn" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </button>
        </form>
    </div>

    <div class="container">
        <div class="form-container">
            <h2 class="text-center mb-4">Add Lab Timetable Entry</h2>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="lab_id" class="form-label">Lab</label>
                        <select class="form-select" id="lab_id" name="lab_id" required>
                            <option value="">Select Lab</option>
                            <?php foreach ($labs as $lab): ?>
                                <option value="<?php echo $lab['location_id']; ?>">
                                    <?php echo htmlspecialchars($lab['location_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="day_of_week" class="form-label">Day of Week</label>
                        <select class="form-select" id="day_of_week" name="day_of_week" required>
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="time_slot" class="form-label">Time Slot</label>
                        <select class="form-select" id="time_slot" name="time_slot" required>
                            <option value="">Select Time Slot</option>
                            <option value="8:00-9:00">8:00-9:00</option>
                            <option value="9:00-10:00">9:00-10:00</option>
                            <option value="10:00-11:00">10:00-11:00</option>
                            <option value="11:00-12:00">11:00-12:00</option>
                            <option value="12:00-13:00">12:00-13:00</option>
                            <option value="13:00-14:00">13:00-14:00</option>
                            <option value="14:00-15:00">14:00-15:00</option>
                            <option value="15:00-16:00">15:00-16:00</option>
                            <option value="16:00-17:00">16:00-17:00</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="academic_year" class="form-label">Academic Year</label>
                        <input type="text" class="form-control" id="academic_year" name="academic_year" 
                               placeholder="e.g., 2024-2025" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="subject_code" class="form-label">Subject Code</label>
                        <input type="text" class="form-control" id="subject_code" name="subject_code" required>
                    </div>
                    <div class="col-md-6">
                        <label for="subject_name" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="faculty_name" class="form-label">Faculty Name</label>
                        <input type="text" class="form-control" id="faculty_name" name="faculty_name" required>
                    </div>
                    <div class="col-md-6">
                        <label for="faculty_email" class="form-label">Faculty Email</label>
                        <input type="email" class="form-control" id="faculty_email" name="faculty_email" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="class_group" class="form-label">Class/Group</label>
                        <input type="text" class="form-control" id="class_group" name="class_group" required>
                    </div>
                    <div class="col-md-6">
                        <label for="semester" class="form-label">Semester</label>
                        <input type="text" class="form-control" id="semester" name="semester" required>
                    </div>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="btn btn-primary">Add Timetable Entry</button>
                    <a href="<?php echo $dashboard_link; ?>" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>