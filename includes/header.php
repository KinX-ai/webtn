<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

// Include database connection
require_once 'includes/db_connect.php';
// Include helper functions
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/log_helper.php';

// Redirect to login page if not logged in, except for login page
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page != 'login.php' && $current_page != 'index.php' && !is_logged_in()) {
    redirect('login.php');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HKD - Hệ thống quản lý</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php if (is_logged_in()): ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <?php include 'includes/sidebar.php'; ?>
            </div>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-1 mb-2 border-bottom">
                    <h1 class="h4">
                        <?php
                        $page_titles = [
                            'dashboard.php' => 'Tổng quan',
                            'profile.php' => 'Thông tin cá nhân',
                            'account_management.php' => 'Quản lý tài khoản',
                            'order.php' => 'Order tài nguyên',
                            'config_resource.php' => 'Cấu hình loại tài nguyên',
                            'return_order.php' => 'Xử lý order tài nguyên',
                            'logs.php' => 'Nhật ký hệ thống',
                            'monthly_stats.php' => 'Thống kê tháng',
                            'fb_config.php' => 'Cấu hình nguồn thuê',
                            'fb_return.php' => 'Xử lý order Facebook',
                            'fb_order.php' => 'Order tài khoản',
                            'resource_management.php'=> 'Kho tài nguyên'
                        ];
                        
                        echo isset($page_titles[$current_page]) ? $page_titles[$current_page] : 'HKD Management';
                        ?>
                    </h1>
                    <div class="btn-toolbar mb-1 mb-md-0">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle py-0 px-2" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i>
                                <small><?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'User'; ?></small>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="profile.php">Hồ sơ cá nhân</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Đăng xuất</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
    <?php endif; ?>