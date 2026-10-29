<?php
require_once '../includes/auth.php';
require_admin();
require_once '../config/database.php';
$page_title = 'Quản lý Phòng ban';

// ===== API gợi ý tìm kiếm (AJAX) =====
if (isset($_GET['suggest'])) {
    header('Content-Type: application/json; charset=utf-8');
    $kw = trim($_GET['suggest']);
    if ($kw === '') {
        echo '[]';
        exit;
    }
    $like = '%' . $kw . '%';
    $stmt = $pdo->prepare("SELECT ma_pb, ten_pb FROM phong_ban WHERE ma_pb LIKE ? OR ten_pb LIKE ? ORDER BY ten_pb ASC LIMIT 6");
    $stmt->execute([$like, $like]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../includes/header.php';

// ========== XỬ LÝ THÊM / SỬA / XÓA ==========
$success = $error = '';

// Xóa
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM phong_ban WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Xóa phòng ban thành công!';
    } catch (PDOException $e) {
        $error = 'Không thể xóa vì phòng ban này đang có nhân viên.';
    }
}

// Thêm hoặc Sửa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $ma_pb = trim($_POST['ma_pb'] ?? '');
    $ten_pb = trim($_POST['ten_pb'] ?? '');
    $so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
    $mo_ta = trim($_POST['mo_ta'] ?? '');

    if (empty($ma_pb) || empty($ten_pb)) {
        $error = 'Mã phòng ban và Tên phòng ban không được để trống.';
    } else {
        try {
            if ($id) {
                // Cập nhật
                $stmt = $pdo->prepare("UPDATE phong_ban SET ma_pb=?, ten_pb=?, so_dien_thoai=?, mo_ta=? WHERE id=?");
                $stmt->execute([$ma_pb, $ten_pb, $so_dien_thoai, $mo_ta, $id]);
                $success = 'Cập nhật phòng ban thành công!';
            } else {
                // Thêm mới
                $stmt = $pdo->prepare("INSERT INTO phong_ban (ma_pb, ten_pb, so_dien_thoai, mo_ta) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ma_pb, $ten_pb, $so_dien_thoai, $mo_ta]);
                $success = 'Thêm phòng ban thành công!';
            }
        } catch (PDOException $e) {
            $error = 'Mã phòng ban đã tồn tại hoặc có lỗi xảy ra.';
        }
    }
}

// Lấy dữ liệu để sửa
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM phong_ban WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $edit_data = $stmt->fetch();
}

// ===== TÌM KIẾM + PHÂN TRANG =====
$keyword = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($keyword !== '') {
    $where = "WHERE pb.ma_pb LIKE ? OR pb.ten_pb LIKE ?";
    $params = ['%' . $keyword . '%', '%' . $keyword . '%'];
}
$qs = $keyword !== '' ? '&q=' . urlencode($keyword) : '';

