<?php
include('config.php');

$serialNo = $_POST['serialNo'] ?? '';
$rNo = $_POST['rNo'] ?? '';

$query = "SELECT a.*, at.type_name, l.location_name, e.emp_name, sc.center_name,
          (SELECT GROUP_CONCAT(CONCAT(ml.move_date, ' - ', ml.from_location, ' to ', ml.to_location) SEPARATOR '<br>') 
           FROM movement_log ml WHERE ml.asset_id = a.asset_id) AS movement_history
          FROM asset a
          JOIN asset_type at ON a.type_id = at.type_id
          JOIN location l ON a.location_id = l.location_id
          LEFT JOIN employee e ON a.current_holder = e.emp_id
          LEFT JOIN service_center sc ON a.status = 'service' AND EXISTS (
              SELECT 1 FROM service_log sl 
              WHERE sl.asset_id = a.asset_id AND sl.center_id = sc.center_id
              ORDER BY sl.service_date DESC LIMIT 1
          )
          WHERE a.serial_no = ? OR a.r_no = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $serialNo, $rNo);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    $asset = mysqli_fetch_assoc($result);
    
    echo '<div class="card">';
    echo '  <div class="card-header">';
    echo '    <h5>Asset Details</h5>';
    echo '  </div>';
    echo '  <div class="card-body">';
    echo '    <div class="row">';
    echo '      <div class="col-md-6">';
    echo '        <p><strong>Asset Name:</strong> '.htmlspecialchars($asset['asset_name']).'</p>';
    echo '        <p><strong>Serial No:</strong> '.htmlspecialchars($asset['serial_no']).'</p>';
    echo '        <p><strong>R No:</strong> '.htmlspecialchars($asset['r_no']).'</p>';
    echo '        <p><strong>Brand:</strong> '.htmlspecialchars($asset['brand']).'</p>';
    echo '      </div>';
    echo '      <div class="col-md-6">';
    echo '        <p><strong>Type:</strong> '.htmlspecialchars($asset['type_name']).'</p>';
    echo '        <p><strong>Location:</strong> '.htmlspecialchars($asset['location_name']).'</p>';
    echo '        <p><strong>Current Holder:</strong> '.htmlspecialchars($asset['emp_name']).'</p>';
    echo '        <p><strong>Status:</strong> '.ucfirst($asset['status']).'</p>';
    echo '      </div>';
    echo '    </div>';
    
    if ($asset['status'] === 'service') {
        echo '<div class="alert alert-warning mt-3">';
        echo '  <p><strong>Currently at Service Center:</strong> '.htmlspecialchars($asset['center_name']).'</p>';
        echo '</div>';
    }
    
    echo '<div class="mt-3">';
    echo '  <h6>Movement History:</h6>';
    echo '  <div class="border p-2">'.($asset['movement_history'] ?: 'No movement history').'</div>';
    echo '</div>';
    
    echo '<div class="mt-3">';
    echo '  <button class="btn btn-primary" onclick="changeStatus('.$asset['asset_id'].')">Change Status</button>';
    echo '  <button class="btn btn-info" onclick="showAssetHistory('.$asset['asset_id'].')">View Full History</button>';
    echo '</div>';
    
    echo '  </div>';
    echo '</div>';
} else {
    echo '<div class="alert alert-danger">No asset found with the provided serial number or R number</div>';
}
?>