<?php
require_once 'db_config.php';
session_start();

// Check if user is logged in and has appropriate designation
if (!isset($_SESSION['emp_id']) || !in_array($_SESSION['designation'], ['Lead', 'Sr SA', 'IT_Admin'])) {
    header("Location: login.php");
    exit();
}

// Get schedule ID from URL
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$schedule_id = $_GET['id'];

// Get schedule details
$schedule_query = "SELECT * FROM lab_schedule WHERE schedule_id = ?";
$schedule_stmt = $pdo->prepare($schedule_query);
$schedule_stmt->execute([$schedule_id]);
$schedule = $schedule_stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    header("Location: index.php");
    exit();
}

// Get all labs
$labs = $pdo->query("SELECT * FROM location WHERE category_id = 1")->fetchAll(PDO::FETCH_ASSOC);

// Time slots
$time_slots = [
    '8:00-9:00', '9:00-10:00', '10:00-11:00', '11:00-12:00',
    '12:00-13:00', '13:00-14:00', '14:00-15:00', '15:00-16:00', '16:00-17:00'
];

// Days of week
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_id = $_POST['lab_id'];
    $day = $_POST['day'];
    $time_slot = $_POST['time_slot'];
    $subject_code = $_POST['subject_code'];
    $subject_name = $_POST['subject_name'];
    $faculty_name = $_POST['faculty_name'];
    $faculty_email = $_POST['faculty_email'];
    $class_group = $_POST['class_group'];
    $semester = $_POST['semester'];
    $academic_year = $_POST['academic_year'];
    
    // Check if slot is already booked (excluding current schedule)
    $check_query = "SELECT * FROM lab_schedule 
                   WHERE lab_id = ? AND day_of_week = ? AND time_slot = ? AND schedule_id != ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$lab_id, $day, $time_slot, $schedule_id]);
    
    if ($check_stmt->rowCount() > 0) {
        $error = "This time slot is already booked for the selected lab.";
    } else {
        // Update schedule
        $update_query = "UPDATE lab_schedule 
                        SET lab_id = ?, day_of_week = ?, time_slot = ?, subject_code = ?, 
                            subject_name = ?, faculty_name = ?, faculty_email = ?, 
                            class_group = ?, semester = ?, academic_year = ?
                        WHERE schedule_id = ?";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([
            $lab_id, $day, $time_slot, $subject_code, $subject_name,
            $faculty_name, $faculty_email, $class_group, $semester, $academic_year, $schedule_id
        ]);
        
        header("Location: index.php?success=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Lab Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Update Lab Schedule</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="lab_id" class="form-label">Lab</label>
                    <select class="form-select" id="lab_id" name="lab_id" required>
                        <option value="">Select Lab</option>
                        <?php foreach ($labs as $lab): ?>
                            <option value="<?= $lab['location_id'] ?>" <?= $lab['location_id'] == $schedule['lab_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lab['location_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="day" class="form-label">Day</label>
                    <select class="form-select" id="day" name="day" required>
                        <option value="">Select Day</option>
                        <?php foreach ($days as $d): ?>
                            <option value="<?= $d ?>" <?= $d == $schedule['day_of_week'] ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="time_slot" class="form-label">Time Slot</label>
                    <select class="form-select" id="time_slot" name="time_slot" required>
                        <option value="">Select Time Slot</option>
                        <?php foreach ($time_slots as $slot): ?>
                            <option value="<?= $slot ?>" <?= $slot == $schedule['time_slot'] ? 'selected' : '' ?>><?= $slot ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="subject_code" class="form-label">Subject Code</label>
                    <input type="text" class="form-control" id="subject_code" name="subject_code" 
                           value="<?= htmlspecialchars($schedule['subject_code']) ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="subject_name" class="form-label">Subject Name</label>
                    <input type="text" class="form-control" id="subject_name" name="subject_name" 
                           value="<?= htmlspecialchars($schedule['subject_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="faculty_name" class="form-label">Faculty Name</label>
                    <input type="text" class="form-control" id="faculty_name" name="faculty_name" 
                           value="<?= htmlspecialchars($schedule['faculty_name']) ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="faculty_email" class="form-label">Faculty Email</label>
                    <input type="email" class="form-control" id="faculty_email" name="faculty_email" 
                           value="<?= htmlspecialchars($schedule['faculty_email']) ?>">
                </div>
                <div class="col-md-6">
                    <label for="class_group" class="form-label">Class Group</label>
                    <input type="text" class="form-control" id="class_group" name="class_group" 
                           value="<?= htmlspecialchars($schedule['class_group']) ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="semester" class="form-label">Semester</label>
                    <input type="text" class="form-control" id="semester" name="semester" 
                           value="<?= htmlspecialchars($schedule['semester']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="academic_year" class="form-label">Academic Year</label>
                    <input type="text" class="form-control" id="academic_year" name="academic_year" 
                           value="<?= htmlspecialchars($schedule['academic_year']) ?>" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Update Schedule</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>