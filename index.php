<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Get current user's name and designation if logged in
$currentUserName = "Guest";
$user_designation = null;
$is_admin = false;
$is_lab_incharge = false;

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT emp_name, designation FROM employee WHERE emp_id = ?";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $currentUserName = $user['emp_name'];
            $user_designation = $user['designation'];
        }
        $stmt->close();
    }
    
    // Check admin privileges
    $is_admin = in_array($user_designation, ['Lead', 'Sr SA', 'IT_Admin']);
    $is_lab_incharge = ($user_designation == 'Lab_incharge');
}

// Handle request creation click
if (isset($_GET['create_request'])) {
    if ($is_logged_in) {
        header("Location: request.php");
        exit();
    } else {
        header("Location: login.php");
        exit();
    }
}

// Get all labs
$labs = [];
$lab_query = "SELECT location_id, location_name FROM location WHERE category_id = 1 ORDER BY location_name";
$lab_result = $conn->query($lab_query);
if ($lab_result) {
    $labs = $lab_result->fetch_all(MYSQLI_ASSOC);
} else {
    echo "<div class='alert alert-danger'>Error fetching labs: " . $conn->error . "</div>";
}

// Get all lab schedules from lab_schedule table
$schedules = [];
$schedule_query = "SELECT ls.*, l.location_name 
                 FROM lab_schedule ls
                 JOIN location l ON ls.lab_id = l.location_id
                 ORDER BY ls.day_of_week, ls.time_slot";
$schedule_result = $conn->query($schedule_query);
if ($schedule_result) {
    $schedules = $schedule_result->fetch_all(MYSQLI_ASSOC);
} else {
    echo "<div class='alert alert-danger'>Error fetching schedules: " . $conn->error . "</div>";
}

// Get all approved lab requests from lab_request table (now available to all users)
$approved_requests = [];
$today = date('Y-m-d');
$next_15_days = date('Y-m-d', strtotime('+15 days'));

$request_query = "SELECT lr.*, l.location_name 
                 FROM lab_request lr
                 JOIN location l ON lr.lab_id = l.location_id
                 WHERE lr.status = 'Approved'
                 AND lr.from_date >= '$today'
                 AND lr.from_date <= '$next_15_days'
                 ORDER BY lr.from_date, lr.from_time";

$request_result = $conn->query($request_query);
if ($request_result) {
    $approved_requests = $request_result->fetch_all(MYSQLI_ASSOC);
} else {
    echo "<div class='alert alert-danger'>Error fetching approved requests: " . $conn->error . "</div>";
}

// Time slots for display
$time_slots = [
    '8:00-9:00', '9:00-10:00', '10:00-11:00', '11:00-12:00',
    '12:00-13:00', '13:00-14:00', '14:00-15:00', '15:00-16:00', '16:00-17:00'
];

// Days of week for display
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Get daily Thirukkural from API
$kural_number = (date('z') % 1330) + 1; // Gives a number between 1-1330 based on day of year
$kural_data = null;

// Function to fetch Thirukkural from API
function getThirukkural($number) {
    $api_url = "https://thirukkural.io/api/v3/kural/$number";
    $response = @file_get_contents($api_url);
    
    if ($response === FALSE) {
        return null;
    }
    
    return json_decode($response, true);
}

// Try to get today's kural
$kural_data = getThirukkural($kural_number);

