<?php
// redirect_control_simple.php
session_start();

// Bật hiển thị lỗi để debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

$configFile = 'config/redirect_config.json';

// Đảm bảo thư mục config tồn tại
if (!file_exists('config')) {
    mkdir('config', 0755, true);
}

// Tạo file cấu hình nếu chưa có
if (!file_exists($configFile)) {
    file_put_contents($configFile, json_encode([
        'enabled' => false,
        'redirect_url' => 'https://shop.mc-dns.net/index.html'
    ]));
}

// Đọc cấu hình
$config = json_decode(file_get_contents($configFile), true);

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config['enabled'] = isset($_POST['enabled']);
    $config['redirect_url'] = $_POST['redirect_url'];
    file_put_contents($configFile, json_encode($config));
    $message = [
        'text' => "Cập nhật thành công!",
        'type' => 'success'
    ];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Panel - Chuyển hướng tự động</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
            --gray-color: #6c757d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: #212529;
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .header h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: var(--gray-color);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.15);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .form-container {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            outline: none;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--primary-color);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .btn:hover {
            background-color: #3a56e8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .status-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary-color);
        }
        
        .status-card h3 {
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }
        
        .status {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .status-on {
            background-color: rgba(40, 167, 69, 0.15);
            color: var(--success-color);
        }
        
        .status-off {
            background-color: rgba(220, 53, 69, 0.15);
            color: var(--danger-color);
        }
        
        .url-display {
            background: #f1f3f5;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-family: monospace;
            word-break: break-all;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-directions"></i> Điều khiển Chuyển hướng</h1>
            <p>Quản lý chuyển hướng tự động hệ thống</p>
        </div>
        
        <?php if (isset($message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= $message['text'] ?>
        </div>
        <?php endif; ?>
        
        <div class="status-card">
            <h3>Trạng thái hiện tại</h3>
            <p>
                Chuyển hướng: 
                <span class="status <?= $config['enabled'] ? 'status-on' : 'status-off' ?>">
                    <i class="fas fa-<?= $config['enabled'] ? 'check' : 'times' ?>"></i>
                    <?= $config['enabled'] ? 'ĐANG BẬT' : 'ĐANG TẮT' ?>
                </span>
            </p>
            <p class="mt-2">URL chuyển hướng:</p>
            <div class="url-display"><?= htmlspecialchars($config['redirect_url']) ?></div>
        </div>
        
        <div class="form-container">
            <form method="post">
                <div class="form-group">
                    <label for="enabled">Trạng thái chuyển hướng</label>
                    <label class="switch">
                        <input type="checkbox" id="enabled" name="enabled" <?= $config['enabled'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                    <small class="text-muted">Khi bật, hệ thống sẽ tự động chuyển hướng khi truy cập trang login</small>
                </div>
                
                <div class="form-group">
                    <label for="redirect_url">URL chuyển hướng</label>
                    <input type="url" class="form-control" id="redirect_url" name="redirect_url" 
                           value="<?= htmlspecialchars($config['redirect_url']) ?>" required
                           placeholder="https://example.com">
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Lưu cài đặt
                </button>
            </form>
        </div>
        
        <div class="mt-4 text-center text-muted small">
            <p><i class="fas fa-info-circle"></i> Hệ thống quản lý chuyển hướng tự động</p>
        </div>
    </div>
</body>
</html>