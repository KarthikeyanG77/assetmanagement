<?php
session_start();
// brand_report.php
require_once 'header.php';
require_once 'config.php'; // Database connection

// Get all brands from assets
$brands_query = "SELECT DISTINCT brand FROM asset WHERE brand IS NOT NULL ORDER BY brand";
$brands_result = $conn->query($brands_query);

// Get lab location IDs
$lab_locations_query = "SELECT location_id FROM location WHERE category_id = 1"; // category_id 1 is Lab
$lab_locations_result = $conn->query($lab_locations_query);
$lab_location_ids = [];
while($row = $lab_locations_result->fetch_assoc()) {
    $lab_location_ids[] = $row['location_id'];
}
$lab_location_ids_str = implode(",", $lab_location_ids);

// Process form submission
if(isset($_GET['brand'])) {
    $selected_brand = $conn->real_escape_string($_GET['brand']);
    
    $query = "SELECT a.asset_id, a.asset_name, a.serial_no, a.r_no, a.model, 
                     l.location_name, t.type_name, a.status
              FROM asset a
              JOIN location l ON a.location_id = l.location_id
              JOIN asset_type t ON a.type_id = t.type_id
              WHERE a.brand = '$selected_brand' 
              AND a.location_id IN ($lab_location_ids_str)
              ORDER BY a.asset_name";
    
    $result = $conn->query($query);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Brand Wise Lab Asset Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .print-button { margin: 20px 0; padding: 10px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <h1>Brand Wise Lab Asset Report</h1>
    
    <form method="get" class="no-print">
        <label for="brand">Select Brand:</label>
        <select name="brand" id="brand" required>
            <option value="">-- Select Brand --</option>
            <?php while($brand = $brands_result->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($brand['brand']) ?>" 
                    <?= isset($selected_brand) && $selected_brand == $brand['brand'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($brand['brand']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit">Generate Report</button>
    </form>
    
    <?php if(isset($result) && $result->num_rows > 0): ?>
        <h2>Report for: <?= htmlspecialchars($selected_brand) ?></h2>
        <button onclick="window.print()" class="print-button no-print">Print Report</button>
        
        <table>
            <thead>
                <tr>
                    <th>Asset ID</th>
                    <th>Name</th>
                    <th>Serial No</th>
                    <th>R No</th>
                    <th>Model</th>
                    <th>Location</th>
                    <th>Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['asset_id']) ?></td>
                        <td><?= htmlspecialchars($row['asset_name']) ?></td>
                        <td><?= htmlspecialchars($row['serial_no']) ?></td>
                        <td><?= htmlspecialchars($row['r_no']) ?></td>
                        <td><?= htmlspecialchars($row['model']) ?></td>
                        <td><?= htmlspecialchars($row['location_name']) ?></td>
                        <td><?= htmlspecialchars($row['type_name']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px; font-style: italic;">
            Report generated on: <?= date('Y-m-d H:i:s') ?>
        </div>
    <?php elseif(isset($result)): ?>
        <p>No assets found for the selected brand in lab locations.</p>
    <?php endif; ?>
</body>
</html>