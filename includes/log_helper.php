<?php
/**
 * Helper functions for logging user activities
 */

/**
 * Add a log entry to the database
 * 
 * @param PDO $conn Database connection
 * @param int $user_id ID of the user performing the action
 * @param string $action Action performed (login, edit, create_order, etc.)
 * @param string $details Details of the action
 * @return bool Success or failure
 */
function add_log($conn, $user_id, $action, $details) {
    try {
        $stmt = $conn->prepare("INSERT INTO logs (user_id, action, details, created_at) 
                                VALUES (:user_id, :action, :details, NOW())");

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindParam(':details', $details, PDO::PARAM_STR);

        return $stmt->execute();
    } catch (PDOException $e) {
        // Log error to PHP error log
        error_log("Add log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log a login attempt
 * 
 * @param PDO $conn Database connection
 * @param string $username Username attempting to login
 * @param bool $success Whether login was successful
 * @param int $user_id User ID (if login successful)
 * @return void
 */
function log_login($conn, $username, $success, $user_id = null) {
    $action = 'login';
    $ip = $_SERVER['REMOTE_ADDR'];
    $details = json_encode([
        'username' => $username,
        'success' => $success,
        'ip' => $ip,
        'time' => date('Y-m-d H:i:s')
    ]);

    if ($success && $user_id) {
        add_log($conn, $user_id, $action, $details);
    } else {
        // For failed logins, we don't have a user_id, so we'll use 0
        add_log($conn, 0, $action, $details);
    }
}

// This function has been moved to functions.php to avoid duplication

/**
 * Log order related actions
 * 
 * @param PDO $conn Database connection
 * @param int $user_id ID of the user performing the action
 * @param string $action 'create_order', 'update_order_status'
 * @param int $order_id ID of the order
 * @param array $details Additional details
 * @return void
 */
function log_order_action($conn, $user_id, $action, $order_id, $details = []) {
    $log_details = json_encode([
        'time' => date('Y-m-d H:i:s'),
        'order_id' => $order_id,
        'details' => $details
    ]);

    add_log($conn, $user_id, $action, $log_details);
}

/**
 * Log resource type actions
 * 
 * @param PDO $conn Database connection
 * @param int $user_id ID of the user performing the action
 * @param string $action 'add_resource_type', 'edit_resource_type', 'delete_resource_type'
 * @param int $resource_id ID of the resource type (if applicable)
 * @param array $details Additional details
 * @return void
 */
function log_resource_action($conn, $user_id, $action, $resource_id = null, $details = []) {
    $log_details = json_encode([
        'time' => date('Y-m-d H:i:s'),
        'resource_id' => $resource_id,
        'details' => $details
    ]);

    add_log($conn, $user_id, $action, $log_details);
}

/**
 * Get logs with optional filtering
 * 
 * @param PDO $conn Database connection
 * @param array $filters Filters to apply (user_id, action, start_date, end_date)
 * @param int $limit Maximum number of logs to return (0 for no limit)
 * @param int $offset Offset for pagination
 * @return array Array of log entries
 */
function get_logs($conn, $filters = [], $limit = 0, $offset = 0) {
    try {
        $where_clauses = [];
        $params = [];

        // Build WHERE clause based on filters
        if (isset($filters['user_id']) && $filters['user_id'] > 0) {
            $where_clauses[] = "user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (isset($filters['action']) && !empty($filters['action'])) {
            $where_clauses[] = "action = :action";
            $params[':action'] = $filters['action'];
        }

        if (isset($filters['start_date']) && !empty($filters['start_date'])) {
            $where_clauses[] = "created_at >= :start_date";
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
        }

        if (isset($filters['end_date']) && !empty($filters['end_date'])) {
            $where_clauses[] = "created_at <= :end_date";
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }

        // Build the SQL query
        $sql = "SELECT l.*, u.username 
                FROM logs l 
                LEFT JOIN users u ON l.user_id = u.id";

        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $sql .= " ORDER BY l.created_at DESC";

        if ($limit > 0) {
            $sql .= " LIMIT :offset, :limit";
            $params[':offset'] = $offset;
            $params[':limit'] = $limit;
        }

        $stmt = $conn->prepare($sql);

        // Bind parameters
        foreach ($params as $key => $value) {
            if ($key == ':limit' || $key == ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        // Log error
        error_log("Get logs error: " . $e->getMessage());
        return [];
    }
}
?>