<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user's name and designation
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

// Check admin privileges
$is_admin = in_array($user_designation, ['Lead', 'Sr SA', 'IT_Admin']);
if (!$is_admin) {
    $_SESSION['error_message'] = "You don't have permission to access this page";
    header("Location: manage_ast.php");
    exit();
}

// Fetch asset types
$asset_types = [];
$type_query = "SELECT type_id, type_name FROM asset_type ORDER BY type_name";
$type_result = $conn->query($type_query);
if ($type_result) {
    $asset_types = $type_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch all locations with their categories
$locations = [];
$location_query = "SELECT l.location_id, l.location_name, c.category_name 
                   FROM location l 
                   LEFT JOIN asset_category c ON l.category_id = c.category_id 
                   ORDER BY c.category_name, l.location_name";
$location_result = $conn->query($location_query);
if ($location_result) {
    $locations = $location_result->fetch_all(MYSQLI_ASSOC);
}

// Get asset ID from URL
$asset_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($asset_id <= 0) {
    $_SESSION['error_message'] = "Invalid asset ID";
    header("Location: manage_ast.php");
    exit();
}

// Fetch asset data with ALL fields
$asset = [];
$query = "SELECT * FROM asset WHERE asset_id = ?";
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $asset_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Asset not found";
        header("Location: manage_ast.php");
        exit();
    }
    
    $asset = $result->fetch_assoc();
    $stmt->close();
}

