<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$errors = [];
$success = false;

// Get user details
$user_id = $_SESSION['user_id'];
$user_query = "SELECT emp_id, emp_name FROM employee WHERE emp_id = '$user_id'";
$user_result = mysqli_query($conn, $user_query);

// Check if query executed successfully
if (!$user_result) {
    die("Database error: " . mysqli_error($conn));
}

$user = mysqli_fetch_assoc($user_result);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $lab_id = mysqli_real_escape_string($conn, $_POST['lab_id'] ?? '');
    $request_date = mysqli_real_escape_string($conn, $_POST['request_date'] ?? '');
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time'] ?? '');
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time'] ?? '');
    $subject_name = mysqli_real_escape_string($conn, $_POST['subject_name'] ?? '');
    $batch = mysqli_real_escape_string($conn, $_POST['batch'] ?? '');
    $purpose = mysqli_real_escape_string($conn, $_POST['purpose'] ?? '');
    $software_required = isset($_POST['software_required']) ? 1 : 0;
    $requester_id = $user['emp_id'];
    $requester_name = $user['emp_name'];
    $status = 'Pending';

    // Combine date and time for database storage if needed
    $request_datetime = $request_date . ' ' . $start_time;
    $end_datetime = $request_date . ' ' . $end_time;

    // Validate required fields
    if (empty($lab_id)) $errors[] = "Lab selection is required";
    if (empty($request_date)) $errors[] = "Date is required";
    if (empty($start_time)) $errors[] = "Start time is required";
    if (empty($end_time)) $errors[] = "End time is required";
    if (empty($subject_name)) $errors[] = "Subject name is required";
    if (empty($batch)) $errors[] = "Batch/Class is required";
    if (empty($purpose)) $errors[] = "Purpose is required";

    // Validate date is not in the past
    $today = date('Y-m-d');
    if ($request_date < $today) {
        $errors[] = "You cannot request a lab for a past date";
    }

    // Validate time slot (end time should be after start time)
    if ($start_time >= $end_time) {
        $errors[] = "End time must be after start time";
    }

    // Check for existing bookings in the same time slot
    if (empty($errors)) {
        // Adjust this query based on your actual database structure
        $conflict_query = "SELECT * FROM lab_request 
                          WHERE lab_id = '$lab_id' 
                          AND request_date = '$request_date'
                          AND (
                              (TIME('$start_time') BETWEEN TIME(request_time) AND TIME(end_time))
                              OR (TIME('$end_time') BETWEEN TIME(request_time) AND TIME(end_time))
                              OR (TIME(request_time) BETWEEN TIME('$start_time') AND TIME('$end_time'))
                          )
                          AND status = 'Approved'";
        
        $conflict_result = mysqli_query($conn, $conflict_query);
        
        // Check if query executed successfully
        if (!$conflict_result) {
            $errors[] = "Database error: " . mysqli_error($conn);
        } elseif (mysqli_num_rows($conflict_result) > 0) {
            $errors[] = "The selected time slot conflicts with an existing approved booking";
        }
    }

    // If no errors, insert the request
    if (empty($errors)) {
        // Adjust this query based on your actual database structure
        $insert_query = "INSERT INTO lab_request (
                            lab_id, 
                            request_date, 
                            request_time, 
                            end_time, 
                            subject_name, 
                            batch, 
                            purpose, 
                            software_required, 
                            requester_id, 
                            requester_name, 
                            status,
                            request_timestamp
                        ) VALUES (
                            '$lab_id', 
                            '$request_date', 
                            '$start_time', 
                            '$end_time', 
                            '$subject_name', 
                            '$batch', 
                            '$purpose', 
                            '$software_required', 
                            '$requester_id', 
                            '$requester_name', 
                            '$status',
                            NOW()
                        )";
        
        if (mysqli_query($conn, $insert_query)) {
            $success = true;
            $_SESSION['success_message'] = "Lab request submitted successfully!";
            header("Location: index.php");
            exit();
        } else {
            $errors[] = "Database error: " . mysqli_error($conn);
        }
    }
}
?>

<!-- Rest of your HTML remains the same -->