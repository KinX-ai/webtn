
/**
 * Main JavaScript file for HKD Management System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar on mobile
    const toggleSidebarBtn = document.querySelector('.sidebar-toggle');
    if (toggleSidebarBtn) {
        toggleSidebarBtn.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    }
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Password strength meter
    const passwordField = document.getElementById('password');
    const passwordStrength = document.getElementById('password-strength');
    
    if (passwordField && passwordStrength) {
        passwordField.addEventListener('input', function() {
            const password = passwordField.value;
            let strength = 0;
            
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[^a-zA-Z0-9]+/)) strength += 1;
            
            switch (strength) {
                case 0:
                case 1:
                    passwordStrength.className = 'progress-bar bg-danger';
                    passwordStrength.style.width = '20%';
                    passwordStrength.textContent = 'Rất yếu';
                    break;
                case 2:
                    passwordStrength.className = 'progress-bar bg-warning';
                    passwordStrength.style.width = '40%';
                    passwordStrength.textContent = 'Yếu';
                    break;
                case 3:
                    passwordStrength.className = 'progress-bar bg-info';
                    passwordStrength.style.width = '60%';
                    passwordStrength.textContent = 'Trung bình';
                    break;
                case 4:
                    passwordStrength.className = 'progress-bar bg-primary';
                    passwordStrength.style.width = '80%';
                    passwordStrength.textContent = 'Mạnh';
                    break;
                case 5:
                    passwordStrength.className = 'progress-bar bg-success';
                    passwordStrength.style.width = '100%';
                    passwordStrength.textContent = 'Rất mạnh';
                    break;
                default:
                    break;
            }
        });
    }
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.btn-delete');
    Array.from(deleteButtons).forEach(button => {
        button.addEventListener('click', function(event) {
            if (!confirm('Bạn có chắc chắn muốn xóa mục này?')) {
                event.preventDefault();
            }
        });
    });
    
    // Toggle password visibility
    const togglePasswordBtn = document.querySelector('.toggle-password');
    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', function() {
            const passwordInput = document.querySelector(this.getAttribute('data-target'));
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordInput.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize datepickers if available
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.datepicker', {
            dateFormat: 'd/m/Y',
            locale: 'vn'
        });
    }
    
    // Order form validation
    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
        const quantityInput = document.getElementById('quantity');
        
        quantityInput.addEventListener('input', function() {
            const value = parseInt(this.value);
            
            if (isNaN(value) || value <= 0) {
                this.setCustomValidity('Số lượng phải là số nguyên dương');
            } else {
                this.setCustomValidity('');
            }
        });
    }
});

/**
 * Filter table data
 * @param {string} inputId - ID of the input field
 * @param {string} tableId - ID of the table to filter
 */
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const filter = input.value.toUpperCase();
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        let found = false;
        const cells = rows[i].getElementsByTagName('td');
        
        for (let j = 0; j < cells.length; j++) {
            const cell = cells[j];
            if (cell) {
                const text = cell.textContent || cell.innerText;
                if (text.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        rows[i].style.display = found ? '' : 'none';
    }
}

/**
 * Format date from MySQL format to Vietnamese format
 * @param {string} dateString - Date string in MySQL format
 * @return {string} Formatted date string
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    
    return `${day}/${month}/${year} ${hours}:${minutes}`;
}
