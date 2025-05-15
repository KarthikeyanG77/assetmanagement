<?php
session_start();
require_once 'config.php';

// Debugging: Check session values
error_log("Session position: " . ($_SESSION['position'] ?? 'not set'));

// Check if user is logged in and is admin/HOD
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Additional check for position (only if your system uses this)
if (isset($_SESSION['position']) && !in_array($_SESSION['position'], ['admin', 'hod'])) {
    header("Location: unauthorized.php"); // Create this page
    exit();
}

// Initialize variables
$search = '';
$departments = [];
$message = '';

// Handle search
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['search'])) {
    $search = trim($_GET['search']);
    $sql = "SELECT * FROM department WHERE dept_name LIKE ? OR hod_name LIKE ?";
    $stmt = $conn->prepare($sql);
    $search_param = "%$search%";
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    $departments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    // Get all departments by default
    $sql = "SELECT * FROM department";
    $result = $conn->query($sql);
    if ($result) {
        $departments = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Database error: " . $conn->error);
        $message = "Error loading departments";
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $dept_id = intval($_POST['dept_id']);
    $default_password = password_hash('abcd1234', PASSWORD_DEFAULT);
    
    $sql = "UPDATE department SET password = ? WHERE dept_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $default_password, $dept_id);
    
    if ($stmt->execute()) {
        $message = "Password reset to default (abcd1234) for selected department";
    } else {
        $message = "Error resetting password: " . $stmt->error;
        error_log("Password reset error: " . $stmt->error);
    }
    $stmt->close();
}

// Display any messages
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .action-btns { white-space: nowrap; }
        .search-box { max-width: 400px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-4">Manage Departments</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Department List</h5>
                <form method="get" class="d-flex search-box">
                    <input type="text" name="search" class="form-control" placeholder="Search departments..." 
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary ms-2">Search</button>
                    <?php if ($search): ?>
                        <a href="manage_department.php" class="btn btn-outline-secondary ms-2">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="card-body">
                <?php if (empty($departments)): ?>
                    <div class="alert alert-warning">No departments found</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Department</th>
                                    <th>HOD Name</th>
                                    <th>HOD Email</th>
                                    <th>HOD Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?= $dept['dept_id'] ?></td>
                                        <td><?= htmlspecialchars($dept['dept_name']) ?></td>
                                        <td><?= htmlspecialchars($dept['hod_name']) ?></td>
                                        <td><?= htmlspecialchars($dept['hod_email']) ?></td>
                                        <td><?= htmlspecialchars($dept['hod_contact']) ?></td>
                                        <td class="action-btns">
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="dept_id" value="<?= $dept['dept_id'] ?>">
                                                <button type="submit" name="reset_password" class="btn btn-warning btn-sm" 
                                                        onclick="return confirm('Reset password to abcd1234?')">
                                                    Reset Password
                                                </button>
                                            </form>
                                            <a href="edit_department.php?id=<?= $dept['dept_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="d-flex justify-content-between">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            <a href="add_department.php" class="btn btn-success">Add New Department</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>