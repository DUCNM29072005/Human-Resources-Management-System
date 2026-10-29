<?php
require_once '../includes/auth.php';
require_admin();
$page_title = 'Quản lý Chấm công';
require_once '../includes/header.php';
require_once '../config/database.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
$success = $error = '';

// Lấy cấu hình giờ chuẩn
$stmt = $pdo->query("SELECT * FROM cai_dat WHERE id = 1");
$cai_dat = $stmt->fetch() ?: ['gio_vao_chuan' => '08:00:00', 'gio_ra_chuan' => '17:00:00'];

// ========== CẬP NHẬT GIỜ CHUẨN ==========
if (isset($_POST['cap_nhat_gio_chuan'])) {
    $gio_vao_chuan = $_POST['gio_vao_chuan'] ?? '08:00:00';
    $gio_ra_chuan = $_POST['gio_ra_chuan'] ?? '17:00:00';

    $stmt = $pdo->prepare("UPDATE cai_dat SET gio_vao_chuan = ?, gio_ra_chuan = ? WHERE id = 1");
    $stmt->execute([$gio_vao_chuan, $gio_ra_chuan]);
    $success = 'Cập nhật giờ chuẩn thành công!';

    // Load lại
    $stmt = $pdo->query("SELECT * FROM cai_dat WHERE id = 1");
    $cai_dat = $stmt->fetch();
}

// ========== XÓA ==========
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM cham_cong WHERE id = ?");
    $stmt->execute([$id]);
    $success = 'Đã xóa bản ghi chấm công.';
}

// ========== THÊM / SỬA CHẤM CÔNG ==========
if (isset($_POST['luu_cham_cong'])) {
    $id = $_POST['id'] ?? null;
    $ma_nv = trim($_POST['ma_nv'] ?? '');
    $ngay_cham = $_POST['ngay_cham'] ?? '';
    $gio_vao = !empty($_POST['gio_vao']) ? $_POST['gio_vao'] : null;
    $gio_ra = !empty($_POST['gio_ra']) ? $_POST['gio_ra'] : null;
    $trang_thai = $_POST['trang_thai'] ?? 'dung_gio';
    $ghi_chu = trim($_POST['ghi_chu'] ?? '');

    // Tìm nhân viên theo mã
    $nhan_vien_id = null;
    if ($ma_nv !== '') {
        $stmt = $pdo->prepare("SELECT id FROM nhan_vien WHERE ma_nv = ? AND trang_thai = 1");
        $stmt->execute([$ma_nv]);
        $nv = $stmt->fetch();
        if ($nv) {
            $nhan_vien_id = $nv['id'];
        }
    }

    if (!$ma_nv || !$ngay_cham) {
        $error = 'Vui lòng nhập Mã nhân viên và chọn ngày chấm công.';
    } elseif (!$nhan_vien_id) {
        $error = 'Không tìm thấy nhân viên có mã: ' . htmlspecialchars($ma_nv);
    } else {
        try {
            if ($id) {
                // Cập nhật
                $stmt = $pdo->prepare("
                    UPDATE cham_cong 
                    SET nhan_vien_id=?, ngay_cham=?, gio_vao=?, gio_ra=?, trang_thai=?, ghi_chu=?
                    WHERE id=?
                ");
                $stmt->execute([$nhan_vien_id, $ngay_cham, $gio_vao, $gio_ra, $trang_thai, $ghi_chu, $id]);
                $success = 'Cập nhật chấm công thành công!';
            } else {
                // Thêm mới
                $stmt = $pdo->prepare("
                    INSERT INTO cham_cong (nhan_vien_id, ngay_cham, gio_vao, gio_ra, trang_thai, ghi_chu)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nhan_vien_id, $ngay_cham, $gio_vao, $gio_ra, $trang_thai, $ghi_chu]);
                $success = 'Thêm chấm công thành công!';
            }
        } catch (PDOException $e) {
            $error = 'Nhân viên này đã có bản ghi chấm công trong ngày này rồi.';
        }
    }
}

// Lấy dữ liệu để sửa
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("
        SELECT cc.*, nv.ma_nv
        FROM cham_cong cc
        JOIN nhan_vien nv ON cc.nhan_vien_id = nv.id
        WHERE cc.id = ?
    ");
    $stmt->execute([(int) $_GET['edit']]);
    $edit_data = $stmt->fetch();
}

// Bộ lọc
$filter_ngay = $_GET['ngay'] ?? date('Y-m-d');
$filter_thang = $_GET['thang'] ?? '';
$filter_phong_ban = $_GET['phong_ban'] ?? '';

$limit = 5;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $limit;


// Xây dựng câu query
$sql = "
    SELECT cc.*, nv.ma_nv, nv.ho_ten, pb.ten_pb
    FROM cham_cong cc
    JOIN nhan_vien nv ON cc.nhan_vien_id = nv.id
    LEFT JOIN phong_ban pb ON nv.phong_ban_id = pb.id
    WHERE 1=1
