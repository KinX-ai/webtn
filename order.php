<?php
// Include header (handles session, database connection, etc.)
include 'includes/header.php';

// Check if user is authorized to access this page
$allowed_roles = ['ADMIN', 'HKD', 'GS', 'USER_HKD'];

if (!check_role($allowed_roles)) {
    error_log("Access denied for user role: " . ($_SESSION['user_role'] ?? 'not logged in'));
    redirect('login.php');
}

// Debug logging to verify role checking
error_log("User role: " . ($_SESSION['user_role'] ?? 'unknown') . " accessing order.php with allowed roles: " . implode(", ", $allowed_roles));

// Get current user ID
$user_id = $_SESSION['user_id'];

// Initialize variables
$success_message = isset($_GET['success']) ? "Đã tạo đơn hàng thành công" : '';
$error_message = '';
$resource_types = [];
$user_orders = [];

// Get resource types
try {
    $resource_types = get_resource_types($conn);
} catch (PDOException $e) {
    $error_message = "Error loading resource types: " . $e->getMessage();
}

// Pagination settings
$items_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total orders count
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $total_orders = $stmt->fetchColumn();
    $total_pages = ceil($total_orders / $items_per_page);
} catch (PDOException $e) {
    $error_message = "Error counting orders: " . $e->getMessage();
}

