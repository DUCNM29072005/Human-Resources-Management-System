<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';
require_login(); // Bắt buộc đăng nhập mới vào được
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Quản lý Nhân sự' ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background-color: #f4f6f9;
        }
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: #1e293b;
            color: #fff;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            transition: all 0.3s;
        }
        .sidebar .nav-link {
            color: #cbd5e1;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 4px 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #334155;
            color: #fff;
        }
        .sidebar .nav-link i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }
        .topbar {
            background: #fff;
            border-radius: 12px;
            padding: 12px 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -260px;
            }
            .sidebar.show {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <div>
            <button class="btn btn-sm btn-outline-secondary d-md-none me-2" id="btnToggleSidebar">
                <i class="bi bi-list"></i>
            </button>
            <span class="fw-semibold"><?= $page_title ?? 'Dashboard' ?></span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <div class="fw-semibold"><?= htmlspecialchars($_SESSION['ho_ten']) ?></div>
                <small class="text-muted"><?= $_SESSION['vai_tro'] === 'admin' ? 'Quản trị viên' : 'Nhân viên' ?></small>
            </div>
            <div class="dropdown">
                <button class="btn btn-light btn-sm rounded-circle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-5"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/quanly_nhansu/<?= $_SESSION['vai_tro'] === 'admin' ? 'admin' : 'employee' ?>/profile.php">
                        <i class="bi bi-person me-2"></i> Hồ sơ cá nhân
                    </a></li>
                    <li><a class="dropdown-item" href="/quanly_nhansu/auth/change_password.php">
                        <i class="bi bi-key me-2"></i> Đổi mật khẩu
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/quanly_nhansu/auth/logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <?php if (!empty($_SESSION['error_permission'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($_SESSION['error_permission']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_permission']); ?>
<?php endif; ?>