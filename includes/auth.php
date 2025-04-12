<?php
/**
 * Authentication and authorization functions
 */

/**
 * Verify user login credentials
 * 
 * @param PDO $conn Database connection
 * @param string $username Username
 * @param string $password Plain text password
 * @return array|bool User data if valid, false otherwise
 */
function login_user($conn, $username, $password) {
    try {
        $stmt = $conn->prepare("SELECT id, username, password, email, role, created_at, status FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] == 0) {
                // Account is locked
                return false;
            }
            return $user;
        }
        
        return false;
    } catch (PDOException $e) {
        // Log error
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

// This function has been moved to functions.php to avoid duplication

// This function has been moved to functions.php to avoid duplication

// This function has been moved to functions.php to avoid duplication

// This function has been moved to functions.php to avoid duplication

/**
 * Get users by role
 * 
 * @param PDO $conn Database connection
 * @param string $role User role to filter by
 * @return array List of users
 */
function get_users_by_role($conn, $role) {
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE role = :role ORDER BY username");
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        // Log error
        error_log("Get users by role error: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if a page is accessible by the current user
 * 
 * @param array $allowed_roles Array of roles allowed to access the page
 * @return bool True if accessible, false otherwise
 */
function is_page_accessible($allowed_roles) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    return in_array($_SESSION['user_role'], $allowed_roles);
}
?>