// If API fails, use a default message
if (!$kural_data || !isset($kural_data['kural'])) {
    $kural_data = [
        'kural' => [
            'number' => $kural_number,
            'line1' => 'API தரவு பெறப்படவில்லை',
            'line2' => 'Failed to fetch data from API',
            'translation' => 'This is a sample Thirukkural as the API failed to respond.',
            'explanation' => 'Please check your internet connection or try again later.'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICT Team IT Asset Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
            background-image: url('https://th.bing.com/th/id/R.b5e1e8d9c5c0a3d0a3e3e3e3e3e3e3e?rik=3D3D3D3D3D3D3D&pid=ImgRaw');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
        }
        .header-container {
            margin-bottom: 30px;
            text-align: center;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .logo {
            max-height: 80px;
            margin-bottom: 15px;
        }
        .user-info {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .guest-info {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: rgba(231, 241, 255, 0.9);
            padding: 10px 15px;
            border-radius: 5px;
            border-left: 5px solid #0d6efd;
            max-width: 300px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .user-icon {
            width: 40px;
            height: 40px;
            background-color: #0d6efd;
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
        }
        .logout-btn:hover {
            color: #0d6efd;
        }
        .timetable-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        .timetable {
            width: 100%;
            overflow-x: auto;
        }
        .time-cell {
            min-width: 150px;
            height: 80px;
            border: 1px solid #ddd;
            padding: 5px;
            position: relative;
        }
        .time-cell.booked {
            background-color: #d4edda;
        }
        .time-cell.pending {
            background-color: #fff3cd;
        }
        .day-header {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }
        .time-header {
            background-color: #e9ecef;
            font-weight: bold;
            text-align: center;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background-color: rgba(255, 255, 255, 0.95);
        }
        .nav-tabs {
            margin-bottom: 20px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 5px;
            padding: 10px;
        }
        .login-register-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .auth-buttons {
            margin-top: 20px;
        }
        .timetable-note {
            margin-top: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            font-size: 0.9em;
        }
        .request-btn-container {
            margin-bottom: 20px;
            text-align: center;
        }
        .kural-container {
            background-color: rgba(248, 249, 250, 0.95);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-style: italic;
            border-left: 5px solid #ffc107;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .kural-number {
            font-weight: bold;
            color: #0d6efd;
        }
        .marquee {
            white-space: nowrap;
            overflow: hidden;
            box-sizing: border-box;
        }
        .marquee span {
            display: inline-block;
            padding-left: 100%;
            animation: marquee 15s linear infinite;
        }
        @keyframes marquee {
            0%   { transform: translate(0, 0); }
            100% { transform: translate(-100%, 0); }
        }
        .kural-tamil {
            font-size: 1.2em;
            font-weight: bold;
            margin: 10px 0;
        }
        .kural-translation {
            margin: 10px 0;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- User/Guest Info Section -->
        <?php if ($is_logged_in): ?>
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
        <?php else: ?>
            <div class="guest-info">
                <i class="fas fa-info-circle"></i> Guest Access
                <p style="margin: 5px 0 0; font-size: 0.9em;">You're viewing this system as a guest. Please <a href="login.php">login</a> to access all features.</p>
            </div>
        <?php endif; ?>

        <!-- Header with Logo -->
        <div class="header-container">
            <img src="/ict/images/logo.jpeg" alt="ICT Logo" class="logo">
            <h1>ICT Team IT Asset Management</h1>
            <p class="lead">Manage lab resources and schedules efficiently</p>
        </div>

        <!-- Thirukkural Display -->
        <div class="kural-container">
            <div class="marquee">
            </div>
        </div>

        <!-- Request Button -->
        <div class="request-btn-container">
            <a href="?create_request=1" class="btn btn-warning btn-lg">
                <i class="fas fa-plus-circle"></i> Create Lab Request
            </a>
        </div>

        <!-- Main Content for all users (logged in or not) -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="timetable-tab" data-bs-toggle="tab" data-bs-target="#timetable" type="button" role="tab" aria-controls="timetable" aria-selected="true">
                    Lab Timetable
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests" type="button" role="tab" aria-controls="requests" aria-selected="false">
                    Approved Requests
                </button>
            </li>
            <?php if ($is_logged_in && ($is_admin || $is_lab_incharge)): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="management-tab" data-bs-toggle="tab" data-bs-target="#management" type="button" role="tab" aria-controls="management" aria-selected="false">
                    Management
                </button>
            </li>
            <?php endif; ?>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Lab Timetable Tab -->
            <div class="tab-pane fade show active" id="timetable" role="tabpanel" aria-labelledby="timetable-tab">
                <div class="timetable-container">
                    <h3 class="mb-4">Lab Timetable</h3>
                    
                    <?php if (empty($labs)): ?>
                        <div class="alert alert-warning">No labs found in the system.</div>
                    <?php else: ?>
                        <?php foreach ($labs as $lab): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h4><?= htmlspecialchars($lab['location_name']) ?></h4>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($schedules)): ?>
                                        <div class="alert alert-info">No schedules found for this lab.</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered timetable">
                                                <thead>
                                                    <tr>
                                                        <th class="time-header">Time</th>
                                                        <?php foreach ($days_of_week as $day): ?>
                                                            <th class="day-header"><?= $day ?></th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($time_slots as $slot): ?>
                                                        <tr>
                                                            <td class="time-header"><?= $slot ?></td>
                                                            <?php foreach ($days_of_week as $day): ?>
                                                                <td class="time-cell">
                                                                    <?php
                                                                    // Check permanent schedule
                                                                    $found = false;
                                                                    foreach ($schedules as $schedule) {
                                                                        if ($schedule['lab_id'] == $lab['location_id'] && 
                                                                            $schedule['day_of_week'] == $day && 
                                                                            $schedule['time_slot'] == $slot) {
                                                                            echo "<strong>" . htmlspecialchars($schedule['subject_code']) . "</strong><br>";
                                                                            echo htmlspecialchars($schedule['subject_name']) . "<br>";
                                                                            echo htmlspecialchars($schedule['faculty_name']) . "<br>";
                                                                            echo htmlspecialchars($schedule['class_group']);
                                                                            $found = true;
                                                                            break;
                                                                        }
                                                                    }
                                                                    
                                                                    if (!$found) {
                                                                        echo "Available";
                                                                    }
                                                                    ?>
                                                                </td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Approved Requests Tab -->
            <div class="tab-pane fade" id="requests" role="tabpanel" aria-labelledby="requests-tab">
                <div class="timetable-container">
                    <h3 class="mb-4">Approved Lab Requests</h3>
                    
                    <?php if (empty($approved_requests)): ?>
                        <div class="alert alert-info">No approved requests found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>From Date</th>
                                        <th>To Date</th>
                                        <th>Lab</th>
                                        <th>Time</th>
                                        <th>Subject</th>
                                        <th>Faculty</th>
                                        <th>Class</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved_requests as $request): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($request['from_date'])) ?></td>
                                            <td><?= date('d M Y', strtotime($request['to_date'])) ?></td>
                                            <td><?= htmlspecialchars($request['location_name']) ?></td>
                                            <td><?= htmlspecialchars($request['from_time']) ?> - <?= htmlspecialchars($request['to_time']) ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($request['subject_code']) ?></strong><br>
                                                <?= htmlspecialchars($request['subject_name']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($request['faculty_name']) ?></td>
                                            <td><?= htmlspecialchars($request['class_group']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($is_logged_in && ($is_admin || $is_lab_incharge)): ?>
            <!-- Management Tab (for admins/lab incharge) -->
            <div class="tab-pane fade" id="management" role="tabpanel" aria-labelledby="management-tab">
                <div class="timetable-container">
                    <h3 class="mb-4">Management Tools</h3>
                    
                    <div class="row">
                        <?php if ($is_admin): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-calendar-plus fa-3x mb-3 text-primary"></i>
                                        <h5 class="card-title">Add Permanent Schedule</h5>
                                        <p class="card-text">Add regular lab schedules that repeat every week.</p>
                                        <a href="add_timetable.php" class="btn btn-primary">Go to Schedule</a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-tasks fa-3x mb-3 text-success"></i>
                                        <h5 class="card-title">Manage Requests</h5>
                                        <p class="card-text">Approve or reject lab booking requests.</p>
                                        <a href="requests.php" class="btn btn-success">Manage Requests</a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-desktop fa-3x mb-3 text-info"></i>
                                        <h5 class="card-title">Manage Assets</h5>
                                        <p class="card-text">View and manage lab equipment and assets.</p>
                                        <a href="manage_ast.php" class="btn btn-info">Manage Assets</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-book fa-3x mb-3 text-warning"></i>
                                    <h5 class="card-title">Request Lab</h5>
                                    <p class="card-text">Submit a request to book a lab for a specific time.</p>
                                    <a href="?create_request=1" class="btn btn-warning">Make Request</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-history fa-3x mb-3 text-secondary"></i>
                                    <h5 class="card-title">My Requests</h5>
                                    <p class="card-text">View the status of your lab booking requests.</p>
                                    <a href="my_requests.php" class="btn btn-secondary">View Requests</a>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($is_lab_incharge): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-clipboard-check fa-3x mb-3 text-danger"></i>
                                        <h5 class="card-title">Lab Maintenance</h5>
                                        <p class="card-text">Report and track lab equipment issues.</p>
                                        <a href="maintenance.php" class="btn btn-danger">Maintenance</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap tabs
        var tabElms = [].slice.call(document.querySelectorAll('button[data-bs-toggle="tab"]'));
        tabElms.forEach(function(tabEl) {
            new bootstrap.Tab(tabEl);
        });
    </script>
</body>
</html>