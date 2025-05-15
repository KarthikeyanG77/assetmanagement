<?php
require_once 'config.php';
require_once 'auth_check.php';

// Define which designations can approve requests
$approver_designations = ['Lead', 'Sr SA', 'SA', 'IT_Admin', 'Lab incharge', 'FI'];

// Check if user has permission to approve requests
if (!in_array($user_designation, $approver_designations)) {
    header("Location: dashboard.php");
    exit();
}

// Get current user's name if not already set in auth_check.php
if (!isset($user_name)) {
    $query = "SELECT emp_name FROM employee WHERE emp_id = ?";
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_name = $user['emp_name'];
        } else {
            $user_name = "User";
        }
        $stmt->close();
    }
}

// Define which designations should see dashboard_fi.php
$fi_dashboard_designations = ['HOD', 'FI', 'Users'];
$dashboard_link = in_array($user_designation, $fi_dashboard_designations) ? 'dashboard_fi.php' : 'dashboard.php';

// Process approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    // Validate action
    if (!in_array($action, ['approve', 'reject'])) {
        $error = "Invalid action specified!";
    } else {
        try {
            $conn->begin_transaction();
            
            // Get request details
            $sql = "SELECT * FROM lab_request WHERE request_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$request) {
                throw new Exception("Request not found!");
            }
            
            if ($action == 'approve') {
                // Calculate day of week from from_date
                $day_of_week = date('l', strtotime($request['from_date']));
                $time_slot = $request['from_time'] . ' - ' . $request['to_time'];
                
                // Check for conflicts
                $conflict_sql = "SELECT * FROM lab_schedule 
                                WHERE lab_id = ? AND day_of_week = ? AND time_slot = ?";
                $stmt = $conn->prepare($conflict_sql);
                $stmt->bind_param("iss", $request['lab_id'], $day_of_week, $time_slot);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("There is already a scheduled class at this time slot!");
                }
                $stmt->close();
                
                // Add to schedule
                $insert_sql = "INSERT INTO lab_schedule (lab_id, day_of_week, time_slot, subject_code, 
                              subject_name, faculty_name, faculty_email, class_group, semester, 
                              academic_year, created_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("isssssssssi", 
                    $request['lab_id'], 
                    $day_of_week, 
                    $time_slot, 
                    $request['subject_code'], 
                    $request['subject_name'], 
                    $request['faculty_name'], 
                    $request['faculty_email'], 
                    $request['class_group'], 
                    $request['semester'], 
                    $request['academic_year'], 
                    $user_id
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Error approving request: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // Update request status (for both approve and reject)
            $status = ($action == 'approve') ? 'Approved' : 'Rejected';
            $update_sql = "UPDATE lab_request SET status = ?, 
                          processed_by = ?, processed_date = NOW(), rejection_reason = ? 
                          WHERE request_id = ?";
            
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sisi", $status, $user_id, $rejection_reason, $request_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating request status: " . $stmt->error);
            }
            
            $conn->commit();
            $success = "Request {$status} successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Get pending requests with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 5;
$offset = ($page - 1) * $per_page;

$requests = [];
$total_requests = 0;

$sql = "SELECT SQL_CALC_FOUND_ROWS r.*, l.location_name 
        FROM lab_request r
        JOIN location l ON r.lab_id = l.location_id
        WHERE r.status = 'Pending'
        ORDER BY r.request_date
        LIMIT ?, ?";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offset, $per_page);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

