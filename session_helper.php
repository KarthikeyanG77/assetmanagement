<?php
function secure_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 86400,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        session_name('ICT_SESSION');
        session_start();
        
        if (empty($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['last_activity'] = time();
        }

        // Session timeout (30 minutes)
        $inactive = 1800;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
            session_unset();
            session_destroy();
            header("Location: login.php?timeout=1");
            exit();
        }
        $_SESSION['last_activity'] = time();

        // Basic session hijacking protection
        if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || 
            $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            session_unset();
            session_destroy();
            header("Location: login.php?security=1");
            exit();
        }
    }
}

function is_logged_in() {
    secure_session_start();
    return isset($_SESSION['user_id']);
}

function redirect_if_not_logged_in() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

function redirect_if_not_admin() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        header("Location: dashboard.php");
        exit();
    }
}

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function generateCSRFToken() {
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Permission functions
function getUserDesignation($conn, $user_id) {
    $query = "SELECT designation FROM employee WHERE emp_id = ?";
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $stmt->close();
                return $user['designation'];
            }
        }
        $stmt->close();
    }
    return null;
}

function hasPermission($conn, $user_id, $required_designations) {
    $designation = getUserDesignation($conn, $user_id);
    return $designation && in_array($designation, $required_designations);
}