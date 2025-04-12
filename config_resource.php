<?php
// Include header (handles session, database connection, etc.)
include 'includes/header.php';

// Check if user is authorized to access this page
$allowed_roles = ['ADMIN'];
if (!check_role($allowed_roles)) {
    redirect('login.php');
}

// Get current user details
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Check if user can edit/delete (only HKD and ADMIN)
$can_edit = in_array($user_role, ['HKD', 'ADMIN']);

// Initialize variables
$success_message = '';
$error_message = '';
$resource_types = [];
$resource_to_edit = null;
$is_edit_mode = false;

// Pagination settings
$items_per_page = 8;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get resource to edit
if (isset($_GET['edit']) && is_numeric($_GET['edit']) && $can_edit) {
    $edit_id = (int)$_GET['edit'];

    try {
        $stmt = $conn->prepare("SELECT * FROM resource_types WHERE id = :id");
        $stmt->bindParam(':id', $edit_id, PDO::PARAM_INT);
        $stmt->execute();
        $resource_to_edit = $stmt->fetch();

        if ($resource_to_edit) {
            $is_edit_mode = true;
        }
    } catch (PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Process delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $can_edit) {
    $delete_id = (int)$_GET['delete'];

    try {
        // Check if resource type is used in any orders
        $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE resource_type = :id");
        $stmt->bindParam(':id', $delete_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            $error_message = "Không thể xóa loại tài nguyên này vì đã được sử dụng trong đơn hàng";
        } else {
            // Get resource name before deleting
            $stmt = $conn->prepare("SELECT name FROM resource_types WHERE id = :id");
            $stmt->bindParam(':id', $delete_id, PDO::PARAM_INT);
            $stmt->execute();
            $resource_name = $stmt->fetchColumn();

            // Delete resource type
            $stmt = $conn->prepare("DELETE FROM resource_types WHERE id = :id");
            $stmt->bindParam(':id', $delete_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                // Log resource delete
                log_resource_action($conn, $user_id, 'delete_resource_type', $delete_id, [
                    'name' => $resource_name
                ]);

                $success_message = "Đã xóa loại tài nguyên thành công";
            } else {
                $error_message = "Không thể xóa loại tài nguyên";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $can_edit) {
    // Get and sanitize input
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $resource_name = isset($_POST['name']) ? clean_input($_POST['name']) : '';

    // Validate input
    if (empty($resource_name)) {
        $error_message = "Vui lòng nhập tên loại tài nguyên";
    } else {
        try {
            if ($action == 'add') {
                // Check if resource type already exists with proper collation
                $stmt = $conn->prepare("SELECT COUNT(*) FROM resource_types WHERE name COLLATE utf8mb4_unicode_ci = :name");
                $stmt->bindParam(':name', $resource_name, PDO::PARAM_STR);
                $stmt->execute();

                if ($stmt->fetchColumn() > 0) {
                    $error_message = "Loại tài nguyên này đã tồn tại";
                } else {
                    // Add new resource type with auto-increment ID
                    $stmt = $conn->prepare("INSERT INTO resource_types (id, name) SELECT COALESCE(MAX(id), 0) + 1, :name FROM resource_types");
                    $stmt->bindParam(':name', $resource_name, PDO::PARAM_STR);

                    if ($stmt->execute()) {
                        $resource_id = $conn->lastInsertId();

                        // Log resource add
                        log_resource_action($conn, $user_id, 'add_resource_type', $resource_id, [
                            'name' => $resource_name
                        ]);

                        $success_message = "Đã thêm loại tài nguyên mới thành công";
                    } else {
                        $error_message = "Không thể thêm loại tài nguyên mới";
                    }
                }
            } elseif ($action == 'edit' && isset($_POST['id'])) {
                $resource_id = (int)$_POST['id'];

                // Check if resource type already exists with this name (except current one)
                $stmt = $conn->prepare("SELECT COUNT(*) FROM resource_types WHERE name = :name AND id != :id");
                $stmt->bindParam(':name', $resource_name, PDO::PARAM_STR);
                $stmt->bindParam(':id', $resource_id, PDO::PARAM_INT);
                $stmt->execute();

                if ($stmt->fetchColumn() > 0) {
                    $error_message = "Loại tài nguyên này đã tồn tại";
                } else {
                    // Update resource type
                    $stmt = $conn->prepare("UPDATE resource_types SET name = :name WHERE id = :id");
                    $stmt->bindParam(':name', $resource_name, PDO::PARAM_STR);
                    $stmt->bindParam(':id', $resource_id, PDO::PARAM_INT);

                    if ($stmt->execute()) {
                        // Log resource edit
                        log_resource_action($conn, $user_id, 'edit_resource_type', $resource_id, [
                            'name' => $resource_name
                        ]);

                        $success_message = "Đã cập nhật loại tài nguyên thành công";
                        $is_edit_mode = false;
                    } else {
                        $error_message = "Không thể cập nhật loại tài nguyên";
                    }
                }
            }
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get total count for pagination
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM resource_types");
    $stmt->execute();
    $total_items = $stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Adjust current page if out of bounds
    if ($current_page < 1) $current_page = 1;
    if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

    // Get resource types with pagination
    $stmt = $conn->prepare("SELECT * FROM resource_types ORDER BY name LIMIT :offset, :limit");
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $resource_types = $stmt->fetchAll();

    // Log view resource types
    add_log($conn, $user_id, 'view_resource_types', json_encode([
        'time' => date('Y-m-d H:i:s'),
        'page' => $current_page
    ]));
} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
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
        <div class="col-md-8">
            <!-- Resource types list -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Danh sách loại tài nguyên</h5>
                    <div class="input-group" style="width: 250px;">
                        <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Tìm kiếm..." onkeyup="filterTable('searchInput', 'resourceTable')">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="resourceTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên loại tài nguyên</th>
                                    <?php if ($can_edit): ?>
                                    <th>Thao tác</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resource_types as $resource): ?>
                                <tr>
                                    <td><?php echo $resource['id']; ?></td>
                                    <td><?php echo htmlspecialchars($resource['name']); ?></td>
                                    <?php if ($can_edit): ?>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?edit=<?php echo $resource['id']; ?>" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Chỉnh sửa">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $resource['id']; ?>" class="btn btn-outline-danger btn-delete" data-bs-toggle="tooltip" title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (empty($resource_types)): ?>
                    <p class="text-muted text-center">Chưa có loại tài nguyên nào</p>
                    <?php endif; ?>

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

        <?php if ($can_edit): ?>
        <div class="col-md-4">
            <!-- Add/Edit resource form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <?php echo $is_edit_mode ? 'Chỉnh sửa loại tài nguyên' : 'Thêm loại tài nguyên mới'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="<?php echo $is_edit_mode ? 'edit' : 'add'; ?>">

                        <?php if ($is_edit_mode): ?>
                        <input type="hidden" name="id" value="<?php echo $resource_to_edit['id']; ?>">
                        <?php endif; ?>

                        <!-- Resource Name -->
                        <div class="mb-3">
                            <label for="name" class="form-label">Tên loại tài nguyên</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo $is_edit_mode ? htmlspecialchars($resource_to_edit['name']) : ''; ?>" required>
                            <div class="invalid-feedback">
                                Vui lòng nhập tên loại tài nguyên
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <?php if ($is_edit_mode): ?>
                            <a href="config_resource.php" class="btn btn-outline-secondary me-md-2">Hủy</a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-<?php echo $is_edit_mode ? 'save' : 'plus'; ?> me-2"></i>
                                <?php echo $is_edit_mode ? 'Lưu thay đổi' : 'Thêm mới'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>