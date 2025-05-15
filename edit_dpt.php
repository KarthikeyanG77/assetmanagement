<?php
require_once 'config.php';
include('header.php');
include('navbar.php');

if (!isset($_GET['id'])) {
    header("Location: manage_dpt.php");
    exit();
}

// Fetch department data
$stmt = $pdo->prepare("SELECT * FROM dpt WHERE DepartmentID = ?");
$stmt->execute([$_GET['id']]);
$department = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$department) {
    $_SESSION['error'] = "Department not found!";
    header("Location: manage_dpt.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departmentName = trim($_POST['departmentName']);
    
    if (empty($departmentName)) {
        $error = "Department name cannot be empty!";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE dpt SET DepartmentName = ? WHERE DepartmentID = ?");
            $stmt->execute([$departmentName, $_GET['id']]);
            
            $_SESSION['message'] = "Department updated successfully!";
            header("Location: manage_dpt.php");
            exit();
        } catch (PDOException $e) {
            $error = "Error updating department: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Department</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 600px; margin-top: 50px; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Edit Department</h3>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="mb-3">
                        <label for="departmentName" class="form-label">Department Name</label>
                        <input type="text" class="form-control" id="departmentName" name="departmentName" 
                               value="<?= htmlspecialchars($department['DepartmentName']) ?>" required>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="manage_dpt.php" class="btn btn-secondary me-md-2">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>