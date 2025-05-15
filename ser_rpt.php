<?php
require_once 'config.php';

// Start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user details
$currentUserName = "User";
$user_id = $_SESSION['user_id'];
$query = "SELECT emp_name FROM employee WHERE emp_id = ?";
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $currentUserName = $user['emp_name'];
    }
    $stmt->close();
}

// Fetch asset types for dropdown
$assetTypes = $pdo->query("SELECT type_id, type_name FROM asset_type ORDER BY type_name")->fetchAll(PDO::FETCH_ASSOC);

// Initialize variables
$selectedAssetType = '';
$reportData = [];
$error = '';

// Handle form submission - MODIFIED TO ONLY SHOW SERVICE STATUS ASSETS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $selectedAssetType = $_POST['type_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.asset_id,
                a.asset_name,
                a.serial_no,
                a.r_no,
                a.brand,
                a.status,
                at.type_name,
                COUNT(sl.service_id) as service_count,
                MAX(sl.service_date) as last_service_date
            FROM 
                asset a
            JOIN 
                asset_type at ON a.type_id = at.type_id
            LEFT JOIN 
                service_log sl ON a.asset_id = sl.asset_id
            WHERE 
                a.type_id = ? AND a.status = 'service'  -- ONLY SHOW SERVICE STATUS
            GROUP BY 
                a.asset_id
            ORDER BY 
                a.asset_name
        ");
        $stmt->execute([$selectedAssetType]);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($reportData)) {
            $error = "No assets in service status found for the selected product type.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Assets Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            padding-top: 20px;
            background-color: #f8f9fa;
        }
        .container { 
            max-width: 1400px; 
            margin-top: 60px;
        }
        .card { 
            border-radius: 10px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            border-top: 4px solid #ffc107; /* Yellow for service status */
        }
        .user-info {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 10px 15px;
            border-radius: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-service { 
            background-color: #fff3cd; 
            color: #856404;
        }
        .clickable-row { cursor: pointer; }
        .clickable-row:hover { background-color: rgba(255, 193, 7, 0.1); }
    </style>
</head>
<body>
    <!-- User Info Section -->
    <div class="user-info d-flex align-items-center">
        <div class="me-2">
            <i class="fas fa-user-circle me-1"></i>
            <?= htmlspecialchars($currentUserName) ?>
        </div>
        <a href="logout.php" class="text-danger ms-2">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary mb-3">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>

        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h3 class="mb-0"><i class="fas fa-tools me-2"></i>Assets In Service Report</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-warning alert-dismissible fade show">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="post" class="mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="type_id" class="form-label">Select Product Type</label>
                            <select class="form-select" id="type_id" name="type_id" required>
                                <option value="">-- Select Product Type --</option>
                                <?php foreach ($assetTypes as $type): ?>
                                    <option value="<?= $type['type_id'] ?>" 
                                        <?= $selectedAssetType == $type['type_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['type_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="generate_report" class="btn btn-warning w-100 text-white">
                                <i class="fas fa-filter me-2"></i> Filter Service Assets
                            </button>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($reportData)): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded">
                        <div>
                            <h4 class="mb-1">
                                <i class="fas fa-tools me-2"></i> 
                                <?= htmlspecialchars($reportData[0]['type_name']) ?> in Service
                            </h4>
                            <small class="text-muted">
                                Showing <?= count($reportData) ?> assets needing service
                            </small>
                        </div>
                        <div>
                            <button onclick="window.print()" class="btn btn-sm btn-success me-2">
                                <i class="fas fa-print me-1"></i> Print
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-warning">
                                <tr>
                                    <th>#</th>
                                    <th>Asset Name</th>
                                    <th>Serial No</th>
                                    <th>R No</th>
                                    <th>Brand</th>
                                    <th>Service Count</th>
                                    <th>Last Service</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $index => $row): ?>
                                    <tr class="clickable-row" onclick="window.location='asset_details.php?id=<?= $row['asset_id'] ?>'">
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($row['asset_name']) ?></td>
                                        <td><?= $row['serial_no'] ? htmlspecialchars($row['serial_no']) : 'N/A' ?></td>
                                        <td><?= $row['r_no'] ? htmlspecialchars($row['r_no']) : 'N/A' ?></td>
                                        <td><?= htmlspecialchars($row['brand']) ?></td>
                                        <td class="text-center"><?= $row['service_count'] ?></td>
                                        <td><?= $row['last_service_date'] ? date('M j, Y', strtotime($row['last_service_date'])) : 'Never' ?></td>
                                        <td>
                                            <a href="service_log.php?asset_id=<?= $row['asset_id'] ?>" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-tools me-1"></i> Service Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>