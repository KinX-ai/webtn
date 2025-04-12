
<?php
include 'includes/header.php';

// Check if user is authorized
$allowed_roles = ['ADMIN', 'HKD', 'GS'];
if (!check_role($allowed_roles)) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $status = isset($_POST['status']) ? clean_input($_POST['status']) : '';
    $notification = isset($_POST['notification']) ? clean_input($_POST['notification']) : '';
    $account_id = isset($_POST['account_id']) ? clean_input($_POST['account_id']) : '';

    try {
        // Get order details first
        $stmt = $conn->prepare("
            SELECT o.*, u.parent_id 
            FROM fb_orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = :order_id
        ");
        $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt->execute();
        $order = $stmt->fetch();

        // Check if user has permission to update this order
        $can_update = false;
        if ($user_role == 'ADMIN') {
            $can_update = true;
        } elseif ($user_role == 'HKD' && $order['parent_id'] == $user_id) {
            $can_update = true;
        }

        if ($can_update) {
            // Lấy thông tin fb_config_id cho đơn hàng này
            if (!empty($account_id)) {
                // Tạo một cấu hình mới chỉ dành riêng cho đơn hàng này
                $stmt_get_config = $conn->prepare("
                    SELECT fc.* FROM fb_configs fc
                    JOIN fb_orders fo ON fo.fb_config_id = fc.id
                    WHERE fo.id = :order_id
                ");
                $stmt_get_config->bindParam(':order_id', $order_id, PDO::PARAM_INT);
                $stmt_get_config->execute();
                $config = $stmt_get_config->fetch();
                
                if ($config) {
                    // Tạo một bản sao của config hiện tại với account_id mới
                    $stmt_new_config = $conn->prepare("
                        INSERT INTO fb_configs (name, account_id, source, account_type_id)
                        VALUES (:name, :account_id, :source, :account_type_id)
                    ");
                    
                    $config_name = $config['name'] . " (Đơn #" . $order_id . ")";
                    
                    $stmt_new_config->bindParam(':name', $config_name, PDO::PARAM_STR);
                    $stmt_new_config->bindParam(':account_id', $account_id, PDO::PARAM_STR);
                    $stmt_new_config->bindParam(':source', $config['source'], PDO::PARAM_STR);
                    $stmt_new_config->bindParam(':account_type_id', $config['account_type_id'], PDO::PARAM_INT);
                    $stmt_new_config->execute();
                    
                    $new_config_id = $conn->lastInsertId();
                    
                    // Cập nhật đơn hàng với fb_config_id mới
                    $stmt_update_order = $conn->prepare("
                        UPDATE fb_orders 
                        SET fb_config_id = :new_config_id 
                        WHERE id = :order_id
                    ");
                    $stmt_update_order->bindParam(':new_config_id', $new_config_id, PDO::PARAM_INT);
                    $stmt_update_order->bindParam(':order_id', $order_id, PDO::PARAM_INT);
                    $stmt_update_order->execute();
                }
            }
            
            $stmt = $conn->prepare("
                UPDATE fb_orders 
                SET status = :status, notification = :notification 
                WHERE id = :order_id
            ");
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':notification', $notification, PDO::PARAM_STR);
            $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                log_order_action($conn, $user_id, 'update_fb_order_status', $order_id, [
                    'old_status' => $order['status'],
                    'new_status' => $status,
                    'notification' => $notification
                ]);
                $success_message = "Đã cập nhật trạng thái đơn hàng thành công";
            } else {
                $error_message = "Không thể cập nhật trạng thái đơn hàng";
            }
        } else {
            $error_message = "Bạn không có quyền cập nhật đơn hàng này";
        }
    } catch (PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get filters from URL parameters
$status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$search_filter = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? clean_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? clean_input($_GET['date_to']) : '';

// Pagination settings
$items_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total count first
try {
    $count_sql = "
        SELECT COUNT(*) 
        FROM fb_orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN fb_configs c ON o.fb_config_id = c.id
        LEFT JOIN fb_sources s ON o.source = s.id
        WHERE 1=1
    ";

    if ($user_role == 'HKD') {
        $count_sql .= " AND (o.user_id = :user_id OR u.parent_id = :user_id)";
    }
    
    // Add filters to query
    if (!empty($status_filter)) {
        $count_sql .= " AND o.status = :status";
    }
    
    if (!empty($search_filter)) {
        $count_sql .= " AND (u.username LIKE :search OR c.account_id LIKE :search OR o.notification LIKE :search OR s.name LIKE :search)";
    }
    
    if (!empty($date_from)) {
        $count_sql .= " AND DATE(o.created_at) >= :date_from";
    }
    
    if (!empty($date_to)) {
        $count_sql .= " AND DATE(o.created_at) <= :date_to";
    }

    $count_stmt = $conn->prepare($count_sql);
    
    if ($user_role == 'HKD') {
        $count_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }
    
    // Bind filter parameters
    if (!empty($status_filter)) {
        $count_stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
    }
    
    if (!empty($search_filter)) {
        $search_param = '%' . $search_filter . '%';
        $count_stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    
    if (!empty($date_from)) {
        $count_stmt->bindParam(':date_from', $date_from, PDO::PARAM_STR);
    }
    
    if (!empty($date_to)) {
        $count_stmt->bindParam(':date_to', $date_to, PDO::PARAM_STR);
    }
    
    $count_stmt->execute();
    $total_orders = $count_stmt->fetchColumn();
    $total_pages = ceil($total_orders / $items_per_page);
} catch (PDOException $e) {
    $error_message = "Error counting orders: " . $e->getMessage();
}

// Get paginated orders based on user role
try {
    $sql = "
        SELECT 
            o.id, o.user_id, o.fb_config_id, o.quantity, o.status, o.notification, o.created_at,
            c.name as config_name, c.account_id, 
            s.name as source_name,
            u.username, u.parent_id,
            p.username as parent_username,
            at.name as account_type_name
        FROM fb_orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN users p ON u.parent_id = p.id
        LEFT JOIN fb_configs c ON o.fb_config_id = c.id
        LEFT JOIN fb_sources s ON o.source = s.id
        LEFT JOIN fb_account_types at ON c.account_type_id = at.id
        WHERE 1=1
    ";

    if ($user_role == 'HKD') {
        $sql .= " AND (o.user_id = :user_id OR u.parent_id = :user_id)";
    }
    
    // Add filters to query
    if (!empty($status_filter)) {
        $sql .= " AND o.status = :status";
    }
    
    if (!empty($search_filter)) {
        $sql .= " AND (u.username LIKE :search OR c.account_id LIKE :search OR o.notification LIKE :search OR s.name LIKE :search)";
    }
    
    if (!empty($date_from)) {
        $sql .= " AND DATE(o.created_at) >= :date_from";
    }
    
    if (!empty($date_to)) {
        $sql .= " AND DATE(o.created_at) <= :date_to";
    }

    $sql .= " ORDER BY o.created_at DESC LIMIT :offset, :limit";

    $stmt = $conn->prepare($sql);

    if ($user_role == 'HKD') {
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }
    
    // Bind filter parameters
    if (!empty($status_filter)) {
        $stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
    }
    
    if (!empty($search_filter)) {
        $search_param = '%' . $search_filter . '%';
        $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    
    if (!empty($date_from)) {
        $stmt->bindParam(':date_from', $date_from, PDO::PARAM_STR);
    }
    
    if (!empty($date_to)) {
        $stmt->bindParam(':date_to', $date_to, PDO::PARAM_STR);
    }
    
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);

    $stmt->execute();
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error loading orders: " . $e->getMessage();
}
?>

<div class="container-fluid">
    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Orders filter and search -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Lọc đơn hàng</h5>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Tất cả</option>
                        <option value="Pending" <?php echo ($status_filter == 'Pending') ? 'selected' : ''; ?>>Đang chờ xử lý</option>
                        <option value="Processed" <?php echo ($status_filter == 'Processed') ? 'selected' : ''; ?>>Đã xử lý</option>
                        <option value="Rejected" <?php echo ($status_filter == 'Rejected') ? 'selected' : ''; ?>>Từ chối</option>
                        <option value="Returned" <?php echo ($status_filter == 'Returned') ? 'selected' : ''; ?>>Treo</option>
                        <option value="PartiallyReturned" <?php echo ($status_filter == 'PartiallyReturned') ? 'selected' : ''; ?>>Đã trả một phần</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Từ ngày</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Đến ngày</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-3">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_filter); ?>" placeholder="Tên/ID tài khoản/Nguồn">
                </div>
                <div class="col-12 mt-3">
                    <button type="submit" class="btn btn-primary">Lọc kết quả</button>
                    <a href="fb_return.php" class="btn btn-secondary ms-2">Đặt lại</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Quản lý đơn hàng Facebook</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Người đặt</th>
                            <th>HKD quản lý</th>
                            <th>Ghi chú</th>
                            <th>Số lượng</th>
                            <th>Loại tài khoản</th>
                            <th>Nguồn</th>
                            <th>ID tài khoản</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo $order['id']; ?></td>
                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                            <td><?php echo htmlspecialchars($order['parent_username'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['notification']); ?></td>
                            <td><?php echo $order['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($order['account_type_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['source_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['account_id'] ?? 'N/A'); ?></td>
                            <td>
                                <?php 
                                    $statusClass = 'secondary';
                                    if ($order['status'] == 'Processed') $statusClass = 'success';
                                    if ($order['status'] == 'Rejected') $statusClass = 'danger';
                                    if ($order['status'] == 'Pending') $statusClass = 'warning';
                                ?>
                                <span class="badge bg-<?php echo $statusClass; ?>">
                                    <?php echo get_order_status($order['status']); ?>
                                </span>
                            </td>
                            <td><?php echo format_date($order['created_at']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $order['id']; ?>">
                                    <i class="fas fa-edit"></i> Cập nhật
                                </button>

                                <!-- Update Modal -->
                                <div class="modal fade" id="updateModal<?php echo $order['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post" action="">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Cập nhật đơn hàng #<?php echo $order['id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label">Trạng thái</label>
                                                        <select class="form-select" name="status" required>
                                                            <option value="Pending" <?php echo $order['status'] == 'Pending' ? 'selected' : ''; ?>>Đang chờ xử lý</option>
                                                            <option value="Processed" <?php echo $order['status'] == 'Processed' ? 'selected' : ''; ?>>Đã xử lý</option>
                                                            <option value="Rejected" <?php echo $order['status'] == 'Rejected' ? 'selected' : ''; ?>>Từ chối</option>
                                                            <option value="Returned" <?php echo $order['status'] == 'Returned' ? 'selected' : ''; ?>>Treo</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">ID tài khoản</label>
                                                        <input type="text" class="form-control" name="account_id" value="<?php echo !empty($order['account_id']) ? htmlspecialchars($order['account_id']) : ''; ?>">
                                                        <small class="text-muted">ID này sẽ chỉ áp dụng cho đơn hàng #<?php echo $order['id']; ?>.</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Ghi chú</label>
                                                        <textarea class="form-control" name="notification" rows="3"><?php echo htmlspecialchars($order['notification']); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-4">
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <?php 
                        // Build pagination URL with current filters
                        $pagination_url = '?';
                        if (!empty($status_filter)) $pagination_url .= 'status=' . urlencode($status_filter) . '&';
                        if (!empty($search_filter)) $pagination_url .= 'search=' . urlencode($search_filter) . '&';
                        if (!empty($date_from)) $pagination_url .= 'date_from=' . urlencode($date_from) . '&';
                        if (!empty($date_to)) $pagination_url .= 'date_to=' . urlencode($date_to) . '&';
                        ?>
                        
                        <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $pagination_url; ?>page=1" aria-label="First">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $pagination_url; ?>page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $pagination_url; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $pagination_url; ?>page=<?php echo $current_page + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $pagination_url; ?>page=<?php echo $total_pages; ?>" aria-label="Last">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
