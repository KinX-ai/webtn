<?php
// Include header (handles session, database connection, etc.)
include 'includes/header.php';

// Check if user is authorized to access this page
$allowed_roles = ['USER_HKD', 'HKD', 'ADMIN', 'GS'];
if (!check_role($allowed_roles)) {
    redirect('login.php');
}

// Get current user information
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get filtered user if provided (for HKD and ADMIN roles)
$filtered_user_id = null;
$filtered_user = null;
if (($user_role == 'HKD' || $user_role == 'ADMIN' || $user_role == 'GS') && isset($_GET['user_id'])) {
    $filtered_user_id = $_GET['user_id']; // No need for (int) cast here, string "all_hkd" is valid

    if ($filtered_user_id !== 'all_hkd' && ($filtered_user_id === null || strpos($filtered_user_id, 'hkd_') !== 0)) {
        // Get user details only if not "all_hkd" or "hkd_" prefix
        $filtered_user_id = (int)$filtered_user_id;
        $filtered_user = get_user_by_id($conn, $filtered_user_id);

        // Check if user exists and current user has permission to view this user
        if (!$filtered_user || 
            ($user_role == 'HKD' && $filtered_user['parent_id'] != $user_id && $filtered_user_id != $user_id)) {
            // Redirect if not authorized or user doesn't exist
            redirect('monthly_stats.php');
        }
    }
}

// Get managed users for dropdown selection (HKD and ADMIN only)
$managed_users = [];
if ($user_role == 'HKD' || $user_role == 'ADMIN' || $user_role == 'GS') {
    $managed_users = get_managed_users($conn, $user_role, $user_id);
}

// Enable detailed error logging for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log request details
error_log("Request: " . $_SERVER['REQUEST_URI']);
error_log("User ID: " . $user_id . ", Role: " . $user_role);
if (isset($_GET['user_id'])) {
    error_log("Filtered user_id parameter: " . $_GET['user_id']);
}
// Get current month (YYYY-MM format)
$current_month = date('Y-m');
error_log("Current month: " . $current_month);

// Get date range or use current month as default if not provided
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = clean_input($_GET['start_date']);
    $end_date = clean_input($_GET['end_date']);
    // Validate date format
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date)) {
        // If invalid format, fall back to current month
        $start_date = date('Y-m-01'); // First day of current month
        $end_date = date('Y-m-t');    // Last day of current month
    }
    // Adjust end date for query to include the full day
    $end_date_for_query = $end_date . ' 23:59:59';
} else {
    // Default to current month
    $start_date = date('Y-m-01');  // First day of current month
    $end_date = date('Y-m-t');     // Last day of current month
    $end_date_for_query = $end_date . ' 23:59:59';
}

// Log date range
error_log("Date range: " . $start_date . " to " . $end_date);

// Initialize variables
$monthly_stats = [];
$total_orders = 0;
$total_quantity = 0;
$total_amount = 0;

