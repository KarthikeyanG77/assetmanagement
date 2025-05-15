<?php
// Database connection
require_once 'config.php';

// Initialize variables
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;
$type_id = isset($_GET['type_id']) ? intval($_GET['type_id']) : 0;
$export = isset($_GET['export']) ? $_GET['export'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active'; // Default to active

// Get categories, locations, and types for dropdowns
$categories = [];
$locations = [];
$types = [];

// Always get all categories
$query = "SELECT * FROM asset_category ORDER BY category_name";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Get locations based on selected category (if any)
if ($category_id > 0) {
    $query = "SELECT * FROM location WHERE category_id = $category_id ORDER BY location_name";
} else {
    $query = "SELECT * FROM location ORDER BY location_name";
}
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $locations[] = $row;
}

// Always get all asset types
$query = "SELECT * FROM asset_type ORDER BY type_name";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $types[] = $row;
}

// Build the base query
$query = "SELECT a.*, at.type_name, l.location_name, ac.category_name 
          FROM asset a
          LEFT JOIN asset_type at ON a.type_id = at.type_id
          LEFT JOIN location l ON a.location_id = l.location_id
          LEFT JOIN asset_category ac ON l.category_id = ac.category_id
          WHERE a.type_id NOT IN (19)"; // Exclude projectors

// Add status filter
$query .= " AND a.status IN ('active', 'inactive')"; // Only show active and inactive

// Add filters
if ($category_id > 0) {
    $query .= " AND l.category_id = $category_id";
}

if ($location_id > 0) {
    $query .= " AND a.location_id = $location_id";
}

if ($type_id > 0) {
    $query .= " AND a.type_id = $type_id";
}

// Add status filter from dropdown
if ($status_filter == 'active') {
    $query .= " AND a.status = 'active'";
} elseif ($status_filter == 'inactive') {
    $query .= " AND a.status = 'inactive'";
}

$query .= " ORDER BY at.type_name, l.location_name, a.asset_name";

// Handle Excel export
if ($export == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="asset_report_' . date('Y-m-d') . '.xls"');
    
    $result = $conn->query($query);
    
    echo "Asset Report\n\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    echo "ID\tName\tSerial No\tR No\tType\tBrand\tModel\tLocation (Category)\tStatus\tProcessor\tRAM\tStorage\n";
    
    while ($row = $result->fetch_assoc()) {
        echo $row['asset_id'] . "\t";
        echo $row['asset_name'] . "\t";
        echo $row['serial_no'] . "\t";
        echo $row['r_no'] . "\t";
        echo $row['type_name'] . "\t";
        echo $row['brand'] . "\t";
        echo $row['model'] . "\t";
        echo $row['location_name'] . ' (' . $row['category_name'] . ")\t";
        echo $row['status'] . "\t";
        echo $row['processor'] . " " . $row['processor_version'] . "\t";
        echo $row['ram_size'] . " " . $row['ram_type'] . "\t";
        echo $row['storage_type'] . " " . ($row['storage_count'] ?? '') . "\n";
    }
    exit;
}

// Get data for HTML display
$result = $conn->query($query);
$assets = [];
$assets_by_type = [];
while ($row = $result->fetch_assoc()) {
    $assets[] = $row;
    $type_name = $row['type_name'];
    if (!isset($assets_by_type[$type_name])) {
        $assets_by_type[$type_name] = [];
    }
    $assets_by_type[$type_name][] = $row;
}

