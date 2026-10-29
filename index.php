<?php
session_start();

// Đã đăng nhập → vào thẳng dashboard theo vai trò
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['vai_tro'] ?? '') === 'admin') {
        header('Location: /quanly_nhansu/admin/dashboard.php');
    } else {
        header('Location: /quanly_nhansu/employee/dashboard.php');
    }
    exit;
}

// Chưa đăng nhập → sang trang đăng nhập
header('Location: /quanly_nhansu/auth/login.php');
exit;