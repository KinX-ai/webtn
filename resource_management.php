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
$success_message = '';
$error_message = '';

// Get all users for tags
try {
    $stmt = $conn->prepare("SELECT id, username FROM users ORDER BY username");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error loading users: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'delete' && isset($_POST['resource_id'])) {
        $resource_id = (int)$_POST['resource_id'];
        try {
            $conn->beginTransaction();

            // Check if resource exists
            $check_stmt = $conn->prepare("SELECT id FROM resources WHERE id = :id");
            $check_stmt->bindParam(':id', $resource_id);
            $check_stmt->execute();

            if ($check_stmt->fetch()) {
                // Delete assignments first (cascade will handle this due to foreign key)
                $delete_stmt = $conn->prepare("DELETE FROM resources WHERE id = :id");
                $delete_stmt->bindParam(':id', $resource_id);

                if ($delete_stmt->execute()) {
                    $conn->commit();
                    header("Location: resource_management.php?success=deleted");
                    exit();
                } else {
                    $conn->rollBack();
                    $error_message = "Không thể xóa tài nguyên. Vui lòng thử lại.";
                }
            } else {
                $conn->rollBack();
                $error_message = "Tài nguyên không tồn tại hoặc đã bị xóa";
            }
        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("Database error: " . $e->getMessage());
            $error_message = "Lỗi khi xóa: " . $e->getMessage();
        }
    }

    if ($action == 'add' || $action == 'edit') {
        $name = clean_input($_POST['name'] ?? '');
        $category = clean_input($_POST['category'] ?? '');
        $status = clean_input($_POST['status'] ?? '');
        $notes = clean_input($_POST['notes'] ?? '');
        $assigned_users = $_POST['assigned_users'] ?? [];

        if (empty($name) || empty($category)) {
            $error_message = "Vui lòng điền đầy đủ thông tin bắt buộc";
        } else {
            try {
                $conn->beginTransaction();

                if ($action == 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO resources (name, category, status, notes, created_by, created_at) 
                        VALUES (:name, :category, :status, :notes, :user_id, NOW())
                    ");

                    $params = [
                        ':name' => $name,
                        ':category' => $category, 
                        ':status' => $status,
                        ':notes' => $notes,
                        ':user_id' => $user_id
                    ];

                    if ($stmt->execute($params)) {
                        $resource_id = $conn->lastInsertId();

                        // Add user assignments for new resource
                        if (!empty($assigned_users)) {
                            $assign_stmt = $conn->prepare("
                                INSERT INTO resource_assignments (resource_id, user_id) 
                                VALUES (:resource_id, :user_id)
                            ");

                            foreach ($assigned_users as $assigned_user) {
                                $assign_stmt->execute([
                                    ':resource_id' => $resource_id,
                                    ':user_id' => $assigned_user
                                ]);
                            }
                        }

                        $conn->commit();
                        header("Location: resource_management.php?success=added");
                        exit();
                    }
                } else {
                    $resource_id = (int)$_POST['resource_id'];
                    $stmt = $conn->prepare("
                        UPDATE resources 
                        SET name = :name, 
                            category = :category, 
                            status = :status, 
                            notes = :notes,
                            updated_at = NOW()
                        WHERE id = :resource_id
                    ");

                    $params = [
                        ':resource_id' => $resource_id,
                        ':name' => $name,
                        ':category' => $category,
                        ':status' => $status,
                        ':notes' => $notes
                    ];

                    if ($stmt->execute($params)) {
                        // Update user assignments
                        $stmt = $conn->prepare("DELETE FROM resource_assignments WHERE resource_id = :resource_id");
                        $stmt->execute([':resource_id' => $resource_id]);

                        if (!empty($assigned_users)) {
                            $assign_stmt = $conn->prepare("
                                INSERT INTO resource_assignments (resource_id, user_id) 
                                VALUES (:resource_id, :user_id)
                            ");

                            foreach ($assigned_users as $assigned_user) {
                                $assign_stmt->execute([
                                    ':resource_id' => $resource_id,
                                    ':user_id' => $assigned_user
                                ]);
                            }
                        }

                        $conn->commit();
                        header("Location: resource_management.php?success=updated");
                        exit();
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    }
}

// Get resources with filters
$where_conditions = [];
$params = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_conditions[] = "(r.name LIKE :search OR r.category LIKE :search OR r.notes LIKE :search)";
    $params[':search'] = $search;
}

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $where_conditions[] = "r.category = :category";
    $params[':category'] = $_GET['category'];
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_conditions[] = "r.status = :status";
    $params[':status'] = $_GET['status'];
}

if (isset($_GET['assigned_user']) && !empty($_GET['assigned_user'])) {
    $where_conditions[] = "ra.user_id = :assigned_user";
    $params[':assigned_user'] = $_GET['assigned_user'];
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // Get distinct categories for filter
    $stmt = $conn->prepare("SELECT DISTINCT category FROM resources ORDER BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get resources
    $sql = "
        SELECT DISTINCT r.*, GROUP_CONCAT(u.username) as assigned_users
        FROM resources r
        LEFT JOIN resource_assignments ra ON r.id = ra.resource_id
        LEFT JOIN users u ON ra.user_id = u.id
        $where_clause
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $resources = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}
?>

<style>
.modal-content {
    border-radius: 15px;
    overflow: hidden;
}

.modal-header {
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
    color: white;
    border: none;
    padding: 1.5rem;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    border-top: 1px solid #eee;
    padding: 1rem 2rem;
}

.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.form-control, .form-select {
    border-radius: 8px;
    padding: 0.6rem 1rem;
    border: 1px solid #e0e6ed;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
}

.toast {
    border-radius: 10px;
}

.toast-body {
    padding: 1rem;
    font-size: 0.95rem;
}
</style>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <?php 
            if (isset($_GET['success'])) {
                $message = '';
                switch($_GET['success']) {
                    case 'added':
                        $message = "Đã thêm tài nguyên mới";
                        break;
                    case 'updated':
                        $message = "Đã cập nhật tài nguyên";
                        break;
                    case 'deleted':
                        $message = "Đã xóa tài nguyên thành công";
                        break;
                }
                echo '<div class="alert alert-success text-center" role="alert">' . $message . '</div>';
            }
            ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="get" id="filterForm">
                <div class="row align-items-center g-2">
                    <div class="col">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Tìm kiếm..." value="<?php echo $_GET['search'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="col">
                        <select class="form-select" id="filter_assigned_user" name="assigned_user">
                            <option value="">Người được gán</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo (isset($_GET['assigned_user']) && $_GET['assigned_user'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col">
                        <select class="form-select" id="status" name="status">
                            <option value="">Trạng thái</option>
                            <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?>>Đang sử dụng</option>
                            <option value="inactive" <?php echo (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'selected' : ''; ?>>Không sử dụng</option>
                            <option value="dead" <?php echo (isset($_GET['status']) && $_GET['status'] == 'dead') ? 'selected' : ''; ?>>Đã die</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            Tìm kiếm
                        </button>
                        <a href="resource_management.php" class="btn btn-secondary">
                            Đặt lại
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Quản lý tài nguyên</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#resourceModal">
                <i class="fas fa-plus me-1"></i> Thêm tài nguyên
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên</th>
                            <th>Nội dung</th>
                            <th>Trạng thái</th>
                            <th>Người được gán</th>
                            <th>Ghi chú</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources as $resource): ?>
                        <tr>
                            <td><?php echo $resource['id']; ?></td>
                            <td><?php echo htmlspecialchars($resource['name']); ?></td>
                            <td><?php echo htmlspecialchars($resource['category']); ?></td>
                            <td>
                                <?php
                                $status_class = 'secondary';
                                $status_text = 'Không xác định';

                                switch ($resource['status']) {
                                    case 'active':
                                        $status_class = 'success';
                                        $status_text = 'Đang sử dụng';
                                        break;
                                    case 'inactive':
                                        $status_class = 'warning';
                                        $status_text = 'Không sử dụng';
                                        break;
                                    case 'dead':
                                        $status_class = 'danger';
                                        $status_text = 'Đã die';
                                        break;
                                }
                                ?>
                                <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            </td>
                            <td>
                                <?php
                                $assigned_users = explode(',', $resource['assigned_users']);
                                foreach ($assigned_users as $assigned_user) {
                                    if (!empty($assigned_user)) {
                                        echo '<span class="badge bg-info me-1">' . htmlspecialchars($assigned_user) . '</span>';
                                    }
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($resource['notes']); ?></td>
                            <td><?php echo format_date($resource['created_at']); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-primary btn-edit" 
                                            data-resource='<?php echo json_encode($resource); ?>'
                                            data-bs-toggle="modal" data-bs-target="#resourceModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-delete" 
                                            data-id="<?php echo $resource['id']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($resources)): ?>
            <p class="text-muted text-center">Không có tài nguyên nào</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Resource Modal -->
<div class="modal fade" id="resourceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="toast-container position-fixed top-0 end-0 p-3">
                <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="2000">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-check-circle me-2"></i> <span id="toastMessage"></span>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <form method="post" id="resourceForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="resource_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title">Thêm tài nguyên mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Trạng thái</label>
                        <select class="form-select" id="modalStatus" name="status">
                            <option value="active">Đang sử dụng</option>
                            <option value="inactive">Không sử dụng</option>
                            <option value="dead">Đã die</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Tên tài nguyên <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="assigned_users" class="form-label">Gán cho người dùng</label>
                        <select class="form-select" id="assigned_users" name="assigned_users[]">
                            <option value="">Chọn người dùng</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="category" class="form-label">Nội dung</label>
                        <textarea class="form-control" id="modalCategory" name="category" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Ghi chú</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="resource_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title">Xác nhận xóa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn xóa tài nguyên này?</p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">Xóa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Removed setDeleteId function - no longer needed

document.addEventListener('DOMContentLoaded', function() {
    const successToast = new bootstrap.Toast(document.getElementById('successToast'));
    const toastMessage = document.getElementById('toastMessage');

    // Xử lý hiển thị thông báo thành công
    if (document.querySelector('.alert-success')) {
        const message = document.querySelector('.alert-success').textContent;
        toastMessage.textContent = message;
        successToast.show();
        // Xóa alert mặc định
        document.querySelector('.alert-success').remove();
    }
    // Handle edit button click
    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', function() {
            const resource = JSON.parse(this.dataset.resource);
            const modal = document.getElementById('resourceModal');

            modal.querySelector('.modal-title').textContent = 'Chỉnh sửa tài nguyên';
            modal.querySelector('[name="action"]').value = 'edit';
            modal.querySelector('[name="resource_id"]').value = resource.id;
            modal.querySelector('[name="name"]').value = resource.name;
            modal.querySelector('[name="category"]').value = resource.category;
            modal.querySelector('[name="status"]').value = resource.status;
            modal.querySelector('[name="notes"]').value = resource.notes;

            // Handle assigned users
            const assignedUsers = resource.assigned_users ? resource.assigned_users.split(',') : [];
            const select = modal.querySelector('[name="assigned_users[]"]');
            Array.from(select.options).forEach(option => {
                option.selected = assignedUsers.includes(option.value);
            });
        });
    });

    // Handle delete button click - simplified
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelector('#deleteModal [name="resource_id"]').value = this.dataset.id;
        });
    });

    // Reset form when modal is closed
    document.getElementById('resourceModal').addEventListener('hidden.bs.modal', function() {
        this.querySelector('form').reset();
        this.querySelector('.modal-title').textContent = 'Thêm tài nguyên mới';
        this.querySelector('[name="action"]').value = 'add';
        this.querySelector('[name="resource_id"]').value = '';
    });
});
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.13.3/css/selectize.bootstrap4.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.13.3/js/selectize.min.js"></script>
<script>
$(document).ready(function() {
    $('.selectize').selectize({
        plugins: ['remove_button'],
        create: false,
        sortField: 'text'
    });

    $('#filter_assigned_user').selectize({
        plugins: ['remove_button'],
        create: false,
        sortField: 'text'
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>