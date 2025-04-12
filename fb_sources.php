
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
        $error_message = "Vui lòng nhập tên nguồn";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO fb_sources (name) VALUES (:name)");
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $success_message = "Đã thêm nguồn mới thành công";
            } else {
                $error_message = "Không thể thêm nguồn";
            }
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get all sources
try {
    $stmt = $conn->prepare("SELECT * FROM fb_sources ORDER BY name");
    $stmt->execute();
    $sources = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error loading sources: " . $e->getMessage();
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
                    <h5 class="card-title mb-0">Thêm nguồn mới</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">Tên nguồn</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i> Thêm nguồn
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Danh sách nguồn</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên nguồn</th>
                                    <th>Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sources as $source): ?>
                                <tr>
                                    <td><?php echo $source['id']; ?></td>
                                    <td><?php echo htmlspecialchars($source['name']); ?></td>
                                    <td><?php echo format_date($source['created_at']); ?></td>
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
