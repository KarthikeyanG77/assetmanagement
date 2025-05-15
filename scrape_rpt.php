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
$is_hod_or_fi = in_array($user_designation, ['HOD', 'FI']);

// Fetch scrap assets
$scrap_assets = [];
$scrap_query = "SELECT a.asset_id, a.asset_name, a.brand, a.model, a.serial_no, a.r_no, 
                l.location_name, e.emp_name as current_holder
                FROM asset a
                LEFT JOIN location l ON a.location_id = l.location_id
                LEFT JOIN employee e ON a.current_holder = e.emp_id
                WHERE a.status = 'scrapped'
                ORDER BY a.asset_name";
                
if ($result = $conn->query($scrap_query)) {
    $scrap_assets = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scrap Report | ICT IT Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
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
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            border-top: 4px solid var(--primary-color);
            margin-bottom: 30px;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .table-responsive {
            margin-top: 20px;
        }
        
        .action-btns {
            white-space: nowrap;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo-container img {
            max-height: 80px;
        }
        
        .badge-admin {
            background-color: var(--accent-color);
        }
        
        .badge-lab {
            background-color: var(--primary-color);
        }
        
        .badge-hod {
            background-color: #6c757d;
        }
        
        .btn-print, .btn-excel {
            margin-right: 10px;
        }
        
        .scrap-row {
            background-color: rgba(231, 76, 60, 0.1);
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
            <?php if ($is_admin): ?>
                <span class="badge badge-admin ms-2">Admin</span>
            <?php elseif ($is_lab_incharge_or_sa): ?>
                <span class="badge badge-lab ms-2">Lab Incharge/SA</span>
            <?php elseif ($is_hod_or_fi): ?>
                <span class="badge badge-hod ms-2">HOD/FI</span>
            <?php endif; ?>
            <form action="logout.php" method="post" style="display: inline;">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="container">
        <!-- College Logo -->
        <div class="logo-container">
            <img src="https://rathinamcollege.ac.in/wp-content/uploads/2020/04/logo-01.jpg" alt="Rathinam College Logo">
        </div>
        
        <!-- Back button to return to dashboard -->
        <a href="dashboard.php" class="btn btn-secondary mb-3">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
        
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-trash-alt me-2"></i>Scrap Assets Report</h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-end mb-3">
                    <button class="btn btn-primary btn-print" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                    <button class="btn btn-success btn-excel" id="exportExcel">
                        <i class="fas fa-file-excel me-2"></i>Export to Excel
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table id="scrapTable" class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>S.No</th>
                                <th>Asset Name</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Serial No</th>
                                <th>R.No</th>
                                <th>Location</th>
                                <th>Current Holder</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($scrap_assets) > 0): ?>
                                <?php foreach ($scrap_assets as $index => $asset): ?>
                                    <tr class="scrap-row">
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($asset['asset_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($asset['brand'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($asset['model'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($asset['serial_no'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($asset['r_no'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($asset['location_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($asset['current_holder'] ?? 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="fas fa-info-circle fa-2x mb-3"></i><br>
                                        No scrap assets found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#scrapTable').DataTable({
                dom: 'Bfrtip',
                buttons: []
            });
            
            // Export to Excel functionality
            $('#exportExcel').click(function() {
                // Get table data
                const table = document.getElementById('scrapTable');
                const workbook = XLSX.utils.table_to_book(table);
                
                // Generate Excel file
                XLSX.writeFile(workbook, 'Scrap_Assets_Report.xlsx');
            });
        });
    </script>
</body>
</html>