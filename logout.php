<?php
// Start session
session_start();

// Include required files
require_once 'includes/db_connect.php';
require_once 'includes/log_helper.php';

// Log logout action if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Add logout log
    add_log($conn, $user_id, 'logout', json_encode([
        'time' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR']
    ]));
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
