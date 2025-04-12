
// Xử lý sidebar dropdown
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý dropdown trong sidebar
    const dropdownToggle = document.querySelectorAll('.sidebar .dropdown-toggle');
    
    dropdownToggle.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdownMenu = this.nextElementSibling;
            
            // Đóng tất cả dropdown khác với hiệu ứng mượt mà
            document.querySelectorAll('.sidebar .dropdown-menu').forEach(function(menu) {
                if (menu !== dropdownMenu && menu.classList.contains('show')) {
                    menu.style.maxHeight = '0px';
                    menu.style.opacity = '0';
                    
                    // Delay để hiệu ứng mượt mà
                    setTimeout(function() {
                        menu.classList.remove('show');
                        menu.previousElementSibling.setAttribute('aria-expanded', 'false');
                    }, 300);
                }
            });
            
            // Toggle dropdown hiện tại với hiệu ứng mượt
            if (dropdownMenu.classList.contains('show')) {
                dropdownMenu.style.maxHeight = '0px';
                dropdownMenu.style.opacity = '0';
                
                // Delay để hiệu ứng mượt mà
                setTimeout(function() {
                    dropdownMenu.classList.remove('show');
                    toggle.setAttribute('aria-expanded', 'false');
                }, 300);
            } else {
                dropdownMenu.classList.add('show');
                this.setAttribute('aria-expanded', 'true');
                
                // Đặt max-height động dựa trên nội dung thực tế
                let totalHeight = 0;
                Array.from(dropdownMenu.children).forEach(item => {
                    totalHeight += item.offsetHeight;
                });
                
                dropdownMenu.style.maxHeight = totalHeight + 50 + 'px';
                dropdownMenu.style.opacity = '1';
            }
            
            // Thêm hiệu ứng active cho menu cha khi menu con được mở
            document.querySelectorAll('.sidebar .nav-link.dropdown-toggle').forEach(function(link) {
                if (link !== toggle) {
                    link.classList.remove('active');
                }
            });
            
            if (!dropdownMenu.classList.contains('show')) {
                this.classList.remove('active');
            } else {
                this.classList.add('active');
            }
        });
    });
    
    // Đánh dấu menu cha là active khi menu con được chọn
    document.querySelectorAll('.sidebar .dropdown-item.active').forEach(function(item) {
        const parentDropdown = item.closest('.dropdown');
        if (parentDropdown) {
            const parentToggle = parentDropdown.querySelector('.dropdown-toggle');
            const dropdownMenu = parentToggle.nextElementSibling;
            
            parentToggle.classList.add('active');
            parentToggle.setAttribute('aria-expanded', 'true');
            dropdownMenu.classList.add('show');
            
            // Đặt max-height động dựa trên nội dung thực tế
            let totalHeight = 0;
            Array.from(dropdownMenu.children).forEach(item => {
                totalHeight += item.offsetHeight;
            });
            
            dropdownMenu.style.maxHeight = totalHeight + 50 + 'px';
            dropdownMenu.style.opacity = '1';
        }
    });
    
    // Đóng dropdown khi click bên ngoài sidebar
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.sidebar')) {
            document.querySelectorAll('.sidebar .dropdown-menu.show').forEach(function(menu) {
                menu.style.maxHeight = '0px';
                menu.style.opacity = '0';
                
                // Delay để hiệu ứng mượt mà
                setTimeout(function() {
                    menu.classList.remove('show');
                    menu.previousElementSibling.setAttribute('aria-expanded', 'false');
                    menu.previousElementSibling.classList.remove('active');
                }, 300);
            });
        }
    });
});

