<?php
require_once 'includes/db_connect.php';
require_once 'includes/auth_check.php';

// Only allow admin users to upload timetables
if (!$is_admin) {
    header("Location: dashboard.php");
    exit();
}

// Process file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['timetable_file'])) {
    $lab_id = $_POST['lab_id'];
    $academic_year = $_POST['academic_year'];
    $semester = $_POST['semester'];
    $is_current = isset($_POST['is_current']) ? 1 : 0;
    
    // File upload handling
    $target_dir = "uploads/timetables/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = basename($_FILES["timetable_file"]["name"]);
    $target_file = $target_dir . uniqid() . '_' . $file_name;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if file is a PDF
    if ($file_type != "pdf") {
        $error = "Only PDF files are allowed.";
    } elseif (move_uploaded_file($_FILES["timetable_file"]["tmp_name"], $target_file)) {
        // If marking as current, first set all others for this lab as not current
        if ($is_current) {
            $conn->query("UPDATE timetable_document SET is_current = 0 WHERE lab_id = $lab_id");
        }
        
        // Insert into database
        $sql = "INSERT INTO timetable_document (lab_id, file_name, file_path, uploaded_by, 
                academic_year, semester, is_current) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ississi", $lab_id, $file_name, $target_file, $user_id, 
                             $academic_year, $semester, $is_current);
            
            if ($stmt->execute()) {
                $success = "Timetable uploaded successfully!";
            } else {
                $error = "Error uploading timetable: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $error = "Sorry, there was an error uploading your file.";
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
    <title>Upload Lab Timetable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container mt-5">
            <h2 class="text-center mb-4">Upload Lab Timetable</h2>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="lab_id" class="form-label">Lab</label>
                        <select class="form-select" id="lab_id" name="lab_id" required>
                            <option value="">Select Lab</option>
                            <?php foreach ($labs as $lab): ?>
                                <option value="<?php echo $lab['location_id']; ?>">
                                    <?