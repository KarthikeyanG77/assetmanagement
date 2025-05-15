<?php
include('header.php');
include('navbar.php');

// Database connection
$db = new PDO('mysql:host=127.0.0.1;dbname=ict', 'root', '');

// Update LAN status function
function updateLanStatus($db, $assetId, $status) {
    $stmt = $db->prepare("UPDATE asset SET lan_status = ? WHERE asset_id = ? AND location_id IN (SELECT location_id FROM location WHERE category_id = 1)");
    return $stmt->execute([$status, $assetId]);
}

// Generate report function
function generateLanReport($db) {
    $stmt = $db->prepare("
        SELECT 
            a.asset_id, a.asset_name, a.serial_no, a.r_no, 
            l.location_name, a.lan_status, a.status AS asset_status
        FROM asset a
        JOIN location l ON a.location_id = l.location_id
        WHERE l.category_id = 1 AND a.lan_status IS NOT NULL
        ORDER BY l.location_name, a.asset_name
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Example usage:
// Update status for asset ID 5
updateLanStatus($db, 5, 'Working');

// Generate and display report
$report = generateLanReport($db);
echo "<h2>Lab Assets LAN Status Report</h2>";
echo "<table border='1'>";
echo "<tr><th>Asset ID</th><th>Name</th><th>Serial No</th><th>R No</th><th>Location</th><th>LAN Status</th><th>Asset Status</th></tr>";

foreach ($report as $row) {
    echo "<tr>";
    echo "<td>".htmlspecialchars($row['asset_id'])."</td>";
    echo "<td>".htmlspecialchars($row['asset_name'])."</td>";
    echo "<td>".htmlspecialchars($row['serial_no'])."</td>";
    echo "<td>".htmlspecialchars($row['r_no'])."</td>";
    echo "<td>".htmlspecialchars($row['location_name'])."</td>";
    echo "<td>".htmlspecialchars($row['lan_status'])."</td>";
    echo "<td>".htmlspecialchars($row['asset_status'])."</td>";
    echo "</tr>";
}

echo "</table>";
?>