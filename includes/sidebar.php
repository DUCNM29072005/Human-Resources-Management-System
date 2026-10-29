<?php
$current_page = basename($_SERVER['PHP_SELF']);
$vai_tro = $_SESSION['vai_tro'] ?? '';
?>

<div class="sidebar" id="sidebar">
    <div class="p-4 border-bottom border-secondary">
        <h5 class="mb-0 fw-bold text-white">
            <i class="bi bi-building me-2"></i> QL Nhân sự
        </h5>
        <small class="text-secondary">Hệ thống quản lý</small>
    </div>

    <nav class="nav flex-column mt-3">

        <?php if ($vai_tro === 'admin'): ?>
            <!-- ===== MENU ADMIN ===== -->
            <a href="/quanly_nhansu/admin/dashboard.php" 
               class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <div class="px-3 mt-3 mb-1">
                <small class="text-uppercase text-secondary fw-semibold">Quản lý</small>
            </div>

            <a href="/quanly_nhansu/admin/nhan_vien.php" 
               class="nav-link <?= $current_page == 'nhan_vien.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Nhân viên
            </a>
            <a href="/quanly_nhansu/admin/phong_ban.php" 
               class="nav-link <?= $current_page == 'phong_ban.php' ? 'active' : '' ?>">
                <i class="bi bi-building"></i> Phòng ban
            </a>
            <a href="/quanly_nhansu/admin/chuc_vu.php" 
               class="nav-link <?= $current_page == 'chuc_vu.php' ? 'active' : '' ?>">
                <i class="bi bi-briefcase"></i> Chức vụ
            </a>

            <div class="px-3 mt-3 mb-1">
                <small class="text-uppercase text-secondary fw-semibold">Chấm công & Nghỉ phép</small>
            </div>

            <a href="/quanly_nhansu/admin/cham_cong.php" 
               class="nav-link <?= $current_page == 'cham_cong.php' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i> Chấm công
            </a>
            <a href="/quanly_nhansu/admin/don_xin_nghi.php" 
               class="nav-link <?= $current_page == 'don_xin_nghi.php' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i> Đơn xin nghỉ
            </a>

            <div class="px-3 mt-3 mb-1">
                <small class="text-uppercase text-secondary fw-semibold">Báo cáo</small>
            </div>

            <a href="/quanly_nhansu/admin/luong.php" 
               class="nav-link <?= $current_page == 'luong.php' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i> Tính lương
            </a>

        <?php else: ?>
            <!-- ===== MENU NHÂN VIÊN ===== -->
            <a href="/quanly_nhansu/employee/dashboard.php" 
               class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <a href="/quanly_nhansu/employee/cham_cong.php" 
               class="nav-link <?= $current_page == 'cham_cong.php' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> Chấm công
            </a>

            <a href="/quanly_nhansu/employee/don_xin_nghi.php" 
               class="nav-link <?= $current_page == 'don_xin_nghi.php' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-plus"></i> Đơn xin nghỉ
            </a>

            <a href="/quanly_nhansu/employee/luong.php" 
               class="nav-link <?= $current_page == 'luong.php' ? 'active' : '' ?>">
                <i class="bi bi-wallet2"></i> Phiếu lương
            </a>

            <a href="/quanly_nhansu/employee/profile.php" 
               class="nav-link <?= $current_page == 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person"></i> Hồ sơ cá nhân
            </a>
        <?php endif; ?>

    </nav>
</div>