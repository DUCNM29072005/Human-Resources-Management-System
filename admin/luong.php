<?php
require_once '../includes/auth.php';
require_admin();
$page_title = 'Tính lương';
require_once '../includes/header.php';
require_once '../config/database.php';

// Lấy tháng/năm lọc
$thang = $_GET['thang'] ?? date('m');
$nam = $_GET['nam'] ?? date('Y');
$thang_nam = sprintf('%04d-%02d', $nam, $thang);

// Lấy danh sách nhân viên đang làm
// ===== PHÂN TRANG =====
$limit = 5;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Lấy danh sách nhân viên đang làm (có phân trang)
$stmt = $pdo->query("
    SELECT nv.id, nv.ma_nv, nv.ho_ten, nv.luong_co_ban, 
           pb.ten_pb, cv.ten_cv, cv.phu_cap
    FROM nhan_vien nv
    LEFT JOIN phong_ban pb ON nv.phong_ban_id = pb.id
    LEFT JOIN chuc_vu cv ON nv.chuc_vu_id = cv.id
    WHERE nv.trang_thai = 1
    ORDER BY nv.ho_ten
    LIMIT $limit OFFSET $offset
");
$ds_nhan_vien = $stmt->fetchAll();

// Đếm tổng số nhân viên
$total_rows = (int) $pdo->query("SELECT COUNT(*) FROM nhan_vien WHERE trang_thai = 1")->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Tính lương cho từng người
$ds_luong = [];
foreach ($ds_nhan_vien as $nv) {
    // 1. Số ngày đi làm thực tế
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM cham_cong 
        WHERE nhan_vien_id = ? 
          AND DATE_FORMAT(ngay_cham, '%Y-%m') = ?
          AND gio_vao IS NOT NULL
    ");
    $stmt->execute([$nv['id'], $thang_nam]);
    $ngay_di_lam = (int) $stmt->fetchColumn();

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
    $stmt->execute([$nv['id'], $ngay_dau_thang, $ngay_dau_thang, $ngay_dau_thang, $ngay_dau_thang, $ngay_dau_thang, $ngay_dau_thang]);
    $don_nghi = $stmt->fetchAll();

    $ngay_nghi_phep = 0;
    $ngay_cuoi_thang = date('Y-m-t', strtotime($ngay_dau_thang));
    foreach ($don_nghi as $don) {
        $start = max($don['ngay_bat_dau'], $ngay_dau_thang);
        $end = min($don['ngay_ket_thuc'], $ngay_cuoi_thang);
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
    $stmt->execute([$nv['id'], $thang_nam]);
    $so_lan_di_muon = (int) $stmt->fetchColumn();

    // 4. Tính toán
    $tong_ngay_cong = $ngay_di_lam + $ngay_nghi_phep;
    $tien_phat = $so_lan_di_muon * 50000;
    $luong_co_ban = (float) $nv['luong_co_ban'];
    $phu_cap = (float) $nv['phu_cap'];
    $luong_thuc_linh = ($luong_co_ban / 26 * $tong_ngay_cong) + $phu_cap - $tien_phat;

    $ds_luong[] = [
        'ma_nv' => $nv['ma_nv'],
        'ho_ten' => $nv['ho_ten'],
        'ten_pb' => $nv['ten_pb'],
        'ten_cv' => $nv['ten_cv'],
        'luong_co_ban' => $luong_co_ban,
        'phu_cap' => $phu_cap,
        'ngay_di_lam' => $ngay_di_lam,
        'ngay_nghi_phep' => $ngay_nghi_phep,
        'tong_ngay_cong' => $tong_ngay_cong,
        'so_lan_di_muon' => $so_lan_di_muon,
        'tien_phat' => $tien_phat,
        'luong_thuc_linh' => $luong_thuc_linh,
    ];
}
?>

<!-- Bộ lọc tháng/năm -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Tháng</label>
                <select name="thang" class="form-select">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $thang == $i ? 'selected' : '' ?>>
                            Tháng <?= $i ?>
                        </option>
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
                <button type="submit" class="btn btn-primary w-100">Xem lương</button>
            </div>
        </form>
    </div>
</div>

<!-- Bảng lương -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Bảng lương tháng <?= $thang ?>/<?= $nam ?></h5>
        <span class="badge bg-primary"><?= $total_rows ?> nhân viên</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nhân viên</th>
                        <th>Phòng ban / Chức vụ</th>
                        <th class="text-end">Lương CB</th>
                        <th class="text-end">Phụ cấp</th>
                        <th class="text-center">Ngày công</th>
                        <th class="text-center">Đi muộn</th>
                        <th class="text-end">Tiền phạt</th>
                        <th class="text-end">Thực lĩnh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ds_luong)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Không có dữ liệu</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ds_luong as $l): ?>
                            <tr>
                                <td>
                                    <a href="#" class="text-decoration-none btn-chi-tiet-luong" data-bs-toggle="modal"
                                        data-bs-target="#chiTietLuongModal" data-hoten="<?= htmlspecialchars($l['ho_ten']) ?>"
                                        data-ma="<?= htmlspecialchars($l['ma_nv']) ?>"
                                        data-pb="<?= htmlspecialchars($l['ten_pb'] ?? '—') ?>"
                                        data-cv="<?= htmlspecialchars($l['ten_cv'] ?? '—') ?>"
                                        data-luongcb="<?= $l['luong_co_ban'] ?>" data-phucap="<?= $l['phu_cap'] ?>"
                                        data-dilam="<?= $l['ngay_di_lam'] ?>" data-nghiphep="<?= $l['ngay_nghi_phep'] ?>"
                                        data-tongcong="<?= $l['tong_ngay_cong'] ?>" data-dimuon="<?= $l['so_lan_di_muon'] ?>"
                                        data-phat="<?= $l['tien_phat'] ?>" data-thuclinh="<?= $l['luong_thuc_linh'] ?>">
                                        <div class="fw-semibold text-primary"><?= htmlspecialchars($l['ho_ten']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($l['ma_nv']) ?></small>
                                    </a>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($l['ten_pb'] ?? '—') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($l['ten_cv'] ?? '—') ?></small>
                                </td>
                                <td class="text-end"><?= number_format($l['luong_co_ban'], 0, ',', '.') ?></td>
                                <td class="text-end"><?= number_format($l['phu_cap'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= $l['tong_ngay_cong'] ?></span>
                                    <div class="small text-muted">
                                        <?= $l['ngay_di_lam'] ?> đi làm
                                        <?php if ($l['ngay_nghi_phep'] > 0): ?>
                                            + <?= $l['ngay_nghi_phep'] ?> nghỉ phép
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?= $l['so_lan_di_muon'] > 0
                                        ? '<span class="badge bg-warning text-dark">' . $l['so_lan_di_muon'] . '</span>'
                                        : '0' ?>
                                </td>
                                <td class="text-end text-danger">
                                    <?= $l['tien_phat'] > 0 ? number_format($l['tien_phat'], 0, ',', '.') : '0' ?>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    <?= number_format($l['luong_thuc_linh'], 0, ',', '.') ?> ₫
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1): ?>
                <nav class="p-3">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&thang=<?= $thang ?>&nam=<?= $nam ?>">Trước</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&thang=<?= $thang ?>&nam=<?= $nam ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&thang=<?= $thang ?>&nam=<?= $nam ?>">Sau</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Modal Chi tiết phiếu lương -->
<div class="modal fade" id="chiTietLuongModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Phiếu lương chi tiết</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <h5 class="mb-0">PHIẾU LƯƠNG</h5>
                    <div class="text-muted">Tháng <?= $thang ?> / <?= $nam ?></div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Họ tên:</strong> <span id="ct_hoten"></span></p>
                        <p class="mb-1"><strong>Mã NV:</strong> <span id="ct_ma"></span></p>
                        <p class="mb-1"><strong>Phòng ban:</strong> <span id="ct_pb"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Chức vụ:</strong> <span id="ct_cv"></span></p>
                        <p class="mb-1"><strong>Lương cơ bản:</strong> <span id="ct_luongcb"></span></p>
                        <p class="mb-1"><strong>Phụ cấp:</strong> <span id="ct_phucap"></span></p>
                    </div>
                </div>

                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td>Số ngày đi làm thực tế</td>
                            <td class="text-end fw-semibold" id="ct_dilam"></td>
                        </tr>
                        <tr>
                            <td>Số ngày nghỉ phép được duyệt</td>
                            <td class="text-end fw-semibold" id="ct_nghiphep"></td>
                        </tr>
                        <tr class="table-light">
                            <td><strong>Tổng ngày công</strong></td>
                            <td class="text-end fw-bold" id="ct_tongcong"></td>
                        </tr>
                        <tr>
                            <td>Số lần đi muộn</td>
                            <td class="text-end" id="ct_dimuon"></td>
                        </tr>
                        <tr>
                            <td>Tiền phạt đi muộn</td>
                            <td class="text-end text-danger" id="ct_phat"></td>
                        </tr>
                        <tr>
                            <td>Lương theo ngày công</td>
                            <td class="text-end" id="ct_luongngay"></td>
                        </tr>
                        <tr>
                            <td>Phụ cấp chức vụ</td>
                            <td class="text-end" id="ct_phucap2"></td>
                        </tr>
                    </tbody>
                </table>

                <div class="bg-success bg-opacity-10 border border-success rounded p-3 text-center">
                    <div class="text-muted small">LƯƠNG THỰC LĨNH</div>
                    <div class="fs-3 fw-bold text-success" id="ct_thuclinh"></div>
                </div>

                <div class="text-muted small text-center mt-2">
                    Công thức: (Lương CB / 26 × Tổng ngày công) + Phụ cấp − Tiền phạt
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        function formatTien(n) {
            return Number(n).toLocaleString('vi-VN') + ' ₫';
        }

        document.querySelectorAll('.btn-chi-tiet-luong').forEach(function (el) {
            el.addEventListener('click', function () {
                const luongcb = parseFloat(this.dataset.luongcb) || 0;
                const phucap = parseFloat(this.dataset.phucap) || 0;
                const tongcong = parseFloat(this.dataset.tongcong) || 0;
                const phat = parseFloat(this.dataset.phat) || 0;
                const thuclinh = parseFloat(this.dataset.thuclinh) || 0;
                const luongNgay = luongcb / 26 * tongcong;

                document.getElementById('ct_hoten').innerText = this.dataset.hoten;
                document.getElementById('ct_ma').innerText = this.dataset.ma;
                document.getElementById('ct_pb').innerText = this.dataset.pb;
                document.getElementById('ct_cv').innerText = this.dataset.cv;
                document.getElementById('ct_luongcb').innerText = formatTien(luongcb);
                document.getElementById('ct_phucap').innerText = formatTien(phucap);
                document.getElementById('ct_dilam').innerText = this.dataset.dilam + ' ngày';
                document.getElementById('ct_nghiphep').innerText = this.dataset.nghiphep + ' ngày';
                document.getElementById('ct_tongcong').innerText = this.dataset.tongcong + ' ngày';
                document.getElementById('ct_dimuon').innerText = this.dataset.dimuon + ' lần';
                document.getElementById('ct_phat').innerText = '− ' + formatTien(phat);
                document.getElementById('ct_luongngay').innerText = formatTien(luongNgay);
                document.getElementById('ct_phucap2').innerText = '+ ' + formatTien(phucap);
                document.getElementById('ct_thuclinh').innerText = formatTien(thuclinh);
            });
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>