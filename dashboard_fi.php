<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 day
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
    session_regenerate_id(true);
}

// Include config file
require_once 'config.php';
require_once 'auth_check.php';

// Set last activity time
$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICT IT Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.85), rgba(0, 0, 0, 0.85)), 
                        url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80') no-repeat center center fixed;
            background-size: cover;
            padding-top: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            color: #fff;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 25px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 0 25px rgba(0,0,0,0.4);
            color: #333;
        }
        
        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .dashboard-card {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border: none;
            border-top: 4px solid var(--primary-color);
            background-color: #fff;
            overflow: hidden;
        }
        
        .dashboard-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            border-top-color: var(--secondary-color);
        }
        
        .user-info {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: rgba(255,255,255,0.9);
            padding: 10px 20px;
            border-radius: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .user-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s;
            padding: 5px;
        }
        
        .logout-btn:hover {
            color: var(--accent-color);
        }
        
        .welcome-section {
            margin-bottom: 30px;
            text-align: center;
            padding: 25px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-section::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }
        
        .dropdown-report, .dropdown-schedule {
            position: relative;
            cursor: pointer;
        }
        
        .dropdown-content, .dropdown-schedule-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 220px;
            box-shadow: 0px 10px 25px rgba(0,0,0,0.2);
            z-index: 1000;
            border-radius: 10px;
            padding: 10px 0;
            left: 0;
            right: 0;
            margin: 0 auto;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .dropdown-report:hover .dropdown-content,
        .dropdown-schedule:hover .dropdown-schedule-content {
            display: block;
        }
        
        .report-item, .schedule-item {
            color: var(--dark-color);
            padding: 10px 20px;
            text-decoration: none;
            display: block;
            text-align: left;
            transition: all 0.3s;
        }
        
        .report-item:hover, .schedule-item:hover {
            background-color: var(--light-color);
            color: var(--primary-color);
            padding-left: 25px;
        }
        
        .admin-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .change-password-btn {
            position: fixed;
            top: 20px;
            right: 180px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 30px;
            padding: 10px 20px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .change-password-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .card-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark-color);
        }
        
        .card-text {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            font-weight: 500;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #495057;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background-color: transparent;
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

    <!-- Change Password Button -->
    <button class="change-password-btn" onclick="window.location.href='change_password.php'">
        <i class="fas fa-key"></i> Change Password
    </button>

    <div class="dashboard-container">
        <div class="welcome-section">
            <h2><i class="fas fa-laptop-code me-2"></i> ICT IT Asset Management System</h2>
            <p class="mb-0">Welcome back, <?php echo htmlspecialchars($currentUserName); ?></p>
            <?php if ($is_hod): ?>
                <span class="badge bg-primary mt-2"><i class="fas fa-user-shield me-1"></i> HOD</span>
            <?php elseif ($is_fi): ?>
                <span class="badge bg-success mt-2"><i class="fas fa-user-tie me-1"></i> FI</span>
            <?php elseif ($is_admin): ?>
                <span class="badge bg-danger mt-2"><i class="fas fa-user-cog me-1"></i> Admin</span>
            <?php else: ?>
                <span class="badge bg-secondary mt-2"><i class="fas fa-user me-1"></i> User</span>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <!-- Lab Timetable -->
            <div class="col-md-4 col-sm-6">
                <a href="add_timetable.php" class="text-decoration-none">
                    <div class="card dashboard-card text-center p-4">
                        <div class="card-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h5 class="card-title">Lab Timetable</h5>
                        <p class="card-text">Manage and view lab schedules</p>
                    </div>
                </a>
            </div>

            <!-- Lab Schedule -->
            <div class="col-md-4 col-sm-6">
                <div class="card dashboard-card text-center p-4 dropdown-schedule">
                    <div class="card-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h5 class="card-title">Lab Schedule</h5>
                    <p class="card-text">Schedule management</p>
                    
                    <div class="dropdown-schedule-content">
                        <a href="request.php" class="schedule-item">
                            <i class="fas fa-calendar-plus me-2"></i> New Schedule Request
                        </a>
                        <?php if ($is_fi || $is_admin): ?>
                            <a href="approve_request.php" class="schedule-item">
                                <i class="fas fa-check-circle me-2"></i> Approve Requests
                            </a>
                        <?php endif; ?>
                        <a href="view_schedule.php" class="schedule-item">
                            <i class="fas fa-eye me-2"></i> View All Schedules
                        </a>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="col-md-4 col-sm-6">
                <a href="change_password.php" class="text-decoration-none">
                    <div class="card dashboard-card text-center p-4">
                        <div class="card-icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <h5 class="card-title">Change Password</h5>
                        <p class="card-text">Update your credentials</p>
                    </div>
                </a>
            </div>

            <!-- Asset Management (Admin/FI only) -->
            <?php if ($is_admin || $is_fi): ?>
                <div class="col-md-4 col-sm-6">
                    <a href="manage_ast.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-laptop"></i>
                            </div>
                            <h5 class="card-title">Asset Management</h5>
                            <p class="card-text">Manage IT assets</p>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
            <!-- User Management (Admin only) -->
            <?php if ($is_admin): ?>
                <div class="col-md-4 col-sm-6">
                    <a href="user_management.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <h5 class="card-title">User Management</h5>
                            <p class="card-text">Manage system users</p>
                            <span class="admin-badge">A</span>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add active class to current page in navigation
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const links = document.querySelectorAll('a[href="' + currentPage + '"]');
            
            links.forEach(link => {
                link.classList.add('active');
                if (link.closest('.card')) {
                    link.closest('.card').style.borderTopColor = '#e74c3c';
                }
            });

            // Add smooth transitions
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach(card => {
                card.style.transition = 'all 0.3s ease';
            });
        });

        // Session timeout warning
        let timeoutWarning;
        const sessionTimeout = <?php echo $inactive; ?> * 1000;
        const warningTime = 5 * 60 * 1000; // 5 minutes warning

        function startTimeoutTimer() {
            timeoutWarning = setTimeout(() => {
                showTimeoutWarning();
            }, sessionTimeout - warningTime);
        }

        function showTimeoutWarning() {
            if (confirm('Your session will expire in 5 minutes. Would you like to stay logged in?')) {
                // Reset session timer
                fetch('keepalive.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            startTimeoutTimer();
                        }
                    });
            }
        }

        // Start timer when page loads
        startTimeoutTimer();

        // Reset timer on user activity
        document.addEventListener('mousemove', resetTimeoutTimer);
        document.addEventListener('keypress', resetTimeoutTimer);

        function resetTimeoutTimer() {
            clearTimeout(timeoutWarning);
            startTimeoutTimer();
        }
    </script>
</body>
</html>