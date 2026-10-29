<?php
require_once '../includes/auth.php';
require_employee();
$page_title = 'Đơn xin nghỉ';
require_once '../includes/header.php';
require_once '../config/database.php';

$nhan_vien_id = $_SESSION['user_id'];
$success = $error = '';

// ========== XÓA ĐƠN (chỉ khi đang chờ duyệt) ==========
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("SELECT * FROM don_xin_nghi WHERE id = ? AND nhan_vien_id = ?");
    $stmt->execute([$id, $nhan_vien_id]);
    $don = $stmt->fetch();

    if ($don && $don['trang_thai'] === 'cho_duyet') {
        $stmt = $pdo->prepare("DELETE FROM don_xin_nghi WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Đã xóa đơn xin nghỉ.';
    } else {
        $error = 'Không thể xóa đơn này.';
    }
}

// ========== THÊM / SỬA ĐƠN ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $loai_nghi = trim($_POST['loai_nghi'] ?? '');
    $ngay_bat_dau = $_POST['ngay_bat_dau'] ?? '';
    $ngay_ket_thuc = $_POST['ngay_ket_thuc'] ?? '';
    $ly_do = trim($_POST['ly_do'] ?? '');

    if (empty($loai_nghi) || empty($ngay_bat_dau) || empty($ngay_ket_thuc) || empty($ly_do)) {
        $error = 'Vui lòng điền đầy đủ thông tin.';
    } elseif ($ngay_ket_thuc < $ngay_bat_dau) {
        $error = 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.';
    } else {
        try {
            if ($id) {
                // Chỉ cho sửa khi đang chờ duyệt
                $stmt = $pdo->prepare("SELECT trang_thai FROM don_xin_nghi WHERE id = ? AND nhan_vien_id = ?");
                $stmt->execute([$id, $nhan_vien_id]);
                $don = $stmt->fetch();

                if ($don && $don['trang_thai'] === 'cho_duyet') {
                    $stmt = $pdo->prepare("
                        UPDATE don_xin_nghi 
                        SET loai_nghi=?, ngay_bat_dau=?, ngay_ket_thuc=?, ly_do=?
                        WHERE id=? AND nhan_vien_id=?
                    ");
                    $stmt->execute([$loai_nghi, $ngay_bat_dau, $ngay_ket_thuc, $ly_do, $id, $nhan_vien_id]);
                    $success = 'Cập nhật đơn thành công!';
                } else {
                    $error = 'Không thể sửa đơn đã được duyệt hoặc từ chối.';
                }
            } else {
                // Thêm mới
                $stmt = $pdo->prepare("
                    INSERT INTO don_xin_nghi (nhan_vien_id, loai_nghi, ngay_bat_dau, ngay_ket_thuc, ly_do)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nhan_vien_id, $loai_nghi, $ngay_bat_dau, $ngay_ket_thuc, $ly_do]);
                $success = 'Gửi đơn xin nghỉ thành công!';
            }
        } catch (PDOException $e) {
            $error = 'Có lỗi xảy ra, vui lòng thử lại.';
        }
    }
}

// Lấy dữ liệu để sửa
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM don_xin_nghi WHERE id = ? AND nhan_vien_id = ? AND trang_thai = 'cho_duyet'");
    $stmt->execute([(int) $_GET['edit'], $nhan_vien_id]);
    $edit_data = $stmt->fetch();
}

