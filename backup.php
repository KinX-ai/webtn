<?php
// Include header (handles session, database connection, etc.)
include 'includes/header.php';

// Ensure database connection is available
global $conn;
if (!isset($conn) || !$conn) {
    // Include database connection if not already included
    require_once 'includes/db_connect_new.php';
}

// Check if user is authorized to access this page (ADMIN only)
$allowed_roles = ['ADMIN'];
if (!check_role($allowed_roles)) {
    redirect('login.php');
}

// Check password protection 
$backup_password = 'ducphi2048';
$password_timeout = 120; // 2 minutes in seconds

// Set password session time when correct password is submitted
if (isset($_POST['backup_password']) && $_POST['backup_password'] === $backup_password) {
    $_SESSION['backup_password_time'] = time();
}

// Check if password session exists and hasn't expired
if (isset($_SESSION['backup_password_time']) && 
    (time() - $_SESSION['backup_password_time']) < $password_timeout) {
    // Password session is still valid
} else if (!isset($_POST['backup_password']) || $_POST['backup_password'] !== $backup_password) {
    // Clear expired session
    unset($_SESSION['backup_password_time']);
    ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Xác thực truy cập</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="mb-3">
                                <label for="backup_password" class="form-label">Mật khẩu truy cập</label>
                                <input type="password" class="form-control" id="backup_password" name="backup_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Xác nhận</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include 'includes/footer.php';
    exit();
}

// Make sure we have access to functions needed for this page
require_once 'includes/functions.php';

// Initialize variables
$success_message = '';
$error_message = '';
$backup_dir = 'backups';

// Create backup directory with proper permissions
if (!file_exists($backup_dir)) {
    if (!@mkdir($backup_dir, 0755, true)) {
        die("Không thể tạo thư mục backup. Vui lòng kiểm tra quyền.");
    }
} elseif (!is_dir($backup_dir)) {
    die("'$backup_dir' tồn tại nhưng không phải là thư mục.");
} elseif (!is_writable($backup_dir)) {
    if (!@chmod($backup_dir, 0755)) {
        die("Thư mục backup không có quyền ghi. Vui lòng kiểm tra quyền.");
    }
}

// Process database backup request
if (isset($_POST['backup_database'])) {
    try {
        // Get current timestamp for the filename
        $timestamp = date('Y-m-d_H-i-s');
        $backup_filename = $backup_dir . '/database_backup_' . $timestamp . '.sql';

        // Get list of tables for MySQL using PDO
        $tables = [];
        $stmt = $conn->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        if (empty($tables)) {
            throw new Exception("Không tìm thấy bảng nào trong cơ sở dữ liệu");
        }

        // Open the backup file
        $file = fopen($backup_filename, 'w');
        if (!$file) {
            throw new Exception("Không thể mở file backup để ghi.");
        }

        // Add header
        fwrite($file, "-- HKD Management System Database Backup\n");
        fwrite($file, "-- Generated: " . date('d/m/Y H:i:s') . "\n\n");

        // Process each table
        foreach ($tables as $table) {
            // Write table header
            fwrite($file, "-- Table: $table\n");

            // Get create table syntax
            $stmt = $conn->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $createTable = $row[1] . ";\n\n";
            fwrite($file, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($file, $createTable);

            // Get column names
            $columns = [];
            $stmt = $conn->query("DESCRIBE `$table`");
            while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $col['Field'];
            }

            // Get table data
            $stmt = $conn->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) > 0) {
                // Insert statements
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($columns as $column) {
                        if (isset($row[$column]) && $row[$column] !== null) {
                            $values[] = $conn->quote($row[$column]);
                        } else {
                            $values[] = 'NULL';
                        }
                    }

                    fwrite($file, "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n");
                }
                fwrite($file, "\n");
            }
        }

        // Close the file
        fclose($file);

        // Log the backup action
        log_profile_action($conn, $_SESSION['user_id'], 'database_backup', ['filename' => basename($backup_filename)]);

        $success_message = "Sao lưu cơ sở dữ liệu thành công! File: " . basename($backup_filename);
    } catch (Exception $e) {
        $error_message = "Lỗi: " . $e->getMessage();
    }
}

