
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = isset($_POST['name']) ? clean_input($_POST['name']) : '';

    if (empty($name)) {
        $error_message = "Vui lòng nhập tên loại tài khoản";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO fb_account_types (name) VALUES (:name)");
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $success_message = "Đã thêm loại tài khoản mới thành công";
            } else {
                $error_message = "Không thể thêm loại tài khoản";
            }
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get all account types
try {
    $stmt = $conn->prepare("SELECT * FROM fb_account_types ORDER BY name");
    $stmt->execute();
    $account_types = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error loading account types: " . $e->getMessage();
}
?>

<div class="container-fluid">
    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Thêm loại tài khoản mới</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">Tên loại tài khoản</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i> Thêm loại tài khoản
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Danh sách loại tài khoản</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên loại tài khoản</th>
                                    <th>Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($account_types as $type): ?>
                                <tr>
                                    <td><?php echo $type['id']; ?></td>
                                    <td><?php echo htmlspecialchars($type['name']); ?></td>
                                    <td><?php echo format_date($type['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
