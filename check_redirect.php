<?php
// check_redirect.php
session_start();

// Đường dẫn đến file cấu hình
$configFile = __DIR__ . '/config/redirect_config.json';

// Kiểm tra nếu là admin thì không chuyển hướng
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    require_once 'login.php';
    exit();
}

// Đọc cấu hình
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    
    // Nếu bật chuyển hướng và có URL hợp lệ
    if ($config['enabled'] && !empty($config['redirect_url'])) {
        header("Location: " . $config['redirect_url']);
        exit();
    }
}

// Nếu không chuyển hướng thì load trang login bình thường
require_once 'login.php';