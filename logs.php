<?php
// Include header (handles session, database connection, etc.)
include 'includes/header.php';

// Handle log deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_log'])) {
        $log_id = (int)$_POST['delete_log'];
        try {
            $stmt = $conn->prepare("DELETE FROM logs WHERE id = :log_id");
            $stmt->bindParam(':log_id', $log_id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                add_log($conn, $user_id, 'delete_log', json_encode(['log_id' => $log_id]));
                header("Location: " . $_SERVER['PHP_SELF'] . "?delete_success=1");
                exit();
            }
        } catch (PDOException $e) {
            error_log("Error deleting log: " . $e->getMessage());
        }
    } elseif (isset($_POST['delete_all_logs'])) {
        try {
            $stmt = $conn->prepare("DELETE FROM logs");
            if ($stmt->execute()) {
                // Log the deletion of all logs
                add_log($conn, $user_id, 'delete_all_logs', json_encode(['time' => date('Y-m-d H:i:s')]));
                header("Location: " . $_SERVER['PHP_SELF'] . "?delete_all_success=1");
                exit();
            }
        } catch (PDOException $e) {
            error_log("Error deleting all logs: " . $e->getMessage());
        }
    }
}

// Check if user is authorized to access this page (ADMIN only)
$allowed_roles = ['ADMIN'];
if (!check_role($allowed_roles)) {
    redirect('login.php');
}

// Get current user ID
$user_id = $_SESSION['user_id'];

// Initialize variables
$logs = [];
$users = [];
$total_logs = 0;

// Pagination settings
$items_per_page = 6; // Giảm số lượng logs mỗi trang xuống 5
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get filters
$filters = [
    'user_id' => isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0,
    'action' => isset($_GET['action']) ? clean_input($_GET['action']) : '',
    'start_date' => isset($_GET['start_date']) ? clean_input($_GET['start_date']) : '',
    'end_date' => isset($_GET['end_date']) ? clean_input($_GET['end_date']) : ''
];

// Get all users for the filter dropdown
try {
    $stmt = $conn->prepare("SELECT id, username FROM users ORDER BY username");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error getting users for log filter: " . $e->getMessage());
}

// Get unique actions for the filter dropdown
try {
    $actions = [];
    $stmt = $conn->prepare("SELECT DISTINCT action FROM logs ORDER BY action");
    $stmt->execute();
    $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error getting actions for log filter: " . $e->getMessage());
}

