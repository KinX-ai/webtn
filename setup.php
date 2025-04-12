<?php
// Set max execution time to 5 minutes for large imports
ini_set('max_execution_time', 300);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$error = '';
$success = '';
$admin_username = 'admin';
$admin_password = 'Admin@123';
$admin_email = 'admin@example.com';

// Process setup form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get database connection parameters from form
    $host = $_POST['db_host'] ?? 'localhost';
    $dbname = $_POST['db_name'] ?? '';
    $username = $_POST['db_user'] ?? '';
    $password = $_POST['db_pass'] ?? '';

    try {
        // Connect to MySQL server without selecting database
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (isset($_POST['setup_database'])) {
            // Create database with utf8mb4
            $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
            $pdo->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $pdo->exec("USE `$dbname`");

            // Import SQL from database.sql file
            $sql = file_get_contents('database.sql');
            $pdo->exec($sql);

            // Create admin account
            $admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, created_at, status) VALUES (?, ?, ?, 'ADMIN', NOW(), 1)");
            $stmt->execute([$admin_username, $admin_hash, $admin_email]);

            $success = "Thiết lập cơ sở dữ liệu thành công. Tài khoản admin đã được tạo:<br>
                       Tài khoản: $admin_username<br>
                       Mật khẩu: $admin_password";

        } else if (isset($_POST['restore_backup']) && isset($_FILES['backup_file'])) {
            $backup_file = $_FILES['backup_file']['tmp_name'];

            if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
                // Use database
                $pdo->exec("USE `$dbname`");

                // Read and execute SQL backup file
                $sql = file_get_contents($backup_file);
                $pdo->exec($sql);

                $success = "Khôi phục cơ sở dữ liệu thành công từ file backup!";
            } else {
                throw new Exception("Lỗi khi tải lên file backup");
            }
        }

        // Create config file
        $config_content = "<?php
// Database configuration
\$host = '$host';
\$dbname = '$dbname';
\$username = '$username';
\$password = '$password';
";
        file_put_contents('includes/config.php', $config_content);

    } catch(Exception $e) {
        $error = "Lỗi: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thiết lập HKD Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            padding-top: 40px;
            padding-bottom: 40px;
        }
        .setup-container {
            max-width: 800px;
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
        <div class="setup-container">
            <div class="text-center mb-4">
                <h2>Thiết lập HKD Management</h2>
                <p class="text-muted">Cấu hình cơ sở dữ liệu và tài khoản quản trị viên</p>
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
            </div>
            <?php else: ?>
            <div class="row">
                <div class="col-md-6">
                    <form method="post" class="setup-form">
                        <h4 class="mb-3">Thiết lập mới</h4>
                        <div class="mb-3">
                            <label class="form-label">Database Host:</label>
                            <input type="text" name="db_host" class="form-control" value="localhost" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Name:</label>
                            <input type="text" name="db_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Username:</label>
                            <input type="text" name="db_user" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Password:</label>
                            <input type="password" name="db_pass" class="form-control" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="setup_database" class="btn btn-primary">
                                <i class="fas fa-cogs me-2"></i> Thiết lập hệ thống
                            </button>
                        </div>
                    </form>
                </div>

                <div class="col-md-6">
                    <form method="post" class="restore-form" enctype="multipart/form-data">
                        <h4 class="mb-3">Khôi phục từ backup</h4>
                        <div class="mb-3">
                            <label class="form-label">Database Host:</label>
                            <input type="text" name="db_host" class="form-control" value="localhost" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Name:</label>
                            <input type="text" name="db_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Username:</label>
                            <input type="text" name="db_user" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Password:</label>
                            <input type="password" name="db_pass" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File Backup SQL:</label>
                            <input type="file" name="backup_file" class="form-control" accept=".sql" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="restore_backup" class="btn btn-warning">
                                <i class="fas fa-undo-alt me-2"></i> Khôi phục từ backup
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>