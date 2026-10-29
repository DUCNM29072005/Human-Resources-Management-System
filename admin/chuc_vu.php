<?php
require_once '../includes/auth.php';
require_admin();
require_once '../config/database.php';
$page_title = 'Quản lý Chức vụ';

// ===== API gợi ý tìm kiếm (AJAX) =====
if (isset($_GET['suggest'])) {
    header('Content-Type: application/json; charset=utf-8');
    $kw = trim($_GET['suggest']);
    if ($kw === '') {
        echo '[]';
        exit;
    }
    $like = '%' . $kw . '%';
    $stmt = $pdo->prepare("SELECT ma_cv, ten_cv FROM chuc_vu WHERE ma_cv LIKE ? OR ten_cv LIKE ? ORDER BY ten_cv ASC LIMIT 6");
    $stmt->execute([$like, $like]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../includes/header.php';

$success = $error = '';

// Xóa
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM chuc_vu WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Xóa chức vụ thành công!';
    } catch (PDOException $e) {
        $error = 'Không thể xóa vì chức vụ này đang được sử dụng.';
    }
}

// Thêm / Sửa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $ma_cv = trim($_POST['ma_cv'] ?? '');
    $ten_cv = trim($_POST['ten_cv'] ?? '');
    $phu_cap = (float) ($_POST['phu_cap'] ?? 0);

    if (empty($ma_cv) || empty($ten_cv)) {
        $error = 'Mã chức vụ và Tên chức vụ không được để trống.';
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE chuc_vu SET ma_cv=?, ten_cv=?, phu_cap=? WHERE id=?");
                $stmt->execute([$ma_cv, $ten_cv, $phu_cap, $id]);
                $success = 'Cập nhật chức vụ thành công!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO chuc_vu (ma_cv, ten_cv, phu_cap) VALUES (?, ?, ?)");
                $stmt->execute([$ma_cv, $ten_cv, $phu_cap]);
                $success = 'Thêm chức vụ thành công!';
            }
        } catch (PDOException $e) {
            $error = 'Mã chức vụ đã tồn tại hoặc có lỗi xảy ra.';
        }
    }
}

// Lấy dữ liệu sửa
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM chuc_vu WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $edit_data = $stmt->fetch();
}

// ===== TÌM KIẾM + PHÂN TRANG =====
$keyword = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($keyword !== '') {
    $where = "WHERE cv.ma_cv LIKE ? OR cv.ten_cv LIKE ?";
    $params = ['%' . $keyword . '%', '%' . $keyword . '%'];
}
$qs = $keyword !== '' ? '&q=' . urlencode($keyword) : '';

