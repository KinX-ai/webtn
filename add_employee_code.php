
<?php
// Include database connection
include 'includes/db_connect.php';

try {
    // Check if employee_code column exists
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'employee_code'");
    $column_exists = $stmt->rowCount() > 0;
    
    if (!$column_exists) {
        // Add employee_code column if it doesn't exist
        $conn->exec("ALTER TABLE users ADD COLUMN employee_code VARCHAR(50) DEFAULT NULL AFTER email");
        echo "Employee code column added successfully.<br>";
    } else {
        echo "Employee code column already exists.<br>";
    }
    
    // Update existing users with employee codes
    $stmt = $conn->query("SELECT id FROM users WHERE employee_code IS NULL OR employee_code = ''");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = 0;
    foreach ($users as $user) {
        $code = 'EMP' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
        $update = $conn->prepare("UPDATE users SET employee_code = :code WHERE id = :id");
        $update->bindParam(':code', $code, PDO::PARAM_STR);
        $update->bindParam(':id', $user['id'], PDO::PARAM_INT);
        $update->execute();
        $count++;
    }
    
    echo "Updated $count users with employee codes.<br>";
    echo "Process completed successfully. <a href='account_management.php'>Go back to Account Management</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
