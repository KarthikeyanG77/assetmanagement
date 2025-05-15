<?php
session_start();
require_once 'config.php';

// Get lab ID from query string if specified
$lab_id = isset($_GET['lab_id']) ? intval($_GET['lab_id']) : 0;

// Fetch all labs for dropdown
$labs_query = "SELECT location_id, location_name FROM location WHERE category_id = 1";
$labs_result = mysqli_query($conn, $labs_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Lab Schedules</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
        }
        .table-responsive {
            border-radius: 0 0 10px 10px;
            overflow: hidden;
        }
        .btn-action {
            min-width: 70px;
            margin: 2px;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .subject-code {
            color: #6c757d;
            font-size: 0.85rem;
        }
        .faculty-email {
            color: #6c757d;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Lab Schedules</h4>
                        <a href="add_schedule.php" class="btn btn-light btn-sm">
                            <i class="fas fa-plus me-1"></i> Add Schedule
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="filter-section">
                            <form method="GET" class="mb-0">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-6">
                                        <label for="lab_filter" class="form-label">Filter by Lab:</label>
                                        <select class="form-select" id="lab_filter" name="lab_id" onchange="this.form.submit()">
                                            <option value="0">All Labs</option>
                                            <?php while($lab = mysqli_fetch_assoc($labs_result)): ?>
                                                <option value="<?= htmlspecialchars($lab['location_id'] ?? '') ?>" 
                                                    <?= ($lab_id == $lab['location_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($lab['location_name'] ?? '') ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <a href="view_schedule.php" class="btn btn-outline-secondary">Reset Filters</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <div class="table-responsive mt-3">
                            <table id="schedulesTable" class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Lab</th>
                                        <th>Day</th>
                                        <th>Time Slot</th>
                                        <th>Subject</th>
                                        <th>Faculty</th>
                                        <th>Class Group</th>
                                        <th>Semester</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Build query based on filter
                                    $query = "SELECT s.*, l.location_name 
                                             FROM lab_schedule s
                                             JOIN location l ON s.lab_id = l.location_id";
                                    
                                    if ($lab_id > 0) {
                                        $query .= " WHERE s.lab_id = $lab_id";
                                    }
                                    
                                    $query .= " ORDER BY l.location_name, 
                                                FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                                                s.time_slot";
                                    
                                    $result = mysqli_query($conn, $query);
                                    
                                    while($schedule = mysqli_fetch_assoc($result)):
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($schedule['location_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($schedule['day_of_week'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($schedule['time_slot'] ?? '') ?></td>
                                        <td>
                                            <?= htmlspecialchars($schedule['subject_name'] ?? '') ?>
                                            <?php if (!empty($schedule['subject_code'])): ?>
                                                <div class="subject-code"><?= htmlspecialchars($schedule['subject_code']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($schedule['faculty_name'] ?? '') ?>
                                            <?php if (!empty($schedule['faculty_email'])): ?>
                                                <div class="faculty-email"><?= htmlspecialchars($schedule['faculty_email']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($schedule['class_group'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($schedule['semester'] ?? '') ?></td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center">
                                                <a href="edit_schedule.php?id=<?= $schedule['schedule_id'] ?>" 
                                                   class="btn btn-sm btn-primary btn-action" 
                                                   title="Edit">
                                                   <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete_schedule.php?id=<?= $schedule['schedule_id'] ?>" 
                                                   class="btn btn-sm btn-danger btn-action" 
                                                   title="Delete"
                                                   onclick="return confirm('Are you sure you want to delete this schedule?')">
                                                   <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <!-- Bootstrap and jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#schedulesTable').DataTable({
                responsive: true,
                order: [[0, 'asc'], [1, 'asc'], [2, 'asc']], // Order by lab, day, then time slot
                columnDefs: [
                    { orderable: false, targets: [7] } // Make actions column not orderable
                ],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search schedules...",
                    lengthMenu: "Show _MENU_ entries per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
        });
    </script>
</body>
</html>