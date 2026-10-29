<?php
$page_title = 'Đổi mật khẩu';
require_once '../includes/header.php';
require_login();
require_once '../config/database.php';

$success = $error = '';
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mat_khau_cu      = $_POST['mat_khau_cu'] ?? '';
    $mat_khau_moi     = $_POST['mat_khau_moi'] ?? '';
    $mat_khau_xac_nhan = $_POST['mat_khau_xac_nhan'] ?? '';

    if (empty($mat_khau_cu) || empty($mat_khau_moi) || empty($mat_khau_xac_nhan)) {
        $error = 'Vui lòng điền đầy đủ thông tin.';
    } elseif ($mat_khau_moi !== $mat_khau_xac_nhan) {
        $error = 'Mật khẩu mới và xác nhận không khớp.';
    } elseif (strlen($mat_khau_moi) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    } else {
        // Kiểm tra mật khẩu cũ
        $stmt = $pdo->prepare("SELECT mat_khau FROM nhan_vien WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($mat_khau_cu, $user['mat_khau'])) {
            $hash = password_hash($mat_khau_moi, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE nhan_vien SET mat_khau = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            $success = 'Đổi mật khẩu thành công!';
        } else {
            $error = 'Mật khẩu cũ không đúng.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Đổi mật khẩu</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu hiện tại</label>
                        <input type="password" name="mat_khau_cu" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu mới</label>
                        <input type="password" name="mat_khau_moi" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Xác nhận mật khẩu mới</label>
                        <input type="password" name="mat_khau_xac_nhan" class="form-control" required minlength="6">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
                        <a href="javascript:history.back()" class="btn btn-outline-secondary">Quay lại</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>