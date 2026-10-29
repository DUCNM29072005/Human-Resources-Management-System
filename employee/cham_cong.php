<?php
require_once '../includes/auth.php';
require_employee();
$page_title = 'Chấm công';
require_once '../includes/header.php';
require_once '../config/database.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
$nhan_vien_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$success = $error = '';

// Lấy bản ghi chấm công hôm nay
$stmt = $pdo->prepare("SELECT * FROM cham_cong WHERE nhan_vien_id = ? AND ngay_cham = ?");
$stmt->execute([$nhan_vien_id, $today]);
$cham_hom_nay = $stmt->fetch();

// ========== CHECK-IN ==========
if (isset($_POST['check_in'])) {
    if ($cham_hom_nay) {
        $error = 'Bạn đã check-in hôm nay rồi!';
    } else {
        // Lấy giờ chuẩn từ cấu hình
        $stmt = $pdo->query("SELECT gio_vao_chuan, gio_ra_chuan FROM cai_dat WHERE id = 1");
        $cai_dat = $stmt->fetch();

        $gio_vao = date('H:i:s');
        $trang_thai = ($gio_vao > $cai_dat['gio_vao_chuan']) ? 'di_muon' : 'dung_gio';

        $stmt = $pdo->prepare("
            INSERT INTO cham_cong (nhan_vien_id, ngay_cham, gio_vao, trang_thai)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$nhan_vien_id, $today, $gio_vao, $trang_thai]);
        $success = 'Check-in thành công lúc ' . date('H:i');
        
        // Load lại dữ liệu
        $stmt = $pdo->prepare("SELECT * FROM cham_cong WHERE nhan_vien_id = ? AND ngay_cham = ?");
        $stmt->execute([$nhan_vien_id, $today]);
        $cham_hom_nay = $stmt->fetch();
    }
}

