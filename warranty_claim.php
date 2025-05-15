<?php
session_start();
require_once 'header.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $asset_id = $_POST['asset_id'];
    $claim_date = $_POST['claim_date'];
    $issue_description = $_POST['issue_description'];
    $status = 'Pending';
    $submitted_by = $_SESSION['emp_id'];
    
    $stmt = $conn->prepare("INSERT INTO warranty_claims (asset_id, claim_date, issue_description, status, submitted_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $asset_id, $claim_date, $issue_description, $status, $submitted_by);
    
    if($stmt->execute()) {
        $success = "Warranty claim submitted successfully!";
    } else {
        $error = "Error submitting warranty claim: " . $conn->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warranty Claim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Submit Warranty Claim</h2>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label for="asset_id" class="form-label">Asset</label>
                <select class="form-select" id="asset_id" name="asset_id" required>
                    <option value="">Select Asset</option>
                    <?php
                    $result = $conn->query("SELECT asset_id, asset_name, r_no FROM asset WHERE warranty_expiry >= CURDATE()");
                    while($row = $result->fetch_assoc()) {
                        echo "<option value='{$row['asset_id']}'>{$row['asset_name']} (R.No: {$row['r_no']})</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="claim_date" class="form-label">Claim Date</label>
                <input type="date" class="form-control" id="claim_date" name="claim_date" required>
            </div>
            
            <div class="mb-3">
                <label for="issue_description" class="form-label">Issue Description</label>
                <textarea class="form-control" id="issue_description" name="issue_description" rows="3" required></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Submit Claim</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>