// Lấy danh sách đơn của nhân viên
// ===== PHÂN TRANG =====
$limit = 5;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Lấy danh sách đơn của nhân viên
$stmt = $pdo->prepare("
    SELECT * FROM don_xin_nghi 
    WHERE nhan_vien_id = ? 
    ORDER BY ngay_tao DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute([$nhan_vien_id]);
$ds_don = $stmt->fetchAll();

// Đếm tổng số đơn
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM don_xin_nghi WHERE nhan_vien_id = ?");
$stmt_count->execute([$nhan_vien_id]);
$total_rows = (int) $stmt_count->fetchColumn();
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

<div class="row"></div>
<!-- DANH SÁCH ĐƠN -->
<div class="col-12">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách đơn của tôi</h5>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary"><?= $total_rows ?> đơn</span>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                    data-bs-target="#themDonModal">
                    <i class="bi bi-plus-lg"></i> Tạo đơn xin nghỉ
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Loại nghỉ</th>
                            <th>Thời gian</th>
                            <th>Lý do</th>
                            <th>Trạng thái</th>
                            <th>Phản hồi</th>
                            <th width="100">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ds_don)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Bạn chưa có đơn xin nghỉ nào</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ds_don as $don): ?>
                                <tr>
                                    <td><?= htmlspecialchars($don['loai_nghi']) ?></td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($don['ngay_bat_dau'])) ?>
                                        →
                                        <?= date('d/m/Y', strtotime($don['ngay_ket_thuc'])) ?>
                                    </td>
                                    <td>
                                        <span title="<?= htmlspecialchars($don['ly_do']) ?>">
                                            <?= htmlspecialchars(mb_strimwidth($don['ly_do'], 0, 30, '...')) ?>
                                        </span>
                                        <?php if (mb_strlen($don['ly_do']) > 30): ?>
                                            <a href="#" class="small text-primary ms-1" data-bs-toggle="modal"
                                                data-bs-target="#chiTietPhanHoi<?= $don['id'] ?>">
                                                Xem thêmm
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge = match ($don['trang_thai']) {
                                            'cho_duyet' => 'bg-warning text-dark',
                                            'da_duyet' => 'bg-success',
                                            'tu_choi' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        $text = match ($don['trang_thai']) {
                                            'cho_duyet' => 'Chờ duyệt',
                                            'da_duyet' => 'Đã duyệt',
                                            'tu_choi' => 'Từ chối',
                                            default => $don['trang_thai']
                                        };
                                        ?>
                                        <span class="badge <?= $badge ?>"><?= $text ?></span>
                                    </td>
                                    <td>
                                        <?php if ($don['phan_hoi']): ?>
                                            <span title="<?= htmlspecialchars($don['phan_hoi']) ?>">
                                                <?= htmlspecialchars(mb_strimwidth($don['phan_hoi'], 0, 25, '...')) ?>
                                            </span>
                                            <?php if (mb_strlen($don['phan_hoi']) > 25): ?>
                                                <a href="#" class="small text-primary ms-1" data-bs-toggle="modal"
                                                    data-bs-target="#chiTietPhanHoi<?= $don['id'] ?>">
                                                    Xem thêm
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($don['trang_thai'] === 'cho_duyet'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-don"
                                                data-bs-toggle="modal" data-bs-target="#themDonModal" data-id="<?= $don['id'] ?>"
                                                data-loai="<?= htmlspecialchars($don['loai_nghi']) ?>"
                                                data-batdau="<?= $don['ngay_bat_dau'] ?>"
                                                data-ketthuc="<?= $don['ngay_ket_thuc'] ?>"
                                                data-lydo="<?= htmlspecialchars($don['ly_do']) ?>" title="Sửa">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                                data-bs-target="#xoaDonModal<?= $don['id'] ?>" title="Xóa">
                                                <i class="bi bi-trash"></i>
                                            </button>

                                            <!-- Modal xác nhận xóa -->
                                            <div class="modal fade" id="xoaDonModal<?= $don['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Xác nhận xóa đơn</h5>
                                                            <button type="button" class="btn-close"
                                                                data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            <p>Bạn có chắc muốn xóa đơn xin nghỉ này?</p>
                                                            <ul class="mb-0">
                                                                <li><strong>Loại nghỉ:</strong>
                                                                    <?= htmlspecialchars($don['loai_nghi']) ?></li>
                                                                <li><strong>Thời gian:</strong>
                                                                    <?= date('d/m/Y', strtotime($don['ngay_bat_dau'])) ?>
                                                                    →
                                                                    <?= date('d/m/Y', strtotime($don['ngay_ket_thuc'])) ?>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Hủy</button>
                                                            <a href="?delete=<?= $don['id'] ?>" class="btn btn-danger">Xác nhận
                                                                xóa</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Modal Chi tiết phản hồi -->
                                    <div class="modal fade" id="chiTietPhanHoi<?= $don['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Chi tiết đơn xin nghỉ</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Loại nghỉ:</strong>
                                                        <?= htmlspecialchars($don['loai_nghi']) ?>
                                                    </p>
                                                    <p><strong>Thời gian:</strong>
                                                        <?= date('d/m/Y', strtotime($don['ngay_bat_dau'])) ?>
                                                        →
                                                        <?= date('d/m/Y', strtotime($don['ngay_ket_thuc'])) ?>
                                                    </p>
                                                    <p><strong>Trạng thái:</strong>
                                                        <?php
                                                        $text = match ($don['trang_thai']) {
                                                            'cho_duyet' => 'Chờ duyệt',
                                                            'da_duyet' => 'Đã duyệt',
                                                            'tu_choi' => 'Từ chối',
                                                            default => $don['trang_thai']
                                                        };
                                                        echo $text;
                                                        ?>
                                                    </p>
                                                    <hr>
                                                    <p><strong>Lý do của bạn:</strong></p>
                                                    <div class="border rounded p-3 bg-light mb-3">
                                                        <?= nl2br(htmlspecialchars($don['ly_do'])) ?>
                                                    </div>
                                                    <?php if ($don['phan_hoi']): ?>
                                                        <p><strong>Phản hồi từ Admin:</strong></p>
                                                        <div class="border rounded p-3 bg-light">
                                                            <?= nl2br(htmlspecialchars($don['phan_hoi'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Đóng</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Modal Chi tiết đơn -->
                                    <div class="modal fade" id="chiTietPhanHoi<?= $don['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Chi tiết đơn xin nghỉ</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Loại nghỉ:</strong>
                                                        <?= htmlspecialchars($don['loai_nghi']) ?>
                                                    </p>
                                                    <p><strong>Thời gian:</strong>
                                                        <?= date('d/m/Y', strtotime($don['ngay_bat_dau'])) ?>
                                                        →
                                                        <?= date('d/m/Y', strtotime($don['ngay_ket_thuc'])) ?>
                                                    </p>
                                                    <p><strong>Trạng thái:</strong>
                                                        <?php
                                                        echo match ($don['trang_thai']) {
                                                            'cho_duyet' => 'Chờ duyệt',
                                                            'da_duyet' => 'Đã duyệt',
                                                            'tu_choi' => 'Từ chối',
                                                            default => $don['trang_thai']
                                                        };
                                                        ?>
                                                    </p>
                                                    <hr>
                                                    <p><strong>Lý do của bạn:</strong></p>
                                                    <div class="border rounded p-3 bg-light mb-3">
                                                        <?= nl2br(htmlspecialchars($don['ly_do'])) ?>
                                                    </div>
                                                    <?php if ($don['phan_hoi']): ?>
                                                        <p><strong>Phản hồi từ Admin:</strong></p>
                                                        <div class="border rounded p-3 bg-light">
                                                            <?= nl2br(htmlspecialchars($don['phan_hoi'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Đóng</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): ?>
                    <nav class="p-3">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>">Trước</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>">Sau</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Modal Tạo / Sửa đơn xin nghỉ -->
<div class="modal fade" id="themDonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDonTitle">Tạo đơn xin nghỉ mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="don_id" value="">
                    <div class="mb-3">
                        <label class="form-label">Loại nghỉ <span class="text-danger">*</span></label>
                        <select name="loai_nghi" id="don_loai" class="form-select" required>
                            <option value="">-- Chọn loại nghỉ --</option>
                            <option value="Nghỉ phép năm">Nghỉ phép năm</option>
                            <option value="Nghỉ ốm">Nghỉ ốm</option>
                            <option value="Việc riêng">Việc riêng</option>
                            <option value="Nghỉ không lương">Nghỉ không lương</option>
                            <option value="Khác">Khác</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Từ ngày <span class="text-danger">*</span></label>
                        <input type="date" name="ngay_bat_dau" id="don_batdau" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Đến ngày <span class="text-danger">*</span></label>
                        <input type="date" name="ngay_ket_thuc" id="don_ketthuc" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lý do <span class="text-danger">*</span></label>
                        <textarea name="ly_do" id="don_lydo" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary" id="btnLuuDon">Gửi đơn</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Nút Sửa
        document.querySelectorAll('.btn-edit-don').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('modalDonTitle').innerText = 'Sửa đơn xin nghỉ';
                document.getElementById('btnLuuDon').innerText = 'Cập nhật đơn';
                document.getElementById('don_id').value = this.getAttribute('data-id');
                document.getElementById('don_loai').value = this.getAttribute('data-loai');
                document.getElementById('don_batdau').value = this.getAttribute('data-batdau');
                document.getElementById('don_ketthuc').value = this.getAttribute('data-ketthuc');
                document.getElementById('don_lydo').value = this.getAttribute('data-lydo');
            });
        });

        // Nút Tạo mới → reset
        document.querySelector('[data-bs-target="#themDonModal"].btn-primary')?.addEventListener('click', function () {
            document.getElementById('modalDonTitle').innerText = 'Tạo đơn xin nghỉ mới';
            document.getElementById('btnLuuDon').innerText = 'Gửi đơn';
            document.getElementById('don_id').value = '';
            document.querySelector('#themDonModal form').reset();
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>