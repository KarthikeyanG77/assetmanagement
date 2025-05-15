<?php 
// Database connection
$host = '127.0.0.1';
$dbname = 'ict';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current user's details
$currentUserName = "User";
$user_designation = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT emp_name, designation FROM employee WHERE emp_id = ?";
    
    if ($stmt = $pdo->prepare($query)) {
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $currentUserName = $user['emp_name'];
            $user_designation = $user['designation'];
        }
    }
}

// Check if Excel export is requested
if (isset($_GET['export_excel'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="productwise_assets_'.date('Y-m-d').'.xls"');
    $excel_export = true;
} else {
    $excel_export = false;
}

// Function to fetch assets based on filters
function getProductwiseAssets($pdo, $type_id = null, $location_id = null, $status = null) {
    $sql = "SELECT 
                a.asset_id,
                a.asset_name,
                a.model,
                a.brand,
                a.serial_no,
                a.r_no,
                a.processor,
                a.processor_version,
                a.ram_type,
                a.ram_size,
                a.storage_type,
                a.storage_count as storage_size,
                a.os_type,
                a.os,
                a.status,
                l.location_name,
                at.type_name,
                e1.emp_name as current_holder,
                e2.emp_name as previous_holder
            FROM asset a
            JOIN location l ON a.location_id = l.location_id
            JOIN asset_type at ON a.type_id = at.type_id
            LEFT JOIN employee e1 ON a.current_holder = e1.emp_id
            LEFT JOIN employee e2 ON a.previous_holder = e2.emp_id
            WHERE a.status != 'scrapped'"; // Changed from 'Scrap' to 'scrapped' to match your enum
    
    $params = [];
    
    if ($type_id) {
        $sql .= " AND a.type_id = :type_id";
        $params[':type_id'] = $type_id;
    }
    
    if ($location_id) {
        $sql .= " AND a.location_id = :location_id";
        $params[':location_id'] = $location_id;
    }
    
    if ($status) {
        $sql .= " AND a.status = :status";
        $params[':status'] = $status;
    }
    
    $sql .= " ORDER BY l.location_name, at.type_name, a.asset_name";
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch asset types (only CPU, LAPTOP, INTERACTIVE PANEL, TV)
function getAssetTypes($pdo) {
    $sql = "SELECT type_id, type_name FROM asset_type 
            WHERE type_name IN ('CPU', 'LAPTOP', 'INTERACTIVE PANEL', 'TV')
            ORDER BY type_name";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch locations (Chanakya Block, Departments CS, IT, CM2, Labs Lab1, Lab2)
function getLocations($pdo) {
    $sql = "SELECT location_id, location_name FROM location 
            WHERE location_name IN ('Chanakya Block', 'CS', 'IT', 'CM2', 'Lab1', 'Lab2')
            ORDER BY location_name";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Process form submission
$type_id = isset($_GET['type_id']) ? $_GET['type_id'] : null;
$location_id = isset($_GET['location_id']) ? $_GET['location_id'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;
$assets = getProductwiseAssets($pdo, $type_id, $location_id, $status);
$asset_types = getAssetTypes($pdo);
$locations = getLocations($pdo);

if (!$excel_export):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productwise Asset Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        @media print {
            .no-print, .no-print * {
                display: none !important;
            }
            body {
                padding: 0;
            }
            .print-only {
                display: block !important;
            }
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }
        .header img {
            height: 80px;
        }
        .report-title {
            margin: 10px 0;
            font-size: 18px;
            font-weight: bold;
        }
        .filter-form {
            margin-bottom: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        .filter-row {
            margin-bottom: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .signature-line {
            display: inline-block;
            width: 200px;
            border-top: 1px solid #000;
            margin: 40px 20px 0 20px;
        }
        .action-button {
            background: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
            margin-right: 10px;
        }
        .action-button:hover {
            background: #45a049;
        }
        .print-only {
            display: none;
            text-align: center;
            margin-bottom: 20px;
        }
        .print-only img {
            height: 60px;
        }
        .user-info {
            float: right;
            margin-bottom: 20px;
            padding: 8px 15px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        .back-button {
            position: absolute;
            left: 0;
            top: 0;
            background: #6c757d;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .back-button:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div class="header">
            <a href="javascript:history.back()" class="back-button">Back</a>
            <img src="https://rathinamcollege.ac.in/wp-content/uploads/2020/04/logo-01.jpg" alt="Rathinam College Logo">
            <div class="report-title">Productwise Asset Report</div>
        </div>

        <div class="user-info">
            User: <?= htmlspecialchars($currentUserName) ?>
            <?php if ($user_designation): ?>
                <br>Designation: <?= htmlspecialchars($user_designation) ?>
            <?php endif; ?>
        </div>

        <div class="filter-form">
            <form method="get">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="location_id">Location:</label>
                        <select name="location_id" id="location_id">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= $location['location_id'] ?>" <?= $location_id == $location['location_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($location['location_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="type_id">Asset Type:</label>
                        <select name="type_id" id="type_id">
                            <option value="">All Types</option>
                            <?php foreach ($asset_types as $type): ?>
                                <option value="<?= $type['type_id'] ?>" <?= $type_id == $type['type_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['type_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status">
                            <option value="">All Status</option>
                            <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="service" <?= $status == 'service' ? 'selected' : '' ?>>Service</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row">
                    <button type="submit" class="action-button">Generate Report</button>
                    <button type="button" class="action-button" onclick="window.print()">Print Report</button>
                    <button type="submit" name="export_excel" value="1" class="action-button">Export to Excel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="print-only">
        <img src="https://rathinamcollege.ac.in/wp-content/uploads/2020/04/logo-01.jpg" alt="Rathinam College Logo">
        <h2>Productwise Asset Report</h2>
        <p>Generated by: <?= htmlspecialchars($currentUserName) ?></p>
    </div>

    <div id="report-data">
    <?php if (!empty($assets)): ?>
        <?php 
        $current_location = null;
        $current_type = null;
        $serial = 1;
        
        foreach ($assets as $asset): 
            // Show location header if changed
            if ($asset['location_name'] != $current_location) {
                $current_location = $asset['location_name'];
                echo '<h3>Location: ' . htmlspecialchars($current_location) . '</h3>';
            }
            
            // Show type header if changed
            if ($asset['type_name'] != $current_type) {
                $current_type = $asset['type_name'];
                echo '<h4>Asset Type: ' . htmlspecialchars($current_type) . '</h4>';
                $serial = 1; // Reset serial number for new type
            }
        ?>
        
        <table>
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Asset Name</th>
                    <th>Model</th>
                    <th>Brand</th>
                    <th>Serial Number</th>
                    <th>R No</th>
                    <th>Current Holder</th>
                    <th>Previous Holder</th>
                    <?php if (in_array($asset['type_name'], ['CPU', 'LAPTOP'])): ?>
                        <th>Processor</th>
                        <th>Processor Version</th>
                        <th>RAM Type</th>
                        <th>RAM Size</th>
                        <th>Storage Type</th>
                        <th>Storage Size</th>
                    <?php elseif (in_array($asset['type_name'], ['INTERACTIVE PANEL', 'TV'])): ?>
                        <th>Processor</th>
                        <th>Processor Version</th>
                        <th>RAM Size</th>
                        <th>Storage Type</th>
                        <th>Storage Size</th>
                        <th>OS Type</th>
                        <th>OS</th>
                    <?php endif; ?>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= $serial++ ?></td>
                    <td><?= htmlspecialchars($asset['asset_name']) ?></td>
                    <td><?= htmlspecialchars($asset['model']) ?></td>
                    <td><?= htmlspecialchars($asset['brand']) ?></td>
                    <td><?= htmlspecialchars($asset['serial_no']) ?></td>
                    <td><?= htmlspecialchars($asset['r_no']) ?></td>
                    <td><?= htmlspecialchars($asset['current_holder'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($asset['previous_holder'] ?? 'N/A') ?></td>
                    
                    <?php if (in_array($asset['type_name'], ['CPU', 'LAPTOP'])): ?>
                        <td><?= htmlspecialchars($asset['processor']) ?></td>
                        <td><?= htmlspecialchars($asset['processor_version']) ?></td>
                        <td><?= htmlspecialchars($asset['ram_type']) ?></td>
                        <td><?= htmlspecialchars($asset['ram_size']) ?></td>
                        <td><?= htmlspecialchars($asset['storage_type']) ?></td>
                        <td><?= htmlspecialchars($asset['storage_size']) ?></td>
                    <?php elseif (in_array($asset['type_name'], ['INTERACTIVE PANEL', 'TV'])): ?>
                        <td><?= htmlspecialchars($asset['processor']) ?></td>
                        <td><?= htmlspecialchars($asset['processor_version']) ?></td>
                        <td><?= htmlspecialchars($asset['ram_size']) ?></td>
                        <td><?= htmlspecialchars($asset['storage_type']) ?></td>
                        <td><?= htmlspecialchars($asset['storage_size']) ?></td>
                        <td><?= htmlspecialchars($asset['os_type']) ?></td>
                        <td><?= htmlspecialchars($asset['os']) ?></td>
                    <?php endif; ?>
                    
                    <td><?= htmlspecialchars(ucfirst($asset['status'])) ?></td>
                </tr>
            </tbody>
        </table>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No assets found for the selected criteria.</p>
    <?php endif; ?>
    </div>

    <div class="footer no-print">
        <div class="signature-line">ICT Lead Signature</div>
        <div class="signature-line">Date: <?= date('d/m/Y') ?></div>
    </div>
</body>
</html>
<?php
else: // Excel export
    echo '<table border="1">';
    echo '<tr><th colspan="20">Productwise Asset Report - Generated on '.date('d/m/Y').' by '.htmlspecialchars($currentUserName).'</th></tr>';
    
    if (!empty($assets)) {
        $current_location = null;
        $current_type = null;
        $serial = 1;
        
        foreach ($assets as $asset) {
            // Show location header if changed
            if ($asset['location_name'] != $current_location) {
                $current_location = $asset['location_name'];
                echo '<tr><th colspan="20">Location: '.htmlspecialchars($current_location).'</th></tr>';
            }
            
            // Show type header if changed
            if ($asset['type_name'] != $current_type) {
                $current_type = $asset['type_name'];
                echo '<tr><th colspan="20">Asset Type: '.htmlspecialchars($current_type).'</th></tr>';
                
                // Header row
                echo '<tr>
                    <th>S.No</th>
                    <th>Asset Name</th>
                    <th>Model</th>
                    <th>Brand</th>
                    <th>Serial Number</th>
                    <th>R No</th>
                    <th>Current Holder</th>
                    <th>Previous Holder</th>';
                
                if (in_array($asset['type_name'], ['CPU', 'LAPTOP'])) {
                    echo '<th>Processor</th>
                        <th>Processor Version</th>
                        <th>RAM Type</th>
                        <th>RAM Size</th>
                        <th>Storage Type</th>
                        <th>Storage Size</th>';
                } elseif (in_array($asset['type_name'], ['INTERACTIVE PANEL', 'TV'])) {
                    echo '<th>Processor</th>
                        <th>Processor Version</th>
                        <th>RAM Size</th>
                        <th>Storage Type</th>
                        <th>Storage Size</th>
                        <th>OS Type</th>
                        <th>OS</th>';
                }
                
                echo '<th>Status</th></tr>';
                
                $serial = 1; // Reset serial number for new type
            }
            
            // Data row
            echo '<tr>
                <td>'.$serial++.'</td>
                <td>'.htmlspecialchars($asset['asset_name']).'</td>
                <td>'.htmlspecialchars($asset['model']).'</td>
                <td>'.htmlspecialchars($asset['brand']).'</td>
                <td>'.htmlspecialchars($asset['serial_no']).'</td>
                <td>'.htmlspecialchars($asset['r_no']).'</td>
                <td>'.htmlspecialchars($asset['current_holder'] ?? 'N/A').'</td>
                <td>'.htmlspecialchars($asset['previous_holder'] ?? 'N/A').'</td>';
            
            if (in_array($asset['type_name'], ['CPU', 'LAPTOP'])) {
                echo '<td>'.htmlspecialchars($asset['processor']).'</td>
                    <td>'.htmlspecialchars($asset['processor_version']).'</td>
                    <td>'.htmlspecialchars($asset['ram_type']).'</td>
                    <td>'.htmlspecialchars($asset['ram_size']).'</td>
                    <td>'.htmlspecialchars($asset['storage_type']).'</td>
                    <td>'.htmlspecialchars($asset['storage_size']).'</td>';
            } elseif (in_array($asset['type_name'], ['INTERACTIVE PANEL', 'TV'])) {
                echo '<td>'.htmlspecialchars($asset['processor']).'</td>
                    <td>'.htmlspecialchars($asset['processor_version']).'</td>
                    <td>'.htmlspecialchars($asset['ram_size']).'</td>
                    <td>'.htmlspecialchars($asset['storage_type']).'</td>
                    <td>'.htmlspecialchars($asset['storage_size']).'</td>
                    <td>'.htmlspecialchars($asset['os_type']).'</td>
                    <td>'.htmlspecialchars($asset['os']).'</td>';
            }
            
            echo '<td>'.htmlspecialchars(ucfirst($asset['status'])).'</td></tr>';
        }
    } else {
        echo '<tr><td colspan="20">No assets found for the selected criteria.</td></tr>';
    }
    
    echo '</table>';
endif;
?>