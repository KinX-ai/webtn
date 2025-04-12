   <?php
//header("Location: https://shop.mc-dns.net/index.html");
//exit();

// Start session
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Include database and helper files
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/log_helper.php';

$error = '';
$username = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize input
    $username = clean_input($_POST['username']);
    $password = $_POST['password']; // Don't clean password as it may affect valid characters
    
    // Check if fields are not empty
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu';
    } else {
        // Attempt login
        $user = login_user($conn, $username, $password);
        
        if ($user) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            
            // Log successful login
            log_login($conn, $username, true, $user['id']);
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            // Log failed login
            log_login($conn, $username, false);
            
            $error = 'Thông tin đăng nhập không chính xác hoặc tài khoản đã bị khóa';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - HKD Management</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #5860af, #4361ee);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 900px;
            height: 600px;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        .login-image {
            flex: 1;
            background: url('https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=987&q=80') center/cover;
            position: relative;
            display: flex;
            align-items: flex-end;
            padding: 40px;
        }
        .login-image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, rgba(67,97,238,0.2) 0%, rgba(67,97,238,0.8) 100%);
            z-index: 1;
        }
        .login-image-text {
            position: relative;
            color: white;
            z-index: 2;
        }
        .login-image-text h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .login-form-container {
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .login-header h2 {
            font-weight: 700;
            color: #3f3d56;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        .login-header p {
            color: #6c757d;
        }
        .login-form .form-floating {
            margin-bottom: 20px;
        }
        .login-form .form-floating .form-control {
            border-radius: 10px;
            height: 58px;
            border: 1px solid #e0e0e0;
            padding: 1.2rem 1rem 0.6rem;
        }
        .login-form .form-floating .form-control:focus {
            box-shadow: 0 0 0 3px rgba(67,97,238,0.15);
            border-color: #4361ee;
        }
        .login-form .form-floating label {
            padding: 1rem 1rem;
        }
        .login-button {
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(45deg, #4361ee, #2040c8);
            font-weight: 600;
            font-size: 1.1rem;
            margin-top: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(46,91,255,0.2);
        }
        .login-button:hover, .login-button:focus {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(46,91,255,0.3);
        }
        .login-error {
            animation: shake 0.6s cubic-bezier(.36,.07,.19,.97) both;
        }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
        .login-form .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .login-form .input-group .form-control {
            height: 55px;
            border-radius: 10px;
            padding-left: 45px;
            font-size: 0.95rem;
            border: 1px solid #e0e0e0;
            background-color: #f9f9f9;
            transition: all 0.3s;
        }
        .login-form .input-group .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 10;
            font-size: 1.1rem;
        }
        .login-form .input-group .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            border: none;
            background: transparent;
            color: #6c757d;
            cursor: pointer;
        }
        .login-form .input-group .form-control:focus {
            box-shadow: 0 0 0 4px rgba(67,97,238,0.15);
            border-color: #4361ee;
            background-color: #fff;
        }
        .login-form .input-group .form-control:focus + .input-icon {
            color: #4361ee;
        }
        
        @media (max-width: 992px) {
            .login-wrapper {
                flex-direction: column;
                height: auto;
            }
            .login-image {
                display: none;
            }
        }
    </style>
</head>
 <body>
    <div class="login-page">
        <div class="login-wrapper">
            <div class="login-image">
                <div class="login-image-overlay"></div>
                <div class="login-image-text">
                    <h2>Chào mừng đến với HKD Management</h2>
                    <p>Hệ thống quản lý doanh nghiệp chuyên nghiệp dành cho bạn. Đăng nhập để tiếp tục.</p>
                </div>
            </div>
            <div class="login-form-container">
                <div class="login-header">
                    <h2>HKD Management</h2>
                    <p>Đăng nhập để tiếp tục</p>
                </div>

                <?php if (!empty($error)): ?>
                <div class="alert alert-danger login-error p-3 mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="login-form needs-validation" novalidate>
                    <div class="input-group">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Tên đăng nhập" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                        <i class="fas fa-user input-icon"></i>
                        <div class="invalid-feedback">
                            Vui lòng nhập tên đăng nhập
                        </div>
                    </div>

                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Mật khẩu" required>
                        <i class="fas fa-lock input-icon"></i>
                        <button class="toggle-password" type="button" data-target="#password">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div class="invalid-feedback">
                            Vui lòng nhập mật khẩu
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 login-button">
                        <i class="fas fa-sign-in-alt me-2"></i> Đăng nhập
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
 
</body>
</html>