// Get user's orders with pagination
try {
    $stmt = $conn->prepare("
        SELECT o.*, r.name as resource_name 
        FROM orders o
        LEFT JOIN resource_types r ON o.resource_type = r.id
        WHERE o.user_id = :user_id 
        ORDER BY o.created_at DESC
        LIMIT :offset, :items_per_page
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':items_per_page', $items_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $user_orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error loading orders: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize input
    $resource_type = isset($_POST['resource_type']) ? (int)$_POST['resource_type'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $source = isset($_POST['source']) ? clean_input($_POST['source']) : '';

    // Validate input
    if (!$resource_type || $resource_type <= 0) {
        $error_message = "Vui lòng chọn loại tài nguyên";
    } elseif ($quantity <= 0) {
        $error_message = "Số lượng phải là số nguyên dương";
    } else {
        try {
            // Check if resource type exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM resource_types WHERE id = :id");
            $stmt->bindParam(':id', $resource_type, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->fetchColumn() == 0) {
                $error_message = "Loại tài nguyên không tồn tại";
            } else {
                // Create new order
                $stmt = $conn->prepare("
                    INSERT INTO orders (user_id, resource_type, quantity, price, source, status, created_at) 
                    VALUES (:user_id, :resource_type, :quantity, :price, :source, 'Pending', NOW())
                ");

                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':resource_type', $resource_type, PDO::PARAM_INT);
                $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
                $stmt->bindParam(':price', $price, PDO::PARAM_STR);
                $stmt->bindParam(':source', $source, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $order_id = $conn->lastInsertId();

                    // Log order creation
                    log_order_action($conn, $user_id, 'create_order', $order_id, [
                        'resource_type' => $resource_type,
                        'quantity' => $quantity,
                        'source' => $source
                    ]);

                    // Remove the existing query and move the redirect to the top
                    ob_end_clean(); // Clear any output buffers
                    header("Location: order.php?success=1");
                    exit();
                } else {
                    $error_message = "Không thể tạo đơn hàng";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success" role="alert">
        <?php echo $success_message; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4 mb-4">
            <!-- Order form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Tạo đơn hàng mới</h5>
                </div>
                <div class="card-body">
                    <form id="orderForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <!-- Resource Type -->
                        <div class="mb-3">
                            <label for="resource_type" class="form-label">Loại tài nguyên</label>
                            <select class="form-select" id="resource_type" name="resource_type" required>
                                <option value="">-- Chọn loại tài nguyên --</option>
                                <?php foreach ($resource_types as $resource): ?>
                                <option value="<?php echo $resource['id']; ?>">
                                    <?php echo htmlspecialchars($resource['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Vui lòng chọn loại tài nguyên
                            </div>
                        </div>

                        <!-- Quantity -->
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Số lượng</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                            <div class="invalid-feedback">
                                Số lượng phải là số nguyên dương
                            </div>
                        </div>

                        <!-- Source -->
                        <div class="mb-3">
                            <label for="source" class="form-label">Ghi chú (tùy chọn)</label>
                            <input type="text" class="form-control" id="source" name="source">
                            <div class="form-text">Nhập thông tin Ghi chú nếu có</div>
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
            <!-- Orders list -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Đơn hàng của bạn</h5>
                    <div class="input-group" style="width: 250px;">
                        <input type="text" class="form-control form-control-sm" id="searchOrderInput" placeholder="Tìm kiếm..." onkeyup="filterTable('searchOrderInput', 'ordersTable')">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="ordersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Loại tài nguyên</th>
                                    <th>Số lượng</th>
                                    <th>Giá tiền</th>
                                    <th>Ghi Chú</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_orders as $order): ?>
                                <tr>
                                    <td><?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['resource_name']); ?></td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td><?php echo $order['price'] ? number_format($order['price'], 0, ',', '.') . ' VNĐ' : '0 VNĐ'; ?></td>
                                    <td><?php echo !empty($order['source']) ? htmlspecialchars($order['source']) : '<span class="text-muted">Không có</span>'; ?></td>
                                    <td>
                                        <?php 
                                            $statusClass = 'secondary';
                                            if ($order['status'] == 'Processed') $statusClass = 'success';
                                            if ($order['status'] == 'Rejected') $statusClass = 'danger';
                                            if ($order['status'] == 'Pending') $statusClass = 'warning';
                                            if ($order['status'] == 'Returned') $statusClass = 'info';
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo get_order_status($order['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_date($order['created_at']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $order['id']; ?>">
                                            <i class="fas fa-eye"></i> Xem chi tiết
                                        </button>

                                        <!-- Detail Modal -->
                                        <div class="modal fade" id="detailModal<?php echo $order['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Chi tiết đơn hàng #<?php echo $order['id']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="table-responsive">
                                                            <table class="table table-bordered">
                                                                <tr>
                                                                    <th>Loại tài nguyên:</th>
                                                                    <td><?php echo htmlspecialchars($order['resource_name']); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Số lượng:</th>
                                                                    <td><?php echo $order['quantity']; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Giá tiền:</th>
                                                                    <td><?php echo $order['price'] ? number_format($order['price'], 0, ',', '.') . ' VNĐ' : '0 VNĐ'; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Ghi chú:</th>
                                                                    <td><?php echo htmlspecialchars($order['source']); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Nguồn mua:</th>
                                                                    <td><?php echo htmlspecialchars($order['source_buy']); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Trạng thái:</th>
                                                                    <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo get_order_status($order['status']); ?></span></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Ngày tạo:</th>
                                                                    <td><?php echo format_date($order['created_at']); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Thời gian xử lý:</th>
                                                                    <td>
                                                                        <?php 
                                                                        // Get the processing time from logs
                                                                                try {
                                                                                    if ($order['status'] == 'Pending') {
                                                                                        echo '<span class="text-warning">Đang chờ xử lý</span>';
                                                                                    } else {
                                                                                        // First check if we have a processing time in logs
                                                                                        $log_stmt = $conn->prepare("
                                                                                            SELECT l.created_at 
                                                                                            FROM logs l
                                                                                            WHERE l.action IN ('update_order', 'update_fb_order_status', 'order_status_update') 
                                                                                            AND (
                                                                                                JSON_CONTAINS(l.details, JSON_OBJECT('order_id', :order_id))
                                                                                                OR JSON_CONTAINS(l.details, JSON_OBJECT('id', :order_id))
                                                                                            )
                                                                                            ORDER BY l.created_at DESC 
                                                                                            LIMIT 1
                                                                                        ");

                                                                                        $log_stmt->bindParam(':order_id', $order['id'], PDO::PARAM_INT);
                                                                                        $log_stmt->execute();
                                                                                        $process_time = $log_stmt->fetchColumn();

                                                                                        if ($process_time) {
                                                                                            echo format_date($process_time);
                                                                                        } else {
                                                                                            // If no log found, use updated_at from orders table
                                                                                            $update_stmt = $conn->prepare("SELECT updated_at FROM orders WHERE id = :order_id AND updated_at IS NOT NULL");
                                                                                            $update_stmt->bindParam(':order_id', $order['id'], PDO::PARAM_INT);
                                                                                            $update_stmt->execute();
                                                                                            $updated_time = $update_stmt->fetchColumn();

                                                                                            if ($updated_time) {
                                                                                                echo format_date($updated_time);
                                                                                            } else {
                                                                                                // If still no time found, use current time and create a log
                                                                                                $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

                                                                                                try {
                                                                                                    // Create a processing log
                                                                                                    log_order_action($conn, $admin_id, 'order_status_update', $order['id'], [
                                                                                                        'order_id' => $order['id'],
                                                                                                        'old_status' => 'Pending',
                                                                                                        'new_status' => $order['status']
                                                                                                    ]);
                                                                                                } catch (Exception $log_e) {
                                                                                                    // Ignore log creation errors
                                                                                                }

                                                                                                $current_time = date('Y-m-d H:i:s');
                                                                                                echo format_date($current_time);
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                } catch (Exception $e) {
                                                                                    // Fallback to just showing the time without error message
                                                                                    if ($order['status'] == 'Pending') {
                                                                                        echo '<span class="text-warning">Đang chờ xử lý</span>';
                                                                                    } else {
                                                                                        echo format_date(date('Y-m-d H:i:s'));
                                                                                    }
                                                                                }
                                                                        ?>
                                                                    </td>
                                                                </tr>
                                                                <?php if (!empty($order['notification'])): ?>
                                                                <tr>
                                                                    <th>Nội dung chi tiết:</th>
                                                                    <td><?php echo nl2br(htmlspecialchars($order['notification'])); ?></td>
                                                                </tr>
                                                                <?php endif; ?>
                                                            </table>
                                                        </div>

                                        <?php
                                        // Display existing documents
                                        $doc_stmt = $conn->prepare("SELECT * FROM order_documents WHERE order_id = :order_id");
                                        $doc_stmt->bindParam(':order_id', $order['id'], PDO::PARAM_INT);
                                        $doc_stmt->execute();
                                        $documents = $doc_stmt->fetchAll();

                                        if (!empty($documents)): ?>
                                            <div class="mb-3">
                                                <h6>Chứng từ đã tải lên:</h6>
                                                <div class="row">
                                                    <?php foreach ($documents as $doc): ?>
                                                    <?php if (!empty($doc['document_path'])): ?>
                                                    <div class="col-4 mb-2">
                                                        <img src="<?php echo htmlspecialchars($doc['document_path']); ?>" 
                                                             class="img-fluid document-thumbnail" 
                                                             alt="Document"
                                                             style="max-width: 100%; cursor: pointer;"
                                                             data-bs-toggle="modal" 
                                                             data-bs-target="#imageModal" 
                                                             data-img-src="<?php echo htmlspecialchars($doc['document_path']); ?>">
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted text-center">Không có chứng từ nào được tải lên</p>
                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (empty($user_orders)): ?>
                    <p class="text-muted text-center">Bạn chưa có đơn hàng nào</p>
                    <?php endif; ?>

                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" tabindex="-1">Trước</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">Sau</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Xem chứng từ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="modalImage" src="" alt="Document" style="max-width: 100%; max-height: 85vh; object-fit: contain;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set up image modal
    document.querySelectorAll('.document-thumbnail').forEach(item => {
        item.addEventListener('click', event => {
            const imgSrc = item.getAttribute('data-img-src');
            document.getElementById('modalImage').src = imgSrc;
        })
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>