try {
    // Get monthly statistics based on user role
    $sql = "
        SELECT 
            o.id,
            o.user_id,
            u.username,
            rt.name as resource_type,
            o.quantity,
            o.price,
            o.source,
            o.status,
            o.created_at,
            o.notification
        FROM orders o
        LEFT JOIN resource_types rt ON o.resource_type = rt.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.created_at BETWEEN :start_date AND :end_date_for_query ";

    // Add user-specific conditions based on role and filtered user
    if ($filtered_user_id === 'all_hkd') {
        // FIX: Include both HKD and USER_HKD roles
        $sql .= "AND u.role IN ('USER_HKD', 'HKD') ";
    } elseif ($filtered_user_id !== null && strpos($filtered_user_id, 'hkd_') === 0) {
        $hkd_id = substr($filtered_user_id, 4); // Extract HKD ID
        error_log("Processing HKD filter with ID: " . $hkd_id);
        // Include both HKD's own orders and their users' orders
        $sql .= "AND (u.id = :hkd_id OR u.parent_id = :hkd_id) ";
    } elseif ($filtered_user_id) {
        // If filtering by specific user
        $sql .= "AND o.user_id = :filtered_user_id ";
    } else {
        // Default filtering based on role
        if ($user_role == 'USER_HKD') {
            $sql .= "AND o.user_id = :user_id ";
        } elseif ($user_role == 'HKD') {
            // Only show orders from this HKD and their assigned users
            $sql .= "AND (o.user_id = :user_id OR o.user_id IN (SELECT id FROM users WHERE parent_id = :user_id)) ";
        } elseif ($user_role == 'ADMIN' || $user_role == 'GS') {
            // Admin and GS can see all
        }
    }

    $sql .= "ORDER BY o.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date_for_query', $end_date_for_query);

    try {
        if ($filtered_user_id !== null && strpos($filtered_user_id, 'hkd_') === 0) {
            $hkd_id = intval(substr($filtered_user_id, 4)); // Đảm bảo hkd_id là số nguyên
            error_log("Binding hkd_id parameter: " . $hkd_id . " (Type: " . gettype($hkd_id) . ")");
            // Kiểm tra xem placeholder có tồn tại trong query không
            if (strpos($sql, ':hkd_id') !== false) {
                $stmt->bindParam(':hkd_id', $hkd_id, PDO::PARAM_INT);
            } else {
                error_log("ERROR: hkd_id placeholder not found in SQL");
                throw new Exception("hkd_id placeholder not found in SQL");
            }
        } elseif ($filtered_user_id && $filtered_user_id !== 'all_hkd') {
            error_log("Binding filtered_user_id parameter: " . $filtered_user_id);
            $stmt->bindParam(':filtered_user_id', $filtered_user_id);
        } elseif ($user_role != 'ADMIN' && $filtered_user_id !== 'all_hkd' && $user_role != 'GS') {
            error_log("Binding user_id parameter: " . $user_id);
            $stmt->bindParam(':user_id', $user_id);
        }

        // Log full SQL with bound parameters for debugging
        error_log("Full SQL Query: " . $sql);

        $stmt->execute();
        error_log("SQL executed successfully");
    } catch (PDOException $e) {
        error_log("SQL Error in main query: " . $e->getMessage());
        error_log("SQL: " . $sql);

        // Chi tiết lỗi hơn cho debug
        error_log("Error code: " . $e->getCode());
        error_log("Error info: " . print_r($stmt->errorInfo(), true));

        // Hiển thị thông báo lỗi thân thiện
        echo '<div class="alert alert-danger">Đã xảy ra lỗi khi truy vấn dữ liệu. Vui lòng thử lại sau.</div>';
    } catch (Exception $e) {
        error_log("General Error: " . $e->getMessage());

        // Hiển thị thông báo lỗi thân thiện
        echo '<div class="alert alert-danger">Đã xảy ra lỗi khi xử lý dữ liệu. Vui lòng thử lại sau.</div>';
    }
    $monthly_stats = $stmt->fetchAll();

    // Calculate totals
    foreach ($monthly_stats as $stat) {
        $total_orders++;
        $total_quantity += $stat['quantity'];
        $total_amount += $stat['price'];
    }

    // Get user summary statistics (for ADMIN and HKD roles)
    $user_summary = [];
    if (($user_role == 'HKD' || $user_role == 'ADMIN' || $user_role == 'GS') && !$filtered_user_id) {
        error_log("Generating user summary for role: " . $user_role);
        $user_sql = "
            SELECT 
                u.id, 
                u.username, 
                u.role,
                COUNT(o.id) as order_count, 
                SUM(o.quantity) as total_quantity, 
                SUM(o.price) as total_amount
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id AND o.created_at BETWEEN :start_date AND :end_date_for_query
            WHERE ";

        if ($user_role == 'ADMIN' || $user_role == 'GS') {
            $user_sql .= "u.role IN ('USER_HKD', 'HKD') ";
        } else { // HKD
            // FIX: Include both HKD's own data and their users' data
            $user_sql .= "(u.id = :user_id OR u.parent_id = :user_id) ";
        }

        $user_sql .= "GROUP BY u.id
                ORDER BY total_amount DESC";

        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bindParam(':start_date', $start_date);
        $user_stmt->bindParam(':end_date_for_query', $end_date_for_query);

        if ($user_role == 'HKD') {
            $user_stmt->bindParam(':user_id', $user_id);
        }

        $user_stmt->execute();
        $user_summary = $user_stmt->fetchAll();
    }

} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
    error_log($e->getMessage());
}