";
$params = [];

if ($filter_thang) {
    $sql .= " AND DATE_FORMAT(cc.ngay_cham, '%Y-%m') = ?";
    $params[] = $filter_thang;
} else {
    $sql .= " AND cc.ngay_cham = ?";
    $params[] = $filter_ngay;
}

if ($filter_phong_ban) {
    $sql .= " AND nv.phong_ban_id = ?";
    $params[] = $filter_phong_ban;
}

$sql .= " ORDER BY cc.ngay_cham DESC, nv.ho_ten ASC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ds_cham_cong = $stmt->fetchAll();
// Đếm tổng số bản ghi
$count_sql = "
    SELECT COUNT(*) 
    FROM cham_cong cc
    JOIN nhan_vien nv ON cc.nhan_vien_id = nv.id
    LEFT JOIN phong_ban pb ON nv.phong_ban_id = pb.id
    WHERE 1=1
";
$count_params = [];

if ($filter_thang) {
    $count_sql .= " AND DATE_FORMAT(cc.ngay_cham, '%Y-%m') = ?";
    $count_params[] = $filter_thang;
} else {
    $count_sql .= " AND cc.ngay_cham = ?";
    $count_params[] = $filter_ngay;
}
if ($filter_phong_ban) {
    $count_sql .= " AND nv.phong_ban_id = ?";
    $count_params[] = $filter_phong_ban;
}

$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($count_params);
$total_rows = (int) $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Lấy danh sách phòng ban để lọc
$ds_phong_ban = $pdo->query("SELECT id, ten_pb FROM phong_ban ORDER BY ten_pb")->fetchAll();
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


