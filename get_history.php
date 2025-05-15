<?php
include('config.php');
include('header.php');
include('navbar.php');

$assetId = intval($_POST['assetId']);
$type = $_POST['type'];

switch ($type) {
    case 'movement':
        $query = "SELECT ml.*, e.emp_name 
                  FROM movement_log ml
                  LEFT JOIN employee e ON ml.moved_by = e.emp_id
                  WHERE ml.asset_id = $assetId
                  ORDER BY ml.move_date DESC";
        $result = mysqli_query($conn, $query);
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<tr>';
            echo '<td>'.htmlspecialchars($row['move_date']).'</td>';
            echo '<td>'.htmlspecialchars($row['from_location']).'</td>';
            echo '<td>'.htmlspecialchars($row['to_location']).'</td>';
            echo '<td>'.htmlspecialchars($row['emp_name']).'</td>';
            echo '<td>'.htmlspecialchars($row['receiver_name']).'</td>';
            echo '<td>'.ucfirst($row['status_before_move']).'</td>';
            echo '<td>'.ucfirst($row['status_after_move']).'</td>';
            echo '</tr>';
        }
        break;
        
    case 'service':
        $query = "SELECT sl.*, sc.center_name 
                  FROM service_log sl
                  JOIN service_center sc ON sl.center_id = sc.center_id
                  WHERE sl.asset_id = $assetId
                  ORDER BY sl.service_date DESC";
        $result = mysqli_query($conn, $query);
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<tr>';
            echo '<td>'.htmlspecialchars($row['center_name']).'</td>';
            echo '<td>'.htmlspecialchars($row['service_date']).'</td>';
            echo '<td>'.htmlspecialchars($row['return_date']).'</td>';
            echo '<td>'.htmlspecialchars($row['issue_description']).'</td>';
            echo '<td>'.htmlspecialchars($row['acknowledged_by']).'<br>'.htmlspecialchars($row['acknowledged_date']).'</td>';
            echo '<td>'.($row['pdf_path'] ? '<a href="'.$row['pdf_path'].'" target="_blank">View PDF</a>' : 'N/A').'</td>';
            echo '</tr>';
        }
        break;
        
    case 'scrap':
        $query = "SELECT * FROM scrap_log WHERE asset_id = $assetId ORDER BY scrap_date DESC";
        $result = mysqli_query($conn, $query);
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<tr>';
            echo '<td>'.htmlspecialchars($row['scrap_date']).'</td>';
            echo '<td>'.htmlspecialchars($row['collected_by']).'</td>';
            echo '<td>'.htmlspecialchars($row['reason']).'</td>';
            echo '</tr>';
        }
        break;
}
?>