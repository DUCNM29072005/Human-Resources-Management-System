<?php
require_once '../includes/auth.php';
require_employee();
require_once '../includes/auth.php';
$page_title = 'Dashboard Nhân viên';
require_once '../includes/header.php';
require_once '../config/database.php';

$nhan_vien_id = $_SESSION['user_id'];
$thang_hien_tai = date('Y-m');
$ngay_dau_thang = date('Y-m-01');
$so_ngay_thang = date('t');

// ===== 1. NGÀY CÔNG THÁNG NÀY =====
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM cham_cong 
    WHERE nhan_vien_id = ? AND DATE_FORMAT(ngay_cham, '%Y-%m') = ? AND gio_vao IS NOT NULL
");
$stmt->execute([$nhan_vien_id, $thang_hien_tai]);
$ngay_di_lam = (int)$stmt->fetchColumn();
$tong_ngay_chuan = 22;

// ===== 2. SỐ GIỜ LÀM VIỆC =====
$stmt = $pdo->prepare("
    SELECT gio_vao, gio_ra FROM cham_cong 
    WHERE nhan_vien_id = ? AND DATE_FORMAT(ngay_cham, '%Y-%m') = ? 
      AND gio_vao IS NOT NULL AND gio_ra IS NOT NULL
");
$stmt->execute([$nhan_vien_id, $thang_hien_tai]);
$tong_gio = 0;
foreach ($stmt->fetchAll() as $row) {
    $tong_gio += (strtotime($row['gio_ra']) - strtotime($row['gio_vao'])) / 3600;
}
$tong_gio = round($tong_gio, 1);
$tb_gio = $ngay_di_lam > 0 ? round($tong_gio / $ngay_di_lam, 1) : 0;

// ===== 3. ĐƠN XIN NGHỈ =====
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM don_xin_nghi 
    WHERE nhan_vien_id = ? AND DATE_FORMAT(ngay_tao, '%Y-%m') = ?
");
$stmt->execute([$nhan_vien_id, $thang_hien_tai]);
$so_don = (int)$stmt->fetchColumn();

// ===== 4. LƯƠNG ƯỚC TÍNH =====
$stmt = $pdo->prepare("
    SELECT nv.luong_co_ban, cv.phu_cap 
    FROM nhan_vien nv LEFT JOIN chuc_vu cv ON nv.chuc_vu_id = cv.id 
    WHERE nv.id = ?
");
$stmt->execute([$nhan_vien_id]);
$info = $stmt->fetch();
$luong_co_ban = (float)($info['luong_co_ban'] ?? 0);
$phu_cap = (float)($info['phu_cap'] ?? 0);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM cham_cong WHERE nhan_vien_id = ? AND DATE_FORMAT(ngay_cham, '%Y-%m') = ? AND trang_thai = 'di_muon'");
$stmt->execute([$nhan_vien_id, $thang_hien_tai]);
$so_di_muon = (int)$stmt->fetchColumn();

$luong_thang = ($luong_co_ban / 26 * $ngay_di_lam) + $phu_cap - ($so_di_muon * 50000);

// ===== LỊCH CHẤM CÔNG =====
$stmt = $pdo->prepare("SELECT ngay_cham, trang_thai FROM cham_cong WHERE nhan_vien_id = ? AND DATE_FORMAT(ngay_cham, '%Y-%m') = ?");
$stmt->execute([$nhan_vien_id, $thang_hien_tai]);
$lich_cham = [];
foreach ($stmt->fetchAll() as $row) {
    $lich_cham[$row['ngay_cham']] = $row['trang_thai'];
}

// ===== DỮ LIỆU BIỂU ĐỒ GIỜ LÀM =====
$labels_gio = [];
$data_gio = [];
for ($i = 1; $i <= $so_ngay_thang; $i++) {
    $ngay = sprintf('%s-%02d', $thang_hien_tai, $i);
    $labels_gio[] = $i;
    $stmt = $pdo->prepare("SELECT gio_vao, gio_ra FROM cham_cong WHERE nhan_vien_id = ? AND ngay_cham = ?");
    $stmt->execute([$nhan_vien_id, $ngay]);
    $row = $stmt->fetch();
    if ($row && $row['gio_vao'] && $row['gio_ra']) {
        $data_gio[] = round((strtotime($row['gio_ra']) - strtotime($row['gio_vao'])) / 3600, 1);
    } else {
        $data_gio[] = 0;
    }
}

?>

<!-- ===== 4 THẺ THỐNG KÊ ===== -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                        <i class="bi bi-calendar-check fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small">Ngày công tháng này</div>
                        <div class="fs-4 fw-bold"><?= $ngay_di_lam ?> / <?= $tong_ngay_chuan ?></div>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-primary" style="width: <?= min(100, round($ngay_di_lam / $tong_ngay_chuan * 100)) ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-info bg-opacity-10 text-info rounded-3 p-3">
                        <i class="bi bi-clock fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Giờ làm việc</div>
                        <div class="fs-4 fw-bold"><?= $tong_gio ?> giờ</div>
                        <small class="text-muted">Trung bình <?= $tb_gio ?> giờ/ngày</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                        <i class="bi bi-file-earmark-text fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Đơn xin nghỉ</div>
                        <div class="fs-4 fw-bold"><?= $so_don ?> đơn</div>
                        <small class="text-muted">Trong tháng này</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-3">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Lương tháng này</div>
                        <div class="fs-5 fw-bold"><?= number_format($luong_thang, 0, ',', '.') ?> ₫</div>
                        <small class="text-muted">Ước tính</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== LỊCH + BIỂU ĐỒ + TỔNG QUAN ===== -->
<div class="row g-4 mb-4">
    <!-- LỊCH CHẤM CÔNG -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Lịch chấm công tháng <?= date('m/Y') ?></h6>
            </div>
            <div class="card-body">
                <style>
                    .calendar-grid {
                        display: grid;
                        grid-template-columns: repeat(7, 1fr);
                        gap: 4px;
                        text-align: center;
                        font-size: 0.85rem;
                    }
                    .calendar-grid .day-header {
                        font-weight: 600;
                        color: #6b7280;
                        padding: 4px 0;
                    }
                    .calendar-grid .day {
                        padding: 6px 0;
                        border-radius: 6px;
                    }
                    .calendar-grid .day.today {
                        border: 2px solid #3b82f6;
                    }
                    .calendar-grid .dot {
                        width: 8px;
                        height: 8px;
                        border-radius: 50%;
                        margin: 3px auto 0;
                    }
                </style>

                <div class="calendar-grid">
                    <?php foreach (['T2','T3','T4','T5','T6','T7','CN'] as $t): ?>
                        <div class="day-header"><?= $t ?></div>
                    <?php endforeach; ?>

                    <?php
                    $first_day = date('N', strtotime($ngay_dau_thang));
                    for ($i = 1; $i < $first_day; $i++) echo "<div></div>";

                    for ($d = 1; $d <= $so_ngay_thang; $d++):
                        $ngay = sprintf('%s-%02d', $thang_hien_tai, $d);
                        $today_class = ($ngay === date('Y-m-d')) ? 'today' : '';
                        $dot_html = '';

                        if (isset($lich_cham[$ngay])) {
                            $color = match($lich_cham[$ngay]) {
                                'dung_gio' => 'background:#22c55e;',
                                'di_muon'  => 'background:#eab308;',
                                've_som'   => 'background:#06b6d4;',
                                default    => 'background:#94a3b8;'
                            };
                            $dot_html = "<div class='dot' style='$color'></div>";
                        } elseif ($ngay < date('Y-m-d')) {
                            $dot_html = "<div class='dot' style='background:#e5e7eb;border:1px solid #d1d5db;'></div>";
                        }
                    ?>
                        <div class="day <?= $today_class ?>">
                            <?= $d ?>
                            <?= $dot_html ?>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="d-flex flex-wrap gap-3 small justify-content-center mt-3">
                    <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#22c55e;"></span> Đi làm</span>
                    <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#eab308;"></span> Đi muộn</span>
                    <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#06b6d4;"></span> Về sớm</span>
                    <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#e5e7eb;border:1px solid #d1d5db;"></span> Chưa chấm</span>
                </div>
            </div>
        </div>
    </div>

    <!-- THỐNG KÊ GIỜ LÀM VIỆC -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Thống kê giờ làm việc</h6>
            </div>
            <div class="card-body" style="height: 280px;">
                <canvas id="chartGioLam"></canvas>
            </div>
        </div>
    </div>

    <!-- TỔNG QUAN NGÀY CÔNG -->
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Tổng quan ngày công</h6>
            </div>
            <div class="card-body">
                <div style="height: 180px;">
                    <canvas id="chartTongQuan"></canvas>
                </div>
                <div class="mt-3 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#22c55e;"></span> Đi làm</span>
                        <span class="fw-semibold"><?= $ngay_di_lam ?> ngày</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#eab308;"></span> Đi muộn</span>
                        <span class="fw-semibold"><?= $so_di_muon ?> ngày</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#94a3b8;"></span> Chưa chấm</span>
                        <span class="fw-semibold"><?= max(0, date('j') - $ngay_di_lam) ?> ngày</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('chartGioLam'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels_gio) ?>,
        datasets: [{
            label: 'Giờ',
            data: <?= json_encode($data_gio) ?>,
            backgroundColor: '#3b82f6',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { beginAtZero: true },
            x: { ticks: { maxTicksLimit: 10 } }
        },
        plugins: { legend: { display: false } }
    }
});

new Chart(document.getElementById('chartTongQuan'), {
    type: 'doughnut',
    data: {
        labels: ['Đi làm', 'Đi muộn', 'Chưa chấm'],
        datasets: [{
            data: [<?= $ngay_di_lam ?>, <?= $so_di_muon ?>, <?= max(0, date('j') - $ngay_di_lam) ?>],
            backgroundColor: ['#22c55e', '#eab308', '#94a3b8'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: { legend: { display: false } }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>