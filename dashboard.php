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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Validate session security
if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || 
    $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    session_unset();
    session_destroy();
    header("Location: login.php?security=1");
    exit();
}

// Check for session timeout (30 minutes)
$inactive = 1800;
if (isset($_SESSION['last_activity'])) {
    $session_life = time() - $_SESSION['last_activity'];
    if ($session_life > $inactive) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
}
$_SESSION['last_activity'] = time();

// Get current user's details
$currentUserName = "User";
$user_designation = null;
$user_id = $_SESSION['user_id'];
$query = "SELECT emp_name, designation FROM employee WHERE emp_id = ?";

if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $currentUserName = $user['emp_name'];
        $user_designation = $user['designation'];
    }
    $stmt->close();
}

// Define user permissions
$is_admin = in_array($user_designation, ['Lead', 'Sr SA', 'IT_Admin']);
$is_lab_incharge_or_sa = in_array($user_designation, ['Lab Incharge', 'SA']);
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
            background-color: #f8f9fa;
            padding-top: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .dashboard-card {
            height: 100%;
            transition: transform 0.3s;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            border-top: 4px solid var(--primary-color);
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            border-top-color: var(--secondary-color);
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
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .logout-btn:hover {
            color: var(--accent-color);
        }
        
        .welcome-section {
            margin-bottom: 30px;
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .dropdown-report, .dropdown-schedule {
            position: relative;
        }
        
        .dropdown-content, .dropdown-schedule-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1000;
            border-radius: 8px;
            padding: 10px 0;
            left: 0;
            right: 0;
            margin: 0 auto;
        }
        
        .dropdown-report:hover .dropdown-content,
        .dropdown-schedule:hover .dropdown-schedule-content {
            display: block;
        }
        
        .report-item, .schedule-item {
            color: var(--dark-color);
            padding: 8px 16px;
            text-decoration: none;
            display: block;
            text-align: left;
            transition: all 0.3s;
        }
        
        .report-item:hover, .schedule-item:hover {
            background-color: var(--light-color);
            text-decoration: none;
            color: var(--primary-color);
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
        }
        
        .disabled-card {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }
        
        .disabled-card::after {
            content: "Access Restricted";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
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

    <div class="dashboard-container">
        <div class="welcome-section">
            <h2>ICT IT Asset Management System</h2>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($currentUserName); ?></p>
            <?php if ($is_admin): ?>
                <span class="badge bg-danger">Administrator</span>
            <?php elseif ($is_lab_incharge_or_sa): ?>
                <span class="badge bg-primary">Lab Incharge/SA</span>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <!-- Employee Management (Disabled for non-admin) -->
            <div class="col-md-4 col-sm-6">
                <?php if (!$is_admin && !$is_lab_incharge_or_sa): ?>
                    <div class="card dashboard-card text-center p-4 disabled-card">
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5>Employee Management</h5>
                        <p class="text-muted">Manage employee records</p>
                    </div>
                <?php else: ?>
                    <a href="manage_emp.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5>Employee Management</h5>
                            <p class="text-muted">Manage employee records</p>
                        </div>
                    </a>
                <?php endif; ?>
            </div>

            <!-- IT Asset (Enabled for all) -->
            <div class="col-md-4 col-sm-6">
                <a href="manage_ast.php" class="text-decoration-none">
                    <div class="card dashboard-card text-center p-4">
                        <div class="card-icon">
                            <i class="fas fa-laptop"></i>
                        </div>
                        <h5>IT Asset</h5>
                        <p class="text-muted">Manage all IT assets</p>
                    </div>
                </a>
            </div>

            <!-- Asset Type (Disabled for non-admin) -->
            <div class="col-md-4 col-sm-6">
                <?php if (!$is_admin && !$is_lab_incharge_or_sa): ?>
                    <div class="card dashboard-card text-center p-4 disabled-card">
                        <div class="card-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <h5>Asset Type</h5>
                        <p class="text-muted">Manage asset types</p>
                    </div>
                <?php else: ?>
                    <a href="manage_astty.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-tags"></i>
                            </div>
                            <h5>Asset Type</h5>
                            <p class="text-muted">Manage asset types</p>
                        </div>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Asset Category (Disabled for non-admin) -->
            <div class="col-md-4 col-sm-6">
                <?php if (!$is_admin && !$is_lab_incharge_or_sa): ?>
                    <div class="card dashboard-card text-center p-4 disabled-card">
                        <div class="card-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <h5>Asset Category</h5>
                        <p class="text-muted">Manage asset categories</p>
                    </div>
                <?php else: ?>
                    <a href="manage_astcat.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <h5>Asset Category</h5>
                            <p class="text-muted">Manage asset categories</p>
                        </div>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Service Centre (Enabled for all) -->
            <div class="col-md-4 col-sm-6">
                <a href="manage_service.php" class="text-decoration-none">
                    <div class="card dashboard-card text-center p-4">
                        <div class="card-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <h5>Service Centre</h5>
                        <p class="text-muted">Manage service centers</p>
                    </div>
                </a>
            </div>

            <!-- Service Log (Enabled for all) -->
            <div class="col-md-4 col-sm-6">
                <a href="manage_servicelog.php" class="text-decoration-none">
                    <div class="card dashboard-card text-center p-4">
                        <div class="card-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h5>Service Log</h5>
                        <p class="text-muted">Manage service logs</p>
                    </div>
                </a>
            </div>

            <!-- Movement (Disabled for non-admin) -->
            <div class="col-md-4 col-sm-6">
                <?php if (!$is_admin && !$is_lab_incharge_or_sa): ?>
                    <div class="card dashboard-card text-center p-4 disabled-card">
                        <div class="card-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <h5>Movement</h5>
                        <p class="text-muted">Track asset movements</p>
                    </div>
                <?php else: ?>
                    <a href="update_status.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <h5>Movement</h5>
                            <p class="text-muted">Track asset movements</p>
                        </div>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Location (Disabled for non-admin) -->
            <div class="col-md-4 col-sm-6">
                <?php if (!$is_admin && !$is_lab_incharge_or_sa): ?>
                    <div class="card dashboard-card text-center p-4 disabled-card">
                        <div class="card-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h5>Location</h5>
                        <p class="text-muted">Location mapping</p>
                    </div>
                <?php else: ?>
                    <a href="manage_loc.php" class="text-decoration-none">
                        <div class="card dashboard-card text-center p-4">
                            <div class="card-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h5>Location</h5>
                            <p class="text-muted">Location mapping</p>
                        </div>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Lab Timetable (Enabled for all) -->
            <div class="col-md-4 col-sm-6">
                <a href="add_timetable.php" class="text-decoration-none">
                    <div class="card dashboard-card text-center p-4">
                        <div class="card-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <h5>Lab Timetable</h5>
                        <p class="text-muted">Manage lab timetables</p>
                    </div>
                </a>
            </div>

            <!-- Lab Schedule (Visible to all) -->
            <div class="col-md-4 col-sm-6">
                <div class="card dashboard-card text-center p-4 dropdown-schedule">
                    <div class="card-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h5>Lab Schedule</h5>
                    <p class="text-muted">Lab schedules</p>
                    
                    <div class="dropdown-schedule-content">
                        <a href="request.php" class="schedule-item">
                            <i class="fas fa-calendar-plus me-2"></i> Schedule Request
                        </a>
                        <?php if ($is_admin || $is_lab_incharge_or_sa): ?>
                            <a href="approve_request.php" class="schedule-item">
                                <i class="fas fa-check-circle me-2"></i> Approve Requests
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Reports (Disabled for non-admin) -->
            <div class="col-md-4 col-sm-6">
                <?php if (!$is_admin && !$is_lab_incharge_or_sa): ?>
                    <div class="card dashboard-card text-center p-4 disabled-card">
                        <div class="card-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h5>Reports</h5>
                        <p class="text-muted">View various reports</p>
                    </div>
                <?php else: ?>
                    <div class="card dashboard-card text-center p-4 dropdown-report">
                        <div class="card-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h5>Reports</h5>
                        <p class="text-muted">View various reports</p>
                        
                        <div class="dropdown-content">
                            <a href="productwise_rpt.php" class="report-item">
                                <i class="fas fa-box me-2"></i> Product-wise Report
                            </a>
                            <a href="labwise_rpt.php" class="report-item">
                                <i class="fas fa-flask me-2"></i> Lab-wise Report
                            </a>
                            <a href="ser_rpt.php" class="report-item">
                                <i class="fas fa-wrench me-2"></i> Service Report
                            </a>
                            <a href="brand_rpt.php" class="report-item">
                                <i class="fas fa-tag me-2"></i> Brand-wise Report
                            </a>
                            <a href="overallasset_rpt.php" class="report-item">
                                <i class="fas fa-truck me-2"></i> Overall Asset
                            </a>
                            <a href="scrap_rpt.php" class="report-item">
                                <i class="fas fa-truck me-2"></i> Scrap Report
                            </a>
                            <a href="rpt_pro.php" class="report-item">
                                <i class="fas fa-video me-2"></i> Projector Report
                            </a>
                            <a href="mvmnt_rpt.php" class="report-item">
                                <i class="fas fa-truck me-2"></i> Movement Register
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add active class to current page in navigation
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const links = document.querySelectorAll('a[href="' + currentPage + '"]');
            
            // Convert NodeList to Array for safer iteration
            if (links.length > 0) {
                Array.from(links).forEach(link => {
                    link.classList.add('active');
                    if (link.closest('.card')) {
                        link.closest('.card').style.borderTopColor = '#e74c3c';
                    }
                });
            }
        });
    </script>
</body>
</html>