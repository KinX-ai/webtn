<?php
// Include header (handles session, database connection, etc.)
include 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

// Get current user information
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Initialize variables
$current_month = isset($_GET['month']) ? clean_input($_GET['month']) : date('Y-m');
list($year, $month) = explode('-', $current_month);
$start_date = $current_month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// Define query based on user role
$expenses = [];
$total_expenses = 0;
$users_under_management = [];

try {
    // Get expenses data based on user role
    if ($user_role == 'ADMIN') {
        // ADMIN can see all expenses
        $sql = "
            SELECT 
                o.id, o.user_id, o.resource_type, o.quantity, o.price, 
                o.source, o.created_at, o.status,
                u.username, u.role,
                r.name as resource_name,
                p.username as parent_username
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN resource_types r ON o.resource_type = r.id
            LEFT JOIN users p ON u.parent_id = p.id
            WHERE o.created_at BETWEEN :start_date AND :end_date
            AND o.status = 'Processed'
            ORDER BY o.created_at DESC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $expenses = $stmt->fetchAll();
        
    } elseif ($user_role == 'HKD') {
        // HKD can see their own expenses and expenses of assigned USER_HKD accounts
        $sql = "
            WITH assigned_users AS (
                SELECT id FROM users WHERE parent_id = :user_id OR id = :user_id
            )
            SELECT 
                o.id, o.user_id, o.resource_type, o.quantity, o.price, 
                o.source, o.created_at, o.status,
                u.username, u.role,
                r.name as resource_name,
                p.username as parent_username
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN resource_types r ON o.resource_type = r.id
            LEFT JOIN users p ON u.parent_id = p.id
            WHERE o.user_id IN (SELECT id FROM assigned_users)
            AND o.created_at BETWEEN :start_date AND :end_date
            AND o.status = 'Processed'
            ORDER BY o.created_at DESC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $expenses = $stmt->fetchAll();
        
        // Get list of users under management
        $users_stmt = $conn->prepare("SELECT id, username FROM users WHERE parent_id = :user_id");
        $users_stmt->bindParam(':user_id', $user_id);
        $users_stmt->execute();
        $users_under_management = $users_stmt->fetchAll();
        
    } else {
        // Regular users (USER_HKD) can only see their own expenses
        $sql = "
            SELECT 
                o.id, o.user_id, o.resource_type, o.quantity, o.price, 
                o.source, o.created_at, o.status,
                u.username, u.role,
                r.name as resource_name,
                p.username as parent_username
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN resource_types r ON o.resource_type = r.id
            LEFT JOIN users p ON u.parent_id = p.id
            WHERE o.user_id = :user_id
            AND o.created_at BETWEEN :start_date AND :end_date
            AND o.status = 'Processed'
            ORDER BY o.created_at DESC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $expenses = $stmt->fetchAll();
    }
    
    // Calculate total expenses
    $total_expenses = array_sum(array_column($expenses, 'price'));
    
    // Log access to expenses page
    add_log($conn, $user_id, 'view_expenses', json_encode([
        'month' => $current_month,
        'time' => date('Y-m-d H:i:s')
    ]));
    
} catch (PDOException $e) {
    $error_message = "Lỗi truy vấn dữ liệu: " . $e->getMessage();
}

// Get previous and next month links
$prev_month = date('Y-m', strtotime($current_month . ' -1 month'));
$next_month = date('Y-m', strtotime($current_month . ' +1 month'));
$current_month_name = date('m/Y', strtotime($current_month));