// Function definition moved here
function get_status_badge($status) {
    $status = strtolower($status);
    $badge_class = '';
    $status_text = '';
    $badge_style = '';

    switch ($status) {
        case 'pending':
        case 'chờ xử lý':
            $badge_class = 'warning';
            $status_text = 'Chờ xử lý';
            $badge_style = 'color: #000; background-color: #ffc107; font-weight: bold;';
            break;
        case 'completed':
        case 'hoàn thành':
            $badge_class = 'success';
            $status_text = 'Hoàn thành';
            $badge_style = 'color: #fff; background-color: #28a745; font-weight: bold;';
            break;
        case 'cancelled':
        case 'đã hủy':
            $badge_class = 'danger';
            $status_text = 'Đã hủy';
            $badge_style = 'color: #fff; background-color: #dc3545; font-weight: bold;';
            break;
        case 'processing':
        case 'đang xử lý':
            $badge_class = 'info';
            $status_text = 'Đang xử lý';
            $badge_style = 'color: #fff; background-color: #17a2b8; font-weight: bold;';
            break;
        case 'processed':
        case 'đã xử lý':
            $badge_class = 'primary';
            $status_text = 'Đã xử lý';
            $badge_style = 'color: #fff; background-color: #007bff; font-weight: bold;';
            break;
        case 'rejected':
        case 'từ chối':
            $badge_class = 'danger';
            $status_text = 'Từ chối';
            $badge_style = 'color: #fff; background-color: #dc3545; font-weight: bold;';
            break;
        default:
            $badge_class = 'secondary';
            $status_text = $status ? $status : 'Không xác định';
            $badge_style = 'color: #fff; background-color: #6c757d; font-weight: bold;';
    }

    return '<span class="badge bg-' . $badge_class . '" style="' . $badge_style . '">' . $status_text . '</span>';
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <!-- Month selector -->
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <?php if (isset($_GET['start_date']) && isset($_GET['end_date'])): ?>
                            Thống kê từ <?php echo date('d/m/Y', strtotime($start_date)); ?> đến <?php echo date('d/m/Y', strtotime($end_date)); ?>
                        <?php else: ?>
                            Thống kê tháng <?php echo date('m/Y', strtotime($start_date)); ?>
                        <?php endif; ?>
                        <?php if ($filtered_user): ?>
                            - Người dùng: <strong><?php echo htmlspecialchars($filtered_user['username']); ?></strong>
                        <?php endif; ?>
                    </h5>

                    <!-- Removed month navigation -->
                </div>
                <?php if ($user_role == 'HKD' || $user_role == 'ADMIN' || $user_role == 'GS'): ?>
                <div class="card-body py-3">
                    <form action="" method="get" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label small mb-1">Từ ngày:</label>
                            <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" 
                                value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label small mb-1">Đến ngày:</label>
                            <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" 
                                value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="user_select" class="form-label small mb-1">Người dùng:</label>
                            <select name="user_id" id="user_select" class="form-select form-select-sm">
                                <option value="">-- Tất cả người dùng --</option>
                                <?php if ($user_role == 'ADMIN' || $user_role == 'GS'): ?>
                                <!-- <option value="all_hkd" <?php echo ($filtered_user_id === 'all_hkd') ? 'selected' : ''; ?>>
                                    Tổng hợp tất cả HKD và USER_HKD
                                </option> -->
                                <?php endif; ?>
                                <?php
                                // Lấy danh sách HKD
                                $hkd_users = array_filter($managed_users, function($user) {
                                    return $user['role'] === 'HKD';
                                });

                                if (!empty($hkd_users)): ?>
                                    <optgroup label="Tổng hợp theo HKD">
                                    <?php foreach ($hkd_users as $hkd): ?>
                                        <option value="hkd_<?php echo $hkd['id']; ?>" 
                                            <?php echo ($filtered_user_id === 'hkd_' . $hkd['id']) ? 'selected' : ''; ?>>
                                            Tổng hợp: <?php echo htmlspecialchars($hkd['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>

                                <optgroup label="Người dùng cụ thể">
                                <?php foreach ($managed_users as $managed_user): ?>
                                <option value="<?php echo $managed_user['id']; ?>" 
                                    <?php echo ($filtered_user_id == $managed_user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($managed_user['username']); ?> 
                                    (<?php echo get_role_name($managed_user['role']); ?>)
                                </option>
                                <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex">
                            <button type="submit" class="btn btn-primary btn-sm me-1">
                                <i class="fas fa-filter"></i> Lọc
                            </button>
                            <?php if ($filtered_user_id && $filtered_user_id !== 'all_hkd' && ($filtered_user_id === null || strpos($filtered_user_id, 'hkd_') !== 0)): ?>
                            <a href="?month=<?php echo $current_month; ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-undo"></i>
                            </a>
                            <?php endif; ?>
                        </div>

                        <!-- Reset các tham số phân trang khi lọc -->
                        <input type="hidden" name="user_page" value="1">
                        <input type="hidden" name="order_page" value="1">
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Summary cards -->
    <div class="row mb-4">
        <?php
        // Get total stats for all managed users if user is HKD
        $managed_total = 0;
        if ($user_role == 'HKD') {
            $managed_sql = "
                SELECT SUM(o.price) as total_amount
                FROM orders o
                JOIN users u ON o.user_id = u.id
                WHERE (u.parent_id = :user_id OR o.user_id = :user_id)
                AND o.created_at BETWEEN :start_date AND :end_date_for_query";

            $managed_stmt = $conn->prepare($managed_sql);
            $managed_stmt->bindParam(':user_id', $user_id);
            $managed_stmt->bindParam(':start_date', $start_date);
            $managed_stmt->bindParam(':end_date_for_query', $end_date_for_query);
            $managed_stmt->execute();
            $managed_result = $managed_stmt->fetch();
            $managed_total = $managed_result['total_amount'] ?: 0;
        }
        ?>

        <?php if ($user_role == 'HKD'): ?>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stats-card bg-warning text-dark h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Tổng chi tiêu (Tất cả)</h5>
                    <p class="display-4 mt-2"><?php echo number_format($managed_total); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="<?php echo $user_role == 'HKD' ? 'col-md-3' : 'col-md-4'; ?> col-sm-6 mb-3">
            <div class="card stats-card bg-primary text-white h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Tổng số đơn hàng</h5>
                    <p class="display-4 mt-2"><?php echo $total_orders; ?></p>
                </div>
            </div>
        </div>

        <div class="<?php echo $user_role == 'HKD' ? 'col-md-3' : 'col-md-4'; ?> col-sm-6 mb-3">
            <div class="card stats-card bg-success text-white h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Tổng số lượng</h5>
                    <p class="display-4 mt-2"><?php echo $total_quantity; ?></p>
                </div>
            </div>
        </div>

        <div class="<?php echo $user_role == 'HKD' ? 'col-md-3' : 'col-md-4'; ?> col-sm-6 mb-3">
            <div class="card stats-card bg-info text-white h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Tổng tiền</h5>
                    <p class="display-4 mt-2"><?php echo number_format($total_amount); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (($user_role == 'HKD' || $user_role == 'ADMIN' || $user_role == 'GS') && !empty($user_summary) && count($user_summary) > 0): ?>
    <!-- User Summary Table with Pagination -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Tóm tắt theo người dùng</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Pagination for user summary
                    $user_page = isset($_GET['user_page']) ? intval($_GET['user_page']) : 1;
                    $user_per_page = 6;
                    $user_total_pages = ceil(count($user_summary) / $user_per_page);
                    $user_start = ($user_page - 1) * $user_per_page;
                    $user_paginated = array_slice($user_summary, $user_start, $user_per_page);
                    ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Người dùng</th>
                                    <th>Loại</th>
                                    <th>Số đơn hàng</th>
                                    <th>Số lượng</th>
                                    <th>Tổng tiền</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_paginated as $user_data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user_data['username']); ?></td>
                                    <td><?php echo get_role_name($user_data['role']); ?></td>
                                    <td><?php echo $user_data['order_count']; ?></td>
                                    <td><?php echo $user_data['total_quantity'] ?: 0; ?></td>
                                    <td><?php echo number_format($user_data['total_amount'] ?: 0); ?> VNĐ</td>
                                    <td>
                                        <a href="?month=<?php echo $current_month; ?>&user_id=<?php echo $user_data['id']; ?>&order_page=1" 
                                            class="btn btn-sm btn-outline-primary">
                                            Chi tiết
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($user_total_pages > 1): ?>
                    <!-- User Pagination -->
                    <nav aria-label="Phân trang người dùng">
                        <ul class="pagination justify-content-center mt-3">
                            <?php 
                            // Build pagination URL with all parameters
                            $pagination_params = [];

                            // Add date parameters
                            $pagination_params[] = 'start_date=' . urlencode($start_date);
                            $pagination_params[] = 'end_date=' . urlencode($end_date);

                            // Add user filter if exists
                            if (isset($filtered_user_id)) {
                                $pagination_params[] = 'user_id=' . urlencode($filtered_user_id);
                            }

                            // Preserve order page
                            if (isset($_GET['order_page'])) {
                                $pagination_params[] = 'order_page=' . $_GET['order_page'];
                            }

                            $pagination_base = 'monthly_stats.php?' . implode('&', $pagination_params) . '&user_page=';

                            if ($user_page > 1): 
                            ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $pagination_base; ?>1">
                                    &laquo;
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $pagination_base . ($user_page-1); ?>">
                                    &lsaquo;
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $user_page - 2);
                            $end_page = min($user_total_pages, $user_page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo ($i == $user_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $pagination_base . $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($user_page < $user_total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $pagination_base . ($user_page+1); ?>">
                                    &rsaquo;
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $pagination_base . $user_total_pages; ?>">
                                    &raquo;
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Order Details Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Chi tiết đơn hàng</h5>
                </div>
                <div class="card-body">
                    <?php 
                    // Lấy tất cả đơn hàng của user trong tháng không phụ thuộc vào filter
                    $all_orders = [];
                    try {
                        error_log("REQUEST URI: " . $_SERVER['REQUEST_URI']);
                        error_log("Getting all orders - start_date: " . $start_date . ", end_date: " . $end_date);
                        error_log("filtered_user_id: " . ($filtered_user_id ?? 'null') . ", user_role: " . $user_role);
                        $orders_sql = "
                            SELECT 
                                o.id,
                                o.user_id,
                                u.username,
                                rt.name as resource_type,
                                o.quantity,
                                o.price,
                                o.source,
                                o.status,
                                o.created_at,
                                o.notification
                            FROM orders o
                            LEFT JOIN resource_types rt ON o.resource_type = rt.id
                            LEFT JOIN users u ON o.user_id = u.id
                            WHERE o.created_at BETWEEN :start_date AND :end_date_for_query ";

                        // Nếu có filtered_user_id cụ thể (không phải all_hkd hoặc hkd_xxx)
                        if ($filtered_user_id && $filtered_user_id !== 'all_hkd' && ($filtered_user_id === null || strpos($filtered_user_id, 'hkd_') !== 0)) {
                            $orders_sql .= "AND o.user_id = :filtered_user_id ";
                        } elseif ($user_role == 'USER_HKD') {
                            $orders_sql .= "AND o.user_id = :user_id ";
                        } elseif ($user_role == 'HKD') {
                            // Show orders from this HKD and their assigned users
                            $orders_sql .= "AND (o.user_id = :user_id OR o.user_id IN (SELECT id FROM users WHERE parent_id = :user_id)) ";
                        }

                        $orders_sql .= "ORDER BY o.created_at DESC";

                        $orders_stmt = $conn->prepare($orders_sql);
                        $orders_stmt->bindParam(':start_date', $start_date);
                        $orders_stmt->bindParam(':end_date_for_query', $end_date_for_query);

                        try {
                            if ($filtered_user_id && $filtered_user_id !== 'all_hkd' && ($filtered_user_id === null || strpos($filtered_user_id, 'hkd_') !== 0)) {
                                error_log("All orders - binding filtered_user_id: " . $filtered_user_id);
                                $orders_stmt->bindParam(':filtered_user_id', $filtered_user_id);
                            } elseif ($filtered_user_id !== null && strpos($filtered_user_id, 'hkd_') === 0) {
                                // Nếu filter là hkd_X, xử lý khác
                                error_log("All orders - detected hkd_X filter, getting all orders for HKD: " . substr($filtered_user_id, 4));

                                try {
                                    // Cần lấy lại đơn hàng của HKD và các user của họ
                                    $hkd_id = intval(substr($filtered_user_id, 4));
                                    error_log("HKD ID extracted: " . $hkd_id . " (Type: " . gettype($hkd_id) . ")");

                                    // Kiểm tra xem HKD tồn tại không
                                    $check_hkd_sql = "SELECT id, username FROM users WHERE id = :hkd_id AND role = 'HKD'";
                                    $check_stmt = $conn->prepare($check_hkd_sql);
                                    $check_stmt->bindParam(':hkd_id', $hkd_id, PDO::PARAM_INT);
                                    $check_stmt->execute();
                                    $hkd_exists = $check_stmt->fetch(PDO::FETCH_ASSOC);

                                    if (!$hkd_exists) {
                                        error_log("WARNING: HKD with ID " . $hkd_id . " not found or not a HKD user");
                                    } else {
                                        error_log("Found HKD: " . $hkd_exists['username'] . " (ID: " . $hkd_exists['id'] . ")");
                                    }

                                    // Thay đổi SQL query để lấy đơn hàng của HKD và user của họ
                                    $orders_sql = "
                                        SELECT 
                                            o.id,
                                            o.user_id,
                                            u.username,
                                            rt.name as resource_type,
                                            o.quantity,
                                            o.price,
                                            o.source,
                                            o.status,
                                            o.created_at,
                                            o.notification
                                        FROM orders o
                                        LEFT JOIN resource_types rt ON o.resource_type = rt.id
                                        LEFT JOIN users u ON o.user_id = u.id
                                        WHERE o.created_at BETWEEN :start_date AND :end_date_for_query
                                        AND (u.id = :hkd_id OR u.parent_id = :hkd_id)
                                        ORDER BY o.created_at DESC";

                                    $orders_stmt = $conn->prepare($orders_sql);
                                    $orders_stmt->bindParam(':start_date', $start_date);
                                    $orders_stmt->bindParam(':end_date_for_query', $end_date_for_query);
                                    $orders_stmt->bindParam(':hkd_id', $hkd_id, PDO::PARAM_INT);

                                    error_log("All orders - modified SQL for HKD: " . $orders_sql);
                                } catch (Exception $e) {
                                    error_log("ERROR processing hkd_ filter: " . $e->getMessage());
                                    // Fallback để hiển thị bảng trống thay vì lỗi 500
                                    $all_orders = [];
                                }
                            } elseif ($user_role == 'USER_HKD' || $user_role == 'HKD') {
                                error_log("All orders - binding user_id: " . $user_id);
                                $orders_stmt->bindParam(':user_id', $user_id);
                            }

                            error_log("Full all_orders SQL: " . $orders_sql);
                            $orders_stmt->execute();
                            error_log("All orders SQL executed successfully");
                        } catch (PDOException $e) {
                            error_log("SQL Error in all_orders query: " . $e->getMessage());
                            error_log("SQL: " . $orders_sql);
                            error_log("Error code: " . $e->getCode());
                            error_log("Error info: " . print_r($orders_stmt->errorInfo(), true));

                            // Hiển thị thông báo lỗi thân thiện thay vì throw exception
                            echo '<div class="alert alert-danger">Đã xảy ra lỗi khi tải thông tin đơn hàng. Chi tiết lỗi đã được ghi lại.</div>';
                            $all_orders = []; // Đặt là mảng rỗng để tránh lỗi
                        }
                        $all_orders = $orders_stmt->fetchAll();
                    } catch (PDOException $e) {
                        error_log("Error getting all orders: " . $e->getMessage());
                    }
                    ?>

                    <?php if (count($all_orders) > 0): ?>
                    <?php
                    // Pagination for orders
                    $order_page = isset($_GET['order_page']) ? intval($_GET['order_page']) : 1;
                    $order_per_page = 6;
                    $order_total_pages = ceil(count($all_orders) / $order_per_page);
                    $order_start = ($order_page - 1) * $order_per_page;
                    $order_paginated = array_slice($all_orders, $order_start, $order_per_page);
                    ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover" style="width: 100%">
                            <thead>
                                <tr>
                                    <th style="min-width: 50px">ID</th>
                                    <?php if ($user_role == 'HKD' || $user_role == 'ADMIN' || $user_role == 'GS'): ?>
                                    <th style="min-width: 120px">Người dùng</th>
                                    <?php endif; ?>
                                    <th style="min-width: 150px">Loại tài nguyên</th>
                                    <th style="min-width: 100px">Số lượng</th>
                                    <th style="min-width: 120px">Giá tiền</th>
                                    <th style="min-width: 120px">Ghi chú</th>
                                    <th style="min-width: 120px">Trạng thái</th>
                                    <th style="min-width: 150px">Thời gian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_paginated as $order): ?>
                                <tr>
                                    <td><?php echo $order['id']; ?></td>
                                    <?php if ($user_role == 'HKD' || $user_role == 'ADMIN' || $user_role == 'GS'): ?>
                                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($order['resource_type']); ?></td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td><?php echo number_format($order['price']); ?> VNĐ</td>
                                    <td><?php echo htmlspecialchars($order['source']); ?></td>
                                    <td><?php echo get_status_badge($order['status']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($order_total_pages > 1): ?>
                    <!-- Order Pagination -->
                    <nav aria-label="Phân trang đơn hàng">
                        <ul class="pagination justify-content-center mt-3">
                            <?php 
                            // Build pagination URL with all parameters
                            $order_pagination_params = [];

                            // Add date parameters
                            $order_pagination_params[] = 'start_date=' . urlencode($start_date);
                            $order_pagination_params[] = 'end_date=' . urlencode($end_date);

                            // Add user filter if exists
                            if (isset($filtered_user_id)) {
                                $order_pagination_params[] = 'user_id=' . urlencode($filtered_user_id);
                            }

                            // Preserve user page
                            if (isset($_GET['user_page'])) {
                                $order_pagination_params[] = 'user_page=' . $_GET['user_page'];
                            }

                            $order_pagination_base = 'monthly_stats.php?' . implode('&', $order_pagination_params) . '&order_page=';

                            if ($order_page > 1): 
                            ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $order_pagination_base; ?>1">
                                    &laquo;
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $order_pagination_base . ($order_page-1); ?>">
                                    &lsaquo;
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $order_page - 2);
                            $end_page = min($order_total_pages, $order_page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo ($i == $order_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $order_pagination_base . $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($order_page < $order_total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $order_pagination_base . ($order_page+1); ?>">
                                    &rsaquo;
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $order_pagination_base . $order_total_pages; ?>">
                                    &raquo;
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>

                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Không có dữ liệu cho tháng này
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Log lỗi client-side vào console
window.onerror = function(message, source, lineno, colno, error) {
    console.error("JavaScript Error:", message, "at", source, ":", lineno, ":", colno);
    return false;
};

// Toggle between monthly and date range filters
function toggleDateFilter() {
    if (document.getElementById('filter_monthly').checked) {
        document.getElementById('monthly_filter').style.display = 'block';
        document.getElementById('date_range_filter').style.display = 'none';
    } else {
        document.getElementById('monthly_filter').style.display = 'none';
        document.getElementById('date_range_filter').style.display = 'flex';
    }
}

// Ensure correct date format
document.addEventListener('DOMContentLoaded', function() {

    // Set default date values if empty
    var startDateInput = document.getElementById('start_date');
    var endDateInput = document.getElementById('end_date');

    if (!startDateInput.value) {
        var now = new Date();
        var firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        startDateInput.value = firstDay.toISOString().split('T')[0];
    }

    if (!endDateInput.value) {
        var now = new Date();
        var lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        endDateInput.value = lastDay.toISOString().split('T')[0];
    }
});

// Ghi log lỗi AJAX requests
(function() {
    var origOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function(method, url) {
        this.addEventListener('load', function() {
            if (this.status >= 400) {
                console.error('XHR Error:', method, url, this.status, this.statusText);
            }
        });
        origOpen.apply(this, arguments);
    };
})();

console.log("Current page: " + window.location.href);
</script>

<?php include 'includes/footer.php'; ?>
