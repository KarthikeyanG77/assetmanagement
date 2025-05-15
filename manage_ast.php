<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user's name and designation
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

// Check admin privileges
$is_admin = in_array($user_designation, ['Lead', 'Sr SA', 'IT_Admin']);

// Handle search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type_filter']) ? intval($_GET['type_filter']) : 0;
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Base query
$query = "SELECT a.*, at.type_name, l.location_name, ac.category_name 
          FROM asset a 
          LEFT JOIN asset_type at ON a.type_id = at.type_id 
          LEFT JOIN location l ON a.location_id = l.location_id 
          LEFT JOIN asset_category ac ON l.category_id = ac.category_id 
          WHERE 1=1";

$params = [];
$types = '';

// Add search conditions
if (!empty($search)) {
    $query .= " AND (a.asset_name LIKE ? OR a.serial_no LIKE ? OR a.r_no LIKE ? OR a.model LIKE ? OR a.brand LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, array_fill(0, 5, $search_term));
    $types .= str_repeat('s', 5);
}

// Add type filter
if ($type_filter > 0) {
    $query .= " AND a.type_id = ?";
    $params[] = $type_filter;
    $types .= 'i';
}

// Add status filter
if (!empty($status_filter)) {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

// Sort by purchase date (newest first) and then by asset ID (newest first)
$query .= " ORDER BY a.purchase_date DESC, a.asset_id DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$assets = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get asset types for filter dropdown
$asset_types = [];
$type_query = "SELECT type_id, type_name FROM asset_type ORDER BY type_name";
$type_result = $conn->query($type_query);
if ($type_result) {
    $asset_types = $type_result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #f8f9fa;
            --accent-color: #e74c3c;
            --text-color: #333;
            --light-text: #777;
            --border-color: #ddd;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-color);
        }
        
        .header-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .college-logo {
            max-height: 80px;
            margin-bottom: 15px;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 0 auto 30px;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            padding: 10px 15px;
            border-radius: 50px;
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
            font-size: 18px;
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: var(--light-text);
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .logout-btn:hover {
            color: var(--primary-color);
        }
        
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }
        
        .action-btn {
            padding: 5px 10px;
            font-size: 14px;
            margin: 2px;
        }
        
        .badge-active {
            background-color: #28a745;
        }
        
        .badge-service {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-scrapped {
            background-color: #dc3545;
        }
        
        .search-filter {
            background-color: var(--secondary-color);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table th {
            background-color: var(--primary-color);
            color: white;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .status-badge {
            font-size: 0.85rem;
            padding: 5px 10px;
            border-radius: 50px;
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-info {
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- College Logo Header -->
        <div class="header-container">
            <img src="https://rathinamcollege.ac.in/wp-content/uploads/2022/11/RC-logo.png" alt="Rathinam College Logo" class="college-logo">
        </div>
        
        <div class="page-header">
            <h1 class="page-title">Manage Assets</h1>
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
        </div>
        
        <div class="main-container">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="search-filter">
                <form method="get" action="manage_ast.php">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Search assets..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="type_filter">
                                <option value="0">All Asset Types</option>
                                <?php foreach ($asset_types as $type): ?>
                                    <option value="<?= $type['type_id'] ?>" <?= $type_filter == $type['type_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['type_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="status_filter">
                                <option value="">All Statuses</option>
                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="service" <?= $status_filter === 'service' ? 'selected' : '' ?>>In Service</option>
                                <option value="scrapped" <?= $status_filter === 'scrapped' ? 'selected' : '' ?>>Scrapped</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if ($is_admin): ?>
                <div class="mb-3">
                    <a href="add_ast.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add New Asset
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead>
                        <tr>
                            <th>Asset Name</th>
                            <th>Type</th>
                            <th>Serial No</th>
                            <th>R No</th>
                            <th>Model</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($assets) > 0): ?>
                            <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td><?= htmlspecialchars($asset['asset_name']) ?></td>
                                    <td><?= htmlspecialchars($asset['type_name']) ?></td>
                                    <td><?= htmlspecialchars($asset['serial_no'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($asset['r_no'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($asset['model'] ?? 'N/A') ?></td>
                                    <td>
                                        <?= htmlspecialchars(($asset['category_name'] ? $asset['category_name'] . ' - ' : '') . ($asset['location_name'] ?? 'N/A')) ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $badge_class = '';
                                            if ($asset['status'] === 'active') $badge_class = 'badge-active';
                                            elseif ($asset['status'] === 'service') $badge_class = 'badge-service';
                                            elseif ($asset['status'] === 'scrapped') $badge_class = 'badge-scrapped';
                                        ?>
                                        <span class="badge status-badge <?= $badge_class ?>">
                                            <?= ucfirst($asset['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_ast.php?id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-info action-btn" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($is_admin): ?>
                                            <a href="edit_ast.php?id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-warning action-btn" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="del_ast.php?id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-danger action-btn" title="Delete" onclick="return confirm('Are you sure you want to delete this asset?');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No assets found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>