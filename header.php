<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection settings
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ict';

// Set default display variables
$_SESSION['display_name'] = 'User';
$_SESSION['position'] = 'Employee';
$_SESSION['department'] = '';

// Fetch user data if logged in
if (isset($_SESSION['logged_in'])) {
    try {
        $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($_SESSION['user_type'] === 'employee' && isset($_SESSION['emp_id'])) {
            $stmt = $conn->prepare("SELECT emp_id, emp_name, designation, department FROM employee WHERE emp_id = :emp_id");
            $stmt->bindParam(':emp_id', $_SESSION['emp_id']);
            $stmt->execute();
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userData) {
                $_SESSION['display_name'] = $userData['emp_name'];
                $_SESSION['position'] = $userData['designation'];
                $_SESSION['department'] = $userData['department'];
            }

        } elseif ($_SESSION['user_type'] === 'department' && isset($_SESSION['dept_id'])) {
            $stmt = $conn->prepare("SELECT dept_id, dept_name, hod_name FROM department WHERE dept_id = :dept_id");
            $stmt->bindParam(':dept_id', $_SESSION['dept_id']);
            $stmt->execute();
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userData) {
                $_SESSION['display_name'] = $userData['hod_name'];
                $_SESSION['position'] = 'HOD of ' . $userData['dept_name'];
                $_SESSION['department'] = $userData['dept_name'];
            }
        }

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Application</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <!-- Back Button -->
        <button class="btn btn-link text-light" onclick="window.history.back()" title="Go back">
            <i class="bi bi-arrow-left-short" style="font-size: 1.5rem;"></i>
        </button>

        <a class="navbar-brand ms-2" href="#">Your Logo</a>

        <div class="ms-auto d-flex align-items-center">
            <?php if (isset($_SESSION['logged_in'])): ?>
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center dropdown-toggle text-white text-decoration-none" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['display_name']); ?>&background=random&color=fff"
                             alt="User Avatar" class="user-avatar me-2">
                        <div class="d-none d-sm-block">
                            <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['display_name']); ?></div>
                            <div class="small"><?php echo htmlspecialchars($_SESSION['position']); ?></div>
                        </div>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li class="px-3 py-2">
                            <div class="d-flex align-items-center">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['display_name']); ?>&background=random"
                                     class="user-avatar me-3" alt="User Avatar">
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['display_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($_SESSION['position']); ?></small>
                                    <?php if ($_SESSION['user_type'] === 'employee'): ?>
                                        <small class="text-muted d-block">Employee ID: <?php echo htmlspecialchars($_SESSION['emp_id']); ?></small>
                                    <?php endif; ?>
                                    <small class="text-muted d-block">Department: <?php echo htmlspecialchars($_SESSION['department']); ?></small>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person me-2"></i> My Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="settings.php">
                                <i class="bi bi-gear me-2"></i> Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-light">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
