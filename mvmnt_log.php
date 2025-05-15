<?php
require_once 'config.php';
require_once 'session_helper.php';
redirect_if_not_logged_in();

// Fetch assets and employees for dropdowns
$assets = $pdo->query("SELECT asset_id, asset_name FROM asset ORDER BY asset_name")->fetchAll(PDO::FETCH_ASSOC);
$employees = $pdo->query("SELECT emp_id, emp_name FROM employee ORDER BY emp_name")->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $moveData = [
            'asset_id' => $_POST['asset_id'],
            'from_location' => $_POST['from_location'],
            'to_location' => $_POST['to_location'],
            'moved_by' => $_POST['moved_by'],
            'receiver_name' => $_POST['receiver_name'],
            'move_date' => $_POST['move_date']
        ];

        $stmt = $pdo->prepare("
            INSERT INTO movement_log (
                asset_id, from_location, to_location, 
                moved_by, receiver_name, move_date
            ) VALUES (
                :asset_id, :from_location, :to_location, 
                :moved_by, :receiver_name, :move_date
            )
        ");
        $stmt->execute($moveData);

        // Update asset's current location
        $updateStmt = $pdo->prepare("
            UPDATE asset SET location_id = ? WHERE asset_id = ?
        ");
        $updateStmt->execute([$_POST['to_location'], $_POST['asset_id']]);

        $pdo->commit();
        $_SESSION['message'] = "Movement logged successfully!";
        header("Location: manage_mvmnt_log.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error logging movement: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Movement Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 800px; margin-top: 30px; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Add New Movement Log</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Asset</label>
                                <select class="form-select" name="asset_id" required>
                                    <option value="">Select Asset</option>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?= $asset['asset_id'] ?>">
                                            <?= htmlspecialchars($asset['asset_name']) ?> (ID: <?= $asset['asset_id'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">From Location</label>
                                <input type="text" class="form-control" name="from_location" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">To Location</label>
                                <input type="text" class="form-control" name="to_location" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Moved By</label>
                                <select class="form-select" name="moved_by" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['emp_id'] ?>">
                                            <?= htmlspecialchars($emp['emp_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Receiver Name</label>
                                <input type="text" class="form-control" name="receiver_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Move Date</label>
                                <input type="date" class="form-control" name="move_date" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="manage_mvmnt_log.php" class="btn btn-secondary me-md-2">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Log Movement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>