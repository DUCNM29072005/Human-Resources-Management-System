<?php
require_once '../includes/auth.php';
require_admin();
$page_title = 'Dashboard Admin';
require_once '../includes/header.php';
require_once '../config/database.php';

// ===== THỐNG KÊ NHANH =====
$tong_nhan_vien = $pdo->query("SELECT COUNT(*) FROM nhan_vien WHERE trang_thai = 1")->fetchColumn();
$tong_phong_ban = $pdo->query("SELECT COUNT(*) FROM phong_ban")->fetchColumn();
$don_hom_nay    = $pdo->query("SELECT COUNT(*) FROM don_xin_nghi WHERE DATE(ngay_tao) = CURDATE()")->fetchColumn();
$don_cho_duyet  = $pdo->query("SELECT COUNT(*) FROM don_xin_nghi WHERE trang_thai = 'cho_duyet'")->fetchColumn();

// ===== TỶ LỆ NHÂN SỰ THEO PHÒNG BAN =====
$stmt = $pdo->query("
    SELECT pb.ten_pb, COUNT(nv.id) AS so_luong
    FROM phong_ban pb
    LEFT JOIN nhan_vien nv ON pb.id = nv.phong_ban_id AND nv.trang_thai = 1
    GROUP BY pb.id
");
$thong_ke_pb = $stmt->fetchAll();
$labels_pb = [];
$data_pb = [];
foreach ($thong_ke_pb as $row) {
    $labels_pb[] = $row['ten_pb'];
    $data_pb[] = (int)$row['so_luong'];
}

// ===== TÌNH TRẠNG CHẤM CÔNG HÔM NAY =====
$tong_nv = $tong_nhan_vien ?: 1;
$da_cham = $pdo->query("SELECT COUNT(DISTINCT nhan_vien_id) FROM cham_cong WHERE ngay_cham = CURDATE()")->fetchColumn();
$chua_cham = $tong_nv - $da_cham;
$di_muon_hom_nay = $pdo->query("SELECT COUNT(*) FROM cham_cong WHERE ngay_cham = CURDATE() AND trang_thai = 'di_muon'")->fetchColumn();
$nghi_phep_hom_nay = $pdo->query("SELECT COUNT(*) FROM cham_cong WHERE ngay_cham = CURDATE() AND trang_thai = 'nghi_phep'")->fetchColumn();

// ===== BIỂU ĐỒ 7 NGÀY GẦN ĐÂY =====
$labels_7ngay = [];
$data_da_cham = [];
$data_chua_cham = [];
for ($i = 6; $i >= 0; $i--) {
    $ngay = date('Y-m-d', strtotime("-$i days"));
    $labels_7ngay[] = date('d/m', strtotime($ngay));
    
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT nhan_vien_id) FROM cham_cong WHERE ngay_cham = ?");
    $stmt->execute([$ngay]);
    $da = (int)$stmt->fetchColumn();
    $data_da_cham[] = $da;
    $data_chua_cham[] = max(0, $tong_nv - $da);
}


?>

<!-- Thẻ thống kê -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Tổng nhân viên</div>
                        <div class="fs-3 fw-bold"><?= $tong_nhan_vien ?></div>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Số phòng ban</div>
                        <div class="fs-3 fw-bold"><?= $tong_phong_ban ?></div>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success rounded-3 p-3">
                        <i class="bi bi-building fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Đơn xin nghỉ hôm nay</div>
                        <div class="fs-3 fw-bold"><?= $don_hom_nay ?></div>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                        <i class="bi bi-calendar-event fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Đơn chờ duyệt</div>
                        <div class="fs-3 fw-bold"><?= $don_cho_duyet ?></div>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-3">
                        <i class="bi bi-file-earmark-text fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Tỷ lệ phòng ban -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Tỷ lệ nhân sự theo Phòng ban</h6>
            </div>
            <div class="card-body">
                <canvas id="chartPhongBan" height="280"></canvas>
            </div>
        </div>
    </div>

    <!-- Tình trạng chấm công hôm nay -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Tình trạng chấm công hôm nay</h6>
            </div>
            <div class="card-body">
                <canvas id="chartChamCongHomNay" height="280"></canvas>
            </div>
        </div>
    </div>

    <!-- Biểu đồ 7 ngày -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Biểu đồ chấm công 7 ngày gần đây</h6>
            </div>
            <div class="card-body">
                <canvas id="chart7Ngay" height="280"></canvas>
            </div>
        </div>
    </div>
</div>


<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Biểu đồ phòng ban
new Chart(document.getElementById('chartPhongBan'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($labels_pb) ?>,
        datasets: [{
            data: <?= json_encode($data_pb) ?>,
            backgroundColor: ['#3b82f6', '#22c55e', '#eab308', '#ef4444', '#8b5cf6', '#06b6d4']
        }]
    },
    options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'right',
            align: 'center',
            labels: {
                boxWidth: 12,
                padding: 8,
                font: { size: 11 },
                // Không cắt chữ, cho xuống dòng nếu cần
                textAlign: 'left'
            }
        },
        tooltip: {
            callbacks: {
                label: function(ctx) {
                    return ctx.label + ': ' + ctx.raw + ' người';
                }
            }
        }
    }
}
});

// Biểu đồ chấm công hôm nay
new Chart(document.getElementById('chartChamCongHomNay'), {
    type: 'doughnut',
    data: {
        labels: ['Đã chấm công', 'Chưa chấm công', 'Đi muộn', 'Nghỉ phép'],
        datasets: [{
            data: [<?= $da_cham ?>, <?= $chua_cham ?>, <?= $di_muon_hom_nay ?>, <?= $nghi_phep_hom_nay ?>],
            backgroundColor: ['#22c55e', '#ef4444', '#eab308', '#94a3b8']
        }]
    },
    options: {
        plugins: { legend: { position: 'right' } },
        maintainAspectRatio: false
    }
});

// Biểu đồ 7 ngày
new Chart(document.getElementById('chart7Ngay'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels_7ngay) ?>,
        datasets: [
            {
                label: 'Đã chấm công',
                data: <?= json_encode($data_da_cham) ?>,
                backgroundColor: '#3b82f6'
            },
            {
                label: 'Chưa chấm công',
                data: <?= json_encode($data_chua_cham) ?>,
                backgroundColor: '#e2e8f0'
            }
        ]
    },
    options: {
        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
        plugins: { legend: { position: 'bottom' } },
        maintainAspectRatio: false
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>