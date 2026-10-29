<?php
require_once '../includes/auth.php';
require_employee();
$page_title = 'Phiếu lương của tôi';
require_once '../includes/header.php';
require_once '../config/database.php';

$nhan_vien_id = $_SESSION['user_id'];

// Lấy tháng/năm
$thang = $_GET['thang'] ?? date('m');
$nam   = $_GET['nam'] ?? date('Y');
$thang_nam = sprintf('%04d-%02d', $nam, $thang);

// Lấy thông tin nhân viên
$stmt = $pdo->prepare("
    SELECT nv.*, pb.ten_pb, cv.ten_cv, cv.phu_cap
    FROM nhan_vien nv
    LEFT JOIN phong_ban pb ON nv.phong_ban_id = pb.id
    LEFT JOIN chuc_vu cv ON nv.chuc_vu_id = cv.id
    WHERE nv.id = ?
");
$stmt->execute([$nhan_vien_id]);
$nv = $stmt->fetch();

// 1. Số ngày đi làm thực tế
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM cham_cong 
    WHERE nhan_vien_id = ? 
      AND DATE_FORMAT(ngay_cham, '%Y-%m') = ?
      AND gio_vao IS NOT NULL
");
$stmt->execute([$nhan_vien_id, $thang_nam]);
$ngay_di_lam = (int)$stmt->fetchColumn();

// 2. Số ngày nghỉ phép được duyệt
$stmt = $pdo->prepare("
    SELECT ngay_bat_dau, ngay_ket_thuc 
    FROM don_xin_nghi 
    WHERE nhan_vien_id = ? 
      AND trang_thai = 'da_duyet'
      AND (
            (ngay_bat_dau BETWEEN ? AND LAST_DAY(?))
         OR (ngay_ket_thuc BETWEEN ? AND LAST_DAY(?))
         OR (ngay_bat_dau <= ? AND ngay_ket_thuc >= LAST_DAY(?))
      )
");
$ngay_dau_thang = $thang_nam . '-01';
$stmt->execute([$nhan_vien_id, $ngay_dau_thang, $ngay_dau_thang, $ngay_dau_thang, $ngay_dau_thang, $ngay_dau_thang, $ngay_dau_thang]);
$don_nghi = $stmt->fetchAll();

$ngay_nghi_phep = 0;
$ngay_cuoi_thang = date('Y-m-t', strtotime($ngay_dau_thang));

foreach ($don_nghi as $don) {
    $start = max($don['ngay_bat_dau'], $ngay_dau_thang);
    $end   = min($don['ngay_ket_thuc'], $ngay_cuoi_thang);
    if ($end >= $start) {
        $ngay_nghi_phep += (strtotime($end) - strtotime($start)) / 86400 + 1;
    }
}
$ngay_nghi_phep = (int) round($ngay_nghi_phep);

// 3. Số lần đi muộn
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM cham_cong 
    WHERE nhan_vien_id = ? 
      AND DATE_FORMAT(ngay_cham, '%Y-%m') = ?
      AND trang_thai = 'di_muon'
");
$stmt->execute([$nhan_vien_id, $thang_nam]);
$so_lan_di_muon = (int)$stmt->fetchColumn();

// 4. Tính lương
$tong_ngay_cong = $ngay_di_lam + $ngay_nghi_phep;
$tien_phat      = $so_lan_di_muon * 50000;
$luong_co_ban   = (float)$nv['luong_co_ban'];
$phu_cap        = (float)$nv['phu_cap'];
$luong_thuc_linh = ($luong_co_ban / 26 * $tong_ngay_cong) + $phu_cap - $tien_phat;
?>

<!-- Bộ lọc tháng/năm -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Tháng</label>
                <select name="thang" class="form-select">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $thang == $i ? 'selected' : '' ?>>Tháng <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Năm</label>
                <select name="nam" class="form-select">
                    <?php for ($i = date('Y'); $i >= date('Y') - 3; $i--): ?>
                        <option value="<?= $i ?>" <?= $nam == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Xem phiếu lương</button>
            </div>
        </form>
    </div>
</div>

<!-- Phiếu lương -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0">PHIẾU LƯƠNG</h4>
                <div>Tháng <?= $thang ?> / <?= $nam ?></div>
            </div>
            <div class="card-body p-4">
                <!-- Thông tin nhân viên -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Họ tên:</strong> <?= htmlspecialchars($nv['ho_ten']) ?></p>
                        <p class="mb-1"><strong>Mã NV:</strong> <?= htmlspecialchars($nv['ma_nv']) ?></p>
                        <p class="mb-1"><strong>Phòng ban:</strong> <?= htmlspecialchars($nv['ten_pb'] ?? '—') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Chức vụ:</strong> <?= htmlspecialchars($nv['ten_cv'] ?? '—') ?></p>
                        <p class="mb-1"><strong>Lương cơ bản:</strong> <?= number_format($luong_co_ban, 0, ',', '.') ?> ₫</p>
                        <p class="mb-1"><strong>Phụ cấp:</strong> <?= number_format($phu_cap, 0, ',', '.') ?> ₫</p>
                    </div>
                </div>

                <hr>

                <!-- Chi tiết tính lương -->
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td>Số ngày đi làm thực tế</td>
                            <td class="text-end fw-semibold"><?= $ngay_di_lam ?> ngày</td>
                        </tr>
                        <tr>
                            <td>Số ngày nghỉ phép được duyệt</td>
                            <td class="text-end fw-semibold"><?= $ngay_nghi_phep ?> ngày</td>
                        </tr>
                        <tr class="table-light">
                            <td><strong>Tổng ngày công</strong></td>
                            <td class="text-end fw-bold"><?= $tong_ngay_cong ?> ngày</td>
                        </tr>
                        <tr>
                            <td>Số lần đi muộn</td>
                            <td class="text-end"><?= $so_lan_di_muon ?> lần</td>
                        </tr>
                        <tr>
                            <td>Tiền phạt đi muộn (<?= $so_lan_di_muon ?> × 50.000)</td>
                            <td class="text-end text-danger">− <?= number_format($tien_phat, 0, ',', '.') ?> ₫</td>
                        </tr>
                        <tr>
                            <td>Lương theo ngày công (Lương CB / 26 × <?= $tong_ngay_cong ?>)</td>
                            <td class="text-end"><?= number_format($luong_co_ban / 26 * $tong_ngay_cong, 0, ',', '.') ?> ₫</td>
                        </tr>
                        <tr>
                            <td>Phụ cấp chức vụ</td>
                            <td class="text-end">+ <?= number_format($phu_cap, 0, ',', '.') ?> ₫</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Thực lĩnh -->
                <div class="bg-success bg-opacity-10 border border-success rounded p-3 mt-4 text-center">
                    <div class="text-muted mb-1">LƯƠNG THỰC LĨNH</div>
                    <div class="fs-3 fw-bold text-success">
                        <?= number_format($luong_thuc_linh, 0, ',', '.') ?> ₫
                    </div>
                </div>

                <div class="text-muted small mt-3 text-center">
                    Công thức: (Lương cơ bản / 26 × Tổng ngày công) + Phụ cấp − Tiền phạt
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>