$limit = 5;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Danh sách + đếm số nhân viên
$stmt = $pdo->prepare("
    SELECT cv.*, COUNT(nv.id) AS so_nhan_vien
    FROM chuc_vu cv
    LEFT JOIN nhan_vien nv ON cv.id = nv.chuc_vu_id AND nv.trang_thai = 1
    $where
    GROUP BY cv.id
    ORDER BY cv.phu_cap DESC, cv.id ASC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$ds_chuc_vu = $stmt->fetchAll();

// Đếm tổng số chức vụ (theo kết quả tìm kiếm)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM chuc_vu cv $where");
$stmt->execute($params);
$total_rows = (int) $stmt->fetchColumn();
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

<div class="row">
    <!-- Danh sách -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Danh sách Chức vụ</h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary"><?= $total_rows ?> chức vụ</span>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#themChucVuModal">
                        <i class="bi bi-plus-lg"></i> Thêm chức vụ
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Ô tìm kiếm -->
                <div class="p-3 border-bottom">
                    <form method="GET" class="d-flex gap-2" id="formTimKiem" autocomplete="off">
                        <div class="position-relative flex-grow-1">
                            <input type="text" name="q" id="inputTimKiem" class="form-control"
                                placeholder="Tìm theo mã CV hoặc tên chức vụ..."
                                value="<?= htmlspecialchars($keyword) ?>">
                            <div id="goiY" class="list-group position-absolute w-100 shadow-sm"
                                style="z-index: 1050; display: none;"></div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Tìm</button>
                        <?php if ($keyword !== ''): ?>
                            <a href="?" class="btn btn-outline-secondary">Xóa lọc</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Mã CV</th>
                                <th>Tên chức vụ</th>
                                <th>Phụ cấp</th>
                                <th>Số NV</th>
                                <th width="120">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ds_chuc_vu)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <?= $keyword !== '' ? 'Không tìm thấy chức vụ phù hợp' : 'Chưa có chức vụ nào' ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ds_chuc_vu as $index => $cv): ?>
                                    <tr>
                                        <td><?= $offset + $index + 1 ?></td>
                                        <td><code><?= htmlspecialchars($cv['ma_cv']) ?></code></td>
                                        <td><?= htmlspecialchars($cv['ten_cv']) ?></td>
                                        <td><?= number_format($cv['phu_cap'], 0, ',', '.') ?> ₫</td>
                                        <td>
                                            <span class="badge bg-info"><?= $cv['so_nhan_vien'] ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-cv"
                                                data-bs-toggle="modal" data-bs-target="#themChucVuModal"
                                                data-id="<?= $cv['id'] ?>" data-ma="<?= htmlspecialchars($cv['ma_cv']) ?>"
                                                data-ten="<?= htmlspecialchars($cv['ten_cv']) ?>"
                                                data-phucap="<?= $cv['phu_cap'] ?>" title="Sửa">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                                data-bs-target="#xoaCvModal<?= $cv['id'] ?>" title="Xóa">
                                                <i class="bi bi-trash"></i>
                                            </button>

                                            <div class="modal fade" id="xoaCvModal<?= $cv['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Xác nhận xóa</h5>
                                                            <button type="button" class="btn-close"
                                                                data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            Bạn có chắc muốn xóa chức vụ
                                                            <strong><?= htmlspecialchars($cv['ten_cv']) ?></strong>?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Hủy</button>
                                                            <a href="?delete=<?= $cv['id'] ?>" class="btn btn-danger">Xác nhận
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
                                    <a class="page-link" href="?page=<?= $page - 1 ?><?= $qs ?>">Trước</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?><?= $qs ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?><?= $qs ?>">Sau</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal Thêm / Sửa Chức vụ -->
<div class="modal fade" id="themChucVuModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalChucVuTitle">Thêm chức vụ mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="cv_id" value="">
                    <div class="mb-3">
                        <label class="form-label">Mã chức vụ <span class="text-danger">*</span></label>
                        <input type="text" name="ma_cv" id="cv_ma" class="form-control" required
                            placeholder="VD: TP, NV, GD...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tên chức vụ <span class="text-danger">*</span></label>
                        <input type="text" name="ten_cv" id="cv_ten" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phụ cấp (VNĐ)</label>
                        <input type="number" name="phu_cap" id="cv_phucap" class="form-control" min="0" step="1000"
                            value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary" id="btnLuuChucVu">Thêm mới</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Khi bấm nút Sửa
    document.querySelectorAll('.btn-edit-cv').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modalChucVuTitle').innerText = 'Sửa chức vụ';
            document.getElementById('btnLuuChucVu').innerText = 'Cập nhật';
            document.getElementById('cv_id').value = this.dataset.id;
            document.getElementById('cv_ma').value = this.dataset.ma;
            document.getElementById('cv_ten').value = this.dataset.ten;
            document.getElementById('cv_phucap').value = this.dataset.phucap || 0;
        });
    });

    // Reset khi mở modal Thêm mới
    document.getElementById('themChucVuModal').addEventListener('show.bs.modal', function (e) {
        if (!e.relatedTarget || !e.relatedTarget.classList.contains('btn-edit-cv')) {
            document.getElementById('modalChucVuTitle').innerText = 'Thêm chức vụ mới';
            document.getElementById('btnLuuChucVu').innerText = 'Thêm mới';
            document.getElementById('cv_id').value = '';
            document.querySelector('#themChucVuModal form').reset();
            document.getElementById('cv_phucap').value = 0;
        }
    });

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
                            `<button type="button" class="list-group-item list-group-item-action" data-value="${esc(i.ten_cv)}">
                                <code>${esc(i.ma_cv)}</code> - ${esc(i.ten_cv)}
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