// Process code backup request
if (isset($_POST['backup_code'])) {
    try {
        // Get current timestamp for the filename
        $timestamp = date('Y-m-d_H-i-s');
        $backup_filename = $backup_dir . '/code_backup_' . $timestamp . '.zip';

        // Only exclude backup directory itself
        $exclude = [
            $backup_dir,
            $backup_filename
        ];

        // Use zip for code backup
        $zip = new ZipArchive();
        if ($zip->open($backup_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $source = realpath('.');
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                $file = realpath($file);
                if (is_dir($file)) {
                    continue;
                }

                $relative_path = str_replace($source . DIRECTORY_SEPARATOR, '', $file);

                // Check if file should be excluded
                $skip = false;
                foreach ($exclude as $exc) {
                    if (strpos($file, DIRECTORY_SEPARATOR . $exc) !== false) {
                        $skip = true;
                        break;
                    }
                }

                if (!$skip) {
                    $zip->addFile($file, $relative_path);
                }
            }

            $zip->close();
        } else {
            throw new Exception("Không thể tạo file ZIP");
        }

        if (!file_exists($backup_filename)) {
            throw new Exception("Không thể tạo file backup");
        }

        // Verify backup file exists and has content
        if (!file_exists($backup_filename) || filesize($backup_filename) < 100) {
            throw new Exception("Backup file không hợp lệ hoặc trống");
        }


        // Execute the command with better error handling
        $output = [];
        $return_var = 0;
        exec($cmd . ' 2>&1', $output, $return_var);

        if ($return_var === 0 && file_exists($backup_filename)) {
            // Verify zip file size
            $zip_size = filesize($backup_filename);
            if ($zip_size < 100) { // Too small, probably empty or corrupted
                unlink($backup_filename);
                $error_message = "File nén tạo ra quá nhỏ, có thể bị lỗi.";
                return;
            }

            // Verify zip file integrity
            $verify_cmd = "unzip -t " . escapeshellarg($backup_filename) . " 2>&1";
            $verify_output = [];
            $verify_return = 0;
            exec($verify_cmd, $verify_output, $verify_return);

            if ($verify_return === 0) {
                // Double check zip contents
                $list_cmd = "unzip -l " . escapeshellarg($backup_filename) . " 2>&1";
                $list_output = [];
                exec($list_cmd, $list_output);

                if (count($list_output) > 3) { // Has valid contents
                    // Log the backup action
                    log_profile_action($conn, $_SESSION['user_id'], 'code_backup', ['filename' => basename($backup_filename)]);
                    $success_message = "Sao lưu mã nguồn thành công! File: " . basename($backup_filename);
                } else {
                    unlink($backup_filename);
                    $error_message = "File nén không chứa dữ liệu. Vui lòng thử lại.";
                }
            } else {
                unlink($backup_filename); // Delete corrupted zip
                $error_message = "File nén không hợp lệ. Vui lòng thử lại.";
            }
        } else {
            $error_message = "Không thể tạo file nén để sao lưu mã nguồn. Lỗi: " . implode("\n", $output);
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $error_message = "Lỗi: " . $e->getMessage();
    }
}

// Get list of existing backups
$database_backups = [];
$code_backups = [];

if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $file_path = $backup_dir . '/' . $file;
        $file_info = [
            'name' => $file,
            'size' => filesize($file_path),
            'date' => date('d/m/Y H:i:s', filemtime($file_path))
        ];

        if (strpos($file, 'database_backup_') === 0) {
            $database_backups[] = $file_info;
        } elseif (strpos($file, 'code_backup_') === 0) {
            $code_backups[] = $file_info;
        }
    }

    // Sort backups by date (newest first)
    usort($database_backups, function($a, $b) {
        return strtotime(str_replace('/', '-', $b['date'])) - strtotime(str_replace('/', '-', $a['date']));
    });

    usort($code_backups, function($a, $b) {
        return strtotime(str_replace('/', '-', $b['date'])) - strtotime(str_replace('/', '-', $a['date']));
    });
}