// ========== CHECK-OUT ==========
if (isset($_POST['check_out'])) {
    if (!$cham_hom_nay) {
        $error = 'Bạn chưa check-in hôm nay!';
    } elseif ($cham_hom_nay['gio_ra']) {
        $error = 'Bạn đã check-out rồi!';
    } else {
        // Lấy giờ chuẩn
        $stmt = $pdo->query("SELECT gio_vao_chuan, gio_ra_chuan FROM cai_dat WHERE id = 1");
        $cai_dat = $stmt->fetch();
        if (!$cai_dat) {
            $cai_dat = ['gio_vao_chuan' => '08:00:00', 'gio_ra_chuan' => '17:00:00'];
        }

        $gio_ra = date('H:i:s');
        $trang_thai = $cham_hom_nay['trang_thai'];

        // Nếu về trước giờ chuẩn thì đánh dấu về sớm
        if ($gio_ra < $cai_dat['gio_ra_chuan'] && $trang_thai === 'dung_gio') {
            $trang_thai = 've_som';
        }

        $stmt = $pdo->prepare("
            UPDATE cham_cong 
            SET gio_ra = ?, trang_thai = ? 
            WHERE id = ?
        ");
        $stmt->execute([$gio_ra, $trang_thai, $cham_hom_nay['id']]);
        $success = 'Check-out thành công lúc ' . date('H:i');

        // Load lại
        $stmt = $pdo->prepare("SELECT * FROM cham_cong WHERE nhan_vien_id = ? AND ngay_cham = ?");
        $stmt->execute([$nhan_vien_id, $today]);
        $cham_hom_nay = $stmt->fetch();
    }
}

// Lấy lịch sử chấm công tháng hiện tại
// ===== PHÂN TRANG LỊCH SỬ =====
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$thang_hien_tai = date('Y-m');
$stmt = $pdo->prepare("
    SELECT * FROM cham_cong 
    WHERE nhan_vien_id = ? 
      AND DATE_FORMAT(ngay_cham, '%Y-%m') = ?
    ORDER BY ngay_cham DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute([$nhan_vien_id, $thang_hien_tai]);
$lich_su = $stmt->fetchAll();

// Đếm tổng số bản ghi
$stmt_count = $pdo->prepare("
    SELECT COUNT(*) FROM cham_cong 
    WHERE nhan_vien_id = ? AND DATE_FORMAT(ngay_cham, '%Y-%m') = ?
");
$stmt_count->execute([$nhan_vien_id, $thang_hien_tai]);
$total_rows = (int)$stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>

<!-- Thông báo -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Phần Check-in / Check-out hôm nay -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Chấm công hôm nay (<?= date('d/m/Y') ?>)</h5>
            </div>
            <div class="card-body text-center py-4">
                <?php if (!$cham_hom_nay): ?>
                    <!-- Chưa check-in -->
                    <p class="text-muted mb-4">Bạn chưa check-in hôm nay</p>
                    <form method="POST">
                        <button type="submit" name="check_in" class="btn btn-success btn-lg px-5">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Check-in
                        </button>
                    </form>
                <?php elseif (!$cham_hom_nay['gio_ra']): ?>
                    <!-- Đã check-in, chưa check-out -->
                    <div class="mb-3">
                        <div class="fs-5">Giờ vào: <strong><?= date('H:i', strtotime($cham_hom_nay['gio_vao'])) ?></strong></div>
                        <div class="mt-2">
                            <?php
                            $badge = match($cham_hom_nay['trang_thai']) {
                                'dung_gio' => 'bg-success',
                                'di_muon'  => 'bg-warning text-dark',
                                default    => 'bg-secondary'
                            };
                            $text = match($cham_hom_nay['trang_thai']) {
                                'dung_gio' => 'Đúng giờ',
                                'di_muon'  => 'Đi muộn',
                                default    => $cham_hom_nay['trang_thai']
                            };
                            ?>
                            <span class="badge <?= $badge ?>"><?= $text ?></span>
                        </div>
                    </div>
                    <form method="POST">
                        <button type="submit" name="check_out" class="btn btn-danger btn-lg px-5">
                            <i class="bi bi-box-arrow-right me-2"></i> Check-out
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Đã check-out -->
                    <div class="text-success mb-3">
                        <i class="bi bi-check-circle fs-1"></i>
                        <div class="fw-semibold mt-2">Bạn đã hoàn thành chấm công hôm nay</div>
                    </div>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="text-muted small">Giờ vào</div>
                            <div class="fs-5 fw-semibold"><?= date('H:i', strtotime($cham_hom_nay['gio_vao'])) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Giờ ra</div>
                            <div class="fs-5 fw-semibold"><?= date('H:i', strtotime($cham_hom_nay['gio_ra'])) ?></div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <?php
                        $badge = match($cham_hom_nay['trang_thai']) {
                            'dung_gio'  => 'bg-success',
                            'di_muon'   => 'bg-warning text-dark',
                            've_som'    => 'bg-info',
                            'nghi_phep' => 'bg-secondary',
                            default     => 'bg-secondary'
                        };
                        $text = match($cham_hom_nay['trang_thai']) {
                            'dung_gio'  => 'Đúng giờ',
                            'di_muon'   => 'Đi muộn',
                            've_som'    => 'Về sớm',
                            'nghi_phep' => 'Nghỉ phép',
                            default     => $cham_hom_nay['trang_thai']
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= $text ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Thống kê nhanh tháng này -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Thống kê tháng <?= date('m/Y') ?></h5>
            </div>
            <div class="card-body">
                <?php
                $tong_ngay = count($lich_su);
                $di_muon = 0;
                $ve_som = 0;
                foreach ($lich_su as $ls) {
                    if ($ls['trang_thai'] === 'di_muon') $di_muon++;
                    if ($ls['trang_thai'] === 've_som') $ve_som++;
                }
                ?>
                <div class="row text-center">
                    <div class="col-4">
                        <div class="fs-3 fw-bold text-primary"><?= $tong_ngay ?></div>
                        <div class="text-muted small">Ngày công</div>
                    </div>
                    <div class="col-4">
                        <div class="fs-3 fw-bold text-warning"><?= $di_muon ?></div>
                        <div class="text-muted small">Đi muộn</div>
                    </div>
                    <div class="col-4">
                        <div class="fs-3 fw-bold text-info"><?= $ve_som ?></div>
                        <div class="text-muted small">Về sớm</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lịch sử chấm công tháng này -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Lịch sử chấm công tháng <?= date('m/Y') ?></h5>
        <span class="badge bg-primary"><?= $total_rows ?> ngày</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Ngày</th>
                        <th>Giờ vào</th>
                        <th>Giờ ra</th>
                        <th>Trạng thái</th>
                        <th>Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lich_su)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Chưa có dữ liệu chấm công tháng này</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lich_su as $ls): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($ls['ngay_cham'])) ?></td>
                                <td><?= $ls['gio_vao'] ? date('H:i', strtotime($ls['gio_vao'])) : '—' ?></td>
                                <td><?= $ls['gio_ra'] ? date('H:i', strtotime($ls['gio_ra'])) : '—' ?></td>
                                <td>
                                    <?php
                                    $badge = match($ls['trang_thai']) {
                                        'dung_gio'  => 'bg-success',
                                        'di_muon'   => 'bg-warning text-dark',
                                        've_som'    => 'bg-info',
                                        'nghi_phep' => 'bg-secondary',
                                        default     => 'bg-secondary'
                                    };
                                    $text = match($ls['trang_thai']) {
                                        'dung_gio'  => 'Đúng giờ',
                                        'di_muon'   => 'Đi muộn',
                                        've_som'    => 'Về sớm',
                                        'nghi_phep' => 'Nghỉ phép',
                                        default     => $ls['trang_thai']
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= $text ?></span>
                                </td>
                                <td><?= htmlspecialchars($ls['ghi_chu'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1): ?>
<nav class="p-3">
    <ul class="pagination justify-content-center mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page-1 ?>">Trước</a>
        </li>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page+1 ?>">Sau</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>