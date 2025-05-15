<?php
session_start();
require_once 'config.php';
require_once 'session_helper.php';
redirect_if_not_logged_in();
require_once 'header.php';

if (!isset($_GET['id'])) {
    header("Location: manage_servicelog.php");
    exit;
}

$serviceId = (int)$_GET['id'];

// Fetch service log data with asset and center info
$stmt = $pdo->prepare("
    SELECT sl.*, a.asset_name, sc.center_name 
    FROM service_log sl
    LEFT JOIN asset a ON sl.asset_id = a.asset_id
    LEFT JOIN service_center sc ON sl.center_id = sc.center_id
    WHERE sl.service_id = ?
");
$stmt->execute([$serviceId]);
$serviceLog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$serviceLog) {
    header("Location: manage_servicelog.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $updateData = [
            'return_date' => $_POST['return_date'] ?: null,
            'remarks' => $_POST['remarks'] ?: null,
            'service_id' => $serviceId
        ];

        // Handle PDF upload
        if (!empty($_FILES['pdf_file']['name'])) {
            $uploadDir = 'uploads/service_logs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = 'service_' . $serviceId . '_' . time() . '.pdf';
            $targetFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetFile)) {
                $updateData['pdf_path'] = $targetFile;
            } else {
                throw new Exception("Failed to upload PDF file");
            }
        }

        $stmt = $pdo->prepare("
            UPDATE service_log 
            SET return_date = :return_date, 
                remarks = :remarks, 
                pdf_path = COALESCE(:pdf_path, pdf_path) 
            WHERE service_id = :service_id
        ");
        $stmt->execute($updateData);

        $pdo->commit();
        $_SESSION['message'] = "Service log updated successfully!";
        header("Location: manage_servicelog.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating service log: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Service Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 800px; margin-top: 30px; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-label { font-weight: 500; }
        .log-details { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Update Service Log (ID: <?= htmlspecialchars($serviceId) ?>)</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="log-details mb-4">
                    <h5>Service Details</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Asset:</strong> <?= htmlspecialchars($serviceLog['asset_name']) ?></p>
                            <p><strong>Service Center:</strong> <?= htmlspecialchars($serviceLog['center_name']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Service Date:</strong> <?= htmlspecialchars($serviceLog['service_date']) ?></p>
                            <p><strong>Acknowledged By:</strong> <?= htmlspecialchars($serviceLog['acknowledged_by']) ?></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p><strong>Issue Description:</strong></p>
                        <div class="border p-2"><?= nl2br(htmlspecialchars($serviceLog['issue_description'])) ?></div>
                    </div>
                </div>

                <form method="post" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Return Date</label>
                                <input type="date" class="form-control" name="return_date" 
                                       value="<?= htmlspecialchars($serviceLog['return_date']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Service Report (PDF)</label>
                                <input type="file" class="form-control" name="pdf_file" accept=".pdf">
                                <?php if ($serviceLog['pdf_path']): ?>
                                    <small class="text-muted">Current file: 
                                        <a href="<?= htmlspecialchars($serviceLog['pdf_path']) ?>" target="_blank">View PDF</a>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" rows="4"><?= htmlspecialchars($serviceLog['remarks']) ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="manage_servicelog.php" class="btn btn-secondary me-md-2">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Log
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>