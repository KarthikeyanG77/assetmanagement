<?php
require_once 'config.php';
require_once 'session_helper.php';
redirect_if_not_logged_in();

// Set default date range (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Handle form submission for filtering
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'] ?? $start_date;
    $end_date = $_POST['end_date'] ?? $end_date;
    
    // Validate dates
    if (strtotime($start_date) > strtotime($end_date)) {
        $error = "End date must be after start date";
    }
}

// Build the query with filters - joining with asset_type table
$query = "
    SELECT m.*, a.asset_name, at.type_name as asset_type, e.emp_name as mover_name
    FROM movement_log m
    JOIN asset a ON m.asset_id = a.asset_id
    JOIN asset_type at ON a.type_id = at.type_id
    JOIN employee e ON m.moved_by = e.emp_id
    WHERE m.move_date BETWEEN :start_date AND :end_date
    ORDER BY m.move_date DESC, m.move_id DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movement Log Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print, .filter-container, .btn {
                display: none !important;
            }
            body {
                padding: 20px;
                font-size: 12px;
            }
            .table {
                width: 100%;
            }
            .report-header {
                text-align: center;
                margin-bottom: 20px;
            }
            .report-footer {
                text-align: center;
                margin-top: 20px;
                font-size: 10px;
            }
        }
        .container { max-width: 1200px; margin-top: 20px; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .filter-container { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .table-responsive { margin-top: 20px; }
        .report-header { margin-bottom: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Movement Log Report</h3>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <!-- Filter Form -->
                <div class="filter-container no-print">
                    <form method="post" class="row g-3">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?= htmlspecialchars($start_date) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?= htmlspecialchars($end_date) ?>" required>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <button type="button" class="btn btn-success" onclick="window.print()">
                                <i class="fas fa-print"></i> Print Report
                            </button>
                            <a href="export_movement_log.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" 
                               class="btn btn-secondary ms-2">
                                <i class="fas fa-file-excel"></i> Export to Excel
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Report Header (only visible when printing) -->
                <div class="report-header d-none d-print-block">
                    <h2>Movement Log Report</h2>
                    <p>Date Range: <?= date('M d, Y', strtotime($start_date)) ?> to <?= date('M d, Y', strtotime($end_date)) ?></p>
                    <p>Generated on: <?= date('M d, Y H:i:s') ?></p>
                </div>
                
                <!-- Movement Log Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Move ID</th>
                                <th>Date</th>
                                <th>Asset Name</th>
                                <th>Asset Type</th>
                                <th>From Location</th>
                                <th>To Location</th>
                                <th>Moved By</th>
                                <th>Receiver</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($movements)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No movements found for the selected date range</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($movements as $movement): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($movement['move_id']) ?></td>
                                        <td><?= date('M d, Y', strtotime($movement['move_date'])) ?></td>
                                        <td><?= htmlspecialchars($movement['asset_name']) ?></td>
                                        <td><?= htmlspecialchars($movement['asset_type']) ?></td>
                                        <td><?= htmlspecialchars($movement['from_location']) ?></td>
                                        <td><?= htmlspecialchars($movement['to_location']) ?></td>
                                        <td><?= htmlspecialchars($movement['mover_name']) ?></td>
                                        <td><?= htmlspecialchars($movement['receiver_name']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Report Footer (only visible when printing) -->
                <div class="report-footer d-none d-print-block">
                    <p>Page <span id="page-number"></span> - <?= date('Y') ?> &copy; Your Company Name</p>
                </div>
                
                <div class="no-print mt-3">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add page numbers when printing
        window.onbeforeprint = function() {
            var pages = Math.ceil(document.querySelector('tbody').clientHeight / 900);
            document.getElementById('page-number').textContent = '1 of ' + pages;
        };
        
        // Update page numbers during printing (for multi-page reports)
        window.onafterprint = function() {
            var pageNum = 1;
            var contentHeight = document.querySelector('tbody').clientHeight;
            var pageHeight = 900; // approx pixels per page
            
            // This is a simplified approach - for a real solution you might need a PDF library
            if (contentHeight > pageHeight) {
                var totalPages = Math.ceil(contentHeight / pageHeight);
                document.getElementById('page-number').textContent = '1 of ' + totalPages;
            }
        };
    </script>
</body>
</html>