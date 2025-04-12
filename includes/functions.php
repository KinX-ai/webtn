<?php
/**
 * Helper functions for the application
 */

/**
 * Clean input data to prevent XSS attacks
 * 
 * @param string $data Data to be sanitized
 * @return string Sanitized data
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Check if user has required role
 * 
 * @param array $allowed_roles Array of roles allowed to access
 * @return bool True if user has access, false otherwise
 */
function check_role($allowed_roles) {
    if (!isset($_SESSION['user_role'])) {
        error_log("Session user_role not set");
        return false;
    }

    $has_role = in_array($_SESSION['user_role'], $allowed_roles);
    error_log("Role check for " . $_SESSION['user_role'] . " in [" . implode(', ', $allowed_roles) . "]: " . ($has_role ? 'true' : 'false'));

    return $has_role;
}

/**
 * Get current user ID
 * 
 * @return int|null User ID or null if not logged in
 */
function get_user_id() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get role name in Vietnamese
 * 
 * @param string $role_code Role code from database
 * @return string Vietnamese role name
 */
function get_role_name($role_code) {
    switch ($role_code) {
        case 'USER_HKD':
            return 'Người dùng HKD';
        case 'HKD':
            return 'HKD';
        case 'ADMIN':
            return 'Quản trị viên';
        case 'GS':
            return 'Giám sát';
        case 'Returned':
            return 'Đã trả';
        default:
            return 'Không xác định';
    }
}

/**
 * Get order status in Vietnamese
 * 
 * @param string $status_code Status code from database
 * @return string Vietnamese status name
 */
function get_order_status($status_code) {
    switch ($status_code) {
        case 'Pending':
            return 'Đang chờ xử lý';
        case 'Processed':
            return 'Đã xử lý';
        case 'Rejected':
            return 'Từ chối';
        case 'Returned':
            return 'Bị treo';
        case 'PartiallyReturned':
            return 'Đã trả một phần';
        default:
            return 'Không xác định';
    }
}

/**
 * Format date to Vietnamese format
 * 
 * @param string $date Date in MySQL format
 * @return string Formatted date
 */
