
<?php
/**
 * Database connection setup 
 * This file handles the connection to the MySQL database
 */

// Check if configuration file exists and include it
if (file_exists(__DIR__ . '/config.php')) {
    include_once(__DIR__ . '/config.php');
} else {
    // Get database connection parameters from environment variables
    $host = getenv('MYSQL_HOST') ?: 'localhost';
    $dbname = getenv('MYSQL_DATABASE') ?: 'meta_testorr';
    $username = getenv('MYSQL_USER') ?: 'meta_testorr';
    $password = getenv('MYSQL_PASSWORD') ?: 'testorr';
}

// Create a connection
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, array(
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ));
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Kiểm tra xem bảng users có tồn tại không
    $stmt = $conn->prepare("SHOW TABLES LIKE 'users'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        // Nếu bảng không tồn tại, chuyển hướng đến trang setup
        header("Location: /setup.php");
        exit;
    }
} catch(PDOException $e) {
    // Nếu có lỗi kết nối đến cơ sở dữ liệu, chuyển hướng đến trang setup
    header("Location: /setup.php");
    exit;
}
?>
