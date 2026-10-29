<?php
require_once '../includes/auth.php';
require_admin();
require_once '../config/database.php';
$page_title = 'Duyệt Đơn xin nghỉ';

// ===== API gợi ý tìm kiếm (AJAX) =====
if (isset($_GET['suggest'])) {
    header('Content-Type: application/json; charset=utf-8');
    $kw = trim($_GET['suggest']);
    if ($kw === '') {
        echo '[]';
        exit;
    }
    $like = '%' . $kw . '%';
    $stmt = $pdo->prepare("
        SELECT DISTINCT nv.ma_nv, nv.ho_ten
        FROM don_xin_nghi d
        JOIN nhan_vien nv ON d.nhan_vien_id = nv.id
        WHERE nv.ma_nv LIKE ? OR nv.ho_ten LIKE ?
        ORDER BY nv.ho_ten ASC
        LIMIT 8
    ");
    $stmt->execute([$like, $like]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../includes/header.php';

$success = $error = '';
$admin_id = $_SESSION['user_id'];

// ========== DUYỆT ĐƠN ==========
if (isset($_POST['duyet'])) {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("
        UPDATE don_xin_nghi 
        SET trang_thai = 'da_duyet', 
            nguoi_duyet_id = ?, 
            ngay_duyet = NOW(),
            phan_hoi = NULL
        WHERE id = ? AND trang_thai = 'cho_duyet'
    ");
    $stmt->execute([$admin_id, $id]);
    $success = 'Đã duyệt đơn thành công!';
}

// ========== TỪ CHỐI ĐƠN ==========
if (isset($_POST['tu_choi'])) {
    $id = (int)$_POST['id'];
    $phan_hoi = trim($_POST['phan_hoi'] ?? '');

    if (empty($phan_hoi)) {
        $error = 'Vui lòng nhập lý do từ chối.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE don_xin_nghi 
            SET trang_thai = 'tu_choi', 
                nguoi_duyet_id = ?, 
                ngay_duyet = NOW(),
                phan_hoi = ?
            WHERE id = ? AND trang_thai = 'cho_duyet'
        ");
        $stmt->execute([$admin_id, $phan_hoi, $id]);
        $success = 'Đã từ chối đơn.';
    }
}

// Bộ lọc + Tìm kiếm + Phân trang
$filter_trang_thai = $_GET['trang_thai'] ?? 'cho_duyet';
$keyword = trim($_GET['q'] ?? '');
$qs = $keyword !== '' ? '&q=' . urlencode($keyword) : '';

$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$sql = "
    SELECT d.*, nv.ma_nv, nv.ho_ten, pb.ten_pb,
           nd.ho_ten AS ten_nguoi_duyet
    FROM don_xin_nghi d
    JOIN nhan_vien nv ON d.nhan_vien_id = nv.id
    LEFT JOIN phong_ban pb ON nv.phong_ban_id = pb.id
    LEFT JOIN nhan_vien nd ON d.nguoi_duyet_id = nd.id
    WHERE 1=1
";
$params = [];

if ($filter_trang_thai !== 'all') {
    $sql .= " AND d.trang_thai = ?";
    $params[] = $filter_trang_thai;
}

if ($keyword !== '') {
    $sql .= " AND (nv.ma_nv LIKE ? OR nv.ho_ten LIKE ?)";
    $params[] = '%' . $keyword . '%';
    $params[] = '%' . $keyword . '%';
}

$sql .= " ORDER BY 
    CASE d.trang_thai 
        WHEN 'cho_duyet' THEN 1 
        WHEN 'da_duyet' THEN 2 
        ELSE 3 
    END,
    d.ngay_tao DESC
    LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ds_don = $stmt->fetchAll();

// Đếm tổng
$count_sql = "SELECT COUNT(*) FROM don_xin_nghi d JOIN nhan_vien nv ON d.nhan_vien_id = nv.id WHERE 1=1";
$count_params = [];
if ($filter_trang_thai !== 'all') {
    $count_sql .= " AND d.trang_thai = ?";
    $count_params[] = $filter_trang_thai;
}
if ($keyword !== '') {
    $count_sql .= " AND (nv.ma_nv LIKE ? OR nv.ho_ten LIKE ?)";
    $count_params[] = '%' . $keyword . '%';
    $count_params[] = '%' . $keyword . '%';
}
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($count_params);
$total_rows = (int)$stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>

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

<!-- Bộ lọc -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end" id="formTimKiem" autocomplete="off">
    <div class="col-md-3">
        <label class="form-label">Trạng thái</label>
        <select name="trang_thai" class="form-select">
            <option value="cho_duyet" <?= $filter_trang_thai == 'cho_duyet' ? 'selected' : '' ?>>Chờ duyệt</option>
            <option value="da_duyet"  <?= $filter_trang_thai == 'da_duyet' ? 'selected' : '' ?>>Đã duyệt</option>
            <option value="tu_choi"   <?= $filter_trang_thai == 'tu_choi' ? 'selected' : '' ?>>Từ chối</option>
            <option value="all"       <?= $filter_trang_thai == 'all' ? 'selected' : '' ?>>Tất cả</option>
        </select>
    </div>
    <div class="col-md-<?= $keyword !== '' ? 5 : 7 ?>">
        <label class="form-label">Tìm kiếm</label>
        <div class="position-relative">
            <input type="text" name="q" id="inputTimKiem" class="form-control"
                placeholder="Tìm theo mã NV hoặc tên nhân viên..."
                value="<?= htmlspecialchars($keyword) ?>">
            <div id="goiY" class="list-group position-absolute w-100 shadow-sm"
                style="z-index: 1050; display: none;"></div>
        </div>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Lọc</button>
    </div>
    <?php if ($keyword !== ''): ?>
        <div class="col-md-2">
            <a href="?trang_thai=<?= htmlspecialchars($filter_trang_thai) ?>" class="btn btn-outline-secondary w-100">Xóa tìm kiếm</a>
        </div>
    <?php endif; ?>
</form>
    </div>
</div>

<!-- Danh sách -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Danh sách Đơn xin nghỉ</h5>
        <span class="badge bg-primary"><?= $total_rows ?> đơn</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:18%">Nhân viên</th>
                        <th style="width:12%">Loại nghỉ</th>
                        <th style="width:14%">Thời gian</th>
                        <th style="width:18%">Lý do</th>
                        <th style="width:12%">Trạng thái</th>
                        <th style="width:12%">Ngày gửi</th>
                        <th style="width:14%" class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ds_don)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <?= $keyword !== '' ? 'Không tìm thấy đơn phù hợp' : 'Không có đơn nào' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ds_don as $don): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($don['ho_ten']) ?></div>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($don['ma_nv']) ?>
                                        <?= $don['ten_pb'] ? ' · ' . htmlspecialchars($don['ten_pb']) : '' ?>
                                    </small>
                                </td>
                                <td><?= htmlspecialchars($don['loai_nghi']) ?></td>
                                <td>
                                    <div><?= date('d/m/Y', strtotime($don['ngay_bat_dau'])) ?></div>
                                    <small class="text-muted">→ <?= date('d/m/Y', strtotime($don['ngay_ket_thuc'])) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars(mb_strimwidth($don['ly_do'], 0, 30, '...')) ?>
                                    <?php if (mb_strlen($don['ly_do']) > 30): ?>
                                        <a href="#" class="small text-primary" data-bs-toggle="modal" data-bs-target="#chiTietDon<?= $don['id'] ?>">Xem thêm</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badge = match($don['trang_thai']) {
                                        'cho_duyet' => 'bg-warning text-dark',
                                        'da_duyet'  => 'bg-success',
                                        'tu_choi'   => 'bg-danger',
                                        default     => 'bg-secondary'
                                    };
                                    $text = match($don['trang_thai']) {
                                        'cho_duyet' => 'Chờ duyệt',
                                        'da_duyet'  => 'Đã duyệt',
                                        'tu_choi'   => 'Từ chối',
                                        default     => $don['trang_thai']
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= $text ?></span>
                                </td>
                                <td>
                                    <div><?= date('d/m/Y', strtotime($don['ngay_tao'])) ?></div>
                                    <small class="text-muted"><?= date('H:i', strtotime($don['ngay_tao'])) ?></small>
                                </td>
                                <td class="text-center">
                                    <?php if ($don['trang_thai'] === 'cho_duyet'): ?>
                                        <div class="d-flex justify-content-center gap-1">
                                            <button type="button" class="btn btn-sm btn-success"
                                                    data-bs-toggle="modal" data-bs-target="#duyetModal<?= $don['id'] ?>"
                                                    title="Duyệt">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal" data-bs-target="#tuChoiModal<?= $don['id'] ?>"
                                                    title="Từ chối">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>

                                        <!-- Modal Duyệt -->
                                        <div class="modal fade" id="duyetModal<?= $don['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Xác nhận duyệt đơn</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            <input type="hidden" name="id" value="<?= $don['id'] ?>">
                                                            <p>Bạn có chắc muốn <strong class="text-success">duyệt</strong> đơn của:</p>
                                                            <ul class="mb-0">
                                                                <li><strong>Nhân viên:</strong> <?= htmlspecialchars($don['ho_ten']) ?></li>
                                                                <li><strong>Loại nghỉ:</strong> <?= htmlspecialchars($don['loai_nghi']) ?></li>
                                                                <li><strong>Thời gian:</strong> 
                                                                    <?= date('d/m/Y', strtotime($don['ngay_bat_dau'])) ?> → 
                                                                    <?= date('d/m/Y', strtotime($don['ngay_ket_thuc'])) ?>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                            <button type="submit" name="duyet" class="btn btn-success">Xác nhận duyệt</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal Từ chối -->
                                        <div class="modal fade" id="tuChoiModal<?= $don['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Từ chối đơn xin nghỉ</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            <input type="hidden" name="id" value="<?= $don['id'] ?>">
                                                            <p class="mb-2">
                                                                Nhân viên: <strong><?= htmlspecialchars($don['ho_ten']) ?></strong><br>
                                                                Thời gian: <?= date('d/m/Y', strtotime($don['ngay_bat_dau'])) ?> → <?= date('d/m/Y', strtotime($don['ngay_ket_thuc'])) ?>
                                                            </p>
                                                            <div class="mb-3">
                                                                <label class="form-label">Lý do từ chối <span class="text-danger">*</span></label>
                                                                <textarea name="phan_hoi" class="form-control" rows="3" required placeholder="Nhập lý do từ chối..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                            <button type="submit" name="tu_choi" class="btn btn-danger">Xác nhận từ chối</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <small class="text-muted">
                                            <?= $don['ten_nguoi_duyet'] ? htmlspecialchars($don['ten_nguoi_duyet']) : '—' ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Modal Chi tiết lý do -->
                            <div class="modal fade" id="chiTietDon<?= $don['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Chi tiết đơn xin nghỉ</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-start">
                                            <p><strong>Nhân viên:</strong> <?= htmlspecialchars($don['ho_ten']) ?> (<?= htmlspecialchars($don['ma_nv']) ?>)</p>
                                            <p><strong>Loại nghỉ:</strong> <?= htmlspecialchars($don['loai_nghi']) ?></p>
                                            <p><strong>Thời gian:</strong> <?= date('d/m/Y', strtotime($don['ngay_bat_dau'])) ?> → <?= date('d/m/Y', strtotime($don['ngay_ket_thuc'])) ?></p>
                                            <hr>
                                            <p><strong>Lý do:</strong></p>
                                            <div class="border rounded p-3 bg-light">
                                                <?= nl2br(htmlspecialchars($don['ly_do'])) ?>
                                            </div>
                                            <?php if ($don['phan_hoi']): ?>
                                                <p class="mt-3"><strong>Phản hồi Admin:</strong></p>
                                                <div class="border rounded p-3 bg-light">
                                                    <?= nl2br(htmlspecialchars($don['phan_hoi'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav class="p-3">
            <ul class="pagination justify-content-center mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page-1 ?>&trang_thai=<?= $filter_trang_thai ?><?= $qs ?>">Trước</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&trang_thai=<?= $filter_trang_thai ?><?= $qs ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page+1 ?>&trang_thai=<?= $filter_trang_thai ?><?= $qs ?>">Sau</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
    // ===== Gợi ý tìm kiếm =====
    (function () {
        const input = document.getElementById('inputTimKiem');
        const box = document.getElementById('goiY');
        const form = document.getElementById('formTimKiem');
        let timer = null;

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            const kw = this.value.trim();
            if (kw === '') {
                box.style.display = 'none';
                return;
            }
            timer = setTimeout(() => {
                fetch('?suggest=' + encodeURIComponent(kw))
                    .then(r => r.json())
                    .then(list => {
                        if (!list.length) {
                            box.style.display = 'none';
                            return;
                        }
                        box.innerHTML = list.map(i =>
                            `<button type="button" class="list-group-item list-group-item-action" data-value="${esc(i.ho_ten)}">
                                <code>${esc(i.ma_nv)}</code> - ${esc(i.ho_ten)}
                            </button>`
                        ).join('');
                        box.style.display = 'block';
                    });
            }, 250);
        });

        // Bấm vào gợi ý -> điền vào ô và tìm luôn
        box.addEventListener('click', function (e) {
            const item = e.target.closest('[data-value]');
            if (item) {
                input.value = item.dataset.value;
                box.style.display = 'none';
                form.submit();
            }
        });

        // Bấm ra ngoài thì ẩn gợi ý
        document.addEventListener('click', function (e) {
            if (!form.contains(e.target)) box.style.display = 'none';
        });
    })();
</script>

<?php require_once '../includes/footer.php'; ?>