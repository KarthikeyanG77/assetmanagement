<?php
include('config.php');
require_once 'header.php';


if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_Ast.php");
    exit();
}

$asset_id = intval($_GET['id']);

// Fetch asset details with type and location names
$query = "SELECT a.*, at.type_name, l.location_name 
          FROM asset a
          JOIN asset_type at ON a.type_id = at.type_id
          JOIN location l ON a.location_id = l.location_id
          WHERE a.asset_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $asset_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$asset = mysqli_fetch_assoc($result);

if (!$asset) {
    header("Location: manage_Ast.php");
    exit();
}

// Function to display field if not empty
function displayField($label, $value) {
    if (!empty($value)) {
        echo "<div class='col-md-6'><p><strong>$label:</strong> " . htmlspecialchars($value) . "</p></div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Asset</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <style>
        .detail-card {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .section-title {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 20px;
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Asset Details</h2>
            <div>
                <a href="edit_Ast.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-primary">Edit</a>
                <a href="manage_Ast.php" class="btn btn-secondary">Back to List</a>
            </div>
        </div>
        
        <div class="detail-card">
            <div class="row mb-3">
                <div class="col-md-6">
                    <h4><?php echo htmlspecialchars($asset['asset_name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($asset['type_name']); ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <span class="badge bg-secondary">ID: <?php echo $asset['asset_id']; ?></span>
                    <span class="status-<?php echo strtolower($asset['status']); ?>">
                        <?php echo htmlspecialchars($asset['status']); ?>
                    </span>
                </div>
            </div>
            
            <h5 class="section-title">Basic Information</h5>
            <div class="row">
                <?php displayField('Serial Number', $asset['serial_no']); ?>
                <?php displayField('R Number', $asset['r_no']); ?>
                <?php displayField('Model', $asset['model']); ?>
                <?php displayField('Brand', $asset['brand']); ?>
                <?php displayField('Location', $asset['location_name']); ?>
                <?php displayField('Current Holder', $asset['current_holder']); ?>
                <?php displayField('Purchase Date', $asset['purchase_date']); ?>
                <?php displayField('Warranty Expiry', $asset['warranty_expiry']); ?>
            </div>
            
            <?php if ($asset['type_id'] == 1 || $asset['type_id'] == 17): ?>
                <h5 class="section-title mt-4">Computer Specifications</h5>
                <div class="row">
                    <?php displayField('Processor', $asset['processor']); ?>
                    <?php displayField('Processor Version', $asset['processor_version']); ?>
                    <?php displayField('RAM Type', $asset['ram_type']); ?>
                    <?php displayField('RAM Frequency', $asset['ram_frequency']); ?>
                    <?php displayField('RAM Size', $asset['ram_size']); ?>
                    <?php displayField('Graphics Card Available', $asset['graphics_card_available']); ?>
                    
                    <?php if ($asset['graphics_card_available'] == 'Yes'): ?>
                        <?php displayField('Graphics Card Brand', $asset['graphics_card_brand']); ?>
                        <?php displayField('Graphics Card Model', $asset['graphics_card_model']); ?>
                        <?php displayField('Graphics Card Size', $asset['graphics_card_size']); ?>
                    <?php endif; ?>
                    
                    <?php displayField('Storage Type', $asset['storage_type']); ?>
                    <?php displayField('Storage Count', $asset['storage_count']); ?>
                    
                    <?php if ($asset['type_id'] == 1): ?>
                        <?php displayField('LAN Status', $asset['lan_status']); ?>
                    <?php endif; ?>
                </div>
            <?php elseif ($asset['type_id'] == 4): ?>
                <h5 class="section-title mt-4">Printer Specifications</h5>
                <div class="row">
                    <?php displayField('Printer Type', $asset['printer_type']); ?>
                    <?php displayField('Connectivity', $asset['connectivity']); ?>
                    <?php displayField('Paper Size', $asset['paper_size']); ?>
                </div>
            <?php elseif ($asset['type_id'] == 19): ?>
                <h5 class="section-title mt-4">Projector Specifications</h5>
                <div class="row">
                    <?php displayField('Projector Type', $asset['projector_type']); ?>
                    <?php displayField('Has Speaker', $asset['has_speaker']); ?>
                    <?php displayField('Lumens', $asset['lumens']); ?>
                    <?php displayField('Resolution', $asset['resolution']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>