$total_result = $conn->query("SELECT FOUND_ROWS()");
$total_requests = $total_result->fetch_row()[0];
$total_pages = ceil($total_requests / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Lab Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --dark-color: #5a5c69;
        }
        body {
            background-color: #f8f9fc;
            padding-top: 20px;
            position: relative;
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(33, 40, 50, 0.15);
            margin-bottom: 24px;
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.35rem;
            border-radius: 15px 15px 0 0 !important;
        }
        .request-card {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .request-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1.5rem 0 rgba(0, 0, 0, 0.1);
        }
        .badge-pending {
            background-color: var(--warning-color);
            color: #000;
        }
        .badge-approved {
            background-color: var(--success-color);
            color: #fff;
        }
        .badge-rejected {
            background-color: var(--danger-color);
            color: #fff;
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
            z-index: 1000;
        }
        .user-icon {
            width: 40px;
            height: 40px;
            background-color: #3498db;
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
            padding: 0;
            margin-left: 10px;
        }
        .logout-btn:hover {
            color: #e74c3c;
        }
        .user-name {
            font-weight: 500;
        }
        .action-form {
            background: #f8f9fc;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e3e6f0;
        }
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .pagination .page-link {
            color: var(--primary-color);
        }
        .btn-approve {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        .btn-reject {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        .info-label {
            font-weight: 600;
            color: var(--dark-color);
            width: 140px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- User Info Section -->
    <div class="user-info">
        <div class="user-icon">
            <i class="fas fa-user"></i>
        </div>
        <div class="user-name"><?php echo htmlspecialchars($user_name ?? 'User'); ?></div>
        <form action="logout.php" method="post">
            <button type="submit" class="logout-btn" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </button>
        </form>
    </div>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Approve Lab Requests</h2>
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <span class="badge bg-primary"><?php echo htmlspecialchars($user_designation); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center">
                <i class="fas fa-check-circle me-2"></i>
                <div><?php echo $success; ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center">
                <i class="fas fa-exclamation-circle me-2"></i>
                <div><?php echo $error; ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Pending Requests</h5>
                <span class="badge bg-primary"><?php echo $total_requests; ?> pending</span>
            </div>
            <div class="card-body">
                <?php if (empty($requests)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h4>No Pending Requests</h4>
                        <p class="text-muted">All lab requests have been processed.</p>
                        <a href="<?php echo $dashboard_link; ?>" class="btn btn-primary mt-3">
                            <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <div class="card request-card mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h4 class="mb-0">
                                                <i class="fas fa-flask me-2 text-primary"></i>
                                                Request #<?php echo $request['request_id']; ?>
                                            </h4>
                                            <span class="badge badge-pending">Pending</span>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <p class="mb-2"><span class="info-label">Lab:</span> <?php echo htmlspecialchars($request['location_name']); ?></p>
                                                <p class="mb-2"><span class="info-label">Dates:</span> 
                                                    <?php echo date('M j, Y', strtotime($request['from_date'])); ?> to 
                                                    <?php echo date('M j, Y', strtotime($request['to_date'])); ?>
                                                </p>
                                                <p class="mb-2"><span class="info-label">Time Slot:</span> 
                                                    <?php echo date('h:i A', strtotime($request['from_time'])); ?> - 
                                                    <?php echo date('h:i A', strtotime($request['to_time'])); ?>
                                                </p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-2"><span class="info-label">Subject:</span> 
                                                    <?php echo htmlspecialchars($request['subject_code']); ?> - 
                                                    <?php echo htmlspecialchars($request['subject_name']); ?>
                                                </p>
                                                <p class="mb-2"><span class="info-label">Faculty:</span> 
                                                    <?php echo htmlspecialchars($request['faculty_name']); ?>
                                                </p>
                                                <p class="mb-2"><span class="info-label">Email:</span> 
                                                    <?php echo htmlspecialchars($request['faculty_email']); ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p class="mb-2"><span class="info-label">Class Group:</span> <?php echo htmlspecialchars($request['class_group']); ?></p>
                                                <p class="mb-2"><span class="info-label">Semester:</span> <?php echo htmlspecialchars($request['semester']); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-2"><span class="info-label">Academic Year:</span> <?php echo htmlspecialchars($request['academic_year']); ?></p>
                                                <p class="mb-2"><span class="info-label">Requested On:</span> <?php echo date('M j, Y h:i A', strtotime($request['request_date'])); ?></p>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($request['purpose'])): ?>
                                            <div class="mt-3">
                                                <p class="mb-1"><span class="info-label">Purpose:</span></p>
                                                <div class="p-3 bg-light rounded">
                                                    <?php echo nl2br(htmlspecialchars($request['purpose'])); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <form method="post" class="action-form">
                                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label for="rejection_reason_<?php echo $request['request_id']; ?>" class="form-label">
                                                    <i class="fas fa-comment me-1"></i> Rejection Reason (if applicable)
                                                </label>
                                                <textarea class="form-control" id="rejection_reason_<?php echo $request['request_id']; ?>" 
                                                          name="rejection_reason" rows="3" placeholder="Optional reason for rejection..."></textarea>
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                <button type="submit" name="action" value="approve" class="btn btn-approve btn-lg">
                                                    <i class="fas fa-check-circle me-1"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject" class="btn btn-reject btn-lg">
                                                    <i class="fas fa-times-circle me-1"></i> Reject
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Confirm before action
        document.querySelectorAll('form.action-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = e.submitter.value;
                const actionText = action === 'approve' ? 'approve' : 'reject';
                
                if (!confirm(`Are you sure you want to ${actionText} this request?`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>