// Get type name for determining which fields to show
$type_name = '';
foreach ($asset_types as $type) {
    if ($type['type_id'] == $asset['type_id']) {
        $type_name = strtolower($type['type_name']);
        break;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token";
        header("Location: edit_ast.php?id=" . $asset_id);
        exit();
    }
    
    // Basic asset information
    $asset_name = trim($_POST['asset_name']);
    $type_id = intval($_POST['type_id']);
    $serial_no = isset($_POST['serial_no']) && trim($_POST['serial_no']) !== '' ? trim($_POST['serial_no']) : NULL;
    $r_no = isset($_POST['r_no']) && trim($_POST['r_no']) !== '' ? trim($_POST['r_no']) : NULL;
    $model = trim($_POST['model'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $location_id = intval($_POST['location_id'] ?? 0);
    $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : NULL;
    $warranty_expiry = !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : NULL;
    $status = trim($_POST['status'] ?? 'active');
    $current_holder = !empty($_POST['current_holder']) ? trim($_POST['current_holder']) : NULL;
    $previous_holder = !empty($_POST['previous_holder']) ? trim($_POST['previous_holder']) : NULL;
    
    // Get all possible fields with proper null handling
    $processor = isset($_POST['processor']) ? trim($_POST['processor']) : NULL;
    $processor_version = isset($_POST['processor_version']) ? trim($_POST['processor_version']) : NULL;
    $ram_type = isset($_POST['ram_type']) ? trim($_POST['ram_type']) : NULL;
    $ram_frequency = isset($_POST['ram_frequency']) ? trim($_POST['ram_frequency']) : NULL;
    $ram_size = isset($_POST['ram_size']) ? trim($_POST['ram_size']) : NULL;
    $storage_type = isset($_POST['storage_type']) ? trim($_POST['storage_type']) : NULL;
    $storage_count = isset($_POST['storage_count']) ? intval($_POST['storage_count']) : NULL;
    $lan_status = isset($_POST['lan_status']) ? trim($_POST['lan_status']) : NULL;
    
    // For computer/laptop specific fields
    $graphics_card_available = isset($_POST['graphics_card_available']) ? trim($_POST['graphics_card_available']) : 'No';
    $graphics_card_brand = isset($_POST['graphics_card_brand']) ? trim($_POST['graphics_card_brand']) : NULL;
    $graphics_card_model = isset($_POST['graphics_card_model']) ? trim($_POST['graphics_card_model']) : NULL;
    $graphics_card_size = isset($_POST['graphics_card_size']) ? trim($_POST['graphics_card_size']) : NULL;
    
    // For interactive panel fields
    $os_type = isset($_POST['os_type']) ? trim($_POST['os_type']) : NULL;
    $os = isset($_POST['os']) ? trim($_POST['os']) : NULL;
    
    // For projector fields
    $projector_type = isset($_POST['projector_type']) ? trim($_POST['projector_type']) : NULL;
    $has_speaker = isset($_POST['has_speaker']) ? trim($_POST['has_speaker']) : 'No';
    $lumens = isset($_POST['lumens']) ? trim($_POST['lumens']) : NULL;
    $resolution = isset($_POST['resolution']) ? trim($_POST['resolution']) : NULL;
    $mount_type = isset($_POST['mount_type']) ? trim($_POST['mount_type']) : NULL;
    $logic_box_status = isset($_POST['logic_box_status']) ? trim($_POST['logic_box_status']) : NULL;
    $cable_type = isset($_POST['cable_type']) ? trim($_POST['cable_type']) : NULL;
    $screen_type = isset($_POST['screen_type']) ? trim($_POST['screen_type']) : NULL;
    $projector_remarks = isset($_POST['projector_remarks']) ? trim($_POST['projector_remarks']) : NULL;
    
    // For printer fields
    $printer_type = isset($_POST['printer_type']) ? trim($_POST['printer_type']) : NULL;
    $connectivity = isset($_POST['connectivity']) ? trim($_POST['connectivity']) : NULL;
    $paper_size = isset($_POST['paper_size']) ? trim($_POST['paper_size']) : NULL;
    
    // Check if serial number or r_no already exists for other assets
    if ($serial_no !== NULL || $r_no !== NULL) {
        $check_query = "SELECT asset_id FROM asset WHERE (serial_no = ? OR r_no = ?) AND asset_id != ?";
        if ($stmt = $conn->prepare($check_query)) {
            $stmt->bind_param("ssi", $serial_no, $r_no, $asset_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $_SESSION['error_message'] = "Serial number or R number already exists for another asset";
                header("Location: edit_ast.php?id=" . $asset_id);
                exit();
            }
            $stmt->close();
        }
    }
    
    // Prepare SQL query based on type
    if (strpos($type_name, 'cpu') !== false || strpos($type_name, 'laptop') !== false) {
        // Computer/Laptop fields
        $sql = "UPDATE asset SET 
                asset_name = ?, type_id = ?, serial_no = ?, r_no = ?, model = ?, brand = ?, 
                location_id = ?, purchase_date = ?, warranty_expiry = ?, status = ?, current_holder = ?, previous_holder = ?,
                processor = ?, processor_version = ?, ram_type = ?, ram_frequency = ?, ram_size = ?, graphics_card_available = ?, 
                graphics_card_brand = ?, graphics_card_model = ?, graphics_card_size = ?, storage_type = ?, storage_count = ?,
                lan_status = ?
                WHERE asset_id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssssssssssssssssssssii", 
            $asset_name, $type_id, $serial_no, $r_no, $model, $brand, 
            $location_id, $purchase_date, $warranty_expiry, $status, $current_holder, $previous_holder,
            $processor, $processor_version, $ram_type, $ram_frequency, $ram_size, $graphics_card_available, 
            $graphics_card_brand, $graphics_card_model, $graphics_card_size, $storage_type, $storage_count,
            $lan_status, $asset_id);
            
    } elseif (strpos($type_name, 'interactive panel') !== false) {
        // Interactive Panel fields
        $sql = "UPDATE asset SET 
                asset_name = ?, type_id = ?, serial_no = ?, r_no = ?, model = ?, brand = ?, 
                location_id = ?, purchase_date = ?, warranty_expiry = ?, status = ?, current_holder = ?, previous_holder = ?,
                processor = ?, processor_version = ?, ram_size = ?, storage_count = ?, os_type = ?, os = ?
                WHERE asset_id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssssssssssssissi", 
            $asset_name, $type_id, $serial_no, $r_no, $model, $brand, 
            $location_id, $purchase_date, $warranty_expiry, $status, $current_holder, $previous_holder,
            $processor, $processor_version, $ram_size, $storage_count, $os_type, $os, $asset_id);
            
    } elseif (strpos($type_name, 'projector') !== false) {
        // Projector fields
        $sql = "UPDATE asset SET 
                asset_name = ?, type_id = ?, serial_no = ?, r_no = ?, model = ?, brand = ?, 
                location_id = ?, purchase_date = ?, warranty_expiry = ?, status = ?, current_holder = ?, previous_holder = ?,
                projector_type = ?, has_speaker = ?, lumens = ?, resolution = ?, mount_type = ?, logic_box_status = ?, 
                cable_type = ?, screen_type = ?, projector_remarks = ?, lan_status = ?
                WHERE asset_id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sissssssssssssssssssssi", 
            $asset_name, $type_id, $serial_no, $r_no, $model, $brand, 
            $location_id, $purchase_date, $warranty_expiry, $status, $current_holder, $previous_holder,
            $projector_type, $has_speaker, $lumens, $resolution, $mount_type, $logic_box_status, 
            $cable_type, $screen_type, $projector_remarks, $lan_status, $asset_id);
            
    } elseif (strpos($type_name, 'printer') !== false) {
        // Printer fields
        $sql = "UPDATE asset SET 
                asset_name = ?, type_id = ?, serial_no = ?, r_no = ?, model = ?, brand = ?, 
                location_id = ?, purchase_date = ?, warranty_expiry = ?, status = ?, current_holder = ?, previous_holder = ?,
                printer_type = ?, connectivity = ?, paper_size = ?
                WHERE asset_id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssssssssssssi", 
            $asset_name, $type_id, $serial_no, $r_no, $model, $brand, 
            $location_id, $purchase_date, $warranty_expiry, $status, $current_holder, $previous_holder,
            $printer_type, $connectivity, $paper_size, $asset_id);
            
    } else {
        // Default for other asset types
        $sql = "UPDATE asset SET 
                asset_name = ?, type_id = ?, serial_no = ?, r_no = ?, model = ?, brand = ?, 
                location_id = ?, purchase_date = ?, warranty_expiry = ?, status = ?, current_holder = ?, previous_holder = ?
                WHERE asset_id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sissssssssssi", 
            $asset_name, $type_id, $serial_no, $r_no, $model, $brand, 
            $location_id, $purchase_date, $warranty_expiry, $status, $current_holder, $previous_holder, $asset_id);
    }
    
    // Execute the query
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Asset updated successfully!";
        header("Location: manage_ast.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating asset: " . $conn->error;
        header("Location: edit_ast.php?id=" . $asset_id);
        exit();
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Asset</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #f8f9fa;
            --accent-color: #e74c3c;
            --text-color: #333;
            --light-text: #777;
            --border-color: #ddd;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-color);
        }
        
        .header-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .college-logo {
            max-height: 80px;
            margin-bottom: 15px;
        }
        
        .form-container {
            max-width: 900px;
            margin: 0 auto 30px;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-section h4 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .form-section h4 i {
            margin-right: 10px;
        }
        
        .required-field::after {
            content: " *";
            color: var(--accent-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            padding: 10px 15px;
            border-radius: 50px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .user-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: var(--light-text);
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .logout-btn:hover {
            color: var(--primary-color);
        }
        
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }
        
        .back-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s;
        }
        
        .back-btn:hover {
            background-color: #5a6268;
            color: white;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .form-control, .form-select {
            border-radius: 5px;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 10px 25px;
            font-weight: 500;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            padding: 10px 25px;
            font-weight: 500;
            border-radius: 5px;
        }
        
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        #graphics-fields, #printer-fields, #projector-fields {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background-color: var(--secondary-color);
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        
        .alert {
            border-radius: 8px;
            padding: 15px 20px;
        }
        
        .computer-fields, .panel-fields, .projector-fields, .printer-fields {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-info {
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- College Logo Header -->
        <div class="header-container">
            <img src="https://rathinamcollege.ac.in/wp-content/uploads/2022/11/RC-logo.png" alt="Rathinam College Logo" class="college-logo">
        </div>
        
        <div class="page-header">
            <div>
                <a href="manage_ast.php" class="btn btn-secondary back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Assets
                </a>
                <h1 class="page-title">Edit Asset</h1>
            </div>
            <div class="user-info">
                <div class="user-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <span><?php echo htmlspecialchars($currentUserName); ?></span>
                    <form action="logout.php" method="post" style="display: inline;">
                        <button type="submit" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="form-container">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form id="assetForm" action="edit_ast.php?id=<?= $asset_id ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
                
                <!-- Basic Information Section -->
                <div class="form-section">
                    <h4><i class="fas fa-info-circle"></i>Basic Information</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="asset_name" class="form-label required-field">Asset Name</label>
                            <input type="text" class="form-control" id="asset_name" name="asset_name" 
                                   value="<?= htmlspecialchars($asset['asset_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="type_id" class="form-label required-field">Asset Type</label>
                            <select class="form-select" id="type_id" name="type_id" required>
                                <option value="">Select Asset Type</option>
                                <?php foreach ($asset_types as $type): ?>
                                    <option value="<?= $type['type_id'] ?>" 
                                        <?= ($type['type_id'] == $asset['type_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['type_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="serial_no" class="form-label">Serial Number</label>
                            <input type="text" class="form-control" id="serial_no" name="serial_no"
                                   value="<?= htmlspecialchars($asset['serial_no'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="r_no" class="form-label">R Number</label>
                            <input type="text" class="form-control" id="r_no" name="r_no"
                                   value="<?= htmlspecialchars($asset['r_no'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" class="form-control" id="model" name="model"
                                   value="<?= htmlspecialchars($asset['model'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="brand" class="form-label">Brand</label>
                            <input type="text" class="form-control" id="brand" name="brand"
                                   value="<?= htmlspecialchars($asset['brand'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="location_id" class="form-label">Location (Category - Location)</label>
                            <select class="form-select" id="location_id" name="location_id">
                                <option value="">Select Location</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= $location['location_id'] ?>"
                                        <?= ($location['location_id'] == $asset['location_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(($location['category_name'] ? $location['category_name'] . ' - ' : '') . $location['location_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="purchase_date" class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date"
                                   value="<?= htmlspecialchars($asset['purchase_date'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="warranty_expiry" class="form-label">Warranty Expiry</label>
                            <input type="date" class="form-control" id="warranty_expiry" name="warranty_expiry"
                                   value="<?= htmlspecialchars($asset['warranty_expiry'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= ($asset['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="service" <?= ($asset['status'] ?? '') === 'service' ? 'selected' : '' ?>>In Service</option>
                                <option value="scrapped" <?= ($asset['status'] ?? '') === 'scrapped' ? 'selected' : '' ?>>Scrapped</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="current_holder" class="form-label">Current Holder</label>
                            <input type="text" class="form-control" id="current_holder" name="current_holder"
                                   value="<?= htmlspecialchars($asset['current_holder'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="previous_holder" class="form-label">Previous Holder</label>
                            <input type="text" class="form-control" id="previous_holder" name="previous_holder"
                                   value="<?= htmlspecialchars($asset['previous_holder'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Computer/Laptop Specific Fields -->
                <div id="computer-fields" class="form-section computer-fields" 
                     style="<?= (strpos($type_name, 'cpu') !== false || strpos($type_name, 'laptop') !== false) ? 'display:block' : 'display:none' ?>">
                    <h4><i class="fas fa-laptop"></i>Computer/Laptop Specifications</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="lan_status" class="form-label">LAN Status</label>
                            <select class="form-select" id="lan_status" name="lan_status">
                                <option value="">Select Status</option>
                                <option value="Working" <?= ($asset['lan_status'] ?? '') === 'Working' ? 'selected' : '' ?>>Working</option>
                                <option value="Not working" <?= ($asset['lan_status'] ?? '') === 'Not working' ? 'selected' : '' ?>>Not working</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="processor" class="form-label">Processor</label>
                            <select class="form-select" id="processor" name="processor">
                                <option value="">Select Processor</option>
                                <option value="Dual core" <?= ($asset['processor'] ?? '') === 'Dual core' ? 'selected' : '' ?>>Dual core</option>
                                <option value="Pentium" <?= ($asset['processor'] ?? '') === 'Pentium' ? 'selected' : '' ?>>Pentium</option>
                                <option value="i3" <?= ($asset['processor'] ?? '') === 'i3' ? 'selected' : '' ?>>i3</option>
                                <option value="i5" <?= ($asset['processor'] ?? '') === 'i5' ? 'selected' : '' ?>>i5</option>
                                <option value="i7" <?= ($asset['processor'] ?? '') === 'i7' ? 'selected' : '' ?>>i7</option>
                                <option value="Rizon 7" <?= ($asset['processor'] ?? '') === 'Rizon 7' ? 'selected' : '' ?>>Rizon 7</option>
                                <option value="Rizon 9" <?= ($asset['processor'] ?? '') === 'Rizon 9' ? 'selected' : '' ?>>Rizon 9</option>
                                <option value="MAC" <?= ($asset['processor'] ?? '') === 'MAC' ? 'selected' : '' ?>>MAC</option>
                                <option value="Other" <?= ($asset['processor'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="processor_version" class="form-label">Processor Version</label>
                            <input type="text" class="form-control" id="processor_version" name="processor_version"
                                   value="<?= htmlspecialchars($asset['processor_version'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="ram_type" class="form-label">RAM Type</label>
                            <select class="form-select" id="ram_type" name="ram_type">
                                <option value="">Select RAM Type</option>
                                <option value="DDR2" <?= ($asset['ram_type'] ?? '') === 'DDR2' ? 'selected' : '' ?>>DDR2</option>
                                <option value="DDR3" <?= ($asset['ram_type'] ?? '') === 'DDR3' ? 'selected' : '' ?>>DDR3</option>
                                <option value="DDR4" <?= ($asset['ram_type'] ?? '') === 'DDR4' ? 'selected' : '' ?>>DDR4</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="ram_frequency" class="form-label">RAM Frequency</label>
                            <input type="text" class="form-control" id="ram_frequency" name="ram_frequency"
                                   value="<?= htmlspecialchars($asset['ram_frequency'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="ram_size" class="form-label">RAM Size (GB)</label>
                            <input type="text" class="form-control" id="ram_size" name="ram_size"
                                   value="<?= htmlspecialchars($asset['ram_size'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="storage_type" class="form-label">Storage Type</label>
                            <select class="form-select" id="storage_type" name="storage_type">
                                <option value="">Select Storage Type</option>
                                <option value="SSD" <?= ($asset['storage_type'] ?? '') === 'SSD' ? 'selected' : '' ?>>SSD</option>
                                <option value="HDD" <?= ($asset['storage_type'] ?? '') === 'HDD' ? 'selected' : '' ?>>HDD</option>
                                <option value="Both" <?= ($asset['storage_type'] ?? '') === 'Both' ? 'selected' : '' ?>>Both</option>
                                <option value="NVM" <?= ($asset['storage_type'] ?? '') === 'NVM' ? 'selected' : '' ?>>NVM</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="storage_count" class="form-label">Storage Count</label>
                            <input type="number" class="form-control" id="storage_count" name="storage_count" min="0"
                                   value="<?= htmlspecialchars($asset['storage_count'] ?? '0') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Graphics Card Available</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="graphics_card_available" id="graphics_yes" value="Yes"
                                           <?= ($asset['graphics_card_available'] ?? 'No') === 'Yes' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="graphics_yes">Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="graphics_card_available" id="graphics_no" value="No"
                                           <?= ($asset['graphics_card_available'] ?? 'No') === 'No' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="graphics_no">No</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="graphics-fields" style="<?= ($asset['graphics_card_available'] ?? 'No') === 'Yes' ? 'display:block' : 'display:none' ?>">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="graphics_card_brand" class="form-label">Graphics Card Brand</label>
                                <input type="text" class="form-control" id="graphics_card_brand" name="graphics_card_brand"
                                       value="<?= htmlspecialchars($asset['graphics_card_brand'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="graphics_card_model" class="form-label">Graphics Card Model</label>
                                <input type="text" class="form-control" id="graphics_card_model" name="graphics_card_model"
                                                                              value="<?= htmlspecialchars($asset['graphics_card_model'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="graphics_card_size" class="form-label">Graphics Card Size (GB)</label>
                                <input type="text" class="form-control" id="graphics_card_size" name="graphics_card_size"
                                       value="<?= htmlspecialchars($asset['graphics_card_size'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interactive Panel Specific Fields -->
                <div id="panel-fields" class="form-section panel-fields" 
                     style="<?= strpos($type_name, 'interactive panel') !== false ? 'display:block' : 'display:none' ?>">
                    <h4><i class="fas fa-tv"></i>Interactive Panel Specifications</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="os_type" class="form-label">OS Type</label>
                            <select class="form-select" id="os_type" name="os_type">
                                <option value="">Select OS Type</option>
                                <option value="Android" <?= ($asset['os_type'] ?? '') === 'Android' ? 'selected' : '' ?>>Android</option>
                                <option value="Windows" <?= ($asset['os_type'] ?? '') === 'Windows' ? 'selected' : '' ?>>Windows</option>
                                <option value="Linux" <?= ($asset['os_type'] ?? '') === 'Linux' ? 'selected' : '' ?>>Linux</option>
                                <option value="Other" <?= ($asset['os_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="os" class="form-label">OS Version</label>
                            <input type="text" class="form-control" id="os" name="os"
                                   value="<?= htmlspecialchars($asset['os'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="processor" class="form-label">Processor</label>
                            <select class="form-select" id="processor" name="processor">
                                <option value="">Select Processor</option>
                                <option value="Dual core" <?= ($asset['processor'] ?? '') === 'Dual core' ? 'selected' : '' ?>>Dual core</option>
                                <option value="Quad core" <?= ($asset['processor'] ?? '') === 'Quad core' ? 'selected' : '' ?>>Quad core</option>
                                <option value="i3" <?= ($asset['processor'] ?? '') === 'i3' ? 'selected' : '' ?>>i3</option>
                                <option value="i5" <?= ($asset['processor'] ?? '') === 'i5' ? 'selected' : '' ?>>i5</option>
                                <option value="i7" <?= ($asset['processor'] ?? '') === 'i7' ? 'selected' : '' ?>>i7</option>
                                <option value="Other" <?= ($asset['processor'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="processor_version" class="form-label">Processor Version</label>
                            <input type="text" class="form-control" id="processor_version" name="processor_version"
                                   value="<?= htmlspecialchars($asset['processor_version'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="ram_size" class="form-label">RAM Size (GB)</label>
                            <input type="text" class="form-control" id="ram_size" name="ram_size"
                                   value="<?= htmlspecialchars($asset['ram_size'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="storage_count" class="form-label">Storage Count</label>
                            <input type="number" class="form-control" id="storage_count" name="storage_count" min="0"
                                   value="<?= htmlspecialchars($asset['storage_count'] ?? '0') ?>">
                        </div>
                    </div>
                </div>

                <!-- Projector Specific Fields -->
                <div id="projector-fields" class="form-section projector-fields" 
                     style="<?= strpos($type_name, 'projector') !== false ? 'display:block' : 'display:none' ?>">
                    <h4><i class="fas fa-project-diagram"></i>Projector Specifications</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="projector_type" class="form-label">Projector Type</label>
                            <select class="form-select" id="projector_type" name="projector_type">
                                <option value="">Select Type</option>
                                <option value="Short Throw" <?= ($asset['projector_type'] ?? '') === 'Short Throw' ? 'selected' : '' ?>>Short Throw</option>
                                <option value="Long Throw" <?= ($asset['projector_type'] ?? '') === 'Long Throw' ? 'selected' : '' ?>>Long Throw</option>
                                <option value="Ultra Short Throw" <?= ($asset['projector_type'] ?? '') === 'Ultra Short Throw' ? 'selected' : '' ?>>Ultra Short Throw</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Has Speaker</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="has_speaker" id="speaker_yes" value="Yes"
                                           <?= ($asset['has_speaker'] ?? 'No') === 'Yes' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="speaker_yes">Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="has_speaker" id="speaker_no" value="No"
                                           <?= ($asset['has_speaker'] ?? 'No') === 'No' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="speaker_no">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="lumens" class="form-label">Lumens</label>
                            <input type="text" class="form-control" id="lumens" name="lumens"
                                   value="<?= htmlspecialchars($asset['lumens'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="resolution" class="form-label">Resolution</label>
                            <input type="text" class="form-control" id="resolution" name="resolution"
                                   value="<?= htmlspecialchars($asset['resolution'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="mount_type" class="form-label">Mount Type</label>
                            <select class="form-select" id="mount_type" name="mount_type">
                                <option value="">Select Mount Type</option>
                                <option value="Ceiling" <?= ($asset['mount_type'] ?? '') === 'Ceiling' ? 'selected' : '' ?>>Ceiling</option>
                                <option value="Wall" <?= ($asset['mount_type'] ?? '') === 'Wall' ? 'selected' : '' ?>>Wall</option>
                                <option value="Portable" <?= ($asset['mount_type'] ?? '') === 'Portable' ? 'selected' : '' ?>>Portable</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="logic_box_status" class="form-label">Logic Box Status</label>
                            <select class="form-select" id="logic_box_status" name="logic_box_status">
                                <option value="">Select Status</option>
                                <option value="Working" <?= ($asset['logic_box_status'] ?? '') === 'Working' ? 'selected' : '' ?>>Working</option>
                                <option value="Not working" <?= ($asset['logic_box_status'] ?? '') === 'Not working' ? 'selected' : '' ?>>Not working</option>
                                <option value="Not applicable" <?= ($asset['logic_box_status'] ?? '') === 'Not applicable' ? 'selected' : '' ?>>Not applicable</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="cable_type" class="form-label">Cable Type</label>
                            <input type="text" class="form-control" id="cable_type" name="cable_type"
                                   value="<?= htmlspecialchars($asset['cable_type'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="screen_type" class="form-label">Screen Type</label>
                            <input type="text" class="form-control" id="screen_type" name="screen_type"
                                   value="<?= htmlspecialchars($asset['screen_type'] ?? '') ?>">
                        </div>
                        <div class="col-md-12">
                            <label for="projector_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="projector_remarks" name="projector_remarks" rows="2"><?= htmlspecialchars($asset['projector_remarks'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="lan_status" class="form-label">LAN Status</label>
                            <select class="form-select" id="lan_status" name="lan_status">
                                <option value="">Select Status</option>
                                <option value="Working" <?= ($asset['lan_status'] ?? '') === 'Working' ? 'selected' : '' ?>>Working</option>
                                <option value="Not working" <?= ($asset['lan_status'] ?? '') === 'Not working' ? 'selected' : '' ?>>Not working</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Printer Specific Fields -->
                <div id="printer-fields" class="form-section printer-fields" 
                     style="<?= strpos($type_name, 'printer') !== false ? 'display:block' : 'display:none' ?>">
                    <h4><i class="fas fa-print"></i>Printer Specifications</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="printer_type" class="form-label">Printer Type</label>
                            <select class="form-select" id="printer_type" name="printer_type">
                                <option value="">Select Type</option>
                                <option value="Laser" <?= ($asset['printer_type'] ?? '') === 'Laser' ? 'selected' : '' ?>>Laser</option>
                                <option value="Inkjet" <?= ($asset['printer_type'] ?? '') === 'Inkjet' ? 'selected' : '' ?>>Inkjet</option>
                                <option value="Dot Matrix" <?= ($asset['printer_type'] ?? '') === 'Dot Matrix' ? 'selected' : '' ?>>Dot Matrix</option>
                                <option value="Thermal" <?= ($asset['printer_type'] ?? '') === 'Thermal' ? 'selected' : '' ?>>Thermal</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="connectivity" class="form-label">Connectivity</label>
                            <select class="form-select" id="connectivity" name="connectivity">
                                <option value="">Select Connectivity</option>
                                <option value="USB" <?= ($asset['connectivity'] ?? '') === 'USB' ? 'selected' : '' ?>>USB</option>
                                <option value="WiFi" <?= ($asset['connectivity'] ?? '') === 'WiFi' ? 'selected' : '' ?>>WiFi</option>
                                <option value="Ethernet" <?= ($asset['connectivity'] ?? '') === 'Ethernet' ? 'selected' : '' ?>>Ethernet</option>
                                <option value="Bluetooth" <?= ($asset['connectivity'] ?? '') === 'Bluetooth' ? 'selected' : '' ?>>Bluetooth</option>
                                <option value="Multiple" <?= ($asset['connectivity'] ?? '') === 'Multiple' ? 'selected' : '' ?>>Multiple</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="paper_size" class="form-label">Paper Size</label>
                            <select class="form-select" id="paper_size" name="paper_size">
                                <option value="">Select Paper Size</option>
                                <option value="A4" <?= ($asset['paper_size'] ?? '') === 'A4' ? 'selected' : '' ?>>A4</option>
                                <option value="A3" <?= ($asset['paper_size'] ?? '') === 'A3' ? 'selected' : '' ?>>A3</option>
                                <option value="Legal" <?= ($asset['paper_size'] ?? '') === 'Legal' ? 'selected' : '' ?>>Legal</option>
                                <option value="Letter" <?= ($asset['paper_size'] ?? '') === 'Letter' ? 'selected' : '' ?>>Letter</option>
                                <option value="Multiple" <?= ($asset['paper_size'] ?? '') === 'Multiple' ? 'selected' : '' ?>>Multiple</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo me-2"></i>Reset
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide fields based on asset type selection
        document.getElementById('type_id').addEventListener('change', function() {
            const selectedType = this.options[this.selectedIndex].text.toLowerCase();
            
            // Hide all specific fields first
            document.querySelectorAll('.computer-fields, .panel-fields, .projector-fields, .printer-fields').forEach(el => {
                el.style.display = 'none';
            });
            
            // Show relevant fields based on type
            if (selectedType.includes('cpu') || selectedType.includes('laptop')) {
                document.getElementById('computer-fields').style.display = 'block';
            } else if (selectedType.includes('interactive panel')) {
                document.getElementById('panel-fields').style.display = 'block';
            } else if (selectedType.includes('projector')) {
                document.getElementById('projector-fields').style.display = 'block';
            } else if (selectedType.includes('printer')) {
                document.getElementById('printer-fields').style.display = 'block';
            }
        });

        // Toggle graphics card fields
        document.querySelectorAll('input[name="graphics_card_available"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('graphics-fields').style.display = 
                    this.value === 'Yes' ? 'block' : 'none';
            });
        });

        // Initialize date inputs with proper format
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('purchase_date').max = today;
            
            // Set warranty expiry min date to purchase date if exists
            const purchaseDate = document.getElementById('purchase_date');
            const warrantyExpiry = document.getElementById('warranty_expiry');
            
            purchaseDate.addEventListener('change', function() {
                warrantyExpiry.min = this.value;
            });
            
            if (purchaseDate.value) {
                warrantyExpiry.min = purchaseDate.value;
            }
        });
    </script>
</body>
</html>