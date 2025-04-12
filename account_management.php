<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include header (handles session, database connection, etc.)
include 'includes/header.php';

// Check if user is authorized to access this page
$allowed_roles = ['HKD', 'ADMIN', 'GS'];
if (!check_role($allowed_roles)) {
    redirect('login.php');
}

// Only HKD and ADMIN can create new accounts
$can_create_accounts = check_role(['HKD', 'ADMIN']);

// Get current user details
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Initialize variables
$success_message = '';
$error_message = '';
$users = [];
$user_to_edit = [];
$is_edit_mode = false;

// Function to handle user operation
function handle_user_operation($conn, $user_id, $user_role, $action, $data) {
    $error = '';

    try {
        switch ($action) {
            case 'add':
                // Check if username already exists
                $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
                $stmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
                $stmt->execute();

                if ($stmt->fetchColumn() > 0) {
                    return "Tên đăng nhập đã tồn tại";
                }

                // Set appropriate role for new user
                if ($user_role == 'HKD') {
                    $data['role'] = 'USER_HKD'; // HKD can only create USER_HKD
                }

                // Create user with parent_id for USER_HKD role
                // Only assign current user as parent if it's an HKD user adding a USER_HKD
                $parent_id = null;
                if ($data['role'] == 'USER_HKD') {
                    if ($user_role == 'HKD') {
                        // HKD users always assign themselves as parent
                        $parent_id = $user_id;
                    } else if (isset($data['parent_id']) && !empty($data['parent_id'])) {
                        // ADMIN can choose parent HKD
                        $parent_id = $data['parent_id'];
                    }
                }

                // Ensure employee_code is properly captured
                $employee_code = isset($data['employee_code']) ? clean_input($data['employee_code']) : '';

                // Log for debugging
                error_log("Employee code: " . $employee_code);

                if (create_user($conn, $data['username'], $data['password'], $data['email'], $data['role'], $parent_id, $employee_code)) {
                    // Get new user's ID
                    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
                    $stmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
                    $stmt->execute();
                    $new_user_id = $stmt->fetchColumn();

                    // Log action
                    log_user_management($conn, $user_id, 'create_user', $new_user_id, [
                        'username' => $data['username'],
                        'role' => $data['role']
                    ]);
                } else {
                    return "Không thể tạo tài khoản mới";
                }
                break;

            case 'edit':
                // Get target user
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
                $stmt->execute();
                $target_user = $stmt->fetch();

                if (!$target_user) {
                    return "Tài khoản không tồn tại";
                }

                // Check if current user can manage target user
                if (!can_manage_user($user_role, $target_user['role'])) {
                    return "Bạn không có quyền quản lý tài khoản này";
                }

                // Prepare update data
                $update_data = ['email' => $data['email']];
                $changes = ['email' => $data['email']];

                // If password is provided, update it
                if (!empty($data['password'])) {
                    $update_data['password'] = $data['password'];
                    $changes['password'] = 'changed';
                }

                // If status is provided, update it
                if (isset($data['status'])) {
                    $update_data['status'] = $data['status'];
                    $changes['status'] = $data['status'] ? 'active' : 'inactive';
                }

                //If role is provided, update it (only for ADMIN)
                if (isset($data['role'])) {
                    $update_data['role'] = $data['role'];
                    $changes['role'] = $data['role'];
                }

                // Add employee code to update data
                if (isset($data['employee_code'])) {
                    $update_data['employee_code'] = clean_input($data['employee_code']);
                    $changes['employee_code'] = $data['employee_code'];
                }

                // If parent_id is provided (for USER_HKD accounts)
                if (isset($data['parent_id']) && $target_user['role'] == 'USER_HKD') {
                    // Update parent_id in the users table directly
                    $parent_update = $conn->prepare("UPDATE users SET parent_id = :parent_id WHERE id = :id");
                    if (empty($data['parent_id'])) {
                        $parent_update->bindValue(':parent_id', null, PDO::PARAM_NULL);
                        $changes['parent_id'] = 'removed';
                    } else {
                        $parent_update->bindValue(':parent_id', (int)$data['parent_id'], PDO::PARAM_INT);
                        $changes['parent_id'] = $data['parent_id'];
                    }
                    $parent_update->bindParam(':id', $data['id'], PDO::PARAM_INT);
                    $parent_update->execute();
                }

                // Update user
                if (update_user($conn, $data['id'], $update_data)) {
                    // Log action
                    log_user_management($conn, $user_id, 'edit_user', $data['id'], $changes);
                } else {
                    return "Không thể cập nhật tài khoản";
                }
                break;

            case 'lock':
                // Get target user
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
                $stmt->execute();
                $target_user = $stmt->fetch();

                if (!$target_user) {
                    return "Tài khoản không tồn tại";
                }

                // Check if current user can manage target user
                if (!can_manage_user($user_role, $target_user['role'])) {
                    return "Bạn không có quyền quản lý tài khoản này";
                }

                // Lock user
                $update_data = ['status' => 0];
                if (update_user($conn, $data['id'], $update_data)) {
                    // Log action
                    log_user_management($conn, $user_id, 'lock_user', $data['id'], [
                        'username' => $target_user['username']
                    ]);
                } else {
                    return "Không thể khóa tài khoản";
                }
                break;

            case 'unlock':
                // Get target user
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
                $stmt->execute();
                $target_user = $stmt->fetch();

                if (!$target_user) {
                    return "Tài khoản không tồn tại";
                }

                // Check if current user can manage target user
                if (!can_manage_user($user_role, $target_user['role'])) {
                    return "Bạn không có quyền quản lý tài khoản này";
                }

                // Unlock user
                $update_data = ['status' => 1];
                if (update_user($conn, $data['id'], $update_data)) {
                    // Log action
                    log_user_management($conn, $user_id, 'unlock_user', $data['id'], [
                        'username' => $target_user['username']
                    ]);
                } else {
                    return "Không thể mở khóa tài khoản";
                }
                break;
        }

        return '';
    } catch (PDOException $e) {
        return "Database error: " . $e->getMessage();
    }
}

