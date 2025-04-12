<?php
include 'includes/header.php';

// Check if user is authorized to access this page
$allowed_roles = ['USER_HKD', 'HKD', 'ADMIN', 'GS'];
if (!check_role($allowed_roles)) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$success_message = isset($_GET['success']) ? "Đã tạo đơn hàng thành công" : '';
$error_message = '';

// Get Facebook account configurations and sources
try {
    $stmt = $conn->prepare("SELECT * FROM fb_account_types ORDER BY name");
    $stmt->execute();
    $account_types = $stmt->fetchAll();

    $stmt = $conn->prepare("SELECT * FROM fb_sources WHERE status = 'active' OR status IS NULL ORDER BY name");
    $stmt->execute();
    $sources = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error loading data: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $account_type = isset($_POST['account_type']) ? (int)$_POST['account_type'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $source = isset($_POST['source']) ? (int)$_POST['source'] : 0;
    $notification = isset($_POST['notification']) ? clean_input($_POST['notification']) : '';

    if ($account_type <= 0) {
        $error_message = "Vui lòng chọn loại tài khoản";
    } elseif ($quantity <= 0) {
        $error_message = "Số lượng phải là số nguyên dương";
    } elseif ($source <= 0) {
        $error_message = "Vui lòng chọn nguồn";
    } else {
        try {
            // Fetch source name for use in config
            $stmt = $conn->prepare("SELECT name FROM fb_sources WHERE id = :source_id");
            $stmt->bindParam(':source_id', $source, PDO::PARAM_INT);
            $stmt->execute();
            $source_name = $stmt->fetchColumn();

            // Get account type name
            $stmt = $conn->prepare("SELECT name FROM fb_account_types WHERE id = :account_type_id");
            $stmt->bindParam(':account_type_id', $account_type, PDO::PARAM_INT);
            $stmt->execute();
            $account_type_name = $stmt->fetchColumn();

            // Create a new config for each order
            $stmt = $conn->prepare("
                INSERT INTO fb_configs (name, account_id, source, account_type_id) 
                VALUES (:name, :account_id, :source, :account_type_id)
            ");

            // Create a more meaningful name and empty account_id (will be filled by admin later)
            $default_name = $account_type_name . " - " . date('d/m/Y H:i');
            $default_account_id = ""; // Empty account ID
            $source_str = $source_name;

            $stmt->bindParam(':name', $default_name, PDO::PARAM_STR);
            $stmt->bindParam(':account_id', $default_account_id, PDO::PARAM_STR);
            $stmt->bindParam(':source', $source_str, PDO::PARAM_STR);
            $stmt->bindParam(':account_type_id', $account_type, PDO::PARAM_INT);
            $stmt->execute();
            $fb_config_id = $conn->lastInsertId();

            // Now insert the order
            $stmt = $conn->prepare("
                INSERT INTO fb_orders (user_id, fb_config_id, quantity, source, notification, status, created_at) 
                VALUES (:user_id, :fb_config_id, :quantity, :source, :notification, 'Pending', NOW())
            ");

            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':fb_config_id', $fb_config_id, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindParam(':source', $source, PDO::PARAM_INT);
            $stmt->bindParam(':notification', $notification, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $order_id = $conn->lastInsertId();
                log_order_action($conn, $user_id, 'create_fb_order', $order_id, [
                    'fb_config_id' => $fb_config_id,
                    'quantity' => $quantity,
                    'source_id' => $source,
                    'notification' => $notification
                ]);

                header("Location: fb_order.php?success=1");
                exit();
            } else {
                $error_message = "Không thể tạo đơn hàng";
            }
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total count first
try {
    $count_sql = "SELECT COUNT(*) FROM fb_orders WHERE user_id = :user_id";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $count_stmt->execute();
    $total_orders = $count_stmt->fetchColumn();
    $total_pages = ceil($total_orders / $items_per_page);
} catch (PDOException $e) {
    $error_message = "Error counting orders: " . $e->getMessage();
}

// Get user's orders with pagination
try {
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            t.name as account_type_name,
            c.account_id,
            o.quantity,
            s.name as source_name,
            o.notification,
            o.status,
            o.created_at
        FROM fb_orders o
        LEFT JOIN fb_configs c ON o.fb_config_id = c.id
        LEFT JOIN fb_account_types t ON c.account_type_id = t.id
        LEFT JOIN fb_sources s ON o.source = s.id
        WHERE o.user_id = :user_id 
        ORDER BY o.created_at DESC
        LIMIT :offset, :limit
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $user_orders = $stmt->fetchAll();
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

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Đặt tài khoản Facebook</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="account_type" class="form-label">Loại tài khoản</label>
                            <select class="form-select" id="account_type" name="account_type" required>
                                <option value="">-- Chọn loại tài khoản --</option>
                                <?php foreach ($account_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="quantity" class="form-label">Số lượng</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                        </div>

                        <div class="mb-3">
                            <label for="source" class="form-label">Nguồn</label>
                            <select class="form-select" id="source" name="source" required>
                                <option value="">-- Chọn nguồn --</option>
                                <?php foreach ($sources as $source): ?>
                                <option value="<?php echo $source['id']; ?>">
                                    <?php echo htmlspecialchars($source['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="notification" class="form-label">Ghi chú</label>
                            <textarea class="form-control" id="notification" name="notification" rows="3"></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-cart-plus me-2"></i> Đặt hàng
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Đơn hàng của bạn</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID đơn</th>
                                    <th>Loại tài khoản</th>
                                    <th>ID tài khoản</th>
                                    <th>Số lượng</th>
                                    <th>Nguồn</th>
                                    <th>Ghi chú</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_orders as $order): ?>
                                <tr>
                                    <td><?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['account_type_name']); ?></td>
                                    <td><?php echo !empty($order['account_id']) ? htmlspecialchars($order['account_id']) : 'N/A'; ?></td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($order['source_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['notification']); ?></td>
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
                                <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" aria-label="Previous">
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
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>" aria-label="Last">
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
    </div>
</div>

<?php include 'includes/footer.php'; ?>