<?php
// Include header (handles session, database connection, etc.)
include 'includes/header.php';

// Check if user is authorized to access this page
$allowed_roles = ['USER_HKD', 'HKD', 'ADMIN', 'GS'];
if (!check_role($allowed_roles)) {
    redirect('login.php');
}

// Get current user ID
$user_id = $_SESSION['user_id'];

// Initialize variables
$success_message = '';
$error_message = '';
$user = [];

// Get user details
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();
    
    // Log profile view
    log_profile_action($conn, $user_id, 'view_profile');
    
} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if it's avatar form
    if (isset($_POST['update_avatar'])) {
        $avatar_url = clean_input($_POST['avatar_url']);
        
        // Validate URL
        if (filter_var($avatar_url, FILTER_VALIDATE_URL) || empty($avatar_url)) {
            try {
                // Use the default avatar if URL is empty
                $avatar_to_save = !empty($avatar_url) ? $avatar_url : 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQfwTLb_X7P0Cnz9f0H1doBKputDa6Oi6rfi84eNtXgueTQl09pvelj8mXAufbXtVCbomE&usqp=CAU';
                
                $data = ['avatar' => $avatar_to_save];
                $changes = ['avatar' => 'changed'];
                
                // Update user
                if (update_user($conn, $user_id, $data)) {
                    // Log profile edit
                    log_profile_action($conn, $user_id, 'update_avatar', $changes);
                    
                    $success_message = "Ảnh đại diện đã được cập nhật thành công";
                    
                    // Update session avatar if exists
                    if(isset($_SESSION['user_avatar'])) {
                        $_SESSION['user_avatar'] = $avatar_to_save;
                    }
                    
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $user = $stmt->fetch();
                } else {
                    $error_message = "Không thể cập nhật ảnh đại diện";
                }
            } catch (PDOException $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        } else {
            $error_message = "URL ảnh đại diện không hợp lệ";
        }
    } else {
        // Get and sanitize input
        $email = clean_input($_POST['email']);
        // Giữ nguyên mã nhân viên hiện tại, không cho phép thay đổi
        $employee_code = $user['employee_code'];
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Email không hợp lệ";
        } else {
            try {
                // Start with email and employee code update
                $data = [
                    'email' => $email
                ];
                $changes = [
                    'email' => $email
                ];
                
                // Check if password change is requested
                if (!empty($current_password) && !empty($new_password)) {
                    // Verify current password
                    if (password_verify($current_password, $user['password'])) {
                        // Check if new password matches confirmation
                        if ($new_password === $confirm_password) {
                            // Password is at least 8 characters
                            if (strlen($new_password) >= 8) {
                                $data['password'] = $new_password;
                                $changes['password'] = 'changed';
                            } else {
                                $error_message = "Mật khẩu mới phải có ít nhất 8 ký tự";
                            }
                        } else {
                            $error_message = "Mật khẩu mới và xác nhận mật khẩu không khớp";
                        }
                    } else {
                        $error_message = "Mật khẩu hiện tại không chính xác";
                    }
                }
                
                // If no errors, update user
                if (empty($error_message)) {
                    // Update user
                    if (update_user($conn, $user_id, $data)) {
                        // Log profile edit
                        log_profile_action($conn, $user_id, 'edit_profile', $changes);
                        
                        $success_message = "Thông tin đã được cập nhật thành công";
                        
                        // Refresh user data
                        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $user = $stmt->fetch();
                    } else {
                        $error_message = "Không thể cập nhật thông tin";
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-4 mb-4">
            <!-- User info card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thông tin tài khoản</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle">
                            <img src="<?php echo htmlspecialchars($user['avatar'] ?? 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQfwTLb_X7P0Cnz9f0H1doBKputDa6Oi6rfi84eNtXgueTQl09pvelj8mXAufbXtVCbomE&usqp=CAU'); ?>" alt="Avatar" class="img-fluid rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
                        </div>
                        <h5 class="mt-3"><?php echo htmlspecialchars($user['username']); ?></h5>
                        <span class="badge bg-<?php echo $user['status'] ? 'success' : 'danger'; ?>">
                            <?php echo $user['status'] ? 'Hoạt động' : 'Bị khóa'; ?>
                        </span>
                        
                        <!-- Avatar update form button -->
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="collapse" data-bs-target="#avatarUpdateForm">
                            <i class="fas fa-camera me-1"></i> Cập nhật ảnh đại diện
                        </button>
                        
                        <!-- Collapsible avatar form -->
                        <div class="collapse mt-3" id="avatarUpdateForm">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <div class="mb-3">
                                    <label for="avatar_url" class="form-label">URL ảnh đại diện</label>
                                    <input type="url" class="form-control" id="avatar_url" name="avatar_url" placeholder="https://example.com/avatar.jpg">
                                    <div class="form-text">Để trống để sử dụng ảnh mặc định</div>
                                </div>
                                <input type="hidden" name="update_avatar" value="1">
                                <button type="submit" class="btn btn-sm btn-primary">Cập nhật</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="profile-info">
                        <div class="row mb-2">
                            <div class="col-5 profile-label">Quyền:</div>
                            <div class="col-7"><?php echo get_role_name($user['role']); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 profile-label">Email:</div>
                            <div class="col-7"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 profile-label">Mã nhân viên:</div>
                            <div class="col-7"><?php echo htmlspecialchars($user['employee_code'] ?? 'Chưa cập nhật'); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 profile-label">Ngày tạo:</div>
                            <div class="col-7"><?php echo format_date($user['created_at']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Edit profile card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Chỉnh sửa thông tin</h5>
                </div>
                <div class="card-body">
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
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="needs-validation" novalidate>
                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <div class="invalid-feedback">
                                Vui lòng nhập email hợp lệ
                            </div>
                        </div>
                        
                        <!-- Employee Code - Read Only -->
                        <div class="mb-3">
                            <label for="employee_code" class="form-label">Mã nhân viên</label>
                            <input type="text" class="form-control" id="employee_code" value="<?php echo htmlspecialchars($user['employee_code'] ?? 'Chưa cập nhật'); ?>" readonly disabled>
                            <input type="hidden" name="employee_code" value="<?php echo htmlspecialchars($user['employee_code'] ?? ''); ?>">
                            <div class="form-text">Mã nhân viên của bạn (không thể thay đổi)</div>
                        </div>
                        
                        <h5 class="mt-4 mb-3">Đổi mật khẩu</h5>
                        <p class="text-muted small">Để trống nếu không muốn thay đổi mật khẩu</p>
                        
                        <!-- Current Password -->
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mật khẩu hiện tại</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#current_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- New Password -->
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Mật khẩu mới</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="8">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#new_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Mật khẩu phải có ít nhất 8 ký tự</div>
                            
                            <!-- Password strength meter -->
                            <div class="mt-2 mb-3">
                                <div class="progress">
                                    <div id="password-strength" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>
