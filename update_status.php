<?php
// Start session and error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ict');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create PDO connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Authentication check
function redirect_if_not_logged_in() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Get current user's name
$currentUserName = "User";
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT emp_name FROM employee WHERE emp_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $currentUserName = $user['emp_name'];
            $_SESSION['username'] = $user['emp_name']; // Store username in session
        }
    } catch (PDOException $e) {
        // Silently fail - we'll just use "User" as default
    }
}

// HTML Header
function render_header($currentUserName) {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Movement Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 1200px; margin-top: 30px; }
        .table-responsive { margin-top: 20px; }
        .action-btns { white-space: nowrap; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-section { margin-bottom: 30px; }
        .asset-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
        .required-field::after { content: " *"; color: red; }
        .whatsapp-btn { background-color: #25D366; border-color: #25D366; }
        .whatsapp-btn:hover { background-color: #128C7E; border-color: #128C7E; }
        .user-info {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-icon {
            width: 40px;
            height: 40px;
            background-color: #0d6efd;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
        }
        body {
            padding-top: 70px;
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="dashboard.php" class="btn btn-secondary back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    
    <!-- User Info Section -->
    <div class="user-info">
        <div class="user-icon">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <span>{$currentUserName}</span>
            <form action="logout.php" method="post" style="display: inline;">
                <button type="submit" class="btn btn-link p-0" style="color: #6c757d;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>
HTML;
}

// HTML Footer
function render_footer() {
    return <<<HTML
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
}

// Generate text receipt (no GD library required)
function generate_receipt($movement_id, $postData, $assetData, $locationData, $username) {
    // Create receipts directory if not exists
    $dir = 'receipts';
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Create text content
    $content = "================================\n";
    $content .= "    ASSET MOVEMENT RECEIPT\n";
    $content .= "================================\n\n";
    $content .= "Movement ID: $movement_id\n";
    $content .= "Date: " . date('Y-m-d H:i:s') . "\n\n";
    
    $content .= "Asset Details:\n";
    $content .= "--------------------------------\n";
    $content .= "ID:          {$assetData['asset_id']}\n";
    $content .= "Serial No:   {$assetData['serial_no']}\n";
    $content .= "R No:        {$assetData['r_no']}\n\n";
    
    $content .= "Movement Information:\n";
    $content .= "--------------------------------\n";
    $content .= "From:        {$assetData['location_name']}\n";
    $content .= "To:          {$locationData['location_name']}\n";
    $content .= "Status:      {$postData['status_after_move']}\n\n";
    
    $content .= "Receiver Details:\n";
    $content .= "--------------------------------\n";
    $content .= "Name:        {$postData['receiver_name']}\n";
    $content .= "Mobile:      {$postData['mobile_number']}\n";
    $content .= "Move Date:   {$postData['move_date']}\n\n";
    
    $content .= "Processed by: $username\n";
    $content .= "================================\n";
    
    // Save to file
    $filename = "receipt_$movement_id.txt";
    $filepath = "$dir/$filename";
    file_put_contents($filepath, $content);
    
    // Generate WhatsApp URL
    $whatsapp_url = "https://wa.me/{$postData['mobile_number']}?text=" . urlencode(
        "Asset Movement Receipt\n\n" .
        "Asset: {$assetData['serial_no']} (R No: {$assetData['r_no']})\n" .
        "From: {$assetData['location_name']}\n" .
        "To: {$locationData['location_name']}\n" .
        "Status: {$postData['status_after_move']}\n" .
        "Receiver: {$postData['receiver_name']}\n" .
        "Mobile: {$postData['mobile_number']}\n" .
        "Date: {$postData['move_date']}\n" .
        "Processed by: $username"
    );
    
    return [
        'filepath' => $filepath,
        'whatsapp_url' => $whatsapp_url
    ];
}

// Main Application Logic
redirect_if_not_logged_in();
$assetData = null;

// Search for asset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_asset'])) {
    $search_term = trim($_POST['search_term']);
    
    if (!empty($search_term)) {
        try {
            $stmt = $pdo->prepare("SELECT a.*, l.location_name 
                                  FROM asset a 
                                  LEFT JOIN location l ON a.location_id = l.location_id
                                  WHERE a.serial_no = :term OR a.r_no = :term");
            $stmt->bindParam(':term', $search_term);
            $stmt->execute();
            
            $assetData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$assetData) {
                $_SESSION['error'] = "No asset found with the provided serial number or r_no.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Please enter a serial number or r_no to search.";
    }
}

// Handle movement log submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_movement'])) {
    try {
        // Validate required fields
        $required = ['asset_id', 'to_location', 'receiver_name', 'mobile_number'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("All required fields must be filled");
            }
        }

        // Validate mobile number
        $mobile_number = preg_replace('/[^0-9]/', '', $_POST['mobile_number']);
        if (!preg_match('/^[0-9]{10,15}$/', $mobile_number)) {
            throw new Exception("Please enter a valid mobile number (10-15 digits)");
        }

        // Get current asset data
        $assetStmt = $pdo->prepare("SELECT a.*, l.location_name 
                                   FROM asset a 
                                   LEFT JOIN location l ON a.location_id = l.location_id
                                   WHERE a.asset_id = :asset_id");
        $assetStmt->bindParam(':asset_id', $_POST['asset_id']);
        $assetStmt->execute();
        $currentAssetData = $assetStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentAssetData) {
            throw new Exception("Asset not found");
        }

        // Get new location data
        $locationStmt = $pdo->prepare("SELECT location_id, location_name FROM location WHERE location_id = :location_id");
        $locationStmt->bindParam(':location_id', $_POST['to_location']);
        $locationStmt->execute();
        $newLocationData = $locationStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$newLocationData) {
            throw new Exception("Selected location not found");
        }

        // Update asset location
        $updateStmt = $pdo->prepare("UPDATE asset SET 
                                    location_id = :location_id,
                                    status = :status_after_move
                                    WHERE asset_id = :asset_id");
        $updateStmt->execute([
            ':location_id' => $newLocationData['location_id'],
            ':status_after_move' => $_POST['status_after_move'],
            ':asset_id' => $_POST['asset_id']
        ]);

        // Log the movement
        $stmt = $pdo->prepare("INSERT INTO movement_log 
                             (asset_id, from_location, to_location, moved_by, 
                              receiver_name, receiver_mobile, move_date, status_before_move, 
                              status_after_move) 
                             VALUES 
                             (:asset_id, :from_location, :to_location, :moved_by, 
                              :receiver_name, :receiver_mobile, :move_date, :status_before_move, 
                              :status_after_move)");
        
        $stmt->execute([
            ':asset_id' => $_POST['asset_id'],
            ':from_location' => $currentAssetData['location_name'],
            ':to_location' => $newLocationData['location_name'],
            ':moved_by' => $_SESSION['user_id'],
            ':receiver_name' => $_POST['receiver_name'],
            ':receiver_mobile' => $mobile_number,
            ':move_date' => $_POST['move_date'],
            ':status_before_move' => $currentAssetData['status'],
            ':status_after_move' => $_POST['status_after_move']
        ]);
        
        $movement_id = $pdo->lastInsertId();
        
        // Generate receipt (text version)
        $receipt = generate_receipt($movement_id, $_POST, $currentAssetData, $newLocationData, $currentUserName);
        
        // Success message with download and WhatsApp options
        $_SESSION['message'] = "Movement logged successfully! 
        <div class='mt-3'>
            <a href='{$receipt['filepath']}' class='btn btn-primary me-2' download>
                <i class='fas fa-download'></i> Download Receipt (TXT)
            </a>
            <a href='{$receipt['whatsapp_url']}' target='_blank' class='btn btn-success whatsapp-btn'>
                <i class='fab fa-whatsapp'></i> Send via WhatsApp
            </a>
        </div>";
        
        // Refresh asset data
        $stmt = $pdo->prepare("SELECT a.*, l.location_name 
                              FROM asset a 
                              LEFT JOIN location l ON a.location_id = l.location_id
                              WHERE a.asset_id = :asset_id");
        $stmt->bindParam(':asset_id', $_POST['asset_id']);
        $stmt->execute();
        $assetData = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error processing movement: " . $e->getMessage();
    }
}

// Fetch all locations
try {
    $locations = $pdo->query("SELECT location_id, location_name FROM location ORDER BY location_name")->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching locations: " . $e->getMessage();
}

// Render the page
echo render_header($currentUserName);
?>
<div class="container">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">Asset Movement Log</h3>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success"><?= $_SESSION['message'] ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Search Form -->
            <div class="card form-section">
                <div class="card-header bg-light">
                    <h4 class="h5">Search Asset</h4>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-9">
                                <label for="search_term" class="form-label">Enter Serial Number or R_No</label>
                                <input type="text" class="form-control" id="search_term" name="search_term" required>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" name="search_asset" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Movement Form -->
            <?php if ($assetData): ?>
            <div class="card form-section">
                <div class="card-header bg-light">
                    <h4 class="h5">Movement Details</h4>
                </div>
                <div class="card-body">
                    <form method="post" id="movementForm">
                        <input type="hidden" name="asset_id" value="<?= $assetData['asset_id'] ?>">
                        
                        <div class="mb-4 asset-info">
                            <h5>Asset Information</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <p><strong>Asset ID:</strong><br><?= htmlspecialchars($assetData['asset_id']) ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>Serial No:</strong><br><?= htmlspecialchars($assetData['serial_no']) ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>R No:</strong><br><?= htmlspecialchars($assetData['r_no']) ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>Current Location:</strong><br>
                                        <?= htmlspecialchars($assetData['location_name'] ?? 'Not Assigned') ?>
                                    </p>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-3">
                                    <p><strong>Status:</strong><br><?= htmlspecialchars($assetData['status']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required-field">New Location</label>
                                <select class="form-select" name="to_location" required>
                                    <option value="">Select Location</option>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?= $loc['location_id'] ?>">
                                            <?= htmlspecialchars($loc['location_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label required-field">Receiver Name</label>
                                <input type="text" class="form-control" name="receiver_name" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label required-field">Mobile Number</label>
                                <input type="tel" class="form-control" name="mobile_number" 
                                       pattern="[0-9]{10,15}" required>
                                <small class="text-muted">10-15 digits only</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label required-field">Move Date</label>
                                <input type="date" class="form-control" name="move_date" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label required-field">New Status</label>
                                <select class="form-select" name="status_after_move" required>
                                    <option value="active">Active</option>
                                    <option value="service">Service</option>
                                    <option value="scrapped">Scrapped</option>
                                </select>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" name="submit_movement" class="btn btn-success">
                                    <i class="fas fa-paper-plane"></i> Submit Movement
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('movementForm')?.addEventListener('submit', function(e) {
    const mobile = this.querySelector('[name="mobile_number"]');
    if (!mobile.value.match(/^[0-9]{10,15}$/)) {
        alert('Please enter a valid 10-15 digit mobile number');
        e.preventDefault();
    }
});
</script>

<?php
echo render_footer();