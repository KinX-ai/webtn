<?php
include 'includes/header.php';

// Check if user is authorized
$allowed_roles = ['ADMIN'];
if (!check_role($allowed_roles)) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

//Pagination variables
$account_type_page = isset($_GET['type_page']) ? (int)$_GET['type_page'] : 1;
$source_page = isset($_GET['source_page']) ? (int)$_GET['source_page'] : 1;
$config_page = isset($_GET['config_page']) ? (int)$_GET['config_page'] : 1;
$itemsPerPage = 3;
$account_type_offset = ($account_type_page - 1) * $itemsPerPage;
$source_offset = ($source_page - 1) * $itemsPerPage;
$config_offset = ($config_page - 1) * $itemsPerPage;


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_account_type'])) {
        $name = isset($_POST['type_name']) ? clean_input($_POST['type_name']) : '';
        if (!empty($name)) {
            try {
                $stmt = $conn->prepare("INSERT INTO fb_account_types (name) VALUES (:name)");
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Đã thêm loại tài khoản mới thành công";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            } catch (PDOException $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_source'])) {
        $name = isset($_POST['source_name']) ? clean_input($_POST['source_name']) : '';
        if (!empty($name)) {
            try {
                $stmt = $conn->prepare("INSERT INTO fb_sources (name) VALUES (:name)");
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Đã thêm nguồn mới thành công";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            } catch (PDOException $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_source'])) {
        $source_id = isset($_POST['source_id']) ? (int)$_POST['source_id'] : 0;
        $name = isset($_POST['source_name']) ? clean_input($_POST['source_name']) : '';

        if (!empty($name) && $source_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE fb_sources SET name = :name WHERE id = :id");
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindParam(':id', $source_id, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Đã cập nhật nguồn thành công";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            } catch (PDOException $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_source'])) {
        $source_id = isset($_POST['source_id']) ? (int)$_POST['source_id'] : 0;

        if ($source_id > 0) {
            try {
                // Check if source is being used in any orders
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM fb_orders WHERE source = :source_id");
                $check_stmt->bindParam(':source_id', $source_id, PDO::PARAM_INT);
                $check_stmt->execute();
                $count = $check_stmt->fetchColumn();

                if ($count > 0) {
                    $_SESSION['error_message'] = "Không thể xóa nguồn này vì đang được sử dụng trong các đơn hàng";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $stmt = $conn->prepare("DELETE FROM fb_sources WHERE id = :id");
                    $stmt->bindParam(':id', $source_id, PDO::PARAM_INT);
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Đã xóa nguồn thành công";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit;
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['toggle_source_status'])) {
        $source_id = isset($_POST['source_id']) ? (int)$_POST['source_id'] : 0;
        $status = isset($_POST['status']) ? clean_input($_POST['status']) : '';
        $new_status = ($status == 'active') ? 'inactive' : 'active';

        if ($source_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE fb_sources SET status = :status WHERE id = :id");
                $stmt->bindParam(':status', $new_status, PDO::PARAM_STR);
                $stmt->bindParam(':id', $source_id, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    $status_message = ($new_status == 'active') ? "kích hoạt" : "ngưng sử dụng";
                    $_SESSION['success_message'] = "Đã " . $status_message . " nguồn thành công";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            } catch (PDOException $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_account_type'])) {
        $type_id = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
        $name = isset($_POST['type_name']) ? clean_input($_POST['type_name']) : '';

        if (!empty($name) && $type_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE fb_account_types SET name = :name WHERE id = :id");
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindParam(':id', $type_id, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Đã cập nhật loại tài khoản thành công";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            } catch (PDOException $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_account_type'])) {
        $type_id = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;

        if ($type_id > 0) {
            try {
                // Check if account type is being used in any configs
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM fb_configs WHERE account_type_id = :type_id");
                $check_stmt->bindParam(':type_id', $type_id, PDO::PARAM_INT);
                $check_stmt->execute();
                $count = $check_stmt->fetchColumn();

                if ($count > 0) {
                    $_SESSION['error_message'] = "Không thể xóa loại tài khoản này vì đang được sử dụng";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $stmt = $conn->prepare("DELETE FROM fb_account_types WHERE id = :id");
                    $stmt->bindParam(':id', $type_id, PDO::PARAM_INT);
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Đã xóa loại tài khoản thành công";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit;
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    }
}

// Get all data with pagination
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM fb_account_types");
    $stmt->execute();
    $total_account_types = $stmt->fetchColumn();
    $total_type_pages = ceil($total_account_types / $itemsPerPage);

    $stmt = $conn->prepare("SELECT * FROM fb_account_types ORDER BY name LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $account_type_offset, PDO::PARAM_INT);
    $stmt->execute();
    $account_types = $stmt->fetchAll();


    $stmt = $conn->prepare("SELECT COUNT(*) FROM fb_sources");
    $stmt->execute();
    $total_sources = $stmt->fetchColumn();
    $total_source_pages = ceil($total_sources / $itemsPerPage);

    $stmt = $conn->prepare("SELECT * FROM fb_sources ORDER BY name LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $source_offset, PDO::PARAM_INT);
    $stmt->execute();
    $sources = $stmt->fetchAll();


    $stmt = $conn->prepare("SELECT COUNT(*) FROM fb_configs");
    $stmt->execute();
    $total_configs = $stmt->fetchColumn();
    $total_config_pages = ceil($total_configs / $itemsPerPage);

    $stmt = $conn->prepare("
        SELECT c.*, t.name as account_type_name 
        FROM fb_configs c
        LEFT JOIN fb_account_types t ON c.account_type_id = t.id
        ORDER BY c.name LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $config_offset, PDO::PARAM_INT);
    $stmt->execute();
    $configs = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error loading data: " . $e->getMessage();
}
?>

<div class="container-fluid">
    <?php if (!empty($success_message) || isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <?php 
        echo !empty($success_message) ? $success_message : $_SESSION['success_message'];
        if(isset($_SESSION['success_message'])) unset($_SESSION['success_message']);
        ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message) || isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <?php 
        echo !empty($error_message) ? $error_message : $_SESSION['error_message']; 
        if(isset($_SESSION['error_message'])) unset($_SESSION['error_message']);
        ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thêm loại tài khoản mới</h5>
                </div>
                <div class="card-body p-3">
                    <form method="post" action="" class="needs-validation" novalidate>
                        <div class="mb-2">
                            <label for="type_name" class="form-label">Tên loại tài khoản</label>
                            <input type="text" class="form-control form-control-sm" id="type_name" name="type_name" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="add_account_type" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i> Thêm loại tài khoản
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thêm nguồn mới</h5>
                </div>
                <div class="card-body p-3">
                    <form method="post" action="" class="needs-validation" novalidate>
                        <div class="mb-2">
                            <label for="source_name" class="form-label">Tên nguồn</label>
                            <input type="text" class="form-control form-control-sm" id="source_name" name="source_name" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="add_source" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i> Thêm nguồn
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h6 class="card-title mb-0">Danh sách loại tài khoản</h6>
                </div>
                <div class="card-body p-1">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm small">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên loại tài khoản</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($account_types as $type): ?>
                                <tr>
                                    <td><?php echo $type['id']; ?></td>
                                    <td><?php echo htmlspecialchars($type['name']); ?></td>
                                    <td><?php echo format_date($type['created_at']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editTypeModal<?php echo $type['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteTypeModal<?php echo $type['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editTypeModal<?php echo $type['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Sửa loại tài khoản</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="type_id" value="<?php echo $type['id']; ?>">
                                                            <div class="mb-3">
                                                                <label for="edit_type_name<?php echo $type['id']; ?>" class="form-label">Tên loại tài khoản</label>
                                                                <input type="text" class="form-control" id="edit_type_name<?php echo $type['id']; ?>" name="type_name" value="<?php echo htmlspecialchars($type['name']); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                            <button type="submit" name="update_account_type" class="btn btn-primary">Lưu thay đổi</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteTypeModal<?php echo $type['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Xác nhận xóa</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="type_id" value="<?php echo $type['id']; ?>">
                                                            <p>Bạn có chắc chắn muốn xóa loại tài khoản <strong><?php echo htmlspecialchars($type['name']); ?></strong>?</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                            <button type="submit" name="delete_account_type" class="btn btn-danger">Xóa</button>
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

                    <?php if ($total_type_pages > 1): ?>
                    <!-- Pagination for account types -->
                    <div class="d-flex justify-content-center mt-4">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($account_type_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?type_page=1&source_page=<?php echo $source_page; ?>&config_page=<?php echo $config_page; ?>" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?type_page=<?php echo $account_type_page - 1; ?>&source_page=<?php echo $source_page; ?>&config_page=<?php echo $config_page; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $account_type_page - 2);
                                $end_page = min($total_type_pages, $account_type_page + 2);

                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                <li class="page-item <?php echo ($i == $account_type_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type_page=<?php echo $i; ?>&source_page=<?php echo $source_page; ?>&config_page=<?php echo $config_page; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($account_type_page < $total_type_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?type_page=<?php echo $account_type_page + 1; ?>&source_page=<?php echo $source_page; ?>&config_page=<?php echo $config_page; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?type_page=<?php echo $total_type_pages; ?>&source_page=<?php echo $source_page; ?>&config_page=<?php echo $config_page; ?>" aria-label="Last">
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

            <div class="card mb-3">
                <div class="card-header py-2">
                    <h6 class="card-title mb-0">Danh sách nguồn</h6>
                </div>
                <div class="card-body p-1">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm small">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên nguồn</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sources as $source): ?>
                                <tr>
                                    <td><?php echo $source['id']; ?></td>
                                    <td><?php echo htmlspecialchars($source['name']); ?></td>
                                    <td>
                                        <?php 
                                        $status = isset($source['status']) ? $source['status'] : 'active';
                                        $statusClass = ($status == 'active') ? 'success' : 'danger';
                                        $statusText = ($status == 'active') ? 'Đang sử dụng' : 'Ngưng sử dụng';
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                    <td><?php echo format_date($source['created_at']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editSourceModal<?php echo $source['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteSourceModal<?php echo $source['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>

                                        <?php 
                                        $status = isset($source['status']) ? $source['status'] : 'active';
                                        $toggleBtnClass = ($status == 'active') ? 'warning' : 'success';
                                        $toggleIcon = ($status == 'active') ? 'fa-times-circle' : 'fa-check-circle';
                                        $toggleTitle = ($status == 'active') ? 'Ngưng sử dụng' : 'Kích hoạt';
                                        ?>
                                        <button type="button" class="btn btn-sm btn-<?php echo $toggleBtnClass; ?>" data-bs-toggle="modal" data-bs-target="#toggleSourceModal<?php echo $source['id']; ?>" title="<?php echo $toggleTitle; ?>">
                                            <i class="fas <?php echo $toggleIcon; ?>"></i>
                                        </button>

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editSourceModal<?php echo $source['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Sửa nguồn</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="source_id" value="<?php echo $source['id']; ?>">
                                                            <div class="mb-3">
                                                                <label for="edit_source_name<?php echo $source['id']; ?>" class="form-label">Tên nguồn</label>
                                                                <input type="text" class="form-control" id="edit_source_name<?php echo $source['id']; ?>" name="source_name" value="<?php echo htmlspecialchars($source['name']); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                            <button type="submit" name="update_source" class="btn btn-primary">Lưu thay đổi</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteSourceModal<?php echo $source['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Xác nhận xóa</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="source_id" value="<?php echo $source['id']; ?>">
                                                            <p>Bạn có chắc chắn muốn xóa nguồn <strong><?php echo htmlspecialchars($source['name']); ?></strong>?</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                            <button type="submit" name="delete_source" class="btn btn-danger">Xóa</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Toggle Status Modal -->
                                        <div class="modal fade" id="toggleSourceModal<?php echo $source['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Xác nhận thay đổi trạng thái</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="source_id" value="<?php echo $source['id']; ?>">
                                                            <input type="hidden" name="status" value="<?php echo $status; ?>">
                                                            <?php if ($status == 'active'): ?>
                                                            <p>Bạn có chắc chắn muốn <strong>ngưng sử dụng</strong> nguồn <strong><?php echo htmlspecialchars($source['name']); ?></strong>?</p>
                                                            <div class="alert alert-warning">
                                                                <small>Lưu ý: Nguồn bị ngưng sử dụng sẽ không hiển thị trong form đặt hàng.</small>
                                                            </div>
                                                            <?php else: ?>
                                                            <p>Bạn có chắc chắn muốn <strong>kích hoạt</strong> nguồn <strong><?php echo htmlspecialchars($source['name']); ?></strong>?</p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                            <button type="submit" name="toggle_source_status" class="btn btn-<?php echo ($status == 'active') ? 'warning' : 'success'; ?>">
                                                                <?php echo ($status == 'active') ? 'Ngưng sử dụng' : 'Kích hoạt'; ?>
                                                            </button>
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
                    
                    <?php if ($total_source_pages > 1): ?>
                    <!-- Pagination for sources -->
                    <div class="d-flex justify-content-center mt-2">
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm">
                                <?php if ($source_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?type_page=<?php echo $account_type_page; ?>&source_page=1&config_page=<?php echo $config_page; ?>" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?type_page=<?php echo $account_type_page; ?>&source_page=<?php echo $source_page - 1; ?>&config_page=<?php echo $config_page; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $source_page - 2);
                                $end_page = min($total_source_pages, $source_page + 2);

                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                <li class="page-item <?php echo ($i == $source_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type_page=<?php echo $account_type_page; ?>&source_page=<?php echo $i; ?>&config_page=<?php echo $config_page; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($source_page < $total_source_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?type_page=<?php echo $account_type_page; ?>&source_page=<?php echo $source_page + 1; ?>&config_page=<?php echo $config_page; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?type_page=<?php echo $account_type_page; ?>&source_page=<?php echo $total_source_pages; ?>&config_page=<?php echo $config_page; ?>" aria-label="Last">
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