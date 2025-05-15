<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $emp_code = mysqli_real_escape_string($conn, $_POST['emp_code']);
    $emp_name = mysqli_real_escape_string($conn, $_POST['emp_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $dept_id = intval($_POST['dept_id']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact']);
    
    // Handle file upload
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profile_pics/';
        $file_name = uniqid() . '_' . basename($_FILES['profile_pic']['name']);
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_path)) {
            $profile_pic = $target_path;
        }
    }
    
    // Insert into database
    $query = "INSERT INTO employee (emp_code, emp_name, email, password, designation, department, contact, profile_pic)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssssssss', $emp_code, $emp_name, $email, $password, $designation, $dept_id, $contact, $profile_pic);
    
    if (mysqli_stmt_execute($stmt)) {
        header("Location: registration_success.php");
        exit();
    } else {
        die("Error: " . mysqli_error($conn));
    }
}
?>