<div class="row">
    <!-- DANH SÁCH -->
    <div class="col-12">
        <!-- Bộ lọc -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Ngày</label>
                        <input type="date" name="ngay" class="form-control" value="<?= $filter_ngay ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hoặc Tháng</label>
                        <input type="month" name="thang" class="form-control" value="<?= $filter_thang ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phòng ban</label>
                        <select name="phong_ban" class="form-select">
                            <option value="">Tất cả phòng ban</option>
                            <?php foreach ($ds_phong_ban as $pb): ?>
                                <option value="<?= $pb['id'] ?>" <?= $filter_phong_ban == $pb['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pb['ten_pb']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Lọc</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Danh sách Chấm công</h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary"><?= $total_rows ?> bản ghi</span>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#themChamCongModal">
                        <i class="bi bi-plus-lg"></i> Thêm chấm công
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="modal"
                        data-bs-target="#cauHinhGioModal">
                        <i class="bi bi-clock"></i> Cấu hình giờ
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Ngày</th>
                                <th>Nhân viên</th>
                                <th>Phòng ban</th>
                                <th>Giờ vào</th>
                                <th>Giờ ra</th>
                                <th>Trạng thái</th>
                                <th width="100">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ds_cham_cong)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Không có dữ liệu chấm công</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ds_cham_cong as $cc): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($cc['ngay_cham'])) ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($cc['ho_ten']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($cc['ma_nv']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($cc['ten_pb'] ?? '—') ?></td>
                                        <td><?= $cc['gio_vao'] ? date('H:i', strtotime($cc['gio_vao'])) : '—' ?></td>
                                        <td><?= $cc['gio_ra'] ? date('H:i', strtotime($cc['gio_ra'])) : '—' ?></td>
                                        <td>
                                            <?php
                                            $badge = match ($cc['trang_thai']) {
                                                'dung_gio' => 'bg-success',
                                                'di_muon' => 'bg-warning text-dark',
                                                've_som' => 'bg-info',
                                                'nghi_phep' => 'bg-secondary',
                                                default => 'bg-secondary'
                                            };
                                            $text = match ($cc['trang_thai']) {
                                                'dung_gio' => 'Đúng giờ',
                                                'di_muon' => 'Đi muộn',
                                                've_som' => 'Về sớm',
                                                'nghi_phep' => 'Nghỉ phép',
                                                default => $cc['trang_thai']
                                            };
                                            ?>
                                            <span class="badge <?= $badge ?>"><?= $text ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-cc"
                                                data-bs-toggle="modal" data-bs-target="#themChamCongModal"
                                                data-id="<?= $cc['id'] ?>" data-ma="<?= htmlspecialchars($cc['ma_nv']) ?>"
                                                data-ngay="<?= $cc['ngay_cham'] ?>"
                                                data-vaao="<?= $cc['gio_vao'] ? substr($cc['gio_vao'], 0, 5) : '' ?>"
                                                data-ra="<?= $cc['gio_ra'] ? substr($cc['gio_ra'], 0, 5) : '' ?>"
                                                data-tt="<?= $cc['trang_thai'] ?>"
                                                data-ghichu="<?= htmlspecialchars($cc['ghi_chu'] ?? '') ?>" title="Sửa">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                                data-bs-target="#xoaCcModal<?= $cc['id'] ?>" title="Xóa">
                                                <i class="bi bi-trash"></i>
                                            </button>

                                            <div class="modal fade" id="xoaCcModal<?= $cc['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Xác nhận xóa</h5>
                                                            <button type="button" class="btn-close"
                                                                data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            Bạn có chắc muốn xóa bản ghi chấm công của
                                                            <strong><?= htmlspecialchars($cc['ho_ten'] ?? $cc['ma_nv']) ?></strong>
                                                            ngày
                                                            <strong><?= date('d/m/Y', strtotime($cc['ngay_cham'])) ?></strong>?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Hủy</button>
                                                            <a href="?delete=<?= $cc['id'] ?>" class="btn btn-danger">Xác nhận
                                                                xóa</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
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
                                    <a class="page-link"
                                        href="?page=<?= $page - 1 ?>&ngay=<?= $filter_ngay ?>&thang=<?= $filter_thang ?>&phong_ban=<?= $filter_phong_ban ?>">Trước</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link"
                                            href="?page=<?= $i ?>&ngay=<?= $filter_ngay ?>&thang=<?= $filter_thang ?>&phong_ban=<?= $filter_phong_ban ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link"
                                        href="?page=<?= $page + 1 ?>&ngay=<?= $filter_ngay ?>&thang=<?= $filter_thang ?>&phong_ban=<?= $filter_phong_ban ?>">Sau</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal Thêm / Sửa Chấm công -->
<div class="modal fade" id="themChamCongModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalChamCongTitle">Thêm chấm công (bù)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="cc_id" value="">

                    <div class="mb-3">
                        <label class="form-label">Mã nhân viên <span class="text-danger">*</span></label>
                        <input type="text" name="ma_nv" id="cc_ma_nv" class="form-control" required
                            placeholder="Nhập mã nhân viên (VD: NV001)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ngày chấm <span class="text-danger">*</span></label>
                        <input type="date" name="ngay_cham" id="cc_ngay" class="form-control" required
                            value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Giờ vào</label>
                            <input type="time" name="gio_vao" id="cc_gio_vao" class="form-control">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Giờ ra</label>
                            <input type="time" name="gio_ra" id="cc_gio_ra" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="trang_thai" id="cc_trang_thai" class="form-select">
                            <option value="dung_gio">Đúng giờ</option>
                            <option value="di_muon">Đi muộn</option>
                            <option value="ve_som">Về sớm</option>
                            <option value="nghi_phep">Nghỉ phép</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ghi chú</label>
                        <textarea name="ghi_chu" id="cc_ghi_chu" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" name="luu_cham_cong" class="btn btn-primary" id="btnLuuChamCong">Thêm
                        mới</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Khi bấm nút Sửa
    document.querySelectorAll('.btn-edit-cc').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modalChamCongTitle').innerText = 'Sửa chấm công';
            document.getElementById('btnLuuChamCong').innerText = 'Cập nhật';
            document.getElementById('cc_id').value = this.dataset.id;
            document.getElementById('cc_ma_nv').value = this.dataset.ma;
            document.getElementById('cc_ngay').value = this.dataset.ngay;
            document.getElementById('cc_gio_vao').value = this.dataset.vaao || '';
            document.getElementById('cc_gio_ra').value = this.dataset.ra || '';
            document.getElementById('cc_trang_thai').value = this.dataset.tt || 'dung_gio';
            document.getElementById('cc_ghi_chu').value = this.dataset.ghichu || '';
        });
    });

    // Reset khi mở modal Thêm mới
    document.getElementById('themChamCongModal').addEventListener('show.bs.modal', function (e) {
        if (!e.relatedTarget || !e.relatedTarget.classList.contains('btn-edit-cc')) {
            document.getElementById('modalChamCongTitle').innerText = 'Thêm chấm công (bù)';
            document.getElementById('btnLuuChamCong').innerText = 'Thêm mới';
            document.getElementById('cc_id').value = '';
            document.querySelector('#themChamCongModal form').reset();
            document.getElementById('cc_ngay').value = '<?= date('Y-m-d') ?>';
        }
    });
</script>

<!-- Modal Cấu hình giờ chuẩn -->
<div class="modal fade" id="cauHinhGioModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Cấu hình giờ làm việc chuẩn</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Giờ vào chuẩn (sau giờ này = Đi muộn)</label>
                        <input type="time" name="gio_vao_chuan" class="form-control"
                            value="<?= substr($cai_dat['gio_vao_chuan'], 0, 5) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Giờ ra chuẩn (trước giờ này = Về sớm)</label>
                        <input type="time" name="gio_ra_chuan" class="form-control"
                            value="<?= substr($cai_dat['gio_ra_chuan'], 0, 5) ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" name="cap_nhat_gio_chuan" class="btn btn-dark">Lưu cấu hình</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>