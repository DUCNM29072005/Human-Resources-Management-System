<?php
$page_title = 'Hồ sơ cá nhân';
require_once '../includes/header.php';
require_login();
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

// Lấy thông tin đầy đủ
$stmt = $pdo->prepare("
    SELECT nv.*, pb.ten_pb, cv.ten_cv, cv.phu_cap
    FROM nhan_vien nv
    LEFT JOIN phong_ban pb ON nv.phong_ban_id = pb.id
    LEFT JOIN chuc_vu cv ON nv.chuc_vu_id = cv.id
    WHERE nv.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Thông tin cá nhân</h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Mã nhân viên</label>
                        <div class="fw-semibold"><?= htmlspecialchars($user['ma_nv']) ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Họ và tên</label>
                        <div class="fw-semibold"><?= htmlspecialchars($user['ho_ten']) ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Giới tính</label>
                        <div class="fw-semibold"><?= $user['gioi_tinh'] == 1 ? 'Nam' : 'Nữ' ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Ngày sinh</label>
                        <div class="fw-semibold">
                            <?= $user['ngay_sinh'] ? date('d/m/Y', strtotime($user['ngay_sinh'])) : '—' ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Email</label>
                        <div class="fw-semibold"><?= htmlspecialchars($user['email'] ?? '—') ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Số điện thoại</label>
                        <div class="fw-semibold"><?= htmlspecialchars($user['so_dien_thoai'] ?? '—') ?></div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="text-muted small">Địa chỉ</label>
                        <div class="fw-semibold"><?= htmlspecialchars($user['dia_chi'] ?? '—') ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Phòng ban</label>
                        <div class="fw-semibold"><?= htmlspecialchars($user['ten_pb'] ?? '—') ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Chức vụ</label>
                        <div class="fw-semibold"><?= htmlspecialchars($user['ten_cv'] ?? '—') ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Lương cơ bản</label>
                        <div class="fw-semibold"><?= number_format($user['luong_co_ban'], 0, ',', '.') ?> ₫</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Phụ cấp chức vụ</label>
                        <div class="fw-semibold"><?= number_format($user['phu_cap'] ?? 0, 0, ',', '.') ?> ₫</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Tên đăng nhập</label>
                        <div class="fw-semibold"><?= htmlspecialchars($user['ten_dang_nhap']) ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Vai trò</label>
                        <div class="fw-semibold">
                            <?= $user['vai_tro'] === 'admin' ? 'Quản trị viên' : 'Nhân viên' ?>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="/quanly_nhansu/auth/change_password.php" class="btn btn-outline-primary">
                        <i class="bi bi-key me-1"></i> Đổi mật khẩu
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>