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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token";
        header("Location: add_ast.php");
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
    
    // Check if serial number or r_no already exists (if provided)
    if ($serial_no !== NULL || $r_no !== NULL) {
        $check_query = "SELECT asset_id FROM asset WHERE ";
        $conditions = [];
        $params = [];
        $types = '';
        
        if ($serial_no !== NULL) {
            $conditions[] = "serial_no = ?";
            $params[] = $serial_no;
            $types .= 's';
        }
        
        if ($r_no !== NULL) {
            $conditions[] = "r_no = ?";
            $params[] = $r_no;
            $types .= 's';
        }
        
        $check_query .= implode(" OR ", $conditions);
        
        if ($stmt = $conn->prepare($check_query)) {
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $_SESSION['error_message'] = "Serial number or R number already exists";
                header("Location: add_ast.php");
                exit();
            }
            $stmt->close();
        }
    }
    
    // Get type name for determining which fields to process
    $type_name = '';
    foreach ($asset_types as $type) {
        if ($type['type_id'] == $type_id) {
            $type_name = strtolower($type['type_name']);
            break;
        }
    }
    
    // Prepare SQL query based on type
    if (strpos($type_name, 'cpu') !== false || strpos($type_name, 'laptop') !== false) {
        // Computer/Laptop fields
        $processor = trim($_POST['processor'] ?? '');
        $processor_version = trim($_POST['processor_version'] ?? '');
        $ram_type = trim($_POST['ram_type'] ?? '');
        $ram_frequency = trim($_POST['ram_frequency'] ?? '');
        $ram_size = trim($_POST['ram_size'] ?? '');
        $graphics_card_available = trim($_POST['graphics_card_available'] ?? 'No');
        $graphics_card_brand = trim($_POST['graphics_card_brand'] ?? '');
        $graphics_card_model = trim($_POST['graphics_card_model'] ?? '');
        $graphics_card_size = trim($_POST['graphics_card_size'] ?? '');
        $storage_type = trim($_POST['storage_type'] ?? '');
        $storage_count = intval($_POST['storage_count'] ?? 0);
        $lan_status = trim($_POST['lan_status'] ?? '');
        
        $sql = "INSERT INTO asset (asset_name, type_id, serial_no, r_no, model, brand, 
                location_id, purchase_date, warranty_expiry, status, current_holder, previous_holder,
                processor, processor_version, ram_type, ram_frequency, ram_size, graphics_card_available, 
                graphics_card_brand, graphics_card_model, graphics_card_size, storage_type, storage_count,
                lan_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssssssssssssssssssssi", 
            $asset_name, $type_id, $serial_no, $r_no, $model, $brand, 
            $location_id, $purchase_date, $warranty_expiry, $status, $current_holder, $previous_holder,
            $processor, $processor_version, $ram_type, $ram_frequency, $ram_size, $graphics_card_available, 
            $graphics_card_brand, $graphics_card_model, $graphics_card_size, $storage_type, $storage_count,
            $lan_status);
            
    } elseif (strpos($type_name, 'interactive panel') !== false) {
        // Interactive Panel fields
        $processor = trim($_POST['processor'] ?? '');
        $processor_version = trim($_POST['processor_version'] ?? '');
        $ram_size = trim($_POST['ram_size'] ?? '');
        $storage_count = intval($_POST['storage_count'] ?? 0);
        $os_type = trim($_POST['os_type'] ?? '');
        $os = trim($_POST['os'] ?? '');
        
        $sql = "INSERT INTO asset (asset_name, type_id, serial_no, r_no, model, brand, 
                location_id, purchase_date, warranty_expiry, status, current_holder, previous_holder,
                processor, processor_version, ram_size, storage_count, os_type, os) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssssssssssssiss", 
            $asset_name, $type_id, $serial_no, $r_no, $model, $brand, 
            $location_id, $purchase_date, $warranty_expiry, $status, $current_holder, $previous_holder,
            $processor, $processor_version, $ram_size, $storage_count, $os_type, $os);
            
    } elseif (strpos($type_name, 'projector') !== false) {
        // Projector fields
        $projector_type = trim($_POST['projector_type'] ?? '');
        $has_speaker = trim($_POST['has_speaker'] ?? '');
        $lumens = trim($_POST['lumens'] ?? '');
        $resolution = trim($_POST['resolution'] ?? '');
        $mount_type = trim($_POST['mount_type'] ?? '');
        $logic_box_status = trim($_POST['logic_box_status'] ?? '');
        $cable_type = trim($_POST['cable_type'] ?? '');
        $screen_type = trim($_POST['screen_type'] ?? '');
        $projector_remarks = trim($_POST['projector_remarks'] ?? '');
        $lan_status = trim($_POST['lan_status'] ?? '');
        
        $sql = "INSERT INTO asset (asset_name, type_id, serial_no, r_no, model, brand, 
                location_id, purchase_date, warranty_expiry, status, current_holder, previous_holder,
                projector_type, has_speaker, lumens, resolution, mount_type, logic_box_status, 
                cable_type, screen_type, projector_remarks, lan_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sissssssssssssssssssss", 
            $asset_name, $type_id, $serial_no, $r_no, $model, $brand, 
            $location_id, $purchase_date, $warranty_expiry, $status, $current_holder, $previous_holder,
            $projector_type, $has_speaker, $lumens, $resolution, $mount_type, $logic_box_status, 
            $cable_type, $screen_type, $projector_remarks, $lan_status);
            
    } elseif (strpos($type_name, 'printer') !== false) {
        // Printer fields
        $printer_type = trim($_POST['printer_type'] ?? '');
        $connectivity = trim($_POST['connectivity'] ?? '');
        $paper_size = trim($_POST['paper_size'] ?? '');
        
        $sql = "INSERT INTO asset (asset_name, type_id, serial_no, r_no, model, brand, 
                location_id, purchase_date, warranty_expiry, status, current_holder, previous_holder,
                printer_type, connectivity, paper_size) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssssssssssss", 
            $asset_name, $type_id, $serial_no, $r_no, $model, $brand, 
            $location_id, $purchase_date, $warranty_expiry, $status, $current_holder, $previous_holder,
            $printer_type, $connectivity, $paper_size);
            
    } else {
        // Default for other asset types
        $sql = "INSERT INTO asset (asset_name, type_id, serial_no, r_no, model, brand, 
                location_id, purchase_date, warranty_expiry, status, current_holder, previous_holder) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sissssssssss", 
            $asset_name, $type_id, $serial_no, $r_no, $model, $brand, 
            $location_id, $purchase_date, $warranty_expiry, $status, $current_holder, $previous_holder);
    }
    
    // Execute the query
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Asset added successfully!";
        header("Location: manage_ast.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error adding asset: " . $conn->error;
        header("Location: add_ast.php");
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
    <title>Add New Asset</title>
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
                <h1 class="page-title">Add New Asset</h1>
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
            
            <form id="assetForm" action="add_ast.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- Basic Information Section -->
                <div class="form-section">
                    <h4><i class="fas fa-info-circle"></i>Basic Information</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="asset_name" class="form-label required-field">Asset Name</label>
                            <input type="text" class="form-control" id="asset_name" name="asset_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="type_id" class="form-label required-field">Asset Type</label>
                            <select class="form-select" id="type_id" name="type_id" required>
                                <option value="">Select Asset Type</option>
                                <?php foreach ($asset_types as $type): ?>
                                    <option value="<?= $type['type_id'] ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="serial_no" class="form-label">Serial Number</label>
                            <input type="text" class="form-control" id="serial_no" name="serial_no">
                        </div>
                        <div class="col-md-6">
                            <label for="r_no" class="form-label">R Number</label>
                            <input type="text" class="form-control" id="r_no" name="r_no">
                        </div>
                        <div class="col-md-6">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" class="form-control" id="model" name="model">
                        </div>
                        <div class="col-md-6">
                            <label for="brand" class="form-label">Brand</label>
                            <input type="text" class="form-control" id="brand" name="brand">
                        </div>
                        <div class="col-md-6">
                            <label for="location_id" class="form-label">Location (Category - Location)</label>
                            <select class="form-select" id="location_id" name="location_id">
                                <option value="">Select Location</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= $location['location_id'] ?>">
                                        <?= htmlspecialchars(($location['category_name'] ? $location['category_name'] . ' - ' : '') . $location['location_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="purchase_date" class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                        </div>
                        <div class="col-md-6">
                            <label for="warranty_expiry" class="form-label">Warranty Expiry</label>
                            <input type="date" class="form-control" id="warranty_expiry" name="warranty_expiry">
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="service">In Service</option>
                                <option value="scrapped">Scrapped</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="current_holder" class="form-label">Current Holder</label>
                            <input type="text" class="form-control" id="current_holder" name="current_holder">
                        </div>
                        <div class="col-md-6">
                            <label for="previous_holder" class="form-label">Previous Holder</label>
                            <input type="text" class="form-control" id="previous_holder" name="previous_holder">
                        </div>
                    </div>
                </div>
                
                <!-- Computer/Laptop Specific Fields -->
                <div id="computer-fields" class="form-section computer-fields">
                    <h4><i class="fas fa-laptop"></i>Computer/Laptop Specifications</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="lan_status" class="form-label">LAN Status</label>
                            <select class="form-select" id="lan_status" name="lan_status">
                                <option value="">Select Status</option>
                                <option value="Working">Working</option>
                                <option value="Not working">Not working</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="processor" class="form-label">Processor</label>
                            <select class="form-select" id="processor" name="processor">
                                <option value="">Select Processor</option>
                                <option value="Dual core">Dual core</option>
                                <option value="Pentium">Pentium</option>
                                <option value="i3">i3</option>
                                <option value="i5">i5</option>
                                <option value="i7">i7</option>
                                <option value="Rizon 7">Rizon 7</option>
                                <option value="Rizon 9">Rizon 9</option>
                                <option value="MAC">MAC</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="processor_version" class="form-label">Processor Version</label>
                            <input type="text" class="form-control" id="processor_version" name="processor_version">
                        </div>
                        <div class="col-md-6">
                            <label for="ram_type" class="form-label">RAM Type</label>
                            <select class="form-select" id="ram_type" name="ram_type">
                                <option value="">Select RAM Type</option>
                                <option value="DDR2">DDR2</option>
                                <option value="DDR3">DDR3</option>
                                <option value="DDR4">DDR4</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="ram_frequency" class="form-label">RAM Frequency</label>
                            <input type="text" class="form-control" id="ram_frequency" name="ram_frequency">
                        </div>
                        <div class="col-md-6">
                            <label for="ram_size" class="form-label">RAM Size (GB)</label>
                            <input type="text" class="form-control" id="ram_size" name="ram_size">
                        </div>
                        <div class="col-md-6">
                            <label for="storage_type" class="form-label">Storage Type</label>
                            <select class="form-select" id="storage_type" name="storage_type">
                                <option value="">Select Storage Type</option>
                                <option value="SSD">SSD</option>
                                <option value="HDD">HDD</option>
                                <option value="Both">Both</option>
                                <option value="NVM">NVM</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="storage_count" class="form-label">Storage Count</label>
                            <input type="number" class="form-control" id="storage_count" name="storage_count" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Graphics Card Available</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="graphics_card_available" id="graphics_yes" value="Yes">
                                    <label class="form-check-label" for="graphics_yes">Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="graphics_card_available" id="graphics_no" value="No" checked>
                                    <label class="form-check-label" for="graphics_no">No</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="graphics-fields">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="graphics_card_brand" class="form-label">Graphics Card Brand</label>
                                <input type="text" class="form-control" id="graphics_card_brand" name="graphics_card_brand">
                            </div>
                            <div class="col-md-4">
                                <label for="graphics_card_model" class="form-label">Graphics Card Model</label>
                                <input type="text" class="form-control" id="graphics_card_model" name="graphics_card_model">
                            </div>
                            <div class="col-md-4">
                                <label for="graphics_card_size" class="form-label">Graphics Card Size (GB)</label>
                                <input type="text" class="form-control" id="graphics_card_size" name="graphics_card_size">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Interactive Panel Specific Fields -->
                <div id="panel-fields" class="form-section panel-fields">
                    <h4><i class="fas fa-tv"></i>Interactive Panel Specifications</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="processor" class="form-label">Processor</label>
                            <select class="form-select" id="processor" name="processor">
                                <option value="">Select Processor</option>
                                <option value="Dual core">Dual core</option>
                                <option value="Quad core">Quad core</option>
                                <option value="Octa core">Octa core</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="processor_version" class="form-label">Processor Version</label>
                            <input type="text" class="form-control" id="processor_version" name="processor_version">
                        </div>
                        <div class="col-md-6">
                            <label for="ram_size" class="form-label">RAM Size (GB)</label>
                            <input type="text" class="form-control" id="ram_size" name="ram_size">
                        </div>
                        <div class="col-md-6">
                            <label for="storage_count" class="form-label">Storage Count</label>
                            <input type="number" class="form-control" id="storage_count" name="storage_count" min="0">
                        </div>
                        <div class="col-md-6">
                            <label for="os_type" class="form-label">OS Type</label>
                            <select class="form-select" id="os_type" name="os_type">
                                <option value="">Select OS Type</option>
                                <option value="Android">Android</option>
                                <option value="Windows">Windows</option>
                                <option value="Linux">Linux</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="os" class="form-label">OS Version</label>
                            <input type="text" class="form-control" id="os" name="os">
                        </div>
                    </div>
                </div>
                
                <!-- Projector Specific Fields -->
                <div id="projector-fields" class="form-section projector-fields">
                    <h4><i class="fas fa-video"></i>Projector Specifications</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="lan_status" class="form-label">LAN Status</label>
                            <select class="form-select" id="lan_status" name="lan_status">
                                <option value="">Select Status</option>
                                <option value="Working">Working</option>
                                <option value="Not working">Not working</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="projector_type" class="form-label">Projector Type</label>
                            <select class="form-select" id="projector_type" name="projector_type">
                                <option value="">Select Type</option>
                                <option value="Short Throw">Short Throw</option>
                                <option value="Standard">Standard</option>
                                <option value="Long Throw">Long Throw</option>
                                <option value="Ultra Short Throw">Ultra Short Throw</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="has_speaker" class="form-label">Has Speaker</label>
                            <select class="form-select" id="has_speaker" name="has_speaker">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="lumens" class="form-label">Lumens</label>
                            <input type="text" class="form-control" id="lumens" name="lumens">
                        </div>
                        <div class="col-md-6">
                            <label for="resolution" class="form-label">Resolution</label>
                            <input type="text" class="form-control" id="resolution" name="resolution">
                        </div>
                        <div class="col-md-6">
                            <label for="mount_type" class="form-label">Mount Type</label>
                            <select class="form-select" id="mount_type" name="mount_type">
                                <option value="">Select Mount Type</option>
                                <option value="Short Throw">Short Throw</option>
                                <option value="Long Throw">Long Throw</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="logic_box_status" class="form-label">Logic Box Status</label>
                            <select class="form-select" id="logic_box_status" name="logic_box_status">
                                <option value="">Select Status</option>
                                <option value="Working">Working</option>
                                <option value="Not working">Not working</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="cable_type" class="form-label">Cable Type</label>
				                            <select class="form-select" id="cable_type" name="cable_type">
                                <option value="">Select Cable Type</option>
                                <option value="HDMI">HDMI</option>
                                <option value="VGA">VGA</option>
                                <option value="Both">Both</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="screen_type" class="form-label">Screen Type</label>
                            <select class="form-select" id="screen_type" name="screen_type">
                                <option value="">Select Screen Type</option>
                                <option value="Manual">Manual</option>
                                <option value="Motorized">Motorized</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="projector_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="projector_remarks" name="projector_remarks" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Printer Specific Fields -->
                <div id="printer-fields" class="form-section printer-fields">
                    <h4><i class="fas fa-print"></i>Printer Specifications</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="printer_type" class="form-label">Printer Type</label>
                            <select class="form-select" id="printer_type" name="printer_type">
                                <option value="">Select Printer Type</option>
                                <option value="Laser">Laser</option>
                                <option value="Inkjet">Inkjet</option>
                                <option value="Dot Matrix">Dot Matrix</option>
                                <option value="Thermal">Thermal</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="connectivity" class="form-label">Connectivity</label>
                            <select class="form-select" id="connectivity" name="connectivity">
                                <option value="">Select Connectivity</option>
                                <option value="USB">USB</option>
                                <option value="WiFi">WiFi</option>
                                <option value="Ethernet">Ethernet</option>
                                <option value="All">All</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="paper_size" class="form-label">Paper Size</label>
                            <select class="form-select" id="paper_size" name="paper_size">
                                <option value="">Select Paper Size</option>
                                <option value="A4">A4</option>
                                <option value="A3">A3</option>
                                <option value="Legal">Legal</option>
                                <option value="Letter">Letter</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Asset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Show/hide fields based on asset type selection
            $('#type_id').change(function() {
                var selectedType = $(this).find('option:selected').text().toLowerCase();
                
                // Hide all specific fields first
                $('.computer-fields, .panel-fields, .projector-fields, .printer-fields').hide();
                
                // Show relevant fields based on type
                if (selectedType.includes('cpu') || selectedType.includes('laptop')) {
                    $('#computer-fields').show();
                } else if (selectedType.includes('interactive panel')) {
                    $('#panel-fields').show();
                } else if (selectedType.includes('projector')) {
                    $('#projector-fields').show();
                } else if (selectedType.includes('printer')) {
                    $('#printer-fields').show();
                }
            });
            
            // Show/hide graphics card fields based on selection
            $('input[name="graphics_card_available"]').change(function() {
                if ($(this).val() === 'Yes') {
                    $('#graphics-fields').show();
                } else {
                    $('#graphics-fields').hide();
                }
            });
            
            // Initialize the form based on current selections
            $('#type_id').trigger('change');
            $('input[name="graphics_card_available"][value="No"]').prop('checked', true).trigger('change');
            
            // Form validation
            $('#assetForm').submit(function(e) {
                var assetName = $('#asset_name').val();
                var typeId = $('#type_id').val();
                
                if (!assetName || !typeId) {
                    e.preventDefault();
                    alert('Please fill in all required fields (marked with *)');
                    return false;
                }
                return true;
            });
        });
    </script>
</body>
</html>