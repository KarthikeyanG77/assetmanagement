<?php
require_once 'config.php';
require_once 'auth_check.php';

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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lab_id = $_POST['lab_id'];
    $from_date = $_POST['from_date'];
    $to_date = $_POST['to_date'];
    $from_time = $_POST['from_time'];
    $to_time = $_POST['to_time'];
    $subject_code = $_POST['subject_code'];
    $subject_name = $_POST['subject_name'];
    $class_group = $_POST['class_group'];
    $academic_year = $_POST['academic_year'];
    $purpose = $_POST['purpose'];
    
    // Get faculty info from employee table
    $faculty_info = [];
    $sql = "SELECT emp_name, email FROM employee WHERE emp_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($faculty_name, $faculty_email);
        $stmt->fetch();
        $stmt->close();
    }
    
    $sql = "INSERT INTO lab_request (requested_by, from_date, to_date, lab_id, from_time, to_time, 
            subject_code, subject_name, faculty_name, faculty_email, class_group, 
            academic_year, purpose) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("issssssssssss", $user_id, $from_date, $to_date, $lab_id, 
                         $from_time, $to_time, $subject_code, $subject_name, $faculty_name, $faculty_email, 
                         $class_group, $academic_year, $purpose);
        
        if ($stmt->execute()) {
            $success = "Lab request submitted successfully!";
        } else {
            $error = "Error submitting request: " . $stmt->error;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Lab Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
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
        <div class="form-container mt-5">
            <h2 class="text-center mb-4">Request Lab Request</h2>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="row mb-3">
                    <div class="col-md-12">
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
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="from_date" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="from_date" name="from_date" required>
                    </div>
                    <div class="col-md-6">
                        <label for="to_date" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="to_date" name="to_date" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="from_time" class="form-label">From Time</label>
                        <select class="form-select" id="from_time" name="from_time" required>
                            <option value="">Select Start Time</option>
                            <option value="6:00">6:00 AM</option>
                            <option value="7:00">7:00 AM</option>
                            <option value="8:00">8:00 AM</option>
                            <option value="9:00">9:00 AM</option>
                            <option value="10:00">10:00 AM</option>
                            <option value="11:00">11:00 AM</option>
                            <option value="12:00">12:00 PM</option>
                            <option value="13:00">1:00 PM</option>
                            <option value="14:00">2:00 PM</option>
                            <option value="15:00">3:00 PM</option>
                            <option value="16:00">4:00 PM</option>
                            <option value="17:00">5:00 PM</option>
                            <option value="18:00">6:00 PM</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="to_time" class="form-label">To Time</label>
                        <select class="form-select" id="to_time" name="to_time" required>
                            <option value="">Select End Time</option>
                            <option value="7:00">7:00 AM</option>
                            <option value="8:00">8:00 AM</option>
                            <option value="9:00">9:00 AM</option>
                            <option value="10:00">10:00 AM</option>
                            <option value="11:00">11:00 AM</option>
                            <option value="12:00">12:00 PM</option>
                            <option value="13:00">1:00 PM</option>
                            <option value="14:00">2:00 PM</option>
                            <option value="15:00">3:00 PM</option>
                            <option value="16:00">4:00 PM</option>
                            <option value="17:00">5:00 PM</option>
                            <option value="18:00">6:00 PM</option>
                            <option value="19:00">7:00 PM</option>
                        </select>
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
                        <label for="class_group" class="form-label">Class/Group</label>
                        <input type="text" class="form-control" id="class_group" name="class_group" required>
                    </div>
                    <div class="col-md-6">
                        <label for="academic_year" class="form-label">Academic Year</label>
                        <input type="text" class="form-control" id="academic_year" name="academic_year" 
                               placeholder="e.g., 2024-2025" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="purpose" class="form-label">Purpose</label>
                    <textarea class="form-control" id="purpose" name="purpose" rows="2" required></textarea>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set minimum date for date inputs to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('from_date').min = today;
            document.getElementById('to_date').min = today;
            
            // Update to_date min when from_date changes
            document.getElementById('from_date').addEventListener('change', function() {
                document.getElementById('to_date').min = this.value;
            });
        });
    </script>
</body>
</html>