function format_date($date) {
    $timestamp = strtotime($date);
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Redirect to another page
 * 
 * @param string $location URL to redirect to
 * @return void
 */
function redirect($location) {
    header("Location: $location");
    exit;
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Display error message
 * 
 * @param string $message Error message to display
 * @return string HTML for error message
 */
function error_message($message) {
    return '<div class="alert alert-danger" role="alert">' . $message . '</div>';
}

/**
 * Display success message
 * 
 * @param string $message Success message to display
 * @return string HTML for success message
 */
function success_message($message) {
    return '<div class="alert alert-success" role="alert">' . $message . '</div>';
}

/**
 * Get resource types from database
 * 
 * @param PDO $conn Database connection
 * @return array Array of resource types
 */
function get_resource_types($conn) {
    $stmt = $conn->prepare("SELECT * FROM resource_types ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get user details by ID
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @return array|bool User details or false if not found
 */
function get_user_by_id($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch();
}

/**
 * Get role level for permission checking
 * 
 * @param string $role User role
 * @return int Role level (higher is more powerful)
 */
function get_role_level($role) {
    switch ($role) {
        case 'ADMIN':
            return 4;
        case 'HKD':
            return 3;
        case 'GS':
            return 4; // Same level as ADMIN for viewing only
        case 'USER_HKD':
            return 1;
        default:
            return 0;
    }
}

/**
 * Get Bootstrap color class for order status
 * 
 * @param string $status Status code
 * @return string Bootstrap color class
 */
function get_status_color($status) {
    switch ($status) {
        case 'Pending':
            return 'warning';
        case 'Processed':
            return 'success';
        case 'Rejected':
            return 'danger';
        case 'Returned':
            return 'info';
        default:
            return 'secondary';
    }
}

/**
 * Get user avatar URL
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @return string Avatar URL
 */
function get_user_avatar($conn, $user_id) {
    try {
        $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = :id");
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && $result['avatar']) {
            return $result['avatar'];
        }

        // Return default avatar if not found
        return 'https://i.pinimg.com/736x/b7/91/44/b79144e03dc4996ce319ff59118caf65.jpg';
    } catch (PDOException $e) {
        error_log("Error getting avatar: " . $e->getMessage());
        return 'https://i.pinimg.com/736x/b7/91/44/b79144e03dc4996ce319ff59118caf65.jpg';
    }
}

/**
 * Format file size to human-readable format
 * 
 * @param int $size Size in bytes
 * @return string Formatted size with units
 */
function format_size($size) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

/**
 * Update user information
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID to update
 * @param array $data Data to update (keys: email, password, status, role, employee_code, avatar)
 * @return bool True if update was successful, false otherwise
 */
function update_user($conn, $user_id, $data) {
    try {
        // Prepare SQL query
        $fields = [];
        $params = [];

        // Add fields to update
        if (isset($data['email'])) {
            $fields[] = "email = :email";
            $params[':email'] = $data['email'];
        }

        if (isset($data['password'])) {
            $fields[] = "password = :password";
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            $params[':password'] = $hashed_password;
        }

        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }

        if (isset($data['role'])) {
            $fields[] = "role = :role";
            $params[':role'] = $data['role'];
        }

        if (isset($data['employee_code'])) {
            $fields[] = "employee_code = :employee_code";
            $params[':employee_code'] = $data['employee_code'];
        }

        if (isset($data['avatar'])) {
            $fields[] = "avatar = :avatar";
            $params[':avatar'] = $data['avatar'];
        }

        // Nothing to update
        if (empty($fields)) {
            return true;
        }

        // Prepare and execute query
        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = :id";
        $params[':id'] = $user_id;

        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error updating user: " . $e->getMessage());
        return false;
    }
}

/**
 * Log profile action
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID performing the action
 * @param string $action Action performed
 * @param array $details Additional details
 * @return bool True if log was successful, false otherwise
 */
function log_profile_action($conn, $user_id, $action, $details = []) {
    try {
        $sql = "INSERT INTO logs (user_id, action, details, created_at) 
                VALUES (:user_id, :action, :details, NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindParam(':details', json_encode($details), PDO::PARAM_STR);

        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error logging profile action: " . $e->getMessage());
        return false;
    }
}

/**
 * Log user management action
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID performing the action
 * @param string $action Action performed
 * @param int $target_id Target user ID
 * @param array $details Additional details
 * @return bool True if log was successful, false otherwise
 */
function log_user_management($conn, $user_id, $action, $target_id, $details = []) {
    try {
        $sql = "INSERT INTO logs (user_id, action, target_id, details, created_at) 
                VALUES (:user_id, :action, :target_id, :details, NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindParam(':target_id', $target_id, PDO::PARAM_INT);
        $stmt->bindParam(':details', json_encode($details), PDO::PARAM_STR);

        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error logging user management: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a new user
 * 
 * @param PDO $conn Database connection
 * @param string $username Username
 * @param string $password Password (plain text)
 * @param string $email Email address
 * @param string $role User role
 * @param int|null $parent_id Parent user ID (for USER_HKD)
 * @param string $employee_code Employee code
 * @return bool True if user was created successfully, false otherwise
 */
function create_user($conn, $username, $password, $email, $role, $parent_id = null, $employee_code = null) {
    try {
        // Check if username already exists
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $check_stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $check_stmt->execute();

        if ($check_stmt->fetchColumn() > 0) {
            return false; // Username already exists
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Prepare SQL query
        $sql = "INSERT INTO users (username, password, email, role, parent_id, employee_code, created_at, status) 
                VALUES (:username, :password, :email, :role, :parent_id, :employee_code, NOW(), 1)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);

        // Handle nullable parent_id
        if ($parent_id === null) {
            $stmt->bindValue(':parent_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':parent_id', $parent_id, PDO::PARAM_INT);
        }

        // Handle employee_code (null or empty safe)
        if (empty($employee_code)) {
            $employee_code = 'EMP' . str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        }
        $stmt->bindParam(':employee_code', $employee_code, PDO::PARAM_STR);

        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error creating user: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if current user can manage target user
 * 
 * @param string $current_role Current user's role
 * @param string $target_role Target user's role
 * @return bool True if current user can manage target user, false otherwise
 */
function can_manage_user($current_role, $target_role) {
    $current_level = get_role_level($current_role);
    $target_level = get_role_level($target_role);

    // ADMIN can manage all users
    if ($current_role == 'ADMIN') {
        return true;
    }

    // HKD can only manage USER_HKD
    if ($current_role == 'HKD' && $target_role == 'USER_HKD') {
        return true;
    }

    // GS cannot manage any users (view only)
    return false;
}

/**
 * Get users that current user can manage
 * 
 * @param PDO $conn Database connection
 * @param string $user_role Current user's role
 * @param int $user_id Current user's ID
 * @return array Array of users that can be managed
 */
function get_managed_users($conn, $user_role, $user_id) {
    try {
        $params = [];
        $sql = "";

        if ($user_role == 'ADMIN' || $user_role == 'GS') {
            // ADMIN and GS can see all users
            $sql = "SELECT u.*, p.username as parent_username 
                    FROM users u 
                    LEFT JOIN users p ON u.parent_id = p.id 
                    ORDER BY u.id DESC";
        } elseif ($user_role == 'HKD') {
            // HKD can only see their USER_HKD users
            $sql = "SELECT u.*, p.username as parent_username 
                    FROM users u 
                    LEFT JOIN users p ON u.parent_id = p.id 
                    WHERE u.parent_id = :user_id 
                    ORDER BY u.id DESC";
            $params[':user_id'] = $user_id;
        } else {
            // Other roles can't manage users
            return [];
        }

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting managed users: " . $e->getMessage());
        return [];
    }
}
?>