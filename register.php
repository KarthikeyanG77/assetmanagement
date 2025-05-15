<?php
session_start();
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Initialize variables
$emp_name = $email = $department = $password = $confirm_password = '';
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate inputs
    $emp_name = trim($_POST['emp_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validation checks
    if (empty($emp_name)) {
        $errors['emp_name'] = 'Please enter employee name.';
    }

    if (empty($email)) {
        $errors['email'] = 'Please enter email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email.';
    } else {
        // Check if email exists in employee table
        $sql = "SELECT emp_id FROM employee WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $errors['email'] = 'This email is already registered.';
            }
            $stmt->close();
        }
    }

    if (empty($department)) {
        $errors['department'] = 'Please enter department.';
    }

    if (empty($password)) {
        $errors['password'] = 'Please enter a password.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must have at least 8 characters.';
    }

    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Please confirm password.';
    } elseif ($password != $confirm_password) {
        $errors['confirm_password'] = 'Passwords did not match.';
    }

    // If no errors, proceed with registration to EMPLOYEE table
    if (empty($errors)) {
        $sql = "INSERT INTO employee (emp_name, email, password, designation, department, status) 
                VALUES (?, ?, ?, 'User', ?, 'Active')";
        
        if ($stmt = $conn->prepare($sql)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param("ssss", $emp_name, $email, $hashed_password, $department);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Registration successful. You can now login.';
                header("Location: login.php");
                exit();
            } else {
                $errors['database'] = 'Something went wrong. Please try again later.';
                error_log("Database error: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $errors['database'] = 'Database preparation failed.';
            error_log("Prepare failed: " . $conn->error);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .registration-card { 
            max-width: 600px; 
            margin: 50px auto; 
            border-radius: 10px; 
            box-shadow: 0 0 20px rgba(0,0,0,0.1); 
        }
        .card-header {
            background-color: #3498db;
        }
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card registration-card">
            <div class="card-header text-white">
                <h3 class="text-center">Employee Registration</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <div class="mb-3">
                        <label for="emp_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="emp_name" name="emp_name" 
                               value="<?= htmlspecialchars($emp_name) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($email) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="department" class="form-label">Department</label>
                        <input type="text" class="form-control" id="department" name="department" 
                               value="<?= htmlspecialchars($department) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <small class="text-muted">Minimum 8 characters</small>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Register</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Already have an account? <a href="login.php">Sign in</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>