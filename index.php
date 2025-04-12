<?php
// Start session
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
} else {
    // Redirect to login page
    header("Location: login.php");
   // header("Location: index.html");
    exit();
}
?>