// Function to safely output values with htmlspecialchars
function safeOutput($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Function to generate summary counts for CPUs
function generateCpuSummary($assets) {
    $summary = [];
    $total_count = 0;
    
    foreach ($assets as $asset) {
        $processor = isset($asset['processor']) ? $asset['processor'] : '';
        $processor_version = isset($asset['processor_version']) ? $asset['processor_version'] : '';
        $ram_size = isset($asset['ram_size']) ? $asset['ram_size'] : '';
        $ram_type = isset($asset['ram_type']) ? $asset['ram_type'] : '';
        $storage_type = isset($asset['storage_type']) ? $asset['storage_type'] : '';
        $storage_count = isset($asset['storage_count']) ? $asset['storage_count'] : '';
        $graphics_card = isset($asset['graphics_card']) ? $asset['graphics_card'] : '';
        $graphics_size = isset($asset['graphics_size']) ? $asset['graphics_size'] : '';
        
        $key = $asset['brand'] . '|' . $processor . '|' . $ram_size . '|' . $storage_count . '|' . $graphics_card;
        
        if (!isset($summary[$key])) {
            $summary[$key] = [
                'brand' => $asset['brand'],
                'processor' => $processor . ' ' . $processor_version,
                'ram_size' => $ram_size . ' ' . $ram_type,
                'storage' => $storage_type . ' ' . $storage_count . 'GB',
                'graphics' => !empty($graphics_card) ? $graphics_card . ' ' . $graphics_size : 'No',
                'count' => 0
            ];
        }
        $summary[$key]['count']++;
        $total_count++;
    }
    
    return ['summary' => $summary, 'total_count' => $total_count];
}

// Function to generate summary counts for Projectors
function generateProjectorSummary($assets) {
    $summary = [];
    $total_count = 0;
    
    foreach ($assets as $asset) {
        $lumens = isset($asset['lumens']) ? $asset['lumens'] : '';
        $resolution = isset($asset['resolution']) ? $asset['resolution'] : '';
        
        $key = $asset['brand'] . '|' . $asset['model'] . '|' . $lumens . '|' . $resolution;
        
        if (!isset($summary[$key])) {
            $summary[$key] = [
                'brand' => $asset['brand'],
                'model' => $asset['model'],
                'projector_type' => $asset['projector_type'] ?? 'N/A',
                'has_speaker' => !empty($asset['has_speaker']) ? 'Yes' : 'No',
                'lumens' => $lumens,
                'resolution' => $resolution,
                'mount_type' => $asset['mount_type'] ?? 'N/A',
                'logic_box_status' => $asset['logic_box_status'] ?? 'N/A',
                'cable_type' => $asset['cable_type'] ?? 'N/A',
                'screen_type' => $asset['screen_type'] ?? 'N/A',
                'count' => 0
            ];
        }
        $summary[$key]['count']++;
        $total_count++;
    }
    
    return ['summary' => $summary, 'total_count' => $total_count];
}

// Function to generate summary counts for Printers
function generatePrinterSummary($assets) {
    $summary = [];
    $total_count = 0;
    
    foreach ($assets as $asset) {
        $printer_type = isset($asset['printer_type']) ? $asset['printer_type'] : '';
        $connectivity = isset($asset['connectivity']) ? $asset['connectivity'] : '';
        $paper_size = isset($asset['paper_size']) ? $asset['paper_size'] : '';
        
        $key = $asset['brand'] . '|' . $asset['model'] . '|' . $printer_type . '|' . $connectivity;
        
        if (!isset($summary[$key])) {
            $summary[$key] = [
                'brand' => $asset['brand'],
                'model' => $asset['model'],
                'printer_type' => $printer_type,
                'connectivity' => $connectivity,
                'paper_size' => $paper_size,
                'count' => 0
            ];
        }
        $summary[$key]['count']++;
        $total_count++;
    }
    
    return ['summary' => $summary, 'total_count' => $total_count];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .filter-section {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 10px;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        select, button {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            margin-top: 5px;
        }
        button.secondary {
            background-color: #6c757d;
        }
        button.danger {
            background-color: #dc3545;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .print-only {
            display: none;
        }
        .type-section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        .type-header {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .summary-table {
            margin-bottom: 20px;
        }
        .count-badge {
            background-color: #17a2b8;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.9em;
        }
        .status-active {
            color: green;
            font-weight: bold;
        }
        .status-inactive {
            color: orange;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #e9ecef !important;
        }
        .variant-count {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        @media print {
            .no-print {
                display: none;
            }
            .print-only {
                display: block;
                text-align: center;
                margin-bottom: 20px;
                font-size: 18px;
                font-weight: bold;
            }
            body {
                padding: 0;
            }
            .container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="print-only">Asset Report - <?php echo date('Y-m-d'); ?></div>
        
        <div class="no-print">
            <h1>Asset Report</h1>
            
            <div class="filter-section">
                <form method="get" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="category_id">Asset Category:</label>
                            <select name="category_id" id="category_id" onchange="this.form.submit()">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" <?php echo $category['category_id'] == $category_id ? 'selected' : ''; ?>>
                                        <?php echo safeOutput($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="location_id">Location:</label>
                            <select name="location_id" id="location_id">
                                <option value="0">All Locations</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['location_id']; ?>" <?php echo $location['location_id'] == $location_id ? 'selected' : ''; ?>>
                                        <?php echo safeOutput($location['location_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="type_id">Asset Type:</label>
                            <select name="type_id" id="type_id">
                                <option value="0">All Types</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo $type['type_id']; ?>" <?php echo $type['type_id'] == $type_id ? 'selected' : ''; ?>>
                                        <?php echo safeOutput($type['type_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Status:</label>
                            <select name="status" id="status">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active Only</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit">Apply Filters</button>
                        <button type="button" onclick="window.print()" class="secondary">Print Report</button>
                        <button type="submit" name="export" value="excel" class="secondary">Export to Excel</button>
                        <button type="button" onclick="window.location.href='dashboard.php'" class="danger">Back to Dashboard</button>
                    </div>
                </form>
            </div>
            
            <p>Showing <?php echo count($assets); ?> assets (<?php echo $status_filter == 'all' ? 'Active and Inactive' : ucfirst($status_filter); ?>)</p>
        </div>
        
        <?php foreach ($assets_by_type as $type_name => $type_assets): ?>
            <div class="type-section">
                <div class="type-header">
                    <h2><?php echo safeOutput($type_name); ?> <span class="count-badge"><?php echo count($type_assets); ?> items</span></h2>
                </div>
                
                <?php if ($type_name == 'CPU' || $type_name == 'Laptop'): ?>
                    <!-- CPU/Laptop Summary Table -->
                    <?php $cpu_summary_data = generateCpuSummary($type_assets); 
                          $cpu_summary = $cpu_summary_data['summary'];
                          $cpu_total_count = $cpu_summary_data['total_count'];
                    ?>
                    <div class="summary-table">
                        <h3>Summary by Configuration</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Brand</th>
                                    <th>Processor</th>
                                    <th>RAM</th>
                                    <th>Storage</th>
                                    <th>Graphics</th>
                                    <th>Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cpu_summary as $item): ?>
                                    <tr>
                                        <td><?php echo safeOutput($item['brand']); ?></td>
                                        <td><?php echo safeOutput($item['processor']); ?></td>
                                        <td><?php echo safeOutput($item['ram_size']); ?></td>
                                        <td><?php echo safeOutput($item['storage']); ?></td>
                                        <td><?php echo safeOutput($item['graphics']); ?></td>
                                        <td><?php echo $item['count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="5">Total Machines</td>
                                    <td><?php echo $cpu_total_count; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Variant Count Summary -->
                    <div class="variant-count">
                        <h4>Variant Count Summary:</h4>
                        <ul>
                            <?php foreach ($cpu_summary as $item): ?>
                                <li><?php echo safeOutput($item['brand']); ?> <?php echo safeOutput($item['processor']); ?>, <?php echo safeOutput($item['ram_size']); ?>, <?php echo safeOutput($item['storage']); ?>, Graphics: <?php echo safeOutput($item['graphics']); ?> - <?php echo $item['count']; ?> machines</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- CPU/Laptop Detailed Table -->
                    <h3>Detailed List</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Name</th>
                                <th>Serial No</th>
                                <th>R No</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Processor</th>
                                <th>RAM</th>
                                <th>Storage</th>
                                <th>Graphics</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($type_assets as $index => $asset): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo safeOutput($asset['asset_name']); ?></td>
                                    <td><?php echo safeOutput($asset['serial_no'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['r_no'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['brand']); ?></td>
                                    <td><?php echo safeOutput($asset['model']); ?></td>
                                    <td><?php echo safeOutput($asset['location_name']); ?></td>
                                    <td class="status-<?php echo $asset['status']; ?>"><?php echo ucfirst($asset['status']); ?></td>
                                    <td>
                                        <?php 
                                        echo safeOutput($asset['processor'] ?? 'N/A');
                                        if (!empty($asset['processor_version'])) {
                                            echo ' ' . safeOutput($asset['processor_version']);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        echo safeOutput($asset['ram_size'] ?? 'N/A');
                                        if (!empty($asset['ram_type'])) {
                                            echo ' ' . safeOutput($asset['ram_type']);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        echo safeOutput($asset['storage_type'] ?? 'N/A');
                                        if (!empty($asset['storage_count'])) {
                                            echo ' ' . safeOutput($asset['storage_count']) . 'GB';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($asset['graphics_card'])) {
                                            echo safeOutput($asset['graphics_card']);
                                            if (!empty($asset['graphics_size'])) {
                                                echo ' ' . safeOutput($asset['graphics_size']);
                                            }
                                        } else {
                                            echo 'No';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="11">Total Machines</td>
                                <td><?php echo count($type_assets); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    
                <?php elseif ($type_name == 'Projector'): ?>
                    <!-- Projector Summary Table -->
                    <?php $projector_summary_data = generateProjectorSummary($type_assets);
                          $projector_summary = $projector_summary_data['summary'];
                          $projector_total_count = $projector_summary_data['total_count'];
                    ?>
                    <div class="summary-table">
                        <h3>Summary by Model</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Brand</th>
                                    <th>Model</th>
                                    <th>Type</th>
                                    <th>Lumens</th>
                                    <th>Resolution</th>
                                    <th>Speaker</th>
                                    <th>Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projector_summary as $item): ?>
                                    <tr>
                                        <td><?php echo safeOutput($item['brand']); ?></td>
                                        <td><?php echo safeOutput($item['model']); ?></td>
                                        <td><?php echo safeOutput($item['projector_type']); ?></td>
                                        <td><?php echo safeOutput($item['lumens']); ?></td>
                                        <td><?php echo safeOutput($item['resolution']); ?></td>
                                        <td><?php echo safeOutput($item['has_speaker']); ?></td>
                                        <td><?php echo $item['count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="6">Total Projectors</td>
                                    <td><?php echo $projector_total_count; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Variant Count Summary -->
                    <div class="variant-count">
                        <h4>Variant Count Summary:</h4>
                        <ul>
                            <?php foreach ($projector_summary as $item): ?>
                                <li><?php echo safeOutput($item['brand']); ?> <?php echo safeOutput($item['model']); ?>, <?php echo safeOutput($item['lumens']); ?> lumens, <?php echo safeOutput($item['resolution']); ?> - <?php echo $item['count']; ?> units</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- Projector Detailed Table -->
                    <h3>Detailed List</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Name</th>
                                <th>Serial No</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Type</th>
                                <th>Lumens</th>
                                <th>Resolution</th>
                                <th>Speaker</th>
                                <th>Mount Type</th>
                                <th>Logic Box</th>
                                <th>Cable Type</th>
                                <th>Screen Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($type_assets as $index => $asset): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo safeOutput($asset['asset_name']); ?></td>
                                    <td><?php echo safeOutput($asset['serial_no'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['brand']); ?></td>
                                    <td><?php echo safeOutput($asset['model']); ?></td>
                                    <td><?php echo safeOutput($asset['location_name']); ?></td>
                                    <td class="status-<?php echo $asset['status']; ?>"><?php echo ucfirst($asset['status']); ?></td>
                                    <td><?php echo safeOutput($asset['projector_type'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['lumens'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['resolution'] ?? 'N/A'); ?></td>
                                    <td><?php echo !empty($asset['has_speaker']) ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo safeOutput($asset['mount_type'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['logic_box_status'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['cable_type'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['screen_type'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="14">Total Projectors</td>
                                <td><?php echo count($type_assets); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    
                <?php elseif ($type_name == 'Printer'): ?>
                    <!-- Printer Summary Table -->
                    <?php $printer_summary_data = generatePrinterSummary($type_assets);
                          $printer_summary = $printer_summary_data['summary'];
                          $printer_total_count = $printer_summary_data['total_count'];
                    ?>
                    <div class="summary-table">
                        <h3>Summary by Model</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Brand</th>
                                    <th>Model</th>
                                    <th>Type</th>
                                    <th>Connectivity</th>
                                    <th>Paper Size</th>
                                    <th>Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($printer_summary as $item): ?>
                                    <tr>
                                        <td><?php echo safeOutput($item['brand']); ?></td>
                                        <td><?php echo safeOutput($item['model']); ?></td>
                                        <td><?php echo safeOutput($item['printer_type']); ?></td>
                                        <td><?php echo safeOutput($item['connectivity']); ?></td>
                                        <td><?php echo safeOutput($item['paper_size']); ?></td>
                                        <td><?php echo $item['count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="5">Total Printers</td>
                                    <td><?php echo $printer_total_count; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Variant Count Summary -->
                    <div class="variant-count">
                        <h4>Variant Count Summary:</h4>
                        <ul>
                            <?php foreach ($printer_summary as $item): ?>
                                <li><?php echo safeOutput($item['brand']); ?> <?php echo safeOutput($item['model']); ?>, <?php echo safeOutput($item['printer_type']); ?>, <?php echo safeOutput($item['connectivity']); ?> - <?php echo $item['count']; ?> units</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- Printer Detailed Table -->
                    <h3>Detailed List</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Name</th>
                                <th>Serial No</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Type</th>
                                <th>Connectivity</th>
                                <th>Paper Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($type_assets as $index => $asset): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo safeOutput($asset['asset_name']); ?></td>
                                    <td><?php echo safeOutput($asset['serial_no'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['brand']); ?></td>
                                    <td><?php echo safeOutput($asset['model']); ?></td>
                                    <td><?php echo safeOutput($asset['location_name']); ?></td>
                                    <td class="status-<?php echo $asset['status']; ?>"><?php echo ucfirst($asset['status']); ?></td>
                                    <td><?php echo safeOutput($asset['printer_type'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['connectivity'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['paper_size'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="9">Total Printers</td>
                                <td><?php echo count($type_assets); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    
                <?php else: ?>
                    <!-- Default Table for other asset types -->
                    <table>
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Name</th>
                                <th>Serial No</th>
                                <th>R No</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Location</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($type_assets as $index => $asset): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo safeOutput($asset['asset_name']); ?></td>
                                    <td><?php echo safeOutput($asset['serial_no'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['r_no'] ?? 'N/A'); ?></td>
                                    <td><?php echo safeOutput($asset['brand']); ?></td>
                                    <td><?php echo safeOutput($asset['model']); ?></td>
                                    <td><?php echo safeOutput($asset['location_name']); ?></td>
                                    <td class="status-<?php echo $asset['status']; ?>"><?php echo ucfirst($asset['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="7">Total Machines</td>
                                <td><?php echo count($type_assets); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <!-- Variant Count Summary for other types -->
                    <div class="variant-count">
                        <h4>Variant Count Summary:</h4>
                        <?php
                        $other_summary = [];
                        foreach ($type_assets as $asset) {
                            $key = $asset['brand'] . '|' . $asset['model'];
                            if (!isset($other_summary[$key])) {
                                $other_summary[$key] = [
                                    'brand' => $asset['brand'],
                                    'model' => $asset['model'],
                                    'count' => 0
                                ];
                            }
                            $other_summary[$key]['count']++;
                        }
                        ?>
                        <ul>
                            <?php foreach ($other_summary as $item): ?>
                                <li><?php echo safeOutput($item['brand']); ?> <?php echo safeOutput($item['model']); ?> - <?php echo $item['count']; ?> units</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <script>
        // Add confirmation before going back
        document.querySelector('button.danger').addEventListener('click', function() {
            if (confirm('Are you sure you want to go back to the dashboard?')) {
                window.location.href = 'dashboard.php';
            }
        });
        
        // Auto-submit when category changes
        document.getElementById('category_id').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>