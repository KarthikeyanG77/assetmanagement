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
    header('Content-Disposition: attachment; filename="lab_assets_'.date('Y-m-d').'.xls"');
    $excel_export = true;
} else {
    $excel_export = false;
}

// Function to fetch lab assets grouped by asset name
function getLabAssets($pdo, $type_id = null, $location_id = null) {
    $sql = "SELECT 
                a.asset_name,
                a.location_id,
                MAX(l.location_name) as location_name,
                MAX(CASE WHEN at.type_name = 'CPU' THEN a.brand ELSE NULL END) as cpu_brand,
                MAX(CASE WHEN at.type_name = 'CPU' THEN a.model ELSE NULL END) as cpu_model,
                MAX(CASE WHEN at.type_name = 'CPU' THEN a.processor ELSE NULL END) as processor,
                MAX(CASE WHEN at.type_name = 'CPU' THEN a.ram_type ELSE NULL END) as ram_type,
                MAX(CASE WHEN at.type_name = 'CPU' THEN a.ram_size ELSE NULL END) as ram_size,
                MAX(CASE WHEN at.type_name = 'CPU' THEN a.storage_type ELSE NULL END) as storage_type,
                MAX(CASE WHEN at.type_name = 'CPU' THEN a.storage_count ELSE NULL END) as storage_size,
                MAX(CASE WHEN at.type_name = 'Mouse' THEN a.brand ELSE NULL END) as mouse_brand,
                MAX(CASE WHEN at.type_name = 'Mouse' THEN a.model ELSE NULL END) as mouse_model,
                MAX(CASE WHEN at.type_name = 'Mouse' THEN a.status ELSE NULL END) as mouse_status,
                MAX(CASE WHEN at.type_name = 'Keyboard' THEN a.brand ELSE NULL END) as keyboard_brand,
                MAX(CASE WHEN at.type_name = 'Keyboard' THEN a.model ELSE NULL END) as keyboard_model,
                MAX(CASE WHEN at.type_name = 'Keyboard' THEN a.status ELSE NULL END) as keyboard_status,
                MAX(CASE WHEN at.type_name = 'Monitor' THEN a.brand ELSE NULL END) as monitor_brand,
                MAX(CASE WHEN at.type_name = 'Monitor' THEN a.model ELSE NULL END) as monitor_model,
                MAX(CASE WHEN at.type_name = 'Monitor' THEN a.status ELSE NULL END) as monitor_status,
                MAX(CASE WHEN at.type_name = 'CPU' THEN a.lan_status ELSE NULL END) as lan_status,
                MAX(CASE WHEN at.type_name = 'CPU' THEN a.status ELSE NULL END) as cpu_status
            FROM asset a
            JOIN location l ON a.location_id = l.location_id
            JOIN asset_category ac ON l.category_id = ac.category_id
            JOIN asset_type at ON a.type_id = at.type_id
            WHERE ac.category_name = 'Lab'";
    
    $params = [];
    
    if ($type_id) {
        $sql .= " AND a.type_id = :type_id";
        $params[':type_id'] = $type_id;
    }
    
    if ($location_id) {
        $sql .= " AND a.location_id = :location_id";
        $params[':location_id'] = $location_id;
    }
    
    $sql .= " GROUP BY a.asset_name, a.location_id
              ORDER BY l.location_name, a.asset_name";
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch asset types for lab category
function getLabAssetTypes($pdo) {
    $sql = "SELECT DISTINCT at.type_id, at.type_name 
            FROM asset_type at
            JOIN asset a ON at.type_id = a.type_id
            JOIN location l ON a.location_id = l.location_id
            JOIN asset_category ac ON l.category_id = ac.category_id
            WHERE ac.category_name = 'Lab'
            ORDER BY at.type_name";
            
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch lab locations
function getLabLocations($pdo) {
    $sql = "SELECT l.location_id, l.location_name 
            FROM location l
            JOIN asset_category ac ON l.category_id = ac.category_id
            WHERE ac.category_name = 'Lab'
            ORDER BY l.location_name";
            
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Process form submission
$type_id = isset($_GET['type_id']) ? $_GET['type_id'] : null;
$location_id = isset($_GET['location_id']) ? $_GET['location_id'] : null;
$assets = getLabAssets($pdo, $type_id, $location_id);
$asset_types = getLabAssetTypes($pdo);
$lab_locations = getLabLocations($pdo);

// Group assets by location for the report
$lab_assets = [];
foreach ($assets as $asset) {
    $lab_assets[$asset['location_name']][] = $asset;
}

if (!$excel_export):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labwise Asset Report</title>
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
    </style>
</head>
<body>
    <div class="no-print">
        <div class="header">
            <img src="https://rathinamcollege.ac.in/wp-content/uploads/2020/04/logo-01.jpg" alt="Rathinam College Logo">
            <div class="report-title">Labwise Asset Report</div>
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
                    <label for="location_id">Select Lab:</label>
                    <select name="location_id" id="location_id">
                        <option value="">All Labs</option>
                        <?php foreach ($lab_locations as $location): ?>
                            <option value="<?= $location['location_id'] ?>" <?= $location_id == $location['location_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($location['location_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-row">
                    <label for="type_id">Filter by Asset Type:</label>
                    <select name="type_id" id="type_id">
                        <option value="">All Asset Types</option>
                        <?php foreach ($asset_types as $type): ?>
                            <option value="<?= $type['type_id'] ?>" <?= $type_id == $type['type_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['type_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
        <h2>Labwise Asset Report</h2>
        <p>Generated by: <?= htmlspecialchars($currentUserName) ?></p>
    </div>

    <div id="report-data">
    <?php if (!empty($lab_assets)): ?>
        <?php foreach ($lab_assets as $lab_name => $lab_items): ?>
            <h3><?= htmlspecialchars($lab_name) ?></h3>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2">Sno</th>
                        <th rowspan="2">Asset Name</th>
                        <th colspan="7">CPU</th>
                        <th colspan="3">Mouse</th>
                        <th colspan="3">Keyboard</th>
                        <th colspan="3">Monitor</th>
                        <th rowspan="2">LAN Status</th>
                        <th rowspan="2">Status of CPU</th>
                    </tr>
                    <tr>
                        <!-- CPU Subheaders -->
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Processor</th>
                        <th>RAM Type</th>
                        <th>RAM Size</th>
                        <th>Storage Type</th>
                        <th>Storage Size</th>
                        
                        <!-- Mouse Subheaders -->
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Status</th>
                        
                        <!-- Keyboard Subheaders -->
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Status</th>
                        
                        <!-- Monitor Subheaders -->
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lab_items as $index => $asset): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($asset['asset_name']) ?></td>
                            
                            <!-- CPU Details -->
                            <td><?= htmlspecialchars($asset['cpu_brand']) ?></td>
                            <td><?= htmlspecialchars($asset['cpu_model']) ?></td>
                            <td><?= htmlspecialchars($asset['processor']) ?></td>
                            <td><?= htmlspecialchars($asset['ram_type']) ?></td>
                            <td><?= htmlspecialchars($asset['ram_size']) ?></td>
                            <td><?= htmlspecialchars($asset['storage_type']) ?></td>
                            <td><?= htmlspecialchars($asset['storage_size']) ?></td>
                            
                            <!-- Mouse Details -->
                            <td><?= htmlspecialchars($asset['mouse_brand'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($asset['mouse_model'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(ucfirst($asset['mouse_status'] ?? 'N/A')) ?></td>
                            
                            <!-- Keyboard Details -->
                            <td><?= htmlspecialchars($asset['keyboard_brand'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($asset['keyboard_model'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(ucfirst($asset['keyboard_status'] ?? 'N/A')) ?></td>
                            
                            <!-- Monitor Details -->
                            <td><?= htmlspecialchars($asset['monitor_brand'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($asset['monitor_model'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(ucfirst($asset['monitor_status'] ?? 'N/A')) ?></td>
                            
                            <!-- Network and Status -->
                            <td><?= htmlspecialchars($asset['lan_status'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(ucfirst($asset['cpu_status'] ?? 'N/A')) ?></td>
                        </tr>
                    <?php endforeach; ?>
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
    echo '<tr><th colspan="20">Labwise Asset Report - Generated on '.date('d/m/Y').' by '.htmlspecialchars($currentUserName).'</th></tr>';
    
    if (!empty($lab_assets)) {
        foreach ($lab_assets as $lab_name => $lab_items) {
            echo '<tr><th colspan="20">Lab: '.htmlspecialchars($lab_name).'</th></tr>';
            echo '<tr>
                <th rowspan="2">Sno</th>
                <th rowspan="2">Asset Name</th>
                <th colspan="7">CPU</th>
                <th colspan="3">Mouse</th>
                <th colspan="3">Keyboard</th>
                <th colspan="3">Monitor</th>
                <th rowspan="2">LAN Status</th>
                <th rowspan="2">Status of CPU</th>
            </tr>';
            echo '<tr>
                <th>Brand</th>
                <th>Model</th>
                <th>Processor</th>
                <th>RAM Type</th>
                <th>RAM Size</th>
                <th>Storage Type</th>
                <th>Storage Size</th>
                <th>Brand</th>
                <th>Model</th>
                <th>Status</th>
                <th>Brand</th>
                <th>Model</th>
                <th>Status</th>
                <th>Brand</th>
                <th>Model</th>
                <th>Status</th>
            </tr>';
            
            foreach ($lab_items as $index => $asset) {
                echo '<tr>
                    <td>'.($index + 1).'</td>
                    <td>'.htmlspecialchars($asset['asset_name']).'</td>
                    <td>'.htmlspecialchars($asset['cpu_brand']).'</td>
                    <td>'.htmlspecialchars($asset['cpu_model']).'</td>
                    <td>'.htmlspecialchars($asset['processor']).'</td>
                    <td>'.htmlspecialchars($asset['ram_type']).'</td>
                    <td>'.htmlspecialchars($asset['ram_size']).'</td>
                    <td>'.htmlspecialchars($asset['storage_type']).'</td>
                    <td>'.htmlspecialchars($asset['storage_size']).'</td>
                    <td>'.htmlspecialchars($asset['mouse_brand'] ?? 'N/A').'</td>
                    <td>'.htmlspecialchars($asset['mouse_model'] ?? 'N/A').'</td>
                    <td>'.htmlspecialchars(ucfirst($asset['mouse_status'] ?? 'N/A')).'</td>
                    <td>'.htmlspecialchars($asset['keyboard_brand'] ?? 'N/A').'</td>
                    <td>'.htmlspecialchars($asset['keyboard_model'] ?? 'N/A').'</td>
                    <td>'.htmlspecialchars(ucfirst($asset['keyboard_status'] ?? 'N/A')).'</td>
                    <td>'.htmlspecialchars($asset['monitor_brand'] ?? 'N/A').'</td>
                    <td>'.htmlspecialchars($asset['monitor_model'] ?? 'N/A').'</td>
                    <td>'.htmlspecialchars(ucfirst($asset['monitor_status'] ?? 'N/A')).'</td>
                    <td>'.htmlspecialchars($asset['lan_status'] ?? 'N/A').'</td>
                    <td>'.htmlspecialchars(ucfirst($asset['cpu_status'] ?? 'N/A')).'</td>
                </tr>';
            }
        }
    } else {
        echo '<tr><td colspan="20">No assets found for the selected criteria.</td></tr>';
    }
    
    echo '</table>';
endif;
?>