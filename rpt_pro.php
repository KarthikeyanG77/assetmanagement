<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'config.php';

// Get current user info
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

// Get all asset categories
$categories = [];
$categoryQuery = "SELECT category_id, category_name FROM asset_category ORDER BY category_name";
$categoryResult = $conn->query($categoryQuery);
if ($categoryResult->num_rows > 0) {
    while($row = $categoryResult->fetch_assoc()) {
        $categories[$row['category_id']] = $row['category_name'];
    }
}

// Get all blocks/locations based on selected category (if any)
$blocks = [];
$locationQuery = "SELECT location_id, location_name FROM location WHERE location_name IN ('Chanakya Block', 'CV Raman Block', 'Newton Block', 'RPS', 'Library')";

// If category filter is applied, get only locations of that category
if (isset($_GET['category_id']) && !empty($_GET['category_id'])) {
    $category_id = $conn->real_escape_string($_GET['category_id']);
    $locationQuery .= " AND category_id = '$category_id'";
}

$locationQuery .= " ORDER BY location_name";
$blockResult = $conn->query($locationQuery);
if ($blockResult->num_rows > 0) {
    while($row = $blockResult->fetch_assoc()) {
        $blocks[$row['location_id']] = $row['location_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projector Assets Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print, .dataTables_length, .dataTables_filter, 
            .dataTables_info, .dataTables_paginate, .dt-buttons {
                display: none !important;
            }
            body {
                font-size: 12px;
            }
            .table {
                width: 100% !important;
            }
            .block-section {
                page-break-inside: avoid;
                margin-bottom: 30px;
            }
            .logo-print {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
            }
        }
        .logo-print {
            display: none;
        }
        .report-header {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .filter-section {
            background-color: #f0f8ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .filter-group {
            margin-bottom: 15px;
        }
        .form-label {
            font-weight: 500;
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
        .vertical-form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .block-section {
            margin-bottom: 40px;
        }
        .block-header {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .summary-table {
            width: auto;
            margin-bottom: 20px;
        }
        .serial-vertical {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            text-align: center;
            padding: 5px !important;
            width: 20px;
        }
        .action-buttons-top {
            margin-bottom: 20px;
        }
        /* New style for serial number column */
        .serial-number-vertical {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            text-align: center;
            padding: 5px !important;
            width: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Logo for printing -->
        <div class="logo-print">
            <img src="https://rathinamcollege.ac.in/wp-content/uploads/2020/04/logo-01.jpg" alt="College Logo" style="height: 80px;">
        </div>

        <!-- User Reference Section -->
        <div class="alert alert-info no-print">
            <strong>Logged in as:</strong> <?php echo htmlspecialchars($currentUserName); ?>
            <?php if ($user_designation): ?>
                <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($user_designation); ?></span>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-sm btn-secondary float-end">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Action Buttons at Top -->
        <div class="action-buttons-top no-print">
            <button onclick="window.print()" class="btn btn-success me-2">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>

        <div class="report-header">
            <h2 class="text-center">Projector Assets Detailed Report</h2>
        </div>

        <!-- Filter Section -->
        <div class="filter-section no-print">
            <form method="get" action="">
                <div class="row">
                    <!-- Asset Category Filter -->
                    <div class="col-md-4">
                        <div class="vertical-form-group">
                            <label for="category_id" class="form-label">Asset Category</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $id => $name): ?>
                                    <option value="<?php echo $id; ?>" <?php echo (isset($_GET['category_id']) && $_GET['category_id'] == $id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="col-md-4">
                        <div class="vertical-form-group">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="service" <?php echo (isset($_GET['status']) && $_GET['status'] == 'service') ? 'selected' : ''; ?>>In Service</option>
                                <option value="scrapped" <?php echo (isset($_GET['status']) && $_GET['status'] == 'scrapped') ? 'selected' : ''; ?>>Scrapped</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">
                            <i class="fas fa-sync-alt"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <?php
        // Build base query
        $baseSql = "SELECT 
                        a.asset_id, a.asset_name, a.serial_no, a.r_no, 
                        l.location_name, a.brand, 
                        a.has_speaker, a.projector_type, 
                        a.cable_type, a.logic_box_status, a.status, 
                        a.mount_type, a.screen_type, a.lumens, a.resolution
                    FROM asset a
                    JOIN asset_type at ON a.type_id = at.type_id
                    JOIN location l ON a.location_id = l.location_id
                    WHERE at.type_name = 'Projector'";
        
        // Add category filter if selected
        if (isset($_GET['category_id']) && !empty($_GET['category_id'])) {
            $category_id = $conn->real_escape_string($_GET['category_id']);
            $baseSql .= " AND l.category_id = '$category_id'";
        }
        
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $status = $conn->real_escape_string($_GET['status']);
            $baseSql .= " AND a.status = '$status'";
        }
        
        // Process each block
        foreach ($blocks as $block_id => $block_name) {
            $sql = $baseSql . " AND a.location_id = '$block_id' ORDER BY l.location_name, a.asset_name";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                // Get counts by model and mount type for this block
                $countSql = "SELECT 
                                a.brand as model, 
                                a.mount_type, 
                                COUNT(*) as count
                              FROM asset a
                              JOIN asset_type at ON a.type_id = at.type_id
                              JOIN location l ON a.location_id = l.location_id
                              WHERE a.location_id = '$block_id' 
                              AND at.type_name = 'Projector'";
                
                if (isset($_GET['category_id']) && !empty($_GET['category_id'])) {
                    $countSql .= " AND l.category_id = '$category_id'";
                }
                
                if (isset($_GET['status']) && !empty($_GET['status'])) {
                    $countSql .= " AND a.status = '$status'";
                }
                
                $countSql .= " GROUP BY a.brand, a.mount_type ORDER BY a.brand, a.mount_type";
                
                $countResult = $conn->query($countSql);
                $modelCounts = [];
                $totalCount = 0;
                
                if ($countResult->num_rows > 0) {
                    while($row = $countResult->fetch_assoc()) {
                        $model = $row['model'] ? $row['model'] : 'Unknown';
                        $mountType = $row['mount_type'] ? $row['mount_type'] : 'Not Specified';
                        
                        if (!isset($modelCounts[$model])) {
                            $modelCounts[$model] = [];
                        }
                        
                        $modelCounts[$model][$mountType] = $row['count'];
                        $totalCount += $row['count'];
                    }
                }
                
                echo '<div class="block-section">';
                echo '<div class="block-header">';
                echo '<h3>'.$block_name.'</h3>';
                
                // Display summary table
                if (!empty($modelCounts)) {
                    echo '<div class="summary-table-container">';
                    echo '<table class="table table-bordered summary-table">';
                    echo '<thead><tr><th>Model</th><th>Short Throw</th><th>Long Throw</th><th>Not Specified</th><th>Total</th></tr></thead>';
                    echo '<tbody>';
                    
                    foreach ($modelCounts as $model => $mountTypes) {
                        $shortThrow = isset($mountTypes['Short Throw']) ? $mountTypes['Short Throw'] : 0;
                        $longThrow = isset($mountTypes['Long Throw']) ? $mountTypes['Long Throw'] : 0;
                        $notSpecified = isset($mountTypes['Not Specified']) ? $mountTypes['Not Specified'] : 0;
                        $modelTotal = $shortThrow + $longThrow + $notSpecified;
                        
                        echo '<tr>';
                        echo '<td>'.htmlspecialchars($model).'</td>';
                        echo '<td>'.$shortThrow.'</td>';
                        echo '<td>'.$longThrow.'</td>';
                        echo '<td>'.$notSpecified.'</td>';
                        echo '<td>'.$modelTotal.'</td>';
                        echo '</tr>';
                    }
                    
                    echo '<tr><td><strong>Total</strong></td>';
                    echo '<td>'.(isset($modelCounts['Short Throw']) ? array_sum(array_column($modelCounts, 'Short Throw')) : 0).'</td>';
                    echo '<td>'.(isset($modelCounts['Long Throw']) ? array_sum(array_column($modelCounts, 'Long Throw')) : 0).'</td>';
                    echo '<td>'.(isset($modelCounts['Not Specified']) ? array_sum(array_column($modelCounts, 'Not Specified')) : 0).'</td>';
                    echo '<td><strong>'.$totalCount.'</strong></td></tr>';
                    
                    echo '</tbody></table></div>';
                }
                
                echo '</div>'; // end block-header
                
                // Display detailed table
                echo '<div class="table-responsive">';
                echo '<table class="table table-bordered table-striped table-hover">';
                echo '<thead class="table-dark">';
                echo '<tr>';
                echo '<th class="serial-number-vertical">S.No</th>'; // New vertical serial number column
                echo '<th>Asset Name</th>';
                echo '<th>Serial No</th>'; // Changed to horizontal
                echo '<th>R No</th>';
                echo '<th>Location</th>';
                echo '<th>Projector Type</th>';
                echo '<th>Brand</th>';
                echo '<th>Speaker</th>';
                echo '<th>Lumens</th>';
                echo '<th>Resolution</th>';
                echo '<th>Screen Type</th>';
                echo '<th>Cable Type</th>';
                echo '<th>Logic Box</th>';
                echo '<th>Status</th>';
                echo '<th>Mount Type</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                $counter = 1; // Initialize counter for serial numbers
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td class='serial-number-vertical'>".$counter++."</td>"; // Vertical serial number
                    echo "<td>".htmlspecialchars($row['asset_name'])."</td>";
                    echo "<td>".($row['serial_no'] ? htmlspecialchars($row['serial_no']) : 'N/A')."</td>"; // Horizontal serial number
                    echo "<td>".($row['r_no'] ? htmlspecialchars($row['r_no']) : 'N/A')."</td>";
                    echo "<td>".htmlspecialchars($row['location_name'])."</td>";
                    echo "<td>".($row['projector_type'] ? htmlspecialchars($row['projector_type']) : 'N/A')."</td>";
                    echo "<td>".($row['brand'] ? htmlspecialchars($row['brand']) : 'N/A')."</td>";
                    echo "<td>".($row['has_speaker'] ? htmlspecialchars($row['has_speaker']) : 'N/A')."</td>";
                    echo "<td>".($row['lumens'] ? htmlspecialchars($row['lumens']) : 'N/A')."</td>";
                    echo "<td>".($row['resolution'] ? htmlspecialchars($row['resolution']) : 'N/A')."</td>";
                    echo "<td>".($row['screen_type'] ? htmlspecialchars($row['screen_type']) : 'N/A')."</td>";
                    echo "<td>".($row['cable_type'] ? htmlspecialchars($row['cable_type']) : 'N/A')."</td>";
                    echo "<td>".($row['logic_box_status'] ? htmlspecialchars($row['logic_box_status']) : 'N/A')."</td>";
                    
                    // Status with badge
                    echo "<td>";
                    switch($row['status']) {
                        case 'active':
                            echo "<span class='badge badge-active'>Active</span>";
                            break;
                        case 'service':
                            echo "<span class='badge badge-service'>In Service</span>";
                            break;
                        case 'scrapped':
                            echo "<span class='badge badge-scrapped'>Scrapped</span>";
                            break;
                        default:
                            echo "<span class='badge bg-secondary'>".htmlspecialchars($row['status'])."</span>";
                    }
                    echo "</td>";
                    
                    echo "<td>".($row['mount_type'] ? htmlspecialchars($row['mount_type']) : 'N/A')."</td>";
                    echo "</tr>";
                }
                
                echo '</tbody></table></div></div>'; // end table-responsive and block-section
            }
        }
        
        // Check if there are any assets that don't belong to the main blocks
        $otherSql = $baseSql . " AND a.location_id NOT IN ('" . implode("','", array_keys($blocks)) . "')";
        $otherResult = $conn->query($otherSql);
        
        if ($otherResult->num_rows > 0) {
            echo '<div class="block-section">';
            echo '<div class="block-header">';
            echo '<h3>Other Locations</h3>';
            echo '</div>';
            
            echo '<div class="table-responsive">';
            echo '<table class="table table-bordered table-striped table-hover">';
            echo '<thead class="table-dark">';
            echo '<tr>';
            echo '<th class="serial-number-vertical">S.No</th>'; // New vertical serial number column
            echo '<th>Asset Name</th>';
            echo '<th>Serial No</th>'; // Changed to horizontal
            echo '<th>R No</th>';
            echo '<th>Location</th>';
            echo '<th>Projector Type</th>';
            echo '<th>Brand</th>';
            echo '<th>Speaker</th>';
            echo '<th>Lumens</th>';
            echo '<th>Resolution</th>';
            echo '<th>Screen Type</th>';
            echo '<th>Cable Type</th>';
            echo '<th>Logic Box</th>';
            echo '<th>Status</th>';
            echo '<th>Mount Type</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            $counter = 1; // Initialize counter for serial numbers
            while($row = $otherResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td class='serial-number-vertical'>".$counter++."</td>"; // Vertical serial number
                echo "<td>".htmlspecialchars($row['asset_name'])."</td>";
                echo "<td>".($row['serial_no'] ? htmlspecialchars($row['serial_no']) : 'N/A')."</td>"; // Horizontal serial number
                echo "<td>".($row['r_no'] ? htmlspecialchars($row['r_no']) : 'N/A')."</td>";
                echo "<td>".htmlspecialchars($row['location_name'])."</td>";
                echo "<td>".($row['projector_type'] ? htmlspecialchars($row['projector_type']) : 'N/A')."</td>";
                echo "<td>".($row['brand'] ? htmlspecialchars($row['brand']) : 'N/A')."</td>";
                echo "<td>".($row['has_speaker'] ? htmlspecialchars($row['has_speaker']) : 'N/A')."</td>";
                echo "<td>".($row['lumens'] ? htmlspecialchars($row['lumens']) : 'N/A')."</td>";
                echo "<td>".($row['resolution'] ? htmlspecialchars($row['resolution']) : 'N/A')."</td>";
                echo "<td>".($row['screen_type'] ? htmlspecialchars($row['screen_type']) : 'N/A')."</td>";
                echo "<td>".($row['cable_type'] ? htmlspecialchars($row['cable_type']) : 'N/A')."</td>";
                echo "<td>".($row['logic_box_status'] ? htmlspecialchars($row['logic_box_status']) : 'N/A')."</td>";
                
                // Status with badge
                echo "<td>";
                switch($row['status']) {
                    case 'active':
                        echo "<span class='badge badge-active'>Active</span>";
                        break;
                    case 'service':
                        echo "<span class='badge badge-service'>In Service</span>";
                        break;
                    case 'scrapped':
                        echo "<span class='badge badge-scrapped'>Scrapped</span>";
                        break;
                    default:
                        echo "<span class='badge bg-secondary'>".htmlspecialchars($row['status'])."</span>";
                }
                echo "</td>";
                
                echo "<td>".($row['mount_type'] ? htmlspecialchars($row['mount_type']) : 'N/A')."</td>";
                echo "</tr>";
            }
            
            echo '</tbody></table></div></div>'; // end table-responsive and block-section
        }
        ?>

        <!-- Back Button at Bottom -->
        <div class="mt-3 no-print">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>