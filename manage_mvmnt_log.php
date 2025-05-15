<?php
require_once 'config.php';
require_once 'session_helper.php';
redirect_if_not_logged_in();

// Initialize variables
$error = '';
$success = '';
$move_id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = !empty($move_id);

// Form handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $asset_id = $_POST['asset_id'];
        $to_location = $_POST['to_location'];
        $receiver_name = $_POST['receiver_name'];
        $move_date = $_POST['move_date'] ?? date('Y-m-d H:i:s');
        $moved_by = $_SESSION['user_id']; // Assuming user is logged in

        // Get current location from asset table
        $stmt = $pdo->prepare("SELECT location FROM asset WHERE asset_id = ?");
        $stmt->execute([$asset_id]);
        $from_location = $stmt->fetchColumn();

        if ($is_edit) {
            // Update existing movement log
            $stmt = $pdo->prepare("
                UPDATE movement_log 
                SET asset_id = ?, from_location = ?, to_location = ?, 
                    receiver_name = ?, move_date = ?, moved_by = ?
                WHERE move_id = ?
            ");
            $stmt->execute([$asset_id, $from_location, $to_location, $receiver_name, $move_date, $moved_by, $move_id]);
            $success = "Movement log updated successfully!";
        } else {
            // Create new movement log
            $stmt = $pdo->prepare("
                INSERT INTO movement_log (asset_id, from_location, to_location, moved_by, receiver_name, move_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$asset_id, $from_location, $to_location, $moved_by, $receiver_name, $move_date]);
            $success = "Movement log created successfully!";

            // Update asset's current location
            $stmt = $pdo->prepare("UPDATE asset SET location = ? WHERE asset_id = ?");
            $stmt->execute([$to_location, $asset_id]);
        }

        // Redirect to avoid form resubmission
        $_SESSION['message'] = $success;
        header("Location: manage_movement_logs.php");
        exit();
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Delete handling
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM movement_log WHERE move_id = ?");
        $stmt->execute([$_GET['delete']]);
        $_SESSION['message'] = "Movement log deleted successfully!";
        header("Location: manage_movement_logs.php");
        exit();
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch data for edit
$movement = [];
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM movement_log WHERE move_id = ?");
    $stmt->execute([$move_id]);
    $movement = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all movement logs
$stmt = $pdo->query("
    SELECT ml.*, a.asset_name, e.emp_name 
    FROM movement_log ml
    JOIN asset a ON ml.asset_id = a.asset_id
    JOIN employee e ON ml.moved_by = e.emp_id
    ORDER BY ml.move_date DESC
");
$movementLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch assets for dropdown
$assets = $pdo->query("SELECT asset_id, asset_name, location FROM asset")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Movement Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 1400px; margin-top: 30px; }
        .table-responsive { margin-top: 20px; }
        .action-btns { white-space: nowrap; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-section { margin-bottom: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div class="card form-section">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0"><?= $is_edit ? 'Edit' : 'Add' ?> Movement Log</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="asset_id" class="form-label">Asset</label>
                            <select class="form-select" id="asset_id" name="asset_id" required>
                                <option value="">Select Asset</option>
                                <?php foreach ($assets as $asset): ?>
                                    <option value="<?= $asset['asset_id'] ?>" 
                                        <?= ($is_edit && $movement['asset_id'] == $asset['asset_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($asset['asset_name']) ?> (<?= htmlspecialchars($asset['location']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="to_location" class="form-label">To Location</label>
                            <input type="text" class="form-control" id="to_location" name="to_location" 
                                   value="<?= $is_edit ? htmlspecialchars($movement['to_location']) : '' ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="receiver_name" class="form-label">Receiver Name</label>
                            <input type="text" class="form-control" id="receiver_name" name="receiver_name" 
                                   value="<?= $is_edit ? htmlspecialchars($movement['receiver_name']) : '' ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="move_date" class="form-label">Move Date</label>
                            <input type="datetime-local" class="form-control" id="move_date" name="move_date" 
                                   value="<?= $is_edit ? date('Y-m-d\TH:i', strtotime($movement['move_date'])) : date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><?= $is_edit ? 'Update' : 'Save' ?></button>
                            <?php if ($is_edit): ?>
                                <a href="manage_movement_logs.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Movement Logs</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark