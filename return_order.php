<?php
// Include header (handles session, database connection, etc.)
include 'includes/header.php';

// Check if user is authorized to access this page
$allowed_roles = ['ADMIN', 'HKD', 'GS'];

// Disable editing for GS role
$can_edit = $_SESSION['user_role'] != 'GS';

if (!check_role($allowed_roles)) {
    redirect('login.php');
}

// Get current user ID
$user_id = $_SESSION['user_id'];

// Initialize variables
$success_message = isset($_GET['success']) ? "Cập nhật đơn hàng thành công" : '';
$error_message = '';
$all_orders = [];
$status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$search_query = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? clean_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? clean_input($_GET['date_to']) : '';

// Process order status update AND return order
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (!isset($_POST['order_id'])) {
            throw new Exception("Thiếu thông tin đơn hàng");
        }

        $order_id = (int)$_POST['order_id'];
        $status = isset($_POST['status']) ? clean_input($_POST['status']) : '';
        $notification = isset($_POST['notification']) ? clean_input($_POST['notification']) : '';
        $source_buy = isset($_POST['source_buy']) ? clean_input($_POST['source_buy']) : '';
        $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

        // Đảm bảo số lượng luôn >= 1
        $quantity = max(1, $quantity);

        // Update order
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = :status,
                notification = :notification,
                source_buy = :source_buy,
                price = :price,
                quantity = :quantity
            WHERE id = :order_id
        ");

        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':notification', $notification);
        $stmt->bindParam(':source_buy', $source_buy);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':order_id', $order_id);

        if ($stmt->execute()) {
            // Update other order fields if needed
            if (!empty($notification)) {
                $stmt = $conn->prepare("UPDATE orders SET notification = :notification WHERE id = :order_id");
                $stmt->bindParam(':notification', $notification, PDO::PARAM_STR);
                $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
                $stmt->execute();
            }

            // Log the status update
            log_order_action($conn, $user_id, 'update_order', $order_id, [
                'old_status' => $order['status'],
                'new_status' => $status,
                'quantity_returned' => $quantity_returned
            ]);

            // Gửi thông báo cho người dùng về cập nhật đơn hàng
            try {
                $status_text = get_order_status($status);

                // Thêm thông báo vào database
                $stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, type, title, message, order_id, created_at)
                    VALUES (:user_id, 'order_updated', :title, :message, :order_id, NOW())
                ");

                $title = "Cập nhật đơn hàng";
                $message = "Đơn hàng #$order_id đã được cập nhật: $status_text";

                $stmt->bindParam(':user_id', $order['user_id']);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':message', $message);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->execute();

                // Thử gửi thông báo real-time qua WebSocket nếu có thể
                $client = @stream_socket_client("tcp://localhost:8080", $errno, $errorMessage, 1);
                if ($client) {
                    // Giả lập WebSocket handshake đơn giản
                    fwrite($client, "GET / HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\nSec-WebSocket-Version: 13\r\n\r\n");
                    fread($client, 1024); // Đọc phản hồi handshake

                    // Gửi thông báo
                    $notification = json_encode([
                        'type' => 'order_updated',
                        'data' => [
                            'order_id' => $order_id,
                            'user_id' => $order['user_id'],
                            'status' => $status_text
                        ]
                    ]);

                    // Mã hóa dữ liệu WebSocket đơn giản
                    $data = chr(129) . chr(strlen($notification)) . $notification;
                    fwrite($client, $data);
                    fclose($client);
                }
            } catch (Exception $e) {
                // Ghi log lỗi nhưng không dừng quá trình cập nhật đơn hàng
                error_log("Error sending notification: " . $e->getMessage());
            }

            $success_message = "Đã cập nhật trạng thái đơn hàng thành công";
        } else {
            throw new Exception("Không thể cập nhật đơn hàng");
        }

        // Handle document either from file upload or URL
        $upload_path = null;

        // Handle file upload
        if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
            $upload_dir = 'attached_assets/';

            // Kiểm tra và tạo thư mục nếu chưa tồn tại
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('doc_') . '.' . $file_extension;
            $upload_path = $upload_dir . $file_name;

            if (!move_uploaded_file($_FILES['document']['tmp_name'], $upload_path)) {
                throw new Exception("Không thể tải lên tệp");
            }
        } 
        // Handle image URL
        elseif (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
            $image_url = trim($_POST['image_url']);

            // Validate URL
            if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                $upload_path = $image_url;
            } else {
                throw new Exception("URL ảnh không hợp lệ");
            }
        }

        // Save document path to database if we have one
        if ($upload_path) {
            $doc_stmt = $conn->prepare("
                INSERT INTO order_documents (order_id, document_path) 
                VALUES (:order_id, :path)
            ");
            $doc_stmt->bindParam(':order_id', $order_id);
            $doc_stmt->bindParam(':path', $upload_path);

            if (!$doc_stmt->execute()) {
                throw new Exception("Không thể lưu thông tin tệp");
            }
        }

        $success_message = "Cập nhật đơn hàng thành công";
        header("Location: return_order.php?success=1");
        exit();

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Pagination settings
$items_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

try {
    // Get total count
    $count_sql = "
        SELECT COUNT(*) 
        FROM orders o
        LEFT JOIN resource_types r ON o.resource_type = r.id
        WHERE 1=1
    ";

    // Apply filters
    if (!empty($status_filter)) {
        $count_sql .= " AND o.status = :status";
    }

    if (!empty($date_from)) {
        $count_sql .= " AND DATE(o.created_at) >= :date_from";
    }

    if (!empty($date_to)) {
        $count_sql .= " AND DATE(o.created_at) <= :date_to";
    }
    
    // Giới hạn quyền xem cho HKD - chỉ thấy đơn hàng của USER_HKD thuộc quản lý
    if ($_SESSION['user_role'] == 'HKD') {
        $count_sql .= " AND (o.user_id = :user_id OR o.user_id IN (SELECT id FROM users WHERE parent_id = :user_id))";
    }

    if (!empty($search_query)) {
        $count_sql .= " AND (r.name LIKE :search OR o.source_buy LIKE :search OR o.notification LIKE :search)";
    }
    
    // Lọc theo HKD (chỉ áp dụng cho ADMIN và GS)
    $hkd_filter = isset($_GET['hkd_filter']) ? (int)$_GET['hkd_filter'] : '';
    if (!empty($hkd_filter) && ($_SESSION['user_role'] == 'ADMIN' || $_SESSION['user_role'] == 'GS')) {
        $count_sql .= " AND (o.user_id = :hkd_filter OR o.user_id IN (SELECT id FROM users WHERE parent_id = :hkd_filter))";
    }

    $count_stmt = $conn->prepare($count_sql);

    if (!empty($status_filter)) {
        $count_stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
    }

    if (!empty($date_from)) {
        $count_stmt->bindParam(':date_from', $date_from, PDO::PARAM_STR);
    }

    if (!empty($date_to)) {
        $count_stmt->bindParam(':date_to', $date_to, PDO::PARAM_STR);
    }
    
    // Thêm tham số cho phân quyền HKD
    if ($_SESSION['user_role'] == 'HKD') {
        $count_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }

    if (!empty($search_query)) {
        $search_param = '%' . $search_query . '%';
        $count_stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    
    // Bind tham số lọc theo HKD
    if (!empty($hkd_filter) && ($_SESSION['user_role'] == 'ADMIN' || $_SESSION['user_role'] == 'GS')) {
        $count_stmt->bindParam(':hkd_filter', $hkd_filter, PDO::PARAM_INT);
    }

    $count_stmt->execute();
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Get orders with filters
    $sql = "
        SELECT o.*, r.name as resource_name,
        IFNULL(o.quantity, 1) as quantity
        FROM orders o
        LEFT JOIN resource_types r ON o.resource_type = r.id
        WHERE 1=1
    ";

    // Apply filters
    if (!empty($status_filter)) {
        $sql .= " AND o.status = :status";
    }

    if (!empty($date_from)) {
        $sql .= " AND DATE(o.created_at) >= :date_from";
    }

    if (!empty($date_to)) {
        $sql .= " AND DATE(o.created_at) <= :date_to";
    }
    
    // Giới hạn quyền xem cho HKD - chỉ thấy đơn hàng của mình và USER_HKD thuộc quản lý
    if ($_SESSION['user_role'] == 'HKD') {
        $sql .= " AND (o.user_id = :user_id OR o.user_id IN (SELECT id FROM users WHERE parent_id = :user_id))";
    }

    if (!empty($search_query)) {
        $sql .= " AND (r.name LIKE :search OR o.source_buy LIKE :search OR o.notification LIKE :search)";
    }
    
    // Lọc theo HKD (chỉ áp dụng cho ADMIN và GS)
    if (!empty($hkd_filter) && ($_SESSION['user_role'] == 'ADMIN' || $_SESSION['user_role'] == 'GS')) {
        $sql .= " AND (o.user_id = :hkd_filter OR o.user_id IN (SELECT id FROM users WHERE parent_id = :hkd_filter))";
    }

    $sql .= " ORDER BY o.created_at DESC LIMIT :offset, :limit";

    $stmt = $conn->prepare($sql);

    // Bind filter parameters
    if (!empty($status_filter)) {
        $stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
    }

    if (!empty($date_from)) {
        $stmt->bindParam(':date_from', $date_from, PDO::PARAM_STR);
    }

    if (!empty($date_to)) {
        $stmt->bindParam(':date_to', $date_to, PDO::PARAM_STR);
    }
    
    // Thêm tham số phân quyền cho HKD
    if ($_SESSION['user_role'] == 'HKD') {
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }

    if (!empty($search_query)) {
        $search_param = '%' . $search_query . '%';
        $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    
    // Bind tham số lọc theo HKD cho danh sách đơn hàng
    if (!empty($hkd_filter) && ($_SESSION['user_role'] == 'ADMIN' || $_SESSION['user_role'] == 'GS')) {
        $stmt->bindParam(':hkd_filter', $hkd_filter, PDO::PARAM_INT);
    }

    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $orders = [];
    $total_pages = 0;
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
        <div class="col-md-12">
         

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
                                <option value="Returned" <?php echo ($status_filter == 'Returned') ? 'selected' : ''; ?>>Bị treo</option>
                                <option value="PartiallyReturned" <?php echo ($status_filter == 'PartiallyReturned') ? 'selected' : ''; ?>>Đã trả một phần</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">Từ ngày</label>
                            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">Đến ngày</label>
                            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to ?? ''); ?>">
                        </div>
                        <?php if ($_SESSION['user_role'] == 'ADMIN' || $_SESSION['user_role'] == 'GS'): ?>
                        <div class="col-md-2">
                            <label for="hkd_filter" class="form-label">HKD</label>
                            <select class="form-select form-control-sm" id="hkd_filter" name="hkd_filter">
                                <option value="">-- Tất cả HKD --</option>
                                <?php
                                // Lấy danh sách HKD
                                $hkd_query = $conn->prepare("SELECT id, username FROM users WHERE role = 'HKD' ORDER BY username");
                                $hkd_query->execute();
                                $hkd_users = $hkd_query->fetchAll();
                                
                                foreach ($hkd_users as $hkd): ?>
                                <option value="<?php echo $hkd['id']; ?>" <?php echo (isset($_GET['hkd_filter']) && $_GET['hkd_filter'] == $hkd['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($hkd['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-3">
                            <label for="search" class="form-label">Tìm kiếm</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_query ?? ''); ?>" placeholder="Tên/Nguồn mua...">
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-primary btn-sm">Lọc kết quả</button>
                            <a href="return_order.php" class="btn btn-secondary btn-sm ms-2">Đặt lại</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Danh sách đơn hàng</h5>
                    <span class="badge bg-primary"><?php echo count($orders); ?> đơn hàng</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead class="table-light">
                                <tr class="align-middle">
                                    <th width="3%">#ID</th>
                                    <th width="9%">Nguồn mua</th>
                                    <th width="15%">Tên tài nguyên</th>
                                    <th width="4%" class="text-center">SL</th>
                                    <th width="8%" class="text-end">Giá</th>
                                    <th width="7%">Người tạo</th>
                                    <th width="10%">Ghi chú</th>
                                    <th width="12%">Nội Dung</th>
                                    <th width="7%">Trạng thái</th>
                                    <th width="7%">Ngày tạo</th>
                                    <th width="10%">Hành động</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php if (!empty($orders)): ?>
                                    <?php foreach ($orders as $order): ?>
                                    <?php
                                    $statusClass = '';
                                    $showUpdateButton = true;

                                    switch ($order['status']) {
                                        case 'Pending':
                                            $statusClass = 'warning';
                                            $showUpdateButton = true;
                                            break;
                                        case 'Processed':
                                            $statusClass = 'success';
                                            // Chỉ cho phép Admin cập nhật sau khi đã xử lý, HKD không thể
                                            $showUpdateButton = ($_SESSION['user_role'] === 'ADMIN');
                                            break;
                                        case 'Rejected':
                                            $statusClass = 'danger';
                                            // Chỉ cho phép Admin cập nhật sau khi đã xử lý
                                            $showUpdateButton = ($_SESSION['user_role'] === 'ADMIN');
                                            break;
                                        case 'Returned':
                                            $statusClass = 'info';
                                            $showUpdateButton = true;
                                            break;
                                        case 'PartiallyReturned':
                                            $statusClass = 'primary';
                                            $showUpdateButton = true;
                                            break;
                                    }
                                    ?>
                                    <tr class="align-middle">
                                        <td class="fw-bold"><?php echo $order['id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['source_buy'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($order['resource_name'] ?? ''); ?></td>
                                        <td class="text-center"><?php echo isset($order['quantity']) ? $order['quantity'] : 1; ?></td>
                                        <td class="text-end"><?php echo number_format($order['price']); ?> VNĐ</td>
                                        <td><?php 
                                            try {
                                                $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = :user_id");
                                                $user_stmt->bindParam(':user_id', $order['user_id'], PDO::PARAM_INT);
                                                $user_stmt->execute();
                                                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                                                echo htmlspecialchars($user['username'] ?? 'Unknown');
                                            } catch (Exception $e) {
                                                echo 'Unknown';
                                            }
                                        ?></td>
                                        <td><?php echo !empty($order['source']) ? htmlspecialchars($order['source']) : '<span class="text-muted">Không có</span>'; ?></td>
                                        <td>
                                            <?php if (!empty($order['notification'])): ?>
                                                <?php 
                                                $short_note = mb_strlen($order['notification']) > 30 
                                                    ? mb_substr(htmlspecialchars($order['notification']), 0, 30) . '...' 
                                                    : htmlspecialchars($order['notification']);
                                                echo $short_note;
                                                ?>
                                            <?php else: ?>
                                                <span class="text-muted">Không có</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo get_order_status($order['status']); ?></span></td>
                                        <td><?php echo format_date($order['created_at']); ?></td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <button type="button" class="btn btn-info btn-sm btn-xs" data-bs-toggle="modal" data-bs-target="#orderDetailsModal<?php echo $order['id']; ?>">
                                                    <i class="fas fa-eye"></i> Chi tiết
                                                </button>
                                                <?php if ($can_edit && $showUpdateButton): ?>
                                                <button type="button" class="btn btn-primary btn-sm btn-xs" data-bs-toggle="modal" data-bs-target="#orderModal<?php echo $order['id']; ?>">
                                                    <i class="fas fa-edit"></i> Cập nhật
                                                </button>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Order Details Modal -->
                                            <div class="modal fade" id="orderDetailsModal<?php echo $order['id']; ?>" tabindex="-1" aria-labelledby="orderDetailsModalLabel<?php echo $order['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-light">
                                                            <h5 class="modal-title" id="orderDetailsModalLabel<?php echo $order['id']; ?>">
                                                                <i class="fas fa-info-circle me-2"></i>Chi tiết đơn hàng #<?php echo $order['id']; ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="container-fluid">
                                                                <div class="row">
                                                                    <div class="col-md-12">
                                                                        <div class="card mb-3">
                                                                            <div class="card-header bg-primary text-white py-2">
                                                                                <h6 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Thông tin đơn hàng</h6>
                                                                            </div>
                                                                            <div class="card-body p-3">
                                                                                <table class="table table-striped table-bordered table-sm">
                                                                                    <tr>
                                                                                        <th width="20%">ID Đơn hàng:</th>
                                                                                        <td width="80%"><?php echo $order['id']; ?></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>Nguồn mua:</th>
                                                                                        <td><?php echo htmlspecialchars($order['source_buy'] ?? ''); ?></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>Tên tài nguyên:</th>
                                                                                        <td><?php echo htmlspecialchars($order['resource_name'] ?? ''); ?></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>Số lượng:</th>
                                                                                        <td><?php echo isset($order['quantity']) ? $order['quantity'] : 1; ?></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>Giá:</th>
                                                                                        <td class="fw-bold"><?php echo number_format($order['price']); ?> VNĐ</td>
                                                                                    </tr>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-12">
                                                                        <div class="card mb-3">
                                                                            <div class="card-header bg-info text-white py-2">
                                                                                <h6 class="mb-0"><i class="fas fa-user me-2"></i>Thông tin xử lý</h6>
                                                                            </div>
                                                                            <div class="card-body p-3">
                                                                                <table class="table table-striped table-bordered table-sm">
                                                                                    <tr>
                                                                                        <th width="20%">Người tạo:</th>
                                                                                        <td width="80%"><?php 
                                                                                            try {
                                                                                                $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = :user_id");
                                                                                                $user_stmt->bindParam(':user_id', $order['user_id'], PDO::PARAM_INT);
                                                                                                $user_stmt->execute();
                                                                                                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                                                                                                echo htmlspecialchars($user['username'] ?? 'Unknown');
                                                                                            } catch (Exception $e) {
                                                                                                echo 'Unknown';
                                                                                            }
                                                                                        ?></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>Ghi chú:</th>
                                                                                        <td><?php echo !empty($order['source']) ? htmlspecialchars($order['source']) : '<span class="text-muted">Không có</span>'; ?></td>
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
                                                                                            if ($order['status'] == 'Pending') {
                                                                                                echo '<span class="text-warning">Đang chờ xử lý</span>';
                                                                                            } else {
                                                                                                // Get the processing time from logs
                                                                                                try {
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
                                                                                                } catch (Exception $e) {
                                                                                                    // Fallback to just showing the time without error message
                                                                                                    echo format_date(date('Y-m-d H:i:s'));
                                                                                                }
                                                                                            }
                                                                                            ?>
                                                                                        </td>
                                                                                    </tr>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <?php if (!empty($order['notification'])): ?>
                                                                <div class="row">
                                                                    <div class="col-12">
                                                                        <div class="card mb-3">
                                                                            <div class="card-header bg-secondary text-white py-2">
                                                                                <h6 class="mb-0"><i class="fas fa-comment-alt me-2"></i>Nội dung chi tiết</h6>
                                                                            </div>
                                                                            <div class="card-body p-3">
                                                                                <div class="p-2 bg-light rounded">
                                                                                    <?php echo nl2br(htmlspecialchars($order['notification'])); ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php endif; ?>

                                                                <?php
                                                                // Display existing documents
                                                                $doc_stmt = $conn->prepare("SELECT * FROM order_documents WHERE order_id = :order_id");
                                                                $doc_stmt->bindParam(':order_id', $order['id'], PDO::PARAM_INT);
                                                                $doc_stmt->execute();
                                                                $documents = $doc_stmt->fetchAll();

                                                                // Kiểm tra xem có chứng từ thực sự hay không
                                                                $has_documents = false;
                                                                if (!empty($documents)) {
                                                                    foreach ($documents as $doc) {
                                                                        // Loại bỏ kiểm tra file_exists để đường dẫn URL luôn được xác nhận
                                                                        // và kiểm tra kỹ hơn để chỉ hiển thị đường dẫn hợp lệ
                                                                        if (!empty($doc['document_path'])) {
                                                                            if (strpos($doc['document_path'], 'http') === 0 || 
                                                                                (strpos($doc['document_path'], '/') === 0 && file_exists($_SERVER['DOCUMENT_ROOT'] . $doc['document_path'])) ||
                                                                                file_exists($doc['document_path'])) {
                                                                                $has_documents = true;
                                                                                break;
                                                                            }
                                                                        }
                                                                    }
                                                                }

                                                                if ($has_documents): ?>
                                                                <div class="row">
                                                                    <div class="col-12">
                                                                        <div class="card mb-0">
                                                                            <div class="card-header bg-success text-white py-1">
                                                                                <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Chứng từ đã tải lên</h6>
                                                                            </div>
                                                                            <div class="card-body p-2">
                                                                                <div class="row">
                                                                                    <?php foreach ($documents as $doc): ?>
                                                                                    <?php if (!empty($doc['document_path'])): ?>
                                                                                    <div class="col-md-3 col-sm-4 mb-2">
                                                                                        <div class="card h-100">
                                                                                            <img src="<?php echo htmlspecialchars($doc['document_path']); ?>" 
                                                                                                class="card-img-top document-thumbnail" 
                                                                                                alt="Document"
                                                                                                data-bs-toggle="modal" 
                                                                                                data-bs-target="#imageModal" 
                                                                                                data-img-src="<?php echo htmlspecialchars($doc['document_path']); ?>"
                                                                                                style="height: 100px; object-fit: cover; cursor: pointer;">
                                                                                            <div class="card-footer text-center p-0">
                                                                                                <small class="text-muted fs-tiny">Nhấp để xem</small>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <?php endif; ?>
                                                                                    <?php endforeach; ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php else: ?>
                                                                <div class="alert alert-warning text-center">
                                                                    <i class="fas fa-exclamation-triangle me-2"></i>Chưa có chứng từ nào được tải lên
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- End Order Details Modal -->

                                            <?php if ($can_edit): ?>
                                            <!-- Order Update Modal -->
                                            <div class="modal fade" id="orderModal<?php echo $order['id']; ?>" tabindex="-1" aria-labelledby="orderModalLabel<?php echo $order['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-primary text-white">
                                                            <h5 class="modal-title" id="orderModalLabel<?php echo $order['id']; ?>">
                                                                <i class="fas fa-edit me-2"></i>Cập nhật đơn hàng #<?php echo $order['id']; ?>
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                                            <div class="modal-body">
                                                                <?php if (!empty($error_message)): ?>
                                                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                                                <?php endif; ?>
                                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">

                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <div class="card mb-3">
                                                                            <div class="card-header bg-info text-white">
                                                                                <h6 class="mb-0"><i class="fas fa-file-image me-2"></i>Chứng từ đơn hàng</h6>
                                                                            </div>
                                                                            <div class="card-body">
                                                                                <div class="mb-3">
                                                                                    <label for="document<?php echo $order['id']; ?>" class="form-label fw-bold">Tải lên chứng từ</label>
                                                                                    <div class="input-group mb-2">
                                                                                        <span class="input-group-text"><i class="fas fa-upload"></i></span>
                                                                                        <input type="file" class="form-control" id="document<?php echo $order['id']; ?>" name="document" accept="image/*">
                                                                                    </div>
                                                                                    <small class="form-text text-muted">Hỗ trợ định dạng ảnh (JPG, PNG, etc.)</small>
                                                                                </div>
                                                                                <div class="mb-3">
                                                                                    <label for="image_url<?php echo $order['id']; ?>" class="form-label fw-bold">Hoặc dán liên kết ảnh</label>
                                                                                    <div class="input-group">
                                                                                        <span class="input-group-text"><i class="fas fa-link"></i></span>
                                                                                        <input type="url" class="form-control" id="image_url<?php echo $order['id']; ?>" name="image_url" placeholder="https://example.com/image.jpg">
                                                                                    </div>
                                                                                    <small class="form-text text-muted">Dán URL trực tiếp đến ảnh từ nguồn khác</small>
                                                                                </div>

                                                                                <?php
                                                                                // Display existing documents
                                                                                $doc_stmt = $conn->prepare("SELECT * FROM order_documents WHERE order_id = :order_id");
                                                                                $doc_stmt->bindParam(':order_id', $order['id'], PDO::PARAM_INT);
                                                                                $doc_stmt->execute();
                                                                                $documents = $doc_stmt->fetchAll();

                                                                                // Kiểm tra xem có chứng từ thực sự hay không
                                                                                $has_documents = false;
                                                                                if (!empty($documents)) {
                                                                                    foreach ($documents as $doc) {
                                                                                        if (!empty($doc['document_path']) && 
                                                                                            (file_exists($doc['document_path']) || 
                                                                                             strpos($doc['document_path'], 'http') === 0)) {
                                                                                            $has_documents = true;
                                                                                            break;
                                                                                        }
                                                                                    }
                                                                                }

                                                                                if ($has_documents): ?>
                                                                                <div class="mb-0">
                                                                                    <h6 class="fw-bold">Chứng từ đã tải lên:</h6>
                                                                                    <div class="row">
                                                                                        <?php foreach ($documents as $doc): ?>
                                                                                        <?php if (!empty($doc['document_path'])): ?>
                                                                                        <div class="col-md-6 col-sm-6 mb-2">
                                                                                            <div class="card h-100">
                                                                                                <img src="<?php echo htmlspecialchars($doc['document_path']); ?>" 
                                                                                                    class="card-img-top document-thumbnail" 
                                                                                                    alt="Document"
                                                                                                    style="height: 100px; object-fit: cover; cursor: pointer;"
                                                                                                    data-bs-toggle="modal" 
                                                                                                    data-bs-target="#imageModal" 
                                                                                                    data-img-src="<?php echo htmlspecialchars($doc['document_path']); ?>">
                                                                                                <div class="card-footer text-center p-1">
                                                                                                    <small class="text-muted">Nhấp để xem</small>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                        <?php endif; ?>
                                                                                        <?php endforeach; ?>
                                                                                    </div>
                                                                                </div>
                                                                                <?php else: ?>
                                                                                <div class="alert alert-warning text-center">
                                                                                    <i class="fas fa-exclamation-triangle me-2"></i>Chưa có chứng từ nào được tải lên
                                                                                </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <div class="card mb-3">
                                                                            <div class="card-header bg-success text-white">
                                                                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Thông tin đơn hàng</h6>
                                                                            </div>
                                                                            <div class="card-body">
                                                                                <div class="mb-3">
                                                                                    <label for="status<?php echo $order['id']; ?>" class="form-label fw-bold">Trạng thái</label>
                                                                                    <select class="form-select border-primary" id="status<?php echo $order['id']; ?>" name="status" required>
                                                                                        <option value="Pending" <?php echo ($order['status'] == 'Pending') ? 'selected' : ''; ?>>Đang chờ xử lý</option>
                                                                                        <option value="Processed" <?php echo ($order['status'] == 'Processed') ? 'selected' : ''; ?>>Đã xử lý</option>
                                                                                        <option value="Rejected" <?php echo ($order['status'] == 'Rejected') ? 'selected' : ''; ?>>Từ chối</option>
                                                                                        <option value="Returned" <?php echo ($order['status'] == 'Returned') ? 'selected' : ''; ?>>Bị treo</option>
                                                                                        <option value="PartiallyReturned" <?php echo ($order['status'] == 'PartiallyReturned') ? 'selected' : ''; ?>>Đã trả một phần</option>
                                                                                    </select>
                                                                                </div>

                                                                                <div class="mb-3">
                                                                                    <label for="source_buy<?php echo $order['id']; ?>" class="form-label fw-bold">Nguồn mua</label>
                                                                                    <div class="input-group">
                                                                                        <span class="input-group-text"><i class="fas fa-store"></i></span>
                                                                                        <input type="text" class="form-control" id="source_buy<?php echo $order['id']; ?>" name="source_buy" value="<?php echo htmlspecialchars($order['source_buy'] ?? ''); ?>" placeholder="Nhập nguồn mua">
                                                                                    </div>
                                                                                </div>

                                                                                <div class="row">
                                                                                    <div class="col-md-12">
                                                                                        <div class="mb-3">
                                                                                            <label for="quantity<?php echo $order['id']; ?>" class="form-label fw-bold">Số lượng</label>
                                                                                            <div class="input-group">
                                                                                                <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                                                                                <input type="number" class="form-control" id="quantity<?php echo $order['id']; ?>" name="quantity" value="<?php echo isset($order['quantity']) ? $order['quantity'] : 1; ?>" min="1" placeholder="Nhập số lượng">
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-md-12">
                                                                                        <div class="mb-3">
                                                                                            <label for="price<?php echo $order['id']; ?>" class="form-label fw-bold">Giá tiền</label>
                                                                                            <div class="input-group">
                                                                                                <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                                                                                <input type="number" class="form-control form-control-lg" id="price<?php echo $order['id']; ?>" name="price" value="<?php echo $order['price']; ?>" placeholder="Nhập giá tiền">
                                                                                                <span class="input-group-text">VNĐ</span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="row">
                                                                    <div class="col-12">
                                                                        <div class="card">
                                                                            <div class="card-header bg-secondary text-white">
                                                                                <h6 class="mb-0"><i class="fas fa-comment-alt me-2"></i>Nội dung chi tiết</h6>
                                                                            </div>
                                                                            <div class="card-body">
                                                                                <div class="mb-0">
                                                                                    <textarea class="form-control" id="notification<?php echo $order['id']; ?>" name="notification" rows="4" placeholder="Nhập nội dung chi tiết về đơn hàng..."><?php echo htmlspecialchars($order['notification'] ?? ''); ?></textarea>
                                                                                    <small class="form-text text-muted mt-1">Thông tin này sẽ được hiển thị cho người dùng khi họ xem chi tiết đơn hàng</small>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer bg-light">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                    <i class="fas fa-times me-1"></i>Đóng
                                                                </button>
                                                                <button type="submit" class="btn btn-primary">
                                                                    <i class="fas fa-save me-1"></i>Lưu thay đổi
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">Không có đơn hàng nào</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php 
                            // Build pagination URL with all current filters
                            $pagination_url = '?';
                            if (!empty($status_filter)) $pagination_url .= 'status=' . urlencode($status_filter) . '&';
                            if (!empty($search_query)) $pagination_url .= 'search=' . urlencode($search_query) . '&';
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
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="imageModalLabel"><i class="fas fa-file-image me-2"></i>Xem chứng từ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center bg-light p-0">
                <img id="modalImage" src="" alt="Document" style="max-width: 100%; max-height: 85vh; object-fit: contain;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Đóng
                </button>
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