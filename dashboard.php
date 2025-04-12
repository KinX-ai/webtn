<?php
// Include header (handles session, database connection, etc.)
include 'includes/header.php';

// Check if user is authorized to access this page
$allowed_roles = ['USER_HKD', 'HKD', 'ADMIN', 'GS'];
if (!check_role($allowed_roles)) {
    redirect('login.php');
}

// Get current user details
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get stats based on user role
$stats = [];
$recent_orders = [];
$managed_users = [];

try {
    // Get number of orders for current user
    $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $stats['user_orders'] = $stmt->fetchColumn();

    // Get pending orders for current user
    $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :user_id AND status = 'Pending'");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $stats['user_pending_orders'] = $stmt->fetchColumn();

    // Get total spent for current user
    $stmt = $conn->prepare("SELECT SUM(price) FROM orders WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $stats['user_total_spent'] = $stmt->fetchColumn() ?: 0;

    // Get recent orders for current user
    $stmt = $conn->prepare("
        SELECT o.*, r.name as resource_name 
        FROM orders o
        LEFT JOIN resource_types r ON o.resource_type = r.id
        WHERE o.user_id = :user_id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $recent_orders = $stmt->fetchAll();

    // Additional stats for HKD and ADMIN roles
    if ($user_role == 'HKD' || $user_role == 'ADMIN' || $user_role == 'GS') {
        // Get managed users
        $managed_users = get_managed_users($conn, $user_role, $user_id);
        $stats['managed_users'] = count($managed_users);

        if ($user_role == 'HKD') {
            // Get total pending orders for managed users (including own)
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM orders o
                WHERE (o.user_id = :user_id OR o.user_id IN (SELECT id FROM users WHERE parent_id = :user_id))
                AND o.status = 'Pending'
            ");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $stats['hkd_total_pending'] = $stmt->fetchColumn();

            // Get total spent for all managed users (including own)
            $stmt = $conn->prepare("
                SELECT SUM(o.price) 
                FROM orders o
                WHERE (o.user_id = :user_id OR o.user_id IN (SELECT id FROM users WHERE parent_id = :user_id))
            ");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $stats['hkd_total_spent'] = $stmt->fetchColumn() ?: 0;
        }

        // For ADMIN, get more comprehensive stats
        if ($user_role == 'ADMIN') {
            // Total number of orders
            $stmt = $conn->prepare("SELECT COUNT(*) FROM orders");
            $stmt->execute();
            $stats['total_orders'] = $stmt->fetchColumn();

            // Pending orders
            $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE status = 'Pending'");
            $stmt->execute();
            $stats['pending_orders'] = $stmt->fetchColumn();

            // Total number of users
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
            $stmt->execute();
            $stats['total_users'] = $stmt->fetchColumn();

            // Active users
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE status = 1");
            $stmt->execute();
            $stats['active_users'] = $stmt->fetchColumn();

            // Resource types
            $stmt = $conn->prepare("SELECT COUNT(*) FROM resource_types");
            $stmt->execute();
            $stats['resource_types'] = $stmt->fetchColumn();

            // Total spent across all orders
            $stmt = $conn->prepare("SELECT SUM(price) FROM orders");
            $stmt->execute();
            $stats['total_spent'] = $stmt->fetchColumn() ?: 0;
        }
    }

    // Log dashboard access
    add_log($conn, $user_id, 'view_dashboard', json_encode([
        'time' => date('Y-m-d H:i:s')
    ]));

} catch (PDOException $e) {
    // Log error
    error_log("Dashboard error: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <!-- Welcome section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card fade-in">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="welcome-icon me-4">
                            <i class="fas fa-hand-wave fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h4 class="card-title mb-1">Xin chào, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h4>
                            <p class="card-text mb-0">
                                Chào mừng bạn đến với Hệ thống quản lý HKD. Bạn đang đăng nhập với quyền 
                                <span class="badge bg-primary"><?php echo get_role_name($_SESSION['user_role']); ?></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats overview -->
    <div class="row mb-4">
        <!-- Orders for current user -->
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <div class="dashboard-icon mx-auto">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h5 class="card-title">Đơn hàng của bạn</h5>
                    <h2 class="card-text"><?php echo isset($stats['user_orders']) ? $stats['user_orders'] : 0; ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="order.php" class="btn btn-sm btn-outline-primary w-100">Xem chi tiết</a>
                </div>
            </div>
        </div>

        <!-- Pending orders for current user -->
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <div class="dashboard-icon mx-auto">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h5 class="card-title">Đơn hàng đang xử lý</h5>
                    <h2 class="card-text"><?php echo isset($stats['user_pending_orders']) ? $stats['user_pending_orders'] : 0; ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="order.php" class="btn btn-sm btn-outline-primary w-100">Xem chi tiết</a>
                </div>
            </div>
        </div>

        <!-- Total spent for current user -->
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <div class="dashboard-icon mx-auto">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h5 class="card-title">Số tiền đã chi tiêu</h5>
                    <h2 class="card-text"><?php echo number_format($stats['user_total_spent']); ?> VNĐ</h2>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="monthly_stats.php" class="btn btn-sm btn-outline-primary w-100">Xem thống kê</a>
                </div>
            </div>
        </div>

        <?php if ($user_role == 'HKD' || $user_role == 'ADMIN' || $user_role == 'GS'): ?>
        <!-- Managed users -->
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <div class="dashboard-icon mx-auto">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5 class="card-title">Tài khoản quản lý</h5>
                    <h2 class="card-text"><?php echo isset($stats['managed_users']) ? $stats['managed_users'] : 0; ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="account_management.php" class="btn btn-sm btn-outline-primary w-100">Quản lý tài khoản</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($user_role == 'HKD'): ?>
    <!-- HKD total stats -->
    <div class="row mb-4">
        <!-- Total pending orders for all managed users -->
        <div class="col-md-6 col-sm-6 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-clock me-2"></i>Tổng đơn hàng đang xử lý</h5>
                    <p class="card-text fs-4"><?php echo isset($stats['hkd_total_pending']) ? $stats['hkd_total_pending'] : 0; ?></p>
                    <p class="card-text text-muted">Tổng số đơn hàng đang xử lý của bạn và các tài khoản quản lý</p>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="return_order.php" class="btn btn-sm btn-outline-primary">Xử lý đơn hàng</a>
                </div>
            </div>
        </div>

        <!-- Total spent for all managed users -->
        <div class="col-md-6 col-sm-6 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-money-bill-wave me-2"></i>Tổng chi tiêu</h5>
                    <p class="card-text fs-4"><?php echo number_format($stats['hkd_total_spent']); ?> VNĐ</p>
                    <p class="card-text text-muted">Tổng số tiền đã chi tiêu của bạn và các tài khoản quản lý</p>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="monthly_stats.php" class="btn btn-sm btn-outline-primary">Xem thống kê</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($user_role == 'ADMIN' || $user_role == 'GS'): ?>
    <!-- Admin stats -->
    <div class="row mb-4">
        <!-- Pending orders -->
        <div class="col-md-4 col-sm-6 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <div class="dashboard-icon mx-auto">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h5 class="card-title">Đơn hàng chờ xử lý</h5>
                    <h2 class="card-text"><?php echo isset($stats['pending_orders']) ? $stats['pending_orders'] : 0; ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="return_order.php" class="btn btn-sm btn-outline-primary w-100">Xử lý đơn hàng</a>
                </div>
            </div>
        </div>

        <!-- Total orders -->
        <div class="col-md-4 col-sm-6 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <div class="dashboard-icon mx-auto">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h5 class="card-title">Tổng số đơn hàng</h5>
                    <h2 class="card-text"><?php echo isset($stats['total_orders']) ? $stats['total_orders'] : 0; ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="monthly_stats.php" class="btn btn-sm btn-outline-primary w-100">Xem thống kê</a>
                </div>
            </div>
        </div>

        <!-- Total spent -->
        <div class="col-md-4 col-sm-6 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <div class="dashboard-icon mx-auto">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h5 class="card-title">Tổng chi tiêu</h5>
                    <h2 class="card-text"><?php echo number_format($stats['total_spent']); ?> VNĐ</h2>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="monthly_stats.php" class="btn btn-sm btn-outline-primary w-100">Xem chi tiết</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- Additional info for ADMIN (Resource types, user stats) -->
    <?php if ($user_role == 'ADMIN'): ?>
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <h6 class="card-title">Tổng số tài khoản</h6>
                    <p class="card-text fs-4"><?php echo isset($stats['total_users']) ? $stats['total_users'] : 0; ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <h6 class="card-title">Tài khoản đang hoạt động</h6>
                    <p class="card-text fs-4"><?php echo isset($stats['active_users']) ? $stats['active_users'] : 0; ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-sm-12 mb-3">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <h6 class="card-title">Loại tài nguyên</h6>
                    <p class="card-text fs-4"><?php echo isset($stats['resource_types']) ? $stats['resource_types'] : 0; ?> loại</p>
                    <p class="card-text text-muted">Cấu hình các loại tài nguyên để sử dụng trong hệ thống</p>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="config_resource.php" class="btn btn-sm btn-outline-primary">Cấu hình tài nguyên</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent orders -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card fade-in">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Đơn hàng gần đây của bạn</h5>
                        <small class="text-muted">Danh sách các đơn hàng bạn đã tạo gần đây</small>
                    </div>
                    <a href="order.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle me-1"></i> Tạo đơn hàng
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_orders) > 0): ?>
                    <div class="table-filter">
                        <input type="text" class="form-control" id="orderTableSearch" placeholder="Tìm kiếm đơn hàng..." data-table="recentOrdersTable">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="recentOrdersTable">
                            <thead>
                                <tr>
                                    <th width="80">ID</th>
                                    <th>Loại tài nguyên</th>
                                    <th width="100">Số lượng</th>
                                    <th width="140">Trạng thái</th>
                                    <th width="180">Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $loop_count = 0; foreach ($recent_orders as $order): ?>
                                <tr class="scale-in" style="animation-delay: <?php echo 0.05 * $loop_count++; ?>s">
                                    <td><strong>#<?php echo $order['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['resource_name']); ?></td>
                                    <td class="text-center"><?php echo $order['quantity']; ?></td>
                                    <td>
                                        <?php 
                                            $statusClass = 'secondary';
                                            $statusIcon = 'circle-info';

                                            if ($order['status'] == 'Processed') {
                                                $statusClass = 'success';
                                                $statusIcon = 'check-circle';
                                            } elseif ($order['status'] == 'Rejected') {
                                                $statusClass = 'danger';
                                                $statusIcon = 'times-circle';
                                            } elseif ($order['status'] == 'Pending') {
                                                $statusClass = 'warning';
                                                $statusIcon = 'clock';
                                            }
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                            <?php echo get_order_status($order['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_date($order['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state text-center py-5">
                        <i class="fas fa-shopping-basket fa-4x text-muted mb-3"></i>
                        <h5>Không có đơn hàng</h5>
                        <p class="text-muted">Bạn chưa có đơn hàng nào. Hãy tạo đơn hàng mới ngay!</p>
                        <a href="order.php" class="btn btn-primary mt-3">
                            <i class="fas fa-plus-circle me-1"></i> Tạo đơn hàng mới
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($user_role == 'HKD' || $user_role == 'ADMIN' || $user_role == 'GS'): ?>
    <!-- Managed users section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Tài khoản quản lý</h5>
                    <a href="account_management.php" class="btn btn-sm btn-outline-primary">Quản lý</a>
                </div>
                <div class="card-body">
                    <?php if (count($managed_users) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên đăng nhập</th>
                                    <th>Email</th>
                                    <th>Quyền</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($managed_users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo get_role_name($user['role']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['status'] ? 'success' : 'danger'; ?>">
                                            <?php echo $user['status'] ? 'Hoạt động' : 'Bị khóa'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">Không có tài khoản nào trong quyền quản lý của bạn.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>