$limit = 5;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Lấy danh sách phòng ban + đếm số nhân viên
$stmt = $pdo->prepare("
    SELECT pb.*, COUNT(nv.id) AS so_nhan_vien
    FROM phong_ban pb
    LEFT JOIN nhan_vien nv ON pb.id = nv.phong_ban_id AND nv.trang_thai = 1
    $where
    GROUP BY pb.id
    ORDER BY pb.id ASC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$ds_phong_ban = $stmt->fetchAll();

// Đếm tổng số phòng ban (theo kết quả tìm kiếm)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM phong_ban pb $where");
$stmt->execute($params);
$total_rows = (int) $stmt->fetchColumn();
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

<div class="row">
    <!-- Danh sách -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Danh sách Phòng ban</h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary"><?= $total_rows ?> phòng ban</span>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#themPhongBanModal">
                        <i class="bi bi-plus-lg"></i> Thêm phòng ban
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Ô tìm kiếm -->
                <div class="p-3 border-bottom">
                    <form method="GET" class="d-flex gap-2" id="formTimKiem" autocomplete="off">
                        <div class="position-relative flex-grow-1">
                            <input type="text" name="q" id="inputTimKiem" class="form-control"
                                placeholder="Tìm theo mã hoặc tên phòng ban..."
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
                                <th>Mã PB</th>
                                <th>Tên phòng ban</th>
                                <th>SĐT</th>
                                <th>Số NV</th>
                                <th width="120">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ds_phong_ban)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <?= $keyword !== '' ? 'Không tìm thấy phòng ban phù hợp' : 'Chưa có phòng ban nào' ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ds_phong_ban as $index => $pb): ?>
                                    <tr>
                                        <td><?= $offset + $index + 1 ?></td>
                                        <td><code><?= htmlspecialchars($pb['ma_pb']) ?></code></td>
                                        <td><?= htmlspecialchars($pb['ten_pb']) ?></td>
                                        <td><?= htmlspecialchars($pb['so_dien_thoai'] ?: '—') ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= $pb['so_nhan_vien'] ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-pb"
                                                data-bs-toggle="modal" data-bs-target="#themPhongBanModal"
                                                data-id="<?= $pb['id'] ?>" data-ma="<?= htmlspecialchars($pb['ma_pb']) ?>"
                                                data-ten="<?= htmlspecialchars($pb['ten_pb']) ?>"
                                                data-sdt="<?= htmlspecialchars($pb['so_dien_thoai'] ?? '') ?>"
                                                data-mota="<?= htmlspecialchars($pb['mo_ta'] ?? '') ?>" title="Sửa">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                                data-bs-target="#xoaPbModal<?= $pb['id'] ?>" title="Xóa">
                                                <i class="bi bi-trash"></i>
                                            </button>

                                            <!-- Modal xác nhận xóa -->
                                            <div class="modal fade" id="xoaPbModal<?= $pb['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Xác nhận xóa</h5>
                                                            <button type="button" class="btn-close"
                                                                data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            Bạn có chắc muốn xóa phòng ban
                                                            <strong><?= htmlspecialchars($pb['ten_pb']) ?></strong>?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Hủy</button>
                                                            <a href="?delete=<?= $pb['id'] ?>" class="btn btn-danger">Xác nhận
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

<!-- Modal Thêm / Sửa Phòng ban -->
<div class="modal fade" id="themPhongBanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPhongBanTitle">Thêm phòng ban mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="pb_id" value="">
                    <div class="mb-3">
                        <label class="form-label">Mã phòng ban <span class="text-danger">*</span></label>
                        <input type="text" name="ma_pb" id="pb_ma" class="form-control" required
                            placeholder="VD: PB_IT">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tên phòng ban <span class="text-danger">*</span></label>
                        <input type="text" name="ten_pb" id="pb_ten" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" name="so_dien_thoai" id="pb_sdt" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea name="mo_ta" id="pb_mota" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary" id="btnLuuPhongBan">Thêm mới</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Nút Sửa
        document.querySelectorAll('.btn-edit-pb').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('modalPhongBanTitle').innerText = 'Sửa phòng ban';
                document.getElementById('btnLuuPhongBan').innerText = 'Cập nhật';
                document.getElementById('pb_id').value = this.getAttribute('data-id');
                document.getElementById('pb_ma').value = this.getAttribute('data-ma');
                document.getElementById('pb_ten').value = this.getAttribute('data-ten');
                document.getElementById('pb_sdt').value = this.getAttribute('data-sdt') || '';
                document.getElementById('pb_mota').value = this.getAttribute('data-mota') || '';
            });
        });

        // Khi bấm nút Thêm mới → reset form
        document.querySelector('[data-bs-target="#themPhongBanModal"].btn-primary')?.addEventListener('click', function () {
            document.getElementById('modalPhongBanTitle').innerText = 'Thêm phòng ban mới';
            document.getElementById('btnLuuPhongBan').innerText = 'Thêm mới';
            document.getElementById('pb_id').value = '';
            document.querySelector('#themPhongBanModal form').reset();
        });
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
                            `<button type="button" class="list-group-item list-group-item-action" data-value="${esc(i.ten_pb)}">
                                <code>${esc(i.ma_pb)}</code> - ${esc(i.ten_pb)}
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
</document_content>