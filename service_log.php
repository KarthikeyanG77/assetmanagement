<?php
require_once 'config.php';
require_once 'header.php';

if (!isset($_GET['id'])) {
    header("Location: manage_servicelog.php");
    exit();
}

$service_id = $_GET['id'];

// Fetch service log details
$stmt = $pdo->prepare("SELECT * FROM service_log WHERE service_id = ?");
$stmt->execute([$service_id]);
$service_log = $stmt->fetch();

if (!$service_log) {
    header("Location: manage_servicelog.php");
    exit();
}

// Fetch assets and service centers for dropdowns
$assets = $pdo->query("SELECT asset_id, asset_name, r_no FROM asset ORDER BY asset_name")->fetchAll();
$centers = $pdo->query("SELECT center_id, center_name FROM service_center ORDER BY center_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $asset_id = $_POST['asset_id'];
        $center_id = $_POST['center_id'];
        $service_date = $_POST['service_date'];
        $return_date = $_POST['return_date'] ?? null;
        $issue_description = $_POST['issue_description'];
        $remarks = $_POST['remarks'] ?? null;
        
        // Handle file upload
        $pdf_path = $service_log['pdf_path'];
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == UPLOAD_ERR_OK) {
            // Delete old file if exists
            if ($pdf_path && file_exists($pdf_path)) {
                unlink($pdf_path);
            }
            
            $uploadDir = 'uploads/service_logs/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExt = pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'service_' . time() . '.' . $fileExt;
            $pdf_path = $uploadDir . $fileName;
            
            move_uploaded_file($_FILES['pdf_file']['tmp_name'], $pdf_path);
        }

        $stmt = $pdo->prepare("UPDATE service_log SET 
                              asset_id = ?, center_id = ?, service_date = ?, 
                              return_date = ?, issue_description = ?, remarks = ?, 
                              pdf_path = ?
                              WHERE service_id = ?");
        
        $stmt->execute([
            $asset_id, 
            $center_id, 
            $service_date, 
            $return_date, 
            $issue_description, 
            $remarks, 
            $pdf_path,
            $service_id
        ]);

        // Update asset status if return date is set
        if ($return_date) {
            $pdo->prepare("UPDATE asset SET status = 'active' WHERE asset_id = ?")->execute([$asset_id]);
        }
        
        header("Location: manage_servicelog.php?updated=1");
        exit();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
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
    <style>
        .form-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="form-container">
            <h2 class="mb-4">Edit Service Log</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="asset_id" class="form-label required-field">Asset</label>
                        <select class="form-select" id="asset_id" name="asset_id" required>
                            <?php foreach ($assets as $asset): ?>
                                <option value="<?= $asset['asset_id'] ?>" <?= $asset['asset_id'] == $service_log['asset_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($asset['asset_name']) ?> (<?= htmlspecialchars($asset['r_no']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="center_id" class="form-label required-field">Service Center</label>
                        <select class="form-select" id="center_id" name="center_id" required>
                            <?php foreach ($centers as $center): ?>
                                <option value="<?= $center['center_id'] ?>" <?= $center['center_id'] == $service_log['center_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($center['center_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="service_date" class="form-label required-field">Service Date</label>
                        <input type="date" class="form-control" id="service_date" name="service_date" required 
                               value="<?= htmlspecialchars($service_log['service_date']) ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label for="return_date" class="form-label">Return Date</label>
                        <input type="date" class="form-control" id="return_date" name="return_date" 
                               value="<?= htmlspecialchars($service_log['return_date']) ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="issue_description" class="form-label required-field">Issue Description</label>
                    <textarea class="form-control" id="issue_description" name="issue_description" rows="3" required><?= htmlspecialchars($service_log['issue_description']) ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="2"><?= htmlspecialchars($service_log['remarks']) ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Current Document</label>
                    <?php if ($service_log['pdf_path']): ?>
                        <div>
                            <a href="<?= htmlspecialchars($service_log['pdf_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                View Current Document
                            </a>
                            <a href="<?= htmlspecialchars($service_log['pdf_path']) ?>" download class="btn btn-sm btn-outline-secondary">
                                Download
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No document uploaded</p>
                    <?php endif; ?>
                    
                    <label for="pdf_file" class="form-label mt-2">Upload New Document (PDF)</label>
                    <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf">
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">Update Service Log</button>
                    <a href="manage_servicelog.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>