// Get total count of logs with applied filters
try {
    $where_clauses = [];
    $params = [];

    // Build WHERE clause based on filters
    if ($filters['user_id'] > 0) {
        $where_clauses[] = "l.user_id = :user_id";
        $params[':user_id'] = $filters['user_id'];
    }

    if (!empty($filters['action'])) {
        $where_clauses[] = "l.action = :action";
        $params[':action'] = $filters['action'];
    }

    if (!empty($filters['start_date'])) {
        $where_clauses[] = "l.created_at >= :start_date";
        $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
    }

    if (!empty($filters['end_date'])) {
        $where_clauses[] = "l.created_at <= :end_date";
        $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
    }

    $sql = "SELECT COUNT(*) FROM logs l";

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    $stmt = $conn->prepare($sql);

    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $total_logs = $stmt->fetchColumn();

    // Calculate total pages
    $total_pages = ceil($total_logs / $items_per_page);

    // Adjust current page if out of bounds
    if ($current_page < 1) {
        $current_page = 1;
    } elseif ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    }

    // Get logs with filters and pagination
    $logs = get_logs($conn, $filters, $items_per_page, $offset);

    // Log access to logs page
    add_log($conn, $user_id, 'view_logs', json_encode([
        'time' => date('Y-m-d H:i:s'),
        'filters' => $filters,
        'page' => $current_page
    ]));

} catch (PDOException $e) {
    error_log("Error loading logs: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <!-- Logs filter -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Lọc nhật ký hệ thống</h5>
        </div>
        <div class="card-body py-2">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-2">
                <div class="col-md-3">
                    <select class="form-select form-select-sm" id="user_id" name="user_id">
                        <option value="0">Tất cả người dùng</option>
                        <?php foreach ($users as $user_item): ?>
                        <option value="<?php echo $user_item['id']; ?>" <?php echo ($filters['user_id'] == $user_item['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user_item['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <select class="form-select form-select-sm" id="action" name="action">
                        <option value="">Tất cả hành động</option>
                        <?php foreach ($actions as $action): ?>
                        <option value="<?php echo $action; ?>" <?php echo ($filters['action'] == $action) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($action); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="<?php echo $filters['start_date']; ?>" placeholder="Từ ngày">
                </div>

                <div class="col-md-2">
                    <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="<?php echo $filters['end_date']; ?>" placeholder="Đến ngày">
                </div>

                <div class="col-md-2 d-flex">
                    <a href="logs.php" class="btn btn-sm btn-secondary me-1">Đặt lại</a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-filter"></i> Lọc
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs list -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Nhật ký hệ thống <span class="badge bg-primary"><?php echo $total_logs; ?> bản ghi</span></h5>
                <div>
                    <form method="post" onsubmit="return confirm('CẢNH BÁO: Hành động này sẽ xóa tất cả nhật ký hệ thống và không thể khôi phục. Bạn có chắc chắn muốn tiếp tục?');" class="m-0">
                        <input type="hidden" name="delete_all_logs" value="1">
                        <button type="submit" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash-alt me-1"></i> Xóa tất cả
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Thời gian</th>
                            <th>Người dùng</th>
                            <th>Hành động</th>
                            <th>Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td><?php echo format_date($log['created_at']); ?></td>
                                <td>
                                    <?php if ($log['user_id'] == 0): ?>
                                        <span class="text-muted">Hệ thống</span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $action_class = 'info';
                                        if (strpos($log['action'], 'login') !== false) $action_class = 'primary';
                                        if (strpos($log['action'], 'create') !== false) $action_class = 'success';
                                        if (strpos($log['action'], 'edit') !== false) $action_class = 'warning';
                                        if (strpos($log['action'], 'delete') !== false || strpos($log['action'], 'lock') !== false) $action_class = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $action_class; ?>">
                                        <?php
                                        // Chuyển action sang tiếng Việt
                                        $action_vi = '';
                                        switch($log['action']) {
                                            case 'login': 
                                                $action_vi = 'Đăng nhập';
                                                break;
                                            case 'logout': 
                                                $action_vi = 'Đăng xuất';
                                                break;
                                            case 'view_profile': 
                                                $action_vi = 'Xem hồ sơ';
                                                break;
                                            case 'edit_profile': 
                                                $action_vi = 'Sửa hồ sơ';
                                                break;
                                            case 'update_avatar': 
                                                $action_vi = 'Cập nhật ảnh đại diện';
                                                break;
                                            case 'view_dashboard': 
                                                $action_vi = 'Xem bảng điều khiển';
                                                break;
                                            case 'view_logs': 
                                                $action_vi = 'Xem nhật ký';
                                                break;
                                            case 'delete_log': 
                                                $action_vi = 'Xóa nhật ký';
                                                break;
                                            case 'delete_all_logs': 
                                                $action_vi = 'Xóa tất cả nhật ký';
                                                break;
                                            case 'create_user': 
                                                $action_vi = 'Tạo người dùng';
                                                break;
                                            case 'edit_user': 
                                                $action_vi = 'Sửa người dùng';
                                                break;
                                            case 'lock_user': 
                                                $action_vi = 'Khóa người dùng';
                                                break;
                                            case 'unlock_user': 
                                                $action_vi = 'Mở khóa người dùng';
                                                break;
                                            case 'view_resource_types': 
                                                $action_vi = 'Xem loại tài nguyên';
                                                break;
                                            case 'add_resource_type': 
                                                $action_vi = 'Thêm loại tài nguyên';
                                                break;
                                            case 'edit_resource_type': 
                                                $action_vi = 'Sửa loại tài nguyên';
                                                break;
                                            case 'delete_resource_type': 
                                                $action_vi = 'Xóa loại tài nguyên';
                                                break;
                                            case 'create_fb_order': 
                                                $action_vi = 'Tạo đơn hàng';
                                                break;
                                            case 'update_fb_order_status': 
                                                $action_vi = 'Cập nhật trạng thái đơn';
                                                break;
                                            case 'view_return_orders': 
                                                $action_vi = 'Xem đơn trả hàng';
                                                break;
                                            case 'code_backup': 
                                                $action_vi = 'Sao lưu mã nguồn';
                                                break;
                                            case 'backup_download': 
                                                $action_vi = 'Tải bản sao lưu';
                                                break;
                                            case 'order_status_update': 
                                                $action_vi = 'Cập nhật trạng thái đơn';
                                                break;
                                            default:
                                                $action_vi = htmlspecialchars($log['action']);
                                                break;
                                        }
                                        echo $action_vi;
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <form method="post" class="m-0" onsubmit="return confirm('Bạn có chắc chắn muốn xóa bản ghi này?');">
                                            <input type="hidden" name="delete_log" value="<?php echo $log['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php 
                                        $details = json_decode($log['details'], true);
                                        if (json_last_error() === JSON_ERROR_NONE) {
                                            echo '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#logModal'.$log['id'].'">';
                                            echo '<i class="fas fa-info-circle"></i> Xem chi tiết';
                                            echo '</button>';

                                            // Modal for log details
                                            echo '<div class="modal fade" id="logModal'.$log['id'].'" tabindex="-1" aria-labelledby="logModalLabel'.$log['id'].'" aria-hidden="true">';
                                            echo '<div class="modal-dialog modal-lg">';
                                            echo '<div class="modal-content">';
                                            echo '<div class="modal-header">';
                                            echo '<h5 class="modal-title" id="logModalLabel'.$log['id'].'">Chi tiết nhật ký #'.$log['id'].'</h5>';
                                            echo '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
                                            echo '</div>';
                                            echo '<div class="modal-body">';
                                            echo '<div class="table-responsive">';
                                            echo '<table class="table table-bordered table-striped">';
                                            echo '<thead class="table-light"><tr><th width="30%">Trường</th><th>Giá trị</th></tr></thead>';
                                            echo '<tbody>';
                                            foreach ($details as $key => $value) {
                                                echo '<tr>';
                                                
                                                // Chuyển tên trường sang tiếng Việt
                                                $field_vi = '';
                                                switch($key) {
                                                    case 'time':
                                                        $field_vi = 'Thời gian';
                                                        break;
                                                    case 'changes':
                                                        $field_vi = 'Thay đổi';
                                                        break;
                                                    case 'username':
                                                        $field_vi = 'Tên người dùng';
                                                        break;
                                                    case 'success':
                                                        $field_vi = 'Trạng thái';
                                                        break;
                                                    case 'ip':
                                                        $field_vi = 'Địa chỉ IP';
                                                        break;
                                                    case 'order_id':
                                                        $field_vi = 'Mã đơn hàng';
                                                        break;
                                                    case 'details':
                                                        $field_vi = 'Chi tiết';
                                                        break;
                                                    case 'resource_id':
                                                        $field_vi = 'Mã tài nguyên';
                                                        break;
                                                    case 'old_status':
                                                        $field_vi = 'Trạng thái cũ';
                                                        break;
                                                    case 'new_status':
                                                        $field_vi = 'Trạng thái mới';
                                                        break;
                                                    case 'filters':
                                                        $field_vi = 'Bộ lọc';
                                                        break;
                                                    case 'page':
                                                        $field_vi = 'Trang';
                                                        break;
                                                    case 'filename':
                                                        $field_vi = 'Tên tệp';
                                                        break;
                                                    case 'fb_config_id':
                                                        $field_vi = 'Cấu hình Facebook';
                                                        break;
                                                    case 'quantity':
                                                        $field_vi = 'Số lượng';
                                                        break;
                                                    case 'source_id':
                                                        $field_vi = 'Nguồn';
                                                        break;
                                                    case 'notification':
                                                        $field_vi = 'Thông báo';
                                                        break;
                                                    default:
                                                        $field_vi = htmlspecialchars($key);
                                                        break;
                                                }
                                                
                                                echo '<td><strong>' . $field_vi . '</strong></td>';
                                                
                                                if (is_array($value)) {
                                                    echo '<td><ul class="list-unstyled mb-0">';
                                                    foreach ($value as $k => $v) {
                                                        // Chuyển tên trường con sang tiếng Việt
                                                        $subfield_vi = '';
                                                        switch($k) {
                                                            case 'email':
                                                                $subfield_vi = 'Email';
                                                                break;
                                                            case 'status':
                                                                $subfield_vi = 'Trạng thái';
                                                                break;
                                                            case 'role':
                                                                $subfield_vi = 'Vai trò';
                                                                break;
                                                            case 'password':
                                                                $subfield_vi = 'Mật khẩu';
                                                                break;
                                                            case 'avatar':
                                                                $subfield_vi = 'Ảnh đại diện';
                                                                break;
                                                            case 'employee_code':
                                                                $subfield_vi = 'Mã nhân viên';
                                                                break;
                                                            case 'user_id':
                                                                $subfield_vi = 'Mã người dùng';
                                                                break;
                                                            case 'action':
                                                                $subfield_vi = 'Hành động';
                                                                break;
                                                            case 'start_date':
                                                                $subfield_vi = 'Ngày bắt đầu';
                                                                break;
                                                            case 'end_date':
                                                                $subfield_vi = 'Ngày kết thúc';
                                                                break;
                                                            case 'search':
                                                                $subfield_vi = 'Tìm kiếm';
                                                                break;
                                                            case 'name':
                                                                $subfield_vi = 'Tên';
                                                                break;
                                                            default:
                                                                $subfield_vi = htmlspecialchars($k);
                                                                break;
                                                        }
                                                        
                                                        if (is_array($v)) {
                                                            echo '<li><strong>' . $subfield_vi . ':</strong> ' . json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</li>';
                                                        } else {
                                                            // Hiển thị giá trị dịch cho các trường đặc biệt
                                                            if ($k == 'success') {
                                                                $v = $v ? 'Thành công' : 'Thất bại';
                                                            } elseif ($k == 'status') {
                                                                switch($v) {
                                                                    case 'Pending': $v = 'Đang chờ xử lý'; break;
                                                                    case 'Processed': $v = 'Đã xử lý'; break;
                                                                    case 'Rejected': $v = 'Từ chối'; break;
                                                                    case 'Returned': $v = 'Đã trả'; break;
                                                                }
                                                            }
                                                            echo '<li><strong>' . $subfield_vi . ':</strong> ' . htmlspecialchars($v) . '</li>';
                                                        }
                                                    }
                                                    echo '</ul></td>';
                                                } else {
                                                    // Hiển thị giá trị dịch cho một số trường đặc biệt
                                                    if ($key == 'success') {
                                                        $value = $value ? 'Thành công' : 'Thất bại';
                                                    }
                                                    echo '<td>' . htmlspecialchars($value) . '</td>';
                                                }
                                                echo '</tr>';
                                            }
                                            echo '</tbody>';
                                            echo '</table>';
                                            echo '</div>';
                                            echo '</div>';
                                            echo '<div class="modal-footer">';
                                            echo '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>';
                                            echo '</div>';
                                            echo '</div>';
                                            echo '</div>';
                                            echo '</div>';
                                        } else {
                                            echo htmlspecialchars($log['details']);
                                        }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Không có bản ghi nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-4">
                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=1<?php echo '&user_id='.$filters['user_id'].'&action='.$filters['action'].'&start_date='.$filters['start_date'].'&end_date='.$filters['end_date']; ?>" aria-label="First">
                            <span aria-hidden="true">&laquo;&laquo;</span>
                        </a>
                    </li>
                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo '&user_id='.$filters['user_id'].'&action='.$filters['action'].'&start_date='.$filters['start_date'].'&end_date='.$filters['end_date']; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>

                    <?php
                    $range = 2; // Number of pages to show before and after current page
                    $start_page = max(1, $current_page - $range);
                    $end_page = min($total_pages, $current_page + $range);

                    if ($start_page > 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        echo '<li class="page-item '.($i == $current_page ? 'active' : '').'">
                                <a class="page-link" href="?page='.$i.'&user_id='.$filters['user_id'].'&action='.$filters['action'].'&start_date='.$filters['start_date'].'&end_date='.$filters['end_date'].'">'.$i.'</a>
                              </li>';
                    }

                    if ($end_page < $total_pages) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    ?>

                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo '&user_id='.$filters['user_id'].'&action='.$filters['action'].'&start_date='.$filters['start_date'].'&end_date='.$filters['end_date']; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo '&user_id='.$filters['user_id'].'&action='.$filters['action'].'&start_date='.$filters['start_date'].'&end_date='.$filters['end_date']; ?>" aria-label="Last">
                            <span aria-hidden="true">&raquo;&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>
