<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Kiểm tra đã đăng nhập chưa
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Bắt buộc phải đăng nhập, nếu chưa thì chuyển về trang login
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: /quanly_nhansu/auth/login.php');
        exit;
    }
}

/**
 * Bắt buộc phải là Admin
 */
function require_admin() {
    require_login();
    if ($_SESSION['vai_tro'] !== 'admin') {
        $_SESSION['error_permission'] = 'Bạn không có quyền truy cập vào trang Quản trị viên!';
        header('Location: /quanly_nhansu/employee/dashboard.php');
        exit;
    }
}

/**
 * Bắt buộc phải là Nhân viên
 */
function require_employee() {
    require_login();
    if ($_SESSION['vai_tro'] !== 'nhan_vien') {
        $_SESSION['error_permission'] = 'Bạn không có quyền truy cập vào trang Nhân viên!';
        header('Location: /quanly_nhansu/admin/dashboard.php');
        exit;
    }
}

/**
 * Lấy thông tin user hiện tại từ session
 */
function current_user() {
    return [
        'id'         => $_SESSION['user_id'] ?? null,
        'ma_nv'      => $_SESSION['ma_nv'] ?? null,
        'ho_ten'     => $_SESSION['ho_ten'] ?? null,
        'vai_tro'    => $_SESSION['vai_tro'] ?? null,
        'phong_ban'  => $_SESSION['phong_ban'] ?? null,
        'chuc_vu'    => $_SESSION['chuc_vu'] ?? null,
    ];
}