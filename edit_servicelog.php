<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user's name
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

// Check if service_id is provided
if (!isset($_GET['service_id'])) {
    header("Location: manage_servicelog.php");
    exit();
}

$service_id = $_GET['service_id'];

// Fetch service log details
$log = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM service_log WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        header("Location: manage_servicelog.php?error=notfound");
        exit();
    }
} catch (PDOException $e) {
    die("Error fetching service log: " . $e->getMessage());
}

// Fetch assets and service centers for dropdowns
$assets = $pdo->query("SELECT asset_id, asset_name, r_no FROM asset ORDER BY asset_name")->fetchAll();
$centers = $pdo->query("SELECT center_id, center_name FROM service_center ORDER BY center_name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("UPDATE service_log SET 
            asset_id = ?,
            center_id = ?,
            service_date = ?,
            issue_description = ?,
            status = ?,
            warranty_status = ?,
            warranty_covered = ?,
            cost = ?,
            movement_ref = ?,
            return_date = ?,
            diagnosis = ?,
            solution = ?,
            parts_replaced = ?
            WHERE service_id = ?");
        
        $stmt->execute([
            $_POST['asset_id'],
            $_POST['center_id'],
            $_POST['service_date'],
            $_POST['issue_description'],
            $_POST['status'],
            $_POST['warranty_status'],
            $_POST['warranty_covered'],
            $_POST['cost'],
            $_POST['movement_ref'],
            $_POST['return_date'],
            $_POST['diagnosis'],
            $_POST['solution'],
            $_POST['parts_replaced'],
            $service_id
        ]);
        
        header("Location: manage_servicelog.php?updated=1");
        exit();
    } catch (PDOException $e) {
        $error = "Error updating service log: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            background-color: #f8f9fa;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .user-info {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: white;
            padding: 8px 15px;
            border-radius: 30px;
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
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .logout-btn:hover {
            color: var(--accent-color);
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 20px;
            border-radius: 5px 5px 0 0 !important;
        }
        
        .back-btn {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .back-btn:hover {
            color: white;
            background-color: rgba(255,255,255,0.2);
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .required:after {
            content: " *";
            color: var(--accent-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .alert {
            margin-bottom: 20px;
        }
        
        textarea {
            min-height: 100px;
        }
        
        .form-section {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .section-title {
            color: var(--primary-color);
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- User Info Section -->
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

    <div class="container">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <a href="manage_servicelog.php" class="back-btn">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <h2 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Service Log</h2>
                <div style="width: 40px;"></div>
            </div>
            
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-info-circle me-2"></i>Basic Information</h4>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="asset_id" class="form-label required">Asset</label>
                                <select class="form-select" id="asset_id" name="asset_id" required>
                                    <option value="">Select Asset</option>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?= $asset['asset_id'] ?>" <?= $asset['asset_id'] == $log['asset_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($asset['asset_name']) ?> (R.No: <?= htmlspecialchars($asset['r_no']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="center_id" class="form-label required">Service Center</label>
                                <select class="form-select" id="center_id" name="center_id" required>
                                    <option value="">Select Service Center</option>
                                    <?php foreach ($centers as $center): ?>
                                        <option value="<?= $center['center_id'] ?>" <?= $center['center_id'] == $log['center_id'] ? 'selected' : '' ?>><?= htmlspecialchars($center['center_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="service_date" class="form-label required">Service Date</label>
                                <input type="date" class="form-control" id="service_date" name="service_date" value="<?= htmlspecialchars($log['service_date']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="return_date" class="form-label">Return Date</label>
                                <input type="date" class="form-control" id="return_date" name="return_date" value="<?= htmlspecialchars($log['return_date']) ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="status" class="form-label required">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="Open" <?= $log['status'] == 'Open' ? 'selected' : '' ?>>Open</option>
                                    <option value="In Progress" <?= $log['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Completed" <?= $log['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="Waiting for Parts" <?= $log['status'] == 'Waiting for Parts' ? 'selected' : '' ?>>Waiting for Parts</option>
                                    <option value="Cancelled" <?= $log['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="movement_ref" class="form-label">Movement Reference</label>
                                <input type="text" class="form-control" id="movement_ref" name="movement_ref" value="<?= htmlspecialchars($log['movement_ref']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-file-alt me-2"></i>Service Details</h4>
                        <div class="mb-3">
                            <label for="issue_description" class="form-label required">Issue Description</label>
                            <textarea class="form-control" id="issue_description" name="issue_description" rows="3" required><?= htmlspecialchars($log['issue_description']) ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="diagnosis" class="form-label">Technician Diagnosis</label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"><?= htmlspecialchars($log['diagnosis']) ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="solution" class="form-label">Solution Applied</label>
                            <textarea class="form-control" id="solution" name="solution" rows="3"><?= htmlspecialchars($log['solution']) ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="parts_replaced" class="form-label">Parts Replaced</label>
                            <textarea class="form-control" id="parts_replaced" name="parts_replaced" rows="3"><?= htmlspecialchars($log['parts_replaced']) ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-money-bill-wave me-2"></i>Financial Information</h4>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="warranty_status" class="form-label">Warranty Status</label>
                                <select class="form-select" id="warranty_status" name="warranty_status">
                                    <option value="">Select Warranty Status</option>
                                    <option value="In Warranty" <?= $log['warranty_status'] == 'In Warranty' ? 'selected' : '' ?>>In Warranty</option>
                                    <option value="Out of Warranty" <?= $log['warranty_status'] == 'Out of Warranty' ? 'selected' : '' ?>>Out of Warranty</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="warranty_covered" class="form-label">Warranty Coverage</label>
                                <select class="form-select" id="warranty_covered" name="warranty_covered">
                                    <option value="">Select Coverage</option>
                                    <option value="Full" <?= $log['warranty_covered'] == 'Full' ? 'selected' : '' ?>>Full</option>
                                    <option value="Partial" <?= $log['warranty_covered'] == 'Partial' ? 'selected' : '' ?>>Partial</option>
                                    <option value="None" <?= $log['warranty_covered'] == 'None' ? 'selected' : '' ?>>None</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="cost" class="form-label">Cost (₹)</label>
                                <input type="number" step="0.01" class="form-control" id="cost" name="cost" value="<?= htmlspecialchars($log['cost']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-1"></i> Update Service Log
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set today's date as default for return date if status is "Completed"
        document.getElementById('status').addEventListener('change', function() {
            if (this.value === 'Completed' && !document.getElementById('return_date').value) {
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('return_date').value = today;
            }
        });
    </script>
</body>
</html>