// Process backup download request
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $filename = basename($_GET['download']);
    $file = $backup_dir . '/' . $filename;

    if (file_exists($file) && !preg_match('/[^a-zA-Z0-9_\-\.]/', $filename)) {
        // Log the download action
        log_profile_action($conn, $_SESSION['user_id'], 'backup_download', ['filename' => $filename]);

        // Set headers for file download
        header('Content-Description: File Transfer');
        if (strpos($filename, '.sql') !== false) {
            header('Content-Type: application/sql');
        } else {
            header('Content-Type: application/octet-stream');
        }
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));

        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Read file in chunks
        $chunk_size = 1024 * 1024; // 1MB chunks
        $handle = fopen($file, 'rb');
        while (!feof($handle)) {
            echo fread($handle, $chunk_size);
            flush();
        }
        fclose($handle);
        exit;
    }
}

// Process backup delete request
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $filename = basename($_GET['delete']);
    $file = $backup_dir . '/' . $filename;

    if (file_exists($file) && !preg_match('/[^a-zA-Z0-9_\-\.]/', $filename)) {
        if (unlink($file)) {
            // Log the delete action  
            log_profile_action($conn, $_SESSION['user_id'], 'backup_delete', ['filename' => $filename]);
            $_SESSION['backup_success'] = "File sao lưu đã được xóa thành công!";
        } else {
            $_SESSION['backup_error'] = "Không thể xóa file sao lưu.";
        }
        header("Location: backup.php");
        exit();
    } else {
        $_SESSION['backup_error'] = "File sao lưu không tồn tại hoặc không hợp lệ.";
        header("Location: backup.php");
        exit();
    }
}
?>

<?php 
// Redirect after successful backup to prevent form resubmission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($success_message)) {
        $_SESSION['backup_success'] = $success_message;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } elseif (!empty($error_message)) {
        $_SESSION['backup_error'] = $error_message;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Display messages from session
if (isset($_SESSION['backup_success'])) {
    $success_message = $_SESSION['backup_success'];
    unset($_SESSION['backup_success']);
}
if (isset($_SESSION['backup_error'])) {
    $error_message = $_SESSION['backup_error'];
    unset($_SESSION['backup_error']);
}
?>

<?php if (!empty($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100 border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Sao lưu cơ sở dữ liệu</h5>
            </div>
            <div class="card-body">
                <p>Sao lưu toàn bộ dữ liệu trong cơ sở dữ liệu MySQL, bao gồm người dùng, đơn hàng, nhật ký và các thông tin khác.</p>
                <form method="post">
                    <button type="submit" name="backup_database" class="btn btn-primary">
                        <i class="fas fa-database me-2"></i> Sao lưu cơ sở dữ liệu
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100 border-success">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">Sao lưu mã nguồn</h5>
            </div>
            <div class="card-body">
                <p>Sao lưu toàn bộ mã nguồn của ứng dụng, bao gồm tất cả các file PHP, CSS, JavaScript và các tài nguyên khác.</p>
                <form method="post">
                    <button type="submit" name="backup_code" class="btn btn-success">
                        <i class="fas fa-code me-2"></i> Sao lưu mã nguồn
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Các bản sao lưu cơ sở dữ liệu</h5>
                </div>
                <div class="card-body">
                    <?php if (count($database_backups) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Tên file</th>
                                    <th>Kích thước</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($database_backups as $backup): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($backup['name']); ?></td>
                                    <td><?php echo format_size($backup['size']); ?></td>
                                    <td><?php echo $backup['date']; ?></td>
                                    <td>
                                        <a href="?download=<?php echo urlencode($backup['name']); ?>" class="btn btn-sm btn-primary" title="Tải xuống">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="?delete=<?php echo urlencode($backup['name']); ?>" class="btn btn-sm btn-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa bản sao lưu này?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        Chưa có bản sao lưu cơ sở dữ liệu nào.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Các bản sao lưu mã nguồn</h5>
                </div>
                <div class="card-body">
                    <?php if (count($code_backups) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Tên file</th>
                                    <th>Kích thước</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($code_backups as $backup): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($backup['name']); ?></td>
                                    <td><?php echo format_size($backup['size']); ?></td>
                                    <td><?php echo $backup['date']; ?></td>
                                    <td>
                                        <a href="?download=<?php echo urlencode($backup['name']); ?>" class="btn btn-sm btn-primary" title="Tải xuống">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="?delete=<?php echo urlencode($backup['name']); ?>" class="btn btn-sm btn-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa bản sao lưu này?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        Chưa có bản sao lưu mã nguồn nào.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>