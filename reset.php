
<?php
// Set max execution time to 300 seconds (5 minutes)
ini_set('max_execution_time', 300);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';

try {
    // Connect to MySQL 
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo->exec("USE `$dbname`");

    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Truncate all tables
    $tables = ['logs', 'fb_orders', 'orders', 'fb_configs', 'fb_account_types', 'fb_sources', 'resource_types', 'users'];
    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE `$table`");
    }

    // Enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Create admin account
    $admin_username = 'admin';
    $admin_password = 'Admin@123';
    $admin_email = 'admin@example.com';
    $admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, created_at, status) VALUES (?, ?, ?, 'ADMIN', NOW(), 1)");
    $stmt->execute([$admin_username, $admin_hash, $admin_email]);

    // Enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    $success = "Đã xóa tất cả dữ liệu và tạo lại tài khoản admin mới.";

} catch(PDOException $e) {
    $error = "Lỗi: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Database - HKD Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            padding-top: 40px;
            padding-bottom: 40px;
        }
        .reset-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-container">
            <div class="text-center mb-4">
                <h2>Reset Database</h2>
                <p class="text-muted">Xóa tất cả dữ liệu và tạo lại tài khoản admin</p>
            </div>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                <hr>
                <p>Bạn có thể <a href="login.php" class="alert-link">đăng nhập</a> vào hệ thống ngay bây giờ.</p>
                <p><strong>Tài khoản:</strong> <?php echo htmlspecialchars($admin_username); ?></p>
                <p><strong>Mật khẩu:</strong> <?php echo htmlspecialchars($admin_password); ?></p>
            </div>
            <?php else: ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <strong>Cảnh báo:</strong> 
                Thao tác này sẽ xóa tất cả dữ liệu và tạo lại tài khoản admin.
            </div>

            <form method="post" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tất cả dữ liệu?');">
                <div class="d-grid gap-2">
                    <button type="submit" name="reset_db" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-2"></i> Xóa dữ liệu và tạo lại admin
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Quay lại
                    </a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
