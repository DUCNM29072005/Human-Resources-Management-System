<?php
require_once '../includes/auth.php';
require_admin();
$page_title = 'Quản lý Nhân viên';
require_once '../includes/header.php';
require_once '../config/database.php';

$success = $error = '';

// ========== XÓA (chuyển trạng thái nghỉ việc) ==========
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    // Không cho xóa chính mình
    if ($id == $_SESSION['user_id']) {
        $error = 'Bạn không thể xóa chính tài khoản của mình!';
    } else {
        $stmt = $pdo->prepare("UPDATE nhan_vien SET trang_thai = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Đã chuyển nhân viên sang trạng thái Nghỉ việc.';
    }
}

// ========== KHÔI PHỤC ==========
if (isset($_GET['restore'])) {
    $id = (int) $_GET['restore'];
    $stmt = $pdo->prepare("UPDATE nhan_vien SET trang_thai = 1 WHERE id = ?");
    $stmt->execute([$id]);
    $success = 'Đã khôi phục nhân viên thành công!';
}

// ========== THÊM / SỬA ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $ma_nv = trim($_POST['ma_nv'] ?? '');
    $ho_ten = trim($_POST['ho_ten'] ?? '');
    $gioi_tinh = (int) ($_POST['gioi_tinh'] ?? 1);
    $ngay_sinh = $_POST['ngay_sinh'] ?: null;
    $email = trim($_POST['email'] ?? '');
    $so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
    $dia_chi = trim($_POST['dia_chi'] ?? '');
    $luong_co_ban = (float) ($_POST['luong_co_ban'] ?? 0);
    $phong_ban_id = $_POST['phong_ban_id'] ?: null;
    $chuc_vu_id = $_POST['chuc_vu_id'] ?: null;
    $ten_dang_nhap = trim($_POST['ten_dang_nhap'] ?? '');
    $mat_khau = $_POST['mat_khau'] ?? '';
    $vai_tro = $_POST['vai_tro'] ?? 'nhan_vien';
    $trang_thai = (int) ($_POST['trang_thai'] ?? 1);

    // Validate
    if (empty($ma_nv) || empty($ho_ten) || empty($ten_dang_nhap)) {
        $error = 'Mã NV, Họ tên và Tên đăng nhập không được để trống.';
    } elseif (!$id && empty($mat_khau)) {
        $error = 'Vui lòng nhập mật khẩu khi thêm nhân viên mới.';
    } else {
        try {
            if ($id) {
                // ===== CẬP NHẬT =====
                if (!empty($mat_khau)) {
                    // Có nhập mật khẩu mới → cập nhật cả mật khẩu
                    $hash = password_hash($mat_khau, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE nhan_vien SET 
                            ma_nv=?, ho_ten=?, gioi_tinh=?, ngay_sinh=?, email=?, so_dien_thoai=?,
                            dia_chi=?, luong_co_ban=?, phong_ban_id=?, chuc_vu_id=?,
                            ten_dang_nhap=?, mat_khau=?, vai_tro=?, trang_thai=?
                        WHERE id=?
                    ");
                    $stmt->execute([
                        $ma_nv,
                        $ho_ten,
                        $gioi_tinh,
                        $ngay_sinh,
                        $email,
                        $so_dien_thoai,
                        $dia_chi,
                        $luong_co_ban,
                        $phong_ban_id,
                        $chuc_vu_id,
                        $ten_dang_nhap,
                        $hash,
                        $vai_tro,
                        $trang_thai,
                        $id
                    ]);
                } else {
                    // Không đổi mật khẩu
                    $stmt = $pdo->prepare("
                        UPDATE nhan_vien SET 
                            ma_nv=?, ho_ten=?, gioi_tinh=?, ngay_sinh=?, email=?, so_dien_thoai=?,
                            dia_chi=?, luong_co_ban=?, phong_ban_id=?, chuc_vu_id=?,
                            ten_dang_nhap=?, vai_tro=?, trang_thai=?
                        WHERE id=?
                    ");
                    $stmt->execute([
                        $ma_nv,
                        $ho_ten,
                        $gioi_tinh,
                        $ngay_sinh,
                        $email,
                        $so_dien_thoai,
                        $dia_chi,
                        $luong_co_ban,
                        $phong_ban_id,
                        $chuc_vu_id,
                        $ten_dang_nhap,
                        $vai_tro,
                        $trang_thai,
                        $id
                    ]);
                }
                $success = 'Cập nhật nhân viên thành công!';
            } else {
                // ===== THÊM MỚI =====
                $hash = password_hash($mat_khau, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO nhan_vien (
                        ma_nv, ho_ten, gioi_tinh, ngay_sinh, email, so_dien_thoai,
                        dia_chi, luong_co_ban, phong_ban_id, chuc_vu_id,
                        ten_dang_nhap, mat_khau, vai_tro, trang_thai
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $ma_nv,
                    $ho_ten,
                    $gioi_tinh,
                    $ngay_sinh,
                    $email,
                    $so_dien_thoai,
                    $dia_chi,
                    $luong_co_ban,
                    $phong_ban_id,
                    $chuc_vu_id,
                    $ten_dang_nhap,
                    $hash,
                    $vai_tro,
                    $trang_thai
                ]);
                $success = 'Thêm nhân viên thành công!';
            }
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'uk_ma_nv') || str_contains($e->getMessage(), 'Duplicate')) {
                $error = 'Mã nhân viên hoặc Tên đăng nhập đã tồn tại.';
            } else {
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
}

// Lấy dữ liệu để sửa
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM nhan_vien WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $edit_data = $stmt->fetch();
}

// Lấy danh sách phòng ban & chức vụ để đưa vào select
$ds_phong_ban = $pdo->query("SELECT id, ten_pb FROM phong_ban ORDER BY ten_pb")->fetchAll();
$ds_chuc_vu = $pdo->query("SELECT id, ten_cv FROM chuc_vu ORDER BY ten_cv")->fetchAll();

// Bộ lọc
$filter_trang_thai = $_GET['trang_thai'] ?? '1'; // mặc định chỉ hiện đang làm
$filter_phong_ban = $_GET['phong_ban'] ?? '';
$search = trim($_GET['search'] ?? '');

// Xây dựng câu query
$sql = "
    SELECT nv.*, pb.ten_pb, cv.ten_cv 
    FROM nhan_vien nv
    LEFT JOIN phong_ban pb ON nv.phong_ban_id = pb.id
    LEFT JOIN chuc_vu cv ON nv.chuc_vu_id = cv.id
    WHERE 1=1
";

// ===== PHÂN TRANG =====
$limit = 5;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$params = [];

if ($filter_trang_thai !== 'all') {
    $sql .= " AND nv.trang_thai = ?";
    $params[] = $filter_trang_thai;
}
if ($filter_phong_ban) {
    $sql .= " AND nv.phong_ban_id = ?";
    $params[] = $filter_phong_ban;
}
if ($search) {
    $sql .= " AND (nv.ho_ten LIKE ? OR nv.ma_nv LIKE ? OR nv.ten_dang_nhap LIKE ? OR nv.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY nv.trang_thai DESC, nv.id DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ds_nhan_vien = $stmt->fetchAll();
// Đếm tổng số bản ghi
$count_sql = "
    SELECT COUNT(*) 
    FROM nhan_vien nv
    LEFT JOIN phong_ban pb ON nv.phong_ban_id = pb.id
    LEFT JOIN chuc_vu cv ON nv.chuc_vu_id = cv.id
    WHERE 1=1
";
$count_params = [];
if ($filter_trang_thai !== 'all') {
    $count_sql .= " AND nv.trang_thai = ?";
    $count_params[] = $filter_trang_thai;
}
if ($filter_phong_ban) {
    $count_sql .= " AND nv.phong_ban_id = ?";
    $count_params[] = $filter_phong_ban;
}
if ($search) {
    $count_sql .= " AND (nv.ho_ten LIKE ? OR nv.ma_nv LIKE ? OR nv.ten_dang_nhap LIKE ? OR nv.email LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
}
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($count_params);
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

<div class="row">
    <!-- ========== DANH SÁCH ========== -->
    <div class="col-12">
        <!-- Bộ lọc -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Tìm tên, mã NV, email..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="phong_ban" class="form-select">
                            <option value="">Tất cả phòng ban</option>
                            <?php foreach ($ds_phong_ban as $pb): ?>
                                <option value="<?= $pb['id'] ?>" <?= $filter_phong_ban == $pb['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pb['ten_pb']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="trang_thai" class="form-select">
                            <option value="1" <?= $filter_trang_thai === '1' ? 'selected' : '' ?>>Đang làm</option>
                            <option value="0" <?= $filter_trang_thai === '0' ? 'selected' : '' ?>>Nghỉ việc</option>
                            <option value="all" <?= $filter_trang_thai === 'all' ? 'selected' : '' ?>>Tất cả</option>
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
                <h5 class="mb-0">Danh sách Nhân viên</h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary"><?= $total_rows ?> người</span>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#themNhanVienModal">
                        <i class="bi bi-plus-lg"></i> Thêm nhân viên
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Mã NV</th>
                                <th>Họ tên</th>
                                <th>Phòng ban</th>
                                <th>Chức vụ</th>
                                <th>Vai trò</th>
                                <th>Trạng thái</th>
                                <th width="100">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ds_nhan_vien)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Không tìm thấy nhân viên nào</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ds_nhan_vien as $nv): ?>
                                    <tr class="<?= $nv['trang_thai'] == 0 ? 'table-secondary' : '' ?>">
                                        <td><code><?= htmlspecialchars($nv['ma_nv']) ?></code></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($nv['ho_ten']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($nv['email'] ?? '') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($nv['ten_pb'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($nv['ten_cv'] ?? '—') ?></td>
                                        <td>
                                            <?php if ($nv['vai_tro'] === 'admin'): ?>
                                                <span class="badge bg-danger">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Nhân viên</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($nv['trang_thai'] == 1): ?>
                                                <span class="badge bg-success">Đang làm</span>
                                            <?php else: ?>
                                                <span class="badge bg-dark">Nghỉ việc</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-nv"
                                                data-bs-toggle="modal" data-bs-target="#suaNhanVienModal"
                                                data-id="<?= $nv['id'] ?>" data-ma="<?= htmlspecialchars($nv['ma_nv']) ?>"
                                                data-hoten="<?= htmlspecialchars($nv['ho_ten']) ?>"
                                                data-gioitinh="<?= $nv['gioi_tinh'] ?>"
                                                data-ngaysinh="<?= $nv['ngay_sinh'] ?? '' ?>"
                                                data-email="<?= htmlspecialchars($nv['email'] ?? '') ?>"
                                                data-sdt="<?= htmlspecialchars($nv['so_dien_thoai'] ?? '') ?>"
                                                data-diachi="<?= htmlspecialchars($nv['dia_chi'] ?? '') ?>"
                                                data-luong="<?= $nv['luong_co_ban'] ?>"
                                                data-pb="<?= $nv['phong_ban_id'] ?? '' ?>"
                                                data-cv="<?= $nv['chuc_vu_id'] ?? '' ?>"
                                                data-username="<?= htmlspecialchars($nv['ten_dang_nhap']) ?>"
                                                data-vaitro="<?= $nv['vai_tro'] ?>" data-trangthai="<?= $nv['trang_thai'] ?>"
                                                title="Sửa">
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <?php if ($nv['trang_thai'] == 1): ?>
                                                <!-- Nút mở modal xác nhận Nghỉ việc -->
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                                    data-bs-target="#nghiViecModal<?= $nv['id'] ?>" title="Nghỉ việc">
                                                    <i class="bi bi-person-x"></i>
                                                </button>

                                                <!-- Modal xác nhận -->
                                                <div class="modal fade" id="nghiViecModal<?= $nv['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Xác nhận nghỉ việc</h5>
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body text-start">
                                                                <p>Bạn có chắc muốn chuyển nhân viên sau sang trạng thái <strong
                                                                        class="text-danger">Nghỉ việc</strong>?</p>
                                                                <ul class="mb-0">
                                                                    <li><strong>Mã NV:</strong>
                                                                        <?= htmlspecialchars($nv['ma_nv']) ?>
                                                                    </li>
                                                                    <li><strong>Họ tên:</strong>
                                                                        <?= htmlspecialchars($nv['ho_ten']) ?>
                                                                    </li>
                                                                    <li><strong>Phòng ban:</strong>
                                                                        <?= htmlspecialchars($nv['ten_pb'] ?? '—') ?>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">Hủy</button>
                                                                <a href="?delete=<?= $nv['id'] ?>" class="btn btn-danger">Xác nhận
                                                                    nghỉ việc</a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <a href="?restore=<?= $nv['id'] ?>" class="btn btn-sm btn-outline-success"
                                                    title="Khôi phục">
                                                    <i class="bi bi-person-check"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                    <nav class="p-3">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link"
                                    href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&phong_ban=<?= $filter_phong_ban ?>&trang_thai=<?= $filter_trang_thai ?>">Trước</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link"
                                        href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&phong_ban=<?= $filter_phong_ban ?>&trang_thai=<?= $filter_trang_thai ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link"
                                    href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&phong_ban=<?= $filter_phong_ban ?>&trang_thai=<?= $filter_trang_thai ?>">Sau</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!-- ========== MODAL THÊM NHÂN VIÊN (2 BƯỚC) ========== -->
<div class="modal fade" id="themNhanVienModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formThemNhanVien">
                <div class="modal-header">
                    <h5 class="modal-title">Thêm nhân viên mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Bước 1: Thông tin cá nhân -->
                    <div id="step1">
                        <h6 class="mb-3 text-primary">Bước 1/2: Thông tin cá nhân</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mã NV <span class="text-danger">*</span></label>
                                <input type="text" name="ma_nv" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Giới tính</label>
                                <select name="gioi_tinh" class="form-select">
                                    <option value="1">Nam</option>
                                    <option value="0">Nữ</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" name="ho_ten" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ngày sinh</label>
                                <input type="date" name="ngay_sinh" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Số điện thoại</label>
                                <input type="text" name="so_dien_thoai" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lương cơ bản (VNĐ)</label>
                                <input type="number" name="luong_co_ban" class="form-control" min="0" step="1000"
                                    value="0">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Địa chỉ</label>
                                <input type="text" name="dia_chi" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phòng ban</label>
                                <select name="phong_ban_id" class="form-select">
                                    <option value="">-- Chọn phòng ban --</option>
                                    <?php foreach ($ds_phong_ban as $pb): ?>
                                        <option value="<?= $pb['id'] ?>"><?= htmlspecialchars($pb['ten_pb']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Chức vụ</label>
                                <select name="chuc_vu_id" class="form-select">
                                    <option value="">-- Chọn chức vụ --</option>
                                    <?php foreach ($ds_chuc_vu as $cv): ?>
                                        <option value="<?= $cv['id'] ?>"><?= htmlspecialchars($cv['ten_cv']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Bước 2: Tài khoản -->
                    <div id="step2" style="display:none;">
                        <h6 class="mb-3 text-primary">Bước 2/2: Thông tin tài khoản</h6>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                                <input type="text" name="ten_dang_nhap" class="form-control" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                                <input type="password" name="mat_khau" class="form-control" required minlength="6">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vai trò</label>
                                <select name="vai_tro" class="form-select">
                                    <option value="nhan_vien">Nhân viên</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trạng thái</label>
                                <select name="trang_thai" class="form-select">
                                    <option value="1">Đang làm</option>
                                    <option value="0">Nghỉ việc</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-outline-primary" id="btnQuayLai" style="display:none;">Quay
                        lại</button>
                    <button type="button" class="btn btn-primary" id="btnTiepTheo">Tiếp theo</button>
                    <button type="submit" class="btn btn-success" id="btnXacNhan" style="display:none;">Xác nhận
                        thêm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('btnTiepTheo').addEventListener('click', function () {
        // Kiểm tra bước 1
        const maNv = document.querySelector('#step1 input[name="ma_nv"]').value.trim();
        const hoTen = document.querySelector('#step1 input[name="ho_ten"]').value.trim();
        if (!maNv || !hoTen) {
            alert('Vui lòng nhập Mã NV và Họ tên');
            return;
        }
        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
        document.getElementById('btnTiepTheo').style.display = 'none';
        document.getElementById('btnQuayLai').style.display = 'inline-block';
        document.getElementById('btnXacNhan').style.display = 'inline-block';
    });

    document.getElementById('btnQuayLai').addEventListener('click', function () {
        document.getElementById('step1').style.display = 'block';
        document.getElementById('step2').style.display = 'none';
        document.getElementById('btnTiepTheo').style.display = 'inline-block';
        document.getElementById('btnQuayLai').style.display = 'none';
        document.getElementById('btnXacNhan').style.display = 'none';
    });

    // Reset khi đóng modal
    document.getElementById('themNhanVienModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formThemNhanVien').reset();
        document.getElementById('step1').style.display = 'block';
        document.getElementById('step2').style.display = 'none';
        document.getElementById('btnTiepTheo').style.display = 'inline-block';
        document.getElementById('btnQuayLai').style.display = 'none';
        document.getElementById('btnXacNhan').style.display = 'none';
    });
</script>

<!-- Modal Sửa Nhân viên -->
<div class="modal fade" id="suaNhanVienModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Sửa nhân viên</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mã NV <span class="text-danger">*</span></label>
                            <input type="text" name="ma_nv" id="edit_ma" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Giới tính</label>
                            <select name="gioi_tinh" id="edit_gioitinh" class="form-select">
                                <option value="1">Nam</option>
                                <option value="0">Nữ</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" name="ho_ten" id="edit_hoten" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ngày sinh</label>
                            <input type="date" name="ngay_sinh" id="edit_ngaysinh" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="so_dien_thoai" id="edit_sdt" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Lương cơ bản</label>
                            <input type="number" name="luong_co_ban" id="edit_luong" class="form-control" min="0" step="1000">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Địa chỉ</label>
                            <input type="text" name="dia_chi" id="edit_diachi" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phòng ban</label>
                            <select name="phong_ban_id" id="edit_pb" class="form-select">
                                <option value="">-- Chọn phòng ban --</option>
                                <?php foreach ($ds_phong_ban as $pb): ?>
                                    <option value="<?= $pb['id'] ?>"><?= htmlspecialchars($pb['ten_pb']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Chức vụ</label>
                            <select name="chuc_vu_id" id="edit_cv" class="form-select">
                                <option value="">-- Chọn chức vụ --</option>
                                <?php foreach ($ds_chuc_vu as $cv): ?>
                                    <option value="<?= $cv['id'] ?>"><?= htmlspecialchars($cv['ten_cv']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                            <input type="text" name="ten_dang_nhap" id="edit_username" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mật khẩu mới <small class="text-muted">(để trống nếu không đổi)</small></label>
                            <input type="password" name="mat_khau" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vai trò</label>
                            <select name="vai_tro" id="edit_vaitro" class="form-select">
                                <option value="nhan_vien">Nhân viên</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select name="trang_thai" id="edit_trangthai" class="form-select">
                                <option value="1">Đang làm</option>
                                <option value="0">Nghỉ việc</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary">Cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-edit-nv').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_ma').value = this.getAttribute('data-ma');
            document.getElementById('edit_hoten').value = this.getAttribute('data-hoten');
            document.getElementById('edit_gioitinh').value = this.getAttribute('data-gioitinh');
            document.getElementById('edit_ngaysinh').value = this.getAttribute('data-ngaysinh') || '';
            document.getElementById('edit_email').value = this.getAttribute('data-email') || '';
            document.getElementById('edit_sdt').value = this.getAttribute('data-sdt') || '';
            document.getElementById('edit_diachi').value = this.getAttribute('data-diachi') || '';
            document.getElementById('edit_luong').value = this.getAttribute('data-luong') || 0;
            document.getElementById('edit_pb').value = this.getAttribute('data-pb') || '';
            document.getElementById('edit_cv').value = this.getAttribute('data-cv') || '';
            document.getElementById('edit_username').value = this.getAttribute('data-username');
            document.getElementById('edit_vaitro').value = this.getAttribute('data-vaitro');
            document.getElementById('edit_trangthai').value = this.getAttribute('data-trangthai');
        });
    });
});
</script>
<?php require_once '../includes/footer.php'; ?>