// Get user to edit
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindParam(':id', $edit_id, PDO::PARAM_INT);
        $stmt->execute();
        $user_to_edit = $stmt->fetch();

        // Check if current user can manage this user
        if ($user_to_edit && can_manage_user($user_role, $user_to_edit['role'])) {
            $is_edit_mode = true;
        } else {
            $error_message = "Bạn không có quyền chỉnh sửa tài khoản này";
        }
    } catch (PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Process lock/unlock/delete actions
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $action = $_GET['action'];
    $target_id = (int)$_GET['id'];
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

    if ($action == 'lock' || $action == 'unlock') {
        $error = handle_user_operation($conn, $user_id, $user_role, $action, ['id' => $target_id]);

        if (empty($error)) {
            $_SESSION['success_message'] = ($action == 'lock') ? "Đã khóa tài khoản thành công" : "Đã mở khóa tài khoản thành công";
        } else {
            $_SESSION['error_message'] = $error;
        }
    } elseif ($action == 'delete') {
        // Check if user can be deleted
        try {
            // First check if the user exists and if current user can manage it
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->bindParam(':id', $target_id, PDO::PARAM_INT);
            $stmt->execute();
            $target_user = $stmt->fetch();

            if (!$target_user) {
                $_SESSION['error_message'] = "Tài khoản không tồn tại";
            } elseif (!can_manage_user($user_role, $target_user['role'])) {
                $_SESSION['error_message'] = "Bạn không có quyền xóa tài khoản này";
            } else {
                // Delete the user
                $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
                $delete_stmt->bindParam(':id', $target_id, PDO::PARAM_INT);
                $delete_stmt->execute();

                // Log action
                log_user_management($conn, $user_id, 'delete_user', $target_id, [
                    'username' => $target_user['username']
                ]);

                $_SESSION['success_message'] = "Đã xóa tài khoản thành công";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Lỗi khi xóa tài khoản: " . $e->getMessage();
        }
    }

    // Redirect to prevent duplicate actions and maintain pagination
    header("Location: " . $_SERVER['PHP_SELF'] . "?page=" . $current_page);
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize input
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action == 'add' || $action == 'edit') {
        $data = [];

        if ($action == 'add') {
            $data['username'] = clean_input($_POST['username']);
            $data['password'] = $_POST['password']; // Don't clean password
            $data['email'] = clean_input($_POST['email']);
            $data['role'] = clean_input($_POST['role']);
            $data['employee_code'] = clean_input($_POST['employee_code']);
            if(isset($_POST['parent_id'])) $data['parent_id'] = $_POST['parent_id'];

            // Validate input
            if (empty($data['username']) || empty($data['password']) || empty($data['email'])) {
                $_SESSION['error_message'] = "Vui lòng điền đầy đủ thông tin";
            } elseif (strlen($data['password']) < 8) {
                $_SESSION['error_message'] = "Mật khẩu phải có ít nhất 8 ký tự";
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error_message'] = "Email không hợp lệ";
            } else {
                $error = handle_user_operation($conn, $user_id, $user_role, $action, $data);

                if (empty($error)) {
                    $_SESSION['success_message'] = "Đã tạo tài khoản mới thành công";
                } else {
                    $_SESSION['error_message'] = $error;
                }
            }
        } elseif ($action == 'edit') {
            $data['id'] = (int)$_POST['id'];
            $data['email'] = clean_input($_POST['email']);
            $data['password'] = $_POST['password']; // Don't clean password
            $data['status'] = isset($_POST['status']) ? 1 : 0;
            $data['employee_code'] = clean_input($_POST['employee_code']); // Added employee_code

            // Add role if admin is changing it
            if ($user_role == 'ADMIN' && isset($_POST['role'])) {
                $data['role'] = clean_input($_POST['role']);
            }

            // Add parent_id for USER_HKD accounts
            if (isset($_POST['parent_id'])) {
                $data['parent_id'] = $_POST['parent_id'];
            }

            // Validate input
            if (empty($data['email'])) {
                $_SESSION['error_message'] = "Vui lòng điền email";
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error_message'] = "Email không hợp lệ";
            } elseif (!empty($data['password']) && strlen($data['password']) < 8) {
                $_SESSION['error_message'] = "Mật khẩu phải có ít nhất 8 ký tự";
            } else {
                $error = handle_user_operation($conn, $user_id, $user_role, $action, $data);

                if (empty($error)) {
                    $_SESSION['success_message'] = "Đã cập nhật tài khoản thành công";
                } else {
                    $_SESSION['error_message'] = $error;
                }
            }
        }

        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get messages from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get search term if provided
$search_term = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Get list of users based on role with pagination
try {
    $users_per_page = 6; // Display 6 users per page
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $current_page = max(1, $current_page); // Ensure page is at least 1

    // Get total number of users for pagination
    $all_users = get_managed_users($conn, $user_role, $user_id);

    // Filter users if search term is provided
    if (!empty($search_term)) {
        $filtered_users = [];
        foreach ($all_users as $user) {
            // Search in username, email, employee_code, role
            if (
                stripos($user['username'], $search_term) !== false ||
                stripos($user['email'], $search_term) !== false ||
                stripos($user['employee_code'] ?? '', $search_term) !== false ||
                stripos(get_role_name($user['role']), $search_term) !== false
            ) {
                $filtered_users[] = $user;
            }
        }
        $all_users = $filtered_users;
    }

    // Make sure employee_code is available for each user
    foreach ($all_users as &$user) {
        if (!isset($user['employee_code']) || empty($user['employee_code'])) {
            // Set a default employee code if not present
            $user['employee_code'] = 'EMP' . $user['id'];
        }
    }

    $total_users = count($all_users);
    $total_pages = ceil($total_users / $users_per_page);

    // Get users for current page
    $start_index = ($current_page - 1) * $users_per_page;
    $users = array_slice($all_users, $start_index, $users_per_page);
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
            <!-- Users list -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Danh sách tài khoản</h5>
                    <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="d-flex">
                        <?php if (isset($_GET['page'])): ?>
                        <input type="hidden" name="page" value="<?php echo (int)$_GET['page']; ?>">
                        <?php endif; ?>
                        <div class="input-group" style="width: 250px;">
                            <input type="text" class="form-control form-control-sm" id="search" name="search" placeholder="Tìm kiếm..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <button class="input-group-text" type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="usersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên đăng nhập</th>
                                    <th>Mã NV</th>
                                    <th>Quyền</th>
                                    <th>Trực thuộc HKD</th>
                                    <th>Ngày tạo</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user_item): ?>
                                <tr>
                                    <td><?php echo $user_item['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user_item['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['employee_code'] ?? ''); ?></td>
                                    <td><?php echo get_role_name($user_item['role']); ?></td>
                                    <td>
                                        <?php if ($user_item['role'] == 'USER_HKD'): ?>
                                            <?php if (!empty($user_item['parent_username'])): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($user_item['parent_username']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Chưa được gán</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo format_date($user_item['created_at']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user_item['status'] ? 'success' : 'danger'; ?>">
                                            <?php echo $user_item['status'] ? 'Hoạt động' : 'Bị khóa'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (can_manage_user($user_role, $user_item['role']) && $user_role != 'GS'): ?>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?edit=<?php echo $user_item['id']; ?>" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Chỉnh sửa">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user_item['status']): ?>
                                            <a href="?action=lock&id=<?php echo $user_item['id']; ?>" class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Khóa tài khoản" onclick="return confirm('Bạn có chắc chắn muốn khóa tài khoản này?')">
                                                <i class="fas fa-lock"></i>
                                            </a>
                                            <?php else: ?>
                                            <a href="?action=unlock&id=<?php echo $user_item['id']; ?>" class="btn btn-outline-success" data-bs-toggle="tooltip" title="Mở khóa tài khoản">
                                                <i class="fas fa-lock-open"></i>
                                            </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal-<?php echo $user_item['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>

                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal-<?php echo $user_item['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel-<?php echo $user_item['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteModalLabel-<?php echo $user_item['id']; ?>">Xóa tài khoản</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Bạn có chắc chắn muốn xóa tài khoản <strong><?php echo htmlspecialchars($user_item['username']); ?></strong>?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                            <a href="?action=delete&id=<?php echo $user_item['id']; ?>" class="btn btn-danger">Xóa</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted">Không có quyền</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (empty($users)): ?>
                    <p class="text-muted text-center">
                        <?php if (!empty($search_term)): ?>
                            Không tìm thấy tài khoản nào khớp với từ khóa "<?php echo htmlspecialchars($search_term); ?>"
                            <br><a href="account_management.php" class="btn btn-sm btn-outline-secondary mt-2">Xóa bộ lọc</a>
                        <?php else: ?>
                            Không có tài khoản nào trong quyền quản lý của bạn
                        <?php endif; ?>
                    </p>
                    <?php else: ?>
                    <!-- Pagination -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php 
                                // Build query string for pagination links
                                $query_params = [];
                                if (!empty($search_term)) {
                                    $query_params['search'] = urlencode($search_term);
                                }
                                
                                // Function to generate pagination URLs
                                function build_pagination_url($page, $params = []) {
                                    $params['page'] = $page;
                                    return '?' . http_build_query($params);
                                }
                            ?>
                            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo build_pagination_url($current_page - 1, $query_params); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>

                            <?php if ($total_pages > 0): ?>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo build_pagination_url($i, $query_params); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                            <?php else: ?>
                                <li class="page-item active">
                                    <a class="page-link" href="<?php echo build_pagination_url(1, $query_params); ?>">1</a>
                                </li>
                            <?php endif; ?>

                            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo build_pagination_url($current_page + 1, $query_params); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Add/Edit user form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <?php echo $is_edit_mode ? 'Chỉnh sửa tài khoản' : 'Thêm tài khoản mới'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!$is_edit_mode && !$can_create_accounts): ?>
                        <div class="alert alert-info">
                            Bạn không có quyền tạo tài khoản mới.
                        </div>
                    <?php else: ?>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="<?php echo $is_edit_mode ? 'edit' : 'add'; ?>">

                        <?php if ($is_edit_mode): ?>
                        <input type="hidden" name="id" value="<?php echo $user_to_edit['id']; ?>">
                        <?php endif; ?>

                        <?php if (!$is_edit_mode): ?>
                        <!-- Username (only for new users) -->
                        <div class="mb-3">
                            <label for="username" class="form-label">Tên đăng nhập</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="invalid-feedback">
                                Vui lòng nhập tên đăng nhập
                            </div>
                        </div>

                        <!-- Employee Code -->
                        <div class="mb-3">
                            <label for="employee_code" class="form-label">Mã nhân viên</label>
                            <input type="text" class="form-control" id="employee_code" name="employee_code">
                            <div class="form-text">Nhập mã nhân viên (nếu có)</div>
                        </div>
                        <?php else: ?>
                        <!-- Display username for edit mode -->
                        <div class="mb-3">
                            <label class="form-label">Tên đăng nhập</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_to_edit['username']); ?>" disabled>
                        </div>

                        <!-- Employee Code -->
                        <div class="mb-3">
                            <label for="employee_code" class="form-label">Mã nhân viên</label>
                            <input type="text" class="form-control" id="employee_code" name="employee_code" value="<?php echo htmlspecialchars($user_to_edit['employee_code'] ?? ''); ?>">
                            <div class="form-text">Nhập mã nhân viên (nếu có)</div>
                        </div>
                        <?php endif; ?>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $is_edit_mode ? htmlspecialchars($user_to_edit['email']) : ''; ?>" required>
                            <div class="invalid-feedback">
                                Vui lòng nhập email hợp lệ
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <?php echo $is_edit_mode ? 'Mật khẩu mới (để trống nếu không đổi)' : 'Mật khẩu'; ?>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" <?php echo $is_edit_mode ? '' : 'required'; ?> minlength="8">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Mật khẩu phải có ít nhất 8 ký tự</div>
                        </div>

                        <?php if (!$is_edit_mode && $user_role == 'ADMIN'): ?>
                        <!-- Role (only for admin when adding) -->
                        <div class="mb-3">
                            <label for="role" class="form-label">Quyền</label>
                            <select class="form-select" id="role" name="role" required onchange="toggleHKDSelect(this.value)">
                                <option value="USER_HKD">Người dùng HKD</option>
                                <option value="HKD">HKD</option>
                                <option value="GS">Giám sát</option>
                                <option value="ADMIN">ADMIN</option>
                            </select>
                        </div>

                        <!-- HKD Assignment for new USER_HKD (only for ADMIN) -->
                        <div class="mb-3" id="hkd_select_container">
                            <label for="parent_id" class="form-label">Gán cho HKD</label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">-- Không gán --</option>
                                <?php
                                try {
                                    $hkd_query = $conn->prepare("SELECT id, username FROM users WHERE role = 'HKD' ORDER BY username");
                                    $hkd_query->execute();
                                    $hkd_users = $hkd_query->fetchAll();

                                    foreach ($hkd_users as $hkd): ?>
                                        <option value="<?php echo $hkd['id']; ?>">
                                            <?php echo htmlspecialchars($hkd['username']); ?>
                                        </option>
                                    <?php endforeach;
                                } catch (PDOException $e) {
                                    // Silently fail, don't show any HKD options
                                }
                                ?>
                            </select>
                            <div class="form-text">Chọn HKD mà tài khoản USER_HKD này trực thuộc</div>
                        </div>
                        <?php elseif (!$is_edit_mode): ?>
                        <!-- Hidden role for HKD users -->
                        <input type="hidden" name="role" value="USER_HKD">
                        <?php endif; ?>

                        <?php if ($is_edit_mode): ?>
                        <!-- Status (only for edit mode) -->
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="status" name="status" <?php echo $user_to_edit['status'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">Kích hoạt tài khoản</label>
                        </div>

                        <?php if ($user_role == 'ADMIN'): ?>
                        <!-- Role (only for admin when editing) -->
                        <div class="mb-3">
                            <label for="role" class="form-label">Quyền</label>
                            <select class="form-select" id="edit_role" name="role" required onchange="toggleEditHKDSelect(this.value)">
                                <option value="USER_HKD" <?php echo ($user_to_edit['role'] == 'USER_HKD') ? 'selected' : ''; ?>>Người dùng HKD</option>
                                <option value="HKD" <?php echo ($user_to_edit['role'] == 'HKD') ? 'selected' : ''; ?>>HKD</option>
                                <option value="GS" <?php echo ($user_to_edit['role'] == 'GS') ? 'selected' : ''; ?>>Giám sát</option>
                                <option value="ADMIN" <?php echo ($user_to_edit['role'] == 'ADMIN') ? 'selected' : ''; ?>>ADMIN</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ($user_to_edit['role'] == 'USER_HKD' && $user_role == 'ADMIN'): ?>
                        <!-- HKD Assignment (only for USER_HKD edited by ADMIN) -->
                        <div class="mb-3" id="edit_hkd_select_container">
                            <label for="parent_id" class="form-label">Gán cho HKD</label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">-- Không gán --</option>
                                <?php
                                try {
                                    $hkd_query = $conn->prepare("SELECT id, username FROM users WHERE role = 'HKD' ORDER BY username");
                                    $hkd_query->execute();
                                    $hkd_users = $hkd_query->fetchAll();

                                    foreach ($hkd_users as $hkd): ?>
                                        <option value="<?php echo $hkd['id']; ?>" <?php echo ($user_to_edit['parent_id'] == $hkd['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hkd['username']); ?>
                                        </option>
                                    <?php endforeach;
                                } catch (PDOException $e) {
                                    // Silently fail, don't show any HKD options
                                }
                                ?>
                            </select>
                            <div class="form-text">Chọn HKD mà tài khoản USER_HKD này trực thuộc</div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <div class="d-grid gap2 d-md-flex justify-content-md-end">
                            <?php if ($is_edit_mode): ?>
                            <a href="account_management.php" class="btn btn-outline-secondary me-md-2">Hủy</a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-<?php echo $is_edit_mode ? 'save' : 'plus'; ?> me-2"></i>
                                <?php echo $is_edit_mode ? 'Lưu thay đổi' : 'Thêm tài khoản'; ?>
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>
<script>
// Không cần hàm oldFilterTable nữa vì chúng ta đã chuyển sang tìm kiếm phía server

function toggleHKDSelect(role) {
    const hkdSelectContainer = document.getElementById('hkd_select_container');
    if (hkdSelectContainer) {
        if (role === 'USER_HKD') {
            hkdSelectContainer.style.display = 'block';
        } else {
            hkdSelectContainer.style.display = 'none';
        }
    }
}

function toggleEditHKDSelect(role) {
    const hkdSelectContainer = document.getElementById('edit_hkd_select_container');
    if (hkdSelectContainer) {
        if (role === 'USER_HKD') {
            hkdSelectContainer.style.display = 'block';
        } else {
            hkdSelectContainer.style.display = 'none';
        }
    }
}

// Add event listener to show the HKD select container initially if the selected role is USER_HKD
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    if (roleSelect) {
        const selectedRole = roleSelect.value;
        toggleHKDSelect(selectedRole);
    }

    const editRoleSelect = document.getElementById('edit_role');
    if (editRoleSelect) {
        const selectedEditRole = editRoleSelect.value;
        toggleEditHKDSelect(selectedEditRole);
    }
});

// Add toggle password functionality
const togglePasswordButtons = document.querySelectorAll('.toggle-password');
togglePasswordButtons.forEach(button => {
    button.addEventListener('click', function() {
        const passwordInput = document.getElementById(this.dataset.target);
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            this.querySelector('i').classList.remove('fa-eye');
            this.querySelector('i').classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            this.querySelector('i').classList.remove('fa-eye-slash');
            this.querySelector('i').classList.add('fa-eye');
        }
    });
});

</script>