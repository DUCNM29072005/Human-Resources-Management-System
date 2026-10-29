<?php
session_start();
require_once '../config/database.php';

// Nếu đã đăng nhập rồi thì chuyển hướng luôn
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['vai_tro'] === 'admin') {
        header('Location: /quanly_nhansu/admin/dashboard.php');
    } else {
        header('Location: /quanly_nhansu/employee/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ten_dang_nhap = trim($_POST['ten_dang_nhap'] ?? '');
    $mat_khau      = $_POST['mat_khau'] ?? '';

    if (empty($ten_dang_nhap) || empty($mat_khau)) {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        $stmt = $pdo->prepare("
            SELECT nv.*, pb.ten_pb, cv.ten_cv 
            FROM nhan_vien nv 
            LEFT JOIN phong_ban pb ON nv.phong_ban_id = pb.id
            LEFT JOIN chuc_vu cv ON nv.chuc_vu_id = cv.id
            WHERE nv.ten_dang_nhap = ? AND nv.trang_thai = 1
            LIMIT 1
        ");
        $stmt->execute([$ten_dang_nhap]);
        $user = $stmt->fetch();

        if ($user && password_verify($mat_khau, $user['mat_khau'])) {
            // Đăng nhập thành công → lưu session
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['ma_nv']      = $user['ma_nv'];
            $_SESSION['ho_ten']     = $user['ho_ten'];
            $_SESSION['vai_tro']    = $user['vai_tro'];
            $_SESSION['phong_ban']  = $user['ten_pb'];
            $_SESSION['chuc_vu']    = $user['ten_cv'];

            // Chuyển hướng theo vai trò
            if ($user['vai_tro'] === 'admin') {
                header('Location: /quanly_nhansu/admin/dashboard.php');
            } else {
                header('Location: /quanly_nhansu/employee/dashboard.php');
            }
            exit;
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Quản lý Nhân sự</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* Chỉ giữ lại phần Bootstrap không có sẵn: màu navy + kích thước cố định như bản cũ */
        .bg-navy      { background: linear-gradient(160deg, #1c2640 0%, #131b2e 100%); }
        .login-wrap   { max-width: 1000px; min-height: 600px; }
        .pane-left    { padding: 48px 40px; }
        .pane-right   { padding: 56px 52px; }
        .logo-box     { width: 52px; height: 52px; }
        .ico-box      { width: 36px; height: 36px; }
        .brand-title  { font-size: 1.15rem; }
        .login-title  { font-size: 1.7rem; }
        .field        { height: 52px; }

        @media (min-width: 768px) {
            .pane-left   { flex: 0 0 45%; }
            .pane-right  { flex: 0 0 55%; }
            .brand-title { font-size: 1.35rem; }
        }
        @media (max-width: 767.98px) {
            body         { padding: 0 !important; }
            .login-wrap  { min-height: 100vh; border-radius: 0 !important; }
            .pane-left   { padding: 32px 28px; }
            .pane-right  { padding: 32px 28px 40px; }
        }

        /* Ẩn nút hiện mật khẩu mặc định của Edge để không bị trùng nút của trang */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear { display: none; }
    </style>
</head>
<body class="bg-body-tertiary min-vh-100 d-flex align-items-center justify-content-center p-4">

    <div class="login-wrap w-100 card border-0 shadow-lg rounded-4 overflow-hidden flex-md-row">

        <!-- Bên trái: thương hiệu -->
        <div class="pane-left bg-navy text-white d-flex flex-column justify-content-between">
            <div>
                <div class="logo-box bg-primary rounded-3 d-flex align-items-center justify-content-center fs-4">
                    <i class="bi bi-people-fill"></i>
                </div>
                <h1 class="brand-title fw-bold text-nowrap mt-4 mb-2">Hệ thống Quản lý Nhân sự</h1>
                <p class="text-white-50 mb-0 d-none d-md-block">
                    Quản lý hồ sơ, chấm công, đơn từ và lương thưởng trong một nơi duy nhất, nhanh gọn và minh bạch.
                </p>
            </div>

            <ul class="list-unstyled d-none d-md-grid gap-3 mb-0">
                <li class="d-flex align-items-center gap-3">
                    <span class="ico-box bg-white bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center"><i class="bi bi-person-vcard"></i></span>
                    Hồ sơ nhân viên tập trung
                </li>
                <li class="d-flex align-items-center gap-3">
                    <span class="ico-box bg-white bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center"><i class="bi bi-calendar-check"></i></span>
                    Chấm công &amp; duyệt đơn xin nghỉ
                </li>
                <li class="d-flex align-items-center gap-3">
                    <span class="ico-box bg-white bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center"><i class="bi bi-shield-check"></i></span>
                    Phân quyền bảo mật theo vai trò
                </li>
            </ul>
        </div>

        <!-- Bên phải: form đăng nhập -->
        <div class="pane-right bg-white d-flex flex-column justify-content-center">
            <h2 class="login-title fw-bold mb-1">Chào mừng trở lại 👋</h2>
            <p class="text-secondary mb-4 pb-2">Đăng nhập để tiếp tục vào hệ thống.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="mb-3">
                    <label for="ten_dang_nhap" class="form-label fw-semibold small">Tên đăng nhập</label>
                    <div class="input-group">
                        <span class="field input-group-text bg-body-tertiary text-secondary"><i class="bi bi-person"></i></span>
                        <input type="text" id="ten_dang_nhap" name="ten_dang_nhap" class="field form-control"
                               placeholder="Nhập tên đăng nhập"
                               value="<?= htmlspecialchars($_POST['ten_dang_nhap'] ?? '') ?>"
                               required autofocus autocomplete="username">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="mat_khau" class="form-label fw-semibold small">Mật khẩu</label>
                    <div class="input-group">
                        <span class="field input-group-text bg-body-tertiary text-secondary"><i class="bi bi-lock"></i></span>
                        <input type="password" id="mat_khau" name="mat_khau" class="field form-control"
                               placeholder="Nhập mật khẩu" required autocomplete="current-password">
                        <button type="button" class="field btn btn-outline-secondary" id="togglePass" aria-label="Hiện/ẩn mật khẩu">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="field btn btn-primary w-100 fw-semibold" id="btnLogin">Đăng nhập</button>
            </form>

            <p class="text-center text-secondary small mt-4 mb-0">
                © <?= date('Y') ?> Hệ thống Quản lý Nhân sự
            </p>
        </div>

    </div>

    <script>
        // Hiện / ẩn mật khẩu
        const passInput = document.getElementById('mat_khau');
        const toggleBtn = document.getElementById('togglePass');
        toggleBtn.addEventListener('click', () => {
            const show = passInput.type === 'password';
            passInput.type = show ? 'text' : 'password';
            toggleBtn.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            passInput.focus();
        });

        // Hiệu ứng đang đăng nhập (chống bấm 2 lần)
        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('btnLogin');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang đăng nhập...';
        });
    </script>
</body>
</html>