
<div class="position-sticky pt-3">
    <style>
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .submenu.show {
            max-height: 300px; /* Reduced max height */
            overflow-y: auto; /* Add scrolling */
            transition: max-height 0.5s ease-in;
        }
        .parent-menu {
            cursor: pointer;
        }
        .parent-menu .fa-chevron-down, .parent-menu .fa-chevron-up {
            float: right;
            margin-top: 5px;
        }
    </style>
    <div class="text-center mb-4">
        <?php
        $avatar_url = isset($_SESSION['user_id']) ? get_user_avatar($conn, $_SESSION['user_id']) : 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQfwTLb_X7P0Cnz9f0H1doBKputDa6Oi6rfi84eNtXgueTQl09pvelj8mXAufbXtVCbomE&usqp=CAU';
        ?>
        <div class="mx-auto mb-3" style="width: 80px; height: 80px;">
            <img src="<?php echo htmlspecialchars($avatar_url); ?>" class="img-fluid rounded-circle" style="width: 100%; height: 100%; object-fit: cover;" alt="Avatar">
        </div>
        <h4><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Người dùng'; ?></h4>
        <p class="text-muted small">
            <?php 
            if (isset($_SESSION['user_role'])) {
                echo get_role_name($_SESSION['user_role']);
            }
            ?>
        </p>
    </div>
    <hr>
    <ul class="nav flex-column">
        <!-- Tổng quan -->
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i>
                Tổng quan
            </a>
        </li>
        
        <!-- Thông tin cá nhân -->
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
                <i class="fas fa-user me-2"></i>
                Thông tin cá nhân
            </a>
        </li>
        
        <!-- Đặt hàng và trả đơn -->
       
        
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'order.php') ? 'active' : ''; ?>" href="order.php">
                <i class="fas fa-shopping-cart me-2"></i>
                Order tài nguyên
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'fb_order.php') ? 'active' : ''; ?>" href="fb_order.php">
                <i class="fab fa-facebook me-2"></i>
                Order tài khoản Facebook
            </a>
        </li>
        
        <?php if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['HKD', 'ADMIN', 'GS'])): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'fb_return.php') ? 'active' : ''; ?>" href="fb_return.php">
                <i class="fas fa-undo me-2"></i>
                Xử lý order Facebook
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'return_order.php') ? 'active' : ''; ?>" href="return_order.php">
                <i class="fas fa-undo-alt me-2"></i>
                Xử lý order tài nguyên
            </a>
        </li>
        <?php endif; ?>
        
        <!-- Thống kê -->
    
        
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'monthly_stats.php') ? 'active' : ''; ?>" href="monthly_stats.php">
                <i class="fas fa-chart-bar me-2"></i>
                Thống kê tháng
            </a>
        </li>
        
        <?php if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['HKD', 'ADMIN', 'GS'])): ?>
        <!-- Quản lý -->
       
        
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'account_management.php') ? 'active' : ''; ?>" href="account_management.php">
                <i class="fas fa-users-cog me-2"></i>
                Quản lý tài khoản
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'ADMIN'): ?>
        <!-- Cấu hình hệ thống -->
      
        
        <li class="nav-item">
            <a class="nav-link parent-menu" data-target="config-submenu">
                <i class="fas fa-cogs me-2"></i>
                Cấu hình
                <i class="fas fa-chevron-down"></i>
            </a>
        </li>
        <div class="submenu" id="config-submenu">
            <li class="nav-item ms-3">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'config_resource.php') ? 'active' : ''; ?>" href="config_resource.php">
                    <i class="fas fa-box me-2"></i>
                    Loại tài nguyên
                </a>
            </li>
            <li class="nav-item ms-3">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'fb_config.php') ? 'active' : ''; ?>" href="fb_config.php">
                    <i class="fab fa-facebook me-2"></i>
                    Cấu hình Facebook
                </a>
            </li>
            <li class="nav-item ms-3">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'resource_management.php') ? 'active' : ''; ?>" href="resource_management.php">
                    <i class="fas fa-boxes me-2"></i>
                    Kho tài nguyên
                </a>
            </li>
           
         <!--   <li class="nav-item ms-3">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'logs.php') ? 'active' : ''; ?>" href="logs.php">
                    <i class="fas fa-list-alt me-2"></i>
                    Nhật ký hệ thống
                </a>
            </li>
            <li class="nav-item ms-3">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'backup.php') ? 'active' : ''; ?>" href="backup.php">
                    <i class="fas fa-cloud-download-alt me-2"></i>
                    Sao lưu hệ thống
                </a>
            </li> -->
        </div>
        <?php endif; ?>
        
        <!-- Đăng xuất -->
        <li class="nav-item mt-4">
            <a class="nav-link text-danger" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>
                Đăng xuất
            </a>
        </li>
    </ul>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if current page is in a submenu
    const currentPage = '<?php echo basename($_SERVER['PHP_SELF']); ?>';
    const configPages = ['config_resource.php', 'fb_config.php', 'fb_account_types.php', 'fb_sources.php', 'logs.php', 'backup.php'];
    
    // Auto expand submenu if current page is in it
    if(configPages.includes(currentPage)) {
        document.getElementById('config-submenu').classList.add('show');
        const chevron = document.querySelector('.parent-menu[data-target="config-submenu"] .fas');
        chevron.classList.remove('fa-chevron-down');
        chevron.classList.add('fa-chevron-up');
    }
    
    // Add click event to all parent menu items
    const parentMenus = document.querySelectorAll('.parent-menu');
    parentMenus.forEach(menu => {
        menu.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const submenu = document.getElementById(targetId);
            const chevron = this.querySelector('.fas');
            
            if(submenu.classList.contains('show')) {
                submenu.classList.remove('show');
                chevron.classList.remove('fa-chevron-up');
                chevron.classList.add('fa-chevron-down');
            } else {
                submenu.classList.add('show');
                chevron.classList.remove('fa-chevron-down');
                chevron.classList.add('fa-chevron-up');
            }
        });
    });
});
</script>
