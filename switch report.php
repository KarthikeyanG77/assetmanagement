<?php
session_start();
include('config.php');
require_once 'header.php';


// Define server room and lab location IDs (adjust these based on your actual location IDs)
$server_room_locations = [1, 2, 3]; // Example location IDs for server rooms
$lab_locations = [17, 19, 38]; // Example location IDs for labs

// Get all switches from the database
$query = "SELECT a.*, at.type_name, l.location_name 
          FROM asset a
          JOIN asset_type at ON a.type_id = at.type_id
          JOIN location l ON a.location_id = l.location_id
          WHERE at.type_name LIKE '%switch%' OR at.type_name LIKE '%router%' 
          ORDER BY l.location_name, a.asset_name";

$result = mysqli_query($conn, $query);

// Initialize arrays for categorized switches
$server_room_switches = [];
$lab_switches = [];
$other_switches = [];

while ($switch = mysqli_fetch_assoc($result)) {
    if (in_array($switch['location_id'], $server_room_locations)) {
        $server_room_switches[] = $switch;
    } elseif (in_array($switch['location_id'], $lab_locations)) {
        $lab_switches[] = $switch;
    } else {
        $other_switches[] = $switch;
    }
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'];
    $format = $_POST['format'];
    
    // Generate the appropriate report
    switch ($report_type) {
        case 'server_room':
            $switches = $server_room_switches;
            $title = "Server Room Switches Report";
            break;
        case 'lab':
            $switches = $lab_switches;
            $title = "Lab Switches Report";
            break;
        case 'all':
            $switches = array_merge($server_room_switches, $lab_switches, $other_switches);
            $title = "All Switches Report";
            break;
        default:
            $switches = [];
            $title = "Switch Report";
    }
    
    if ($format == 'pdf') {
        // Generate PDF report
        require_once('tcpdf/tcpdf.php');
        
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('ICT Department');
        $pdf->SetTitle($title);
        $pdf->SetHeaderData('', 0, $title, date('Y-m-d H:i:s'));
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->AddPage();
        
        // Report content
        $html = '<h1>'.$title.'</h1>';
        $html .= '<p>Generated on: '.date('Y-m-d H:i:s').'</p>';
        $html .= '<table border="1" cellpadding="4">
                    <tr>
                        <th>Asset Name</th>
                        <th>Serial No</th>
                        <th>R No</th>
                        <th>Model</th>
                        <th>Brand</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>';
        
        foreach ($switches as $switch) {
            $html .= '<tr>
                        <td>'.$switch['asset_name'].'</td>
                        <td>'.$switch['serial_no'].'</td>
                        <td>'.$switch['r_no'].'</td>
                        <td>'.$switch['model'].'</td>
                        <td>'.$switch['brand'].'</td>
                        <td>'.$switch['location_name'].'</td>
                        <td>'.$switch['status'].'</td>
                    </tr>';
        }
        
        $html .= '</table>';
        $html .= '<p>Total Switches: '.count($switches).'</p>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Output PDF
        $pdf->Output('switch_report_'.date('YmdHis').'.pdf', 'D');
        exit();
        
    } elseif ($format == 'excel') {
        // Generate Excel report
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="switch_report_'.date('YmdHis').'.xls"');
        header('Cache-Control: max-age=0');
        
        echo '<table border="1">
                <tr>
                    <th colspan="7">'.$title.'</th>
                </tr>
                <tr>
                    <th>Generated on:</th>
                    <th colspan="6">'.date('Y-m-d H:i:s').'</th>
                </tr>
                <tr>
                    <th>Asset Name</th>
                    <th>Serial No</th>
                    <th>R No</th>
                    <th>Model</th>
                    <th>Brand</th>
                    <th>Location</th>
                    <th>Status</th>
                </tr>';
        
        foreach ($switches as $switch) {
            echo '<tr>
                    <td>'.$switch['asset_name'].'</td>
                    <td>'.$switch['serial_no'].'</td>
                    <td>'.$switch['r_no'].'</td>
                    <td>'.$switch['model'].'</td>
                    <td>'.$switch['brand'].'</td>
                    <td>'.$switch['location_name'].'</td>
                    <td>'.$switch['status'].'</td>
                </tr>';
        }
        
        echo '<tr>
                <td colspan="6">Total Switches</td>
                <td>'.count($switches).'</td>
            </tr>';
        
        echo '</table>';
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Switch Report Generator</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <style>
        .report-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .switch-card {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .switch-card:hover {
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        .status-inactive {
            color: #dc3545;
            font-weight: bold;
        }
        .status-service {
            color: #ffc107;
            font-weight: bold;
        }
        .status-scrapped {
            color: #6c757d;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-4">Switch Report Generator</h2>
        
        <div class="report-container mb-4">
            <form method="post" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Report Type:</label>
                        <select class="form-select" name="report_type" required>
                            <option value="server_room">Server Room Switches</option>
                            <option value="lab">Lab Switches</option>
                            <option value="all">All Switches</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Output Format:</label>
                        <select class="form-select" name="format" required>
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="generate_report" class="btn btn-primary">Generate Report</button>
            </form>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <h4>Server Room Switches</h4>
                <?php if (count($server_room_switches) > 0): ?>
                    <?php foreach ($server_room_switches as $switch): ?>
                        <div class="switch-card">
                            <h5><?php echo htmlspecialchars($switch['asset_name']); ?></h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Serial No:</strong> <?php echo htmlspecialchars($switch['serial_no']); ?></p>
                                    <p><strong>R No:</strong> <?php echo htmlspecialchars($switch['r_no']); ?></p>
                                    <p><strong>Model:</strong> <?php echo htmlspecialchars($switch['model']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Brand:</strong> <?php echo htmlspecialchars($switch['brand']); ?></p>
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($switch['location_name']); ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="status-<?php echo strtolower($switch['status']); ?>">
                                            <?php echo htmlspecialchars($switch['status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">No switches found in server rooms.</div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-6">
                <h4>Lab Switches</h4>
                <?php if (count($lab_switches) > 0): ?>
                    <?php foreach ($lab_switches as $switch): ?>
                        <div class="switch-card">
                            <h5><?php echo htmlspecialchars($switch['asset_name']); ?></h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Serial No:</strong> <?php echo htmlspecialchars($switch['serial_no']); ?></p>
                                    <p><strong>R No:</strong> <?php echo htmlspecialchars($switch['r_no']); ?></p>
                                    <p><strong>Model:</strong> <?php echo htmlspecialchars($switch['model']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Brand:</strong> <?php echo htmlspecialchars($switch['brand']); ?></p>
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($switch['location_name']); ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="status-<?php echo strtolower($switch['status']); ?>">
                                            <?php echo htmlspecialchars($switch['status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">No switches found in labs.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (count($other_switches) > 0): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <h4>Other Switches</h4>
                    <?php foreach ($other_switches as $switch): ?>
                        <div class="switch-card">
                            <h5><?php echo htmlspecialchars($switch['asset_name']); ?></h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Serial No:</strong> <?php echo htmlspecialchars($switch['serial_no']); ?></p>
                                    <p><strong>R No:</strong> <?php echo htmlspecialchars($switch['r_no']); ?></p>
                                    <p><strong>Model:</strong> <?php echo htmlspecialchars($switch['model']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Brand:</strong> <?php echo htmlspecialchars($switch['brand']); ?></p>
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($switch['location_name']); ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="status-<?php echo strtolower($switch['status']); ?>">
                                            <?php echo htmlspecialchars($switch['status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>