?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Báo cáo chi tiêu tháng <?php echo $current_month_name; ?></h5>
                    <div class="btn-group">
                        <a href="?month=<?php echo $prev_month; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-chevron-left"></i> Tháng trước
                        </a>
                        <a href="?month=<?php echo date('Y-m'); ?>" class="btn btn-outline-secondary btn-sm">
                            Tháng hiện tại
                        </a>
                        <?php if (strtotime($next_month) <= strtotime(date('Y-m'))): ?>
                        <a href="?month=<?php echo $next_month; ?>" class="btn btn-outline-primary btn-sm">
                            Tháng sau <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Summary cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Tổng chi tiêu</h5>
                                    <h2 class="display-6"><?php echo number_format($total_expenses, 0, ',', '.'); ?> VNĐ</h2>
                                    <p class="card-text">Tháng <?php echo $current_month_name; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($user_role == 'HKD' || $user_role == 'ADMIN'): ?>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Số đơn hàng</h5>
                                    <h2 class="display-6"><?php echo count($expenses); ?></h2>
                                    <p class="card-text">Tháng <?php echo $current_month_name; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Chi tiêu bình quân</h5>
                                    <h2 class="display-6">
                                        <?php 
                                        $average = (count($expenses) > 0) ? $total_expenses / count($expenses) : 0;
                                        echo number_format($average, 0, ',', '.');
                                        ?> VNĐ
                                    </h2>
                                    <p class="card-text">Trung bình mỗi đơn hàng</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($user_role == 'HKD' && !empty($users_under_management)): ?>
                    <!-- User breakdown for HKD -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Chi tiêu theo nhân viên</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Nhân viên</th>
                                                    <th>Số đơn hàng</th>
                                                    <th>Tổng chi tiêu</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                // Calculate per-user statistics
                                                $user_stats = [];
                                                foreach ($expenses as $expense) {
                                                    $uid = $expense['user_id'];
                                                    if (!isset($user_stats[$uid])) {
                                                        $user_stats[$uid] = [
                                                            'username' => $expense['username'],
                                                            'count' => 0,
                                                            'total' => 0
                                                        ];
                                                    }
                                                    $user_stats[$uid]['count']++;
                                                    $user_stats[$uid]['total'] += $expense['price'];
                                                }
                                                
                                                // Display user statistics
                                                foreach ($user_stats as $stat):
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($stat['username']); ?></td>
                                                    <td><?php echo $stat['count']; ?></td>
                                                    <td><?php echo number_format($stat['total'], 0, ',', '.'); ?> VNĐ</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Expense details -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Chi tiết chi tiêu</h5>
                            <div class="input-group" style="width: 250px;">
                                <input type="text" class="form-control form-control-sm" id="searchExpenseInput" placeholder="Tìm kiếm..." onkeyup="filterTable('searchExpenseInput', 'expenseTable')">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="expenseTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <?php if ($user_role == 'ADMIN' || $user_role == 'HKD'): ?>
                                            <th>Người dùng</th>
                                            <?php endif; ?>
                                            <th>Loại tài nguyên</th>
                                            <th>Số lượng</th>
                                            <th>Giá tiền</th>
                                            <th>Nguồn</th>
                                            <th>Ngày đặt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($expenses)): ?>
                                            <?php foreach ($expenses as $expense): ?>
                                            <tr>
                                                <td><?php echo $expense['id']; ?></td>
                                                <?php if ($user_role == 'ADMIN' || $user_role == 'HKD'): ?>
                                                <td>
                                                    <?php echo htmlspecialchars($expense['username']); ?>
                                                    <?php if ($expense['role'] == 'USER_HKD'): ?>
                                                    <span class="badge bg-secondary">USER</span>
                                                    <?php elseif ($expense['role'] == 'HKD'): ?>
                                                    <span class="badge bg-primary">HKD</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endif; ?>
                                                <td><?php echo htmlspecialchars($expense['resource_name']); ?></td>
                                                <td><?php echo $expense['quantity']; ?></td>
                                                <td><?php echo number_format($expense['price'], 0, ',', '.'); ?> VNĐ</td>
                                                <td><?php echo htmlspecialchars($expense['source']); ?></td>
                                                <td><?php echo format_date($expense['created_at']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?php echo ($user_role == 'ADMIN' || $user_role == 'HKD') ? '7' : '6'; ?>" class="text-center">
                                                    Không có dữ liệu chi tiêu nào trong tháng này
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>