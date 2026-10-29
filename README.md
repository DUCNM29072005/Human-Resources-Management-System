# Hệ thống Quản lý Nhân sự (QL Nhân sự)

Hệ thống quản lý nhân sự nội bộ được xây dựng bằng **PHP thuần** (không framework), **Bootstrap 5** và **MySQL**.

## Công nghệ sử dụng

- **Frontend:** HTML, CSS, Bootstrap 5, Bootstrap Icons, Chart.js
- **Backend:** PHP 8+ (PDO, Session)
- **Database:** MySQL / MariaDB
- **Môi trường chạy:** XAMPP (Apache + MySQL)

## Chức năng chính

### Dành cho Admin
- Dashboard thống kê (tổng nhân viên, phòng ban, chấm công, đơn xin nghỉ)
- Quản lý Phòng ban (Thêm / Sửa / Xóa)
- Quản lý Chức vụ (Thêm / Sửa / Xóa)
- Quản lý Nhân viên (Thêm / Sửa / Nghỉ việc / Khôi phục)
- Quản lý Chấm công toàn công ty
- Cấu hình giờ vào / giờ ra chuẩn
- Duyệt / Từ chối Đơn xin nghỉ (có nhập lý do từ chối)
- Tính lương theo tháng (có phiếu lương chi tiết từng nhân viên)

### Dành cho Nhân viên
- Dashboard cá nhân (ngày công, giờ làm việc, đơn nghỉ, lương ước tính)
- Chấm công Check-in / Check-out hàng ngày
- Tạo / Sửa / Xóa Đơn xin nghỉ (chỉ khi đang chờ duyệt)
- Xem phiếu lương theo tháng
- Hồ sơ cá nhân
- Đổi mật khẩu

## Cài đặt

### 1. Yêu cầu
- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8+)
- Trình duyệt hiện đại (Chrome, Edge, Firefox...)

### 2. Cài đặt source code
1. Clone repository hoặc tải source về.
2. Copy thư mục project vào:
   ```
   C:\xampp\htdocs\quanly_nhansu
   ```
3. Mở **XAMPP Control Panel** → Start **Apache** và **MySQL**.

### 3. Tạo database
1. Truy cập phpMyAdmin: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Tạo database tên: `quanly_nhansu`  
   Collation: `utf8mb4_unicode_ci`
3. Import file SQL trong thư mục `sql/` (nếu có), hoặc chạy các câu lệnh `CREATE TABLE` theo schema dự án.

### 4. Cấu hình kết nối database
Mở file `config/database.php` và chỉnh thông tin nếu cần:

```php
$host = 'localhost';
$dbname = 'quanly_nhansu';
$username = 'root';
$password = ''; // Mặc định XAMPP để trống
```

### 5. Chạy hệ thống
Truy cập đường dẫn:

```
http://localhost/quanly_nhansu/auth/login.php
```

## Tài khoản mẫu

| Vai trò   | Tên đăng nhập | Mật khẩu  |
|-----------|---------------|-----------|
| Admin     | `admin`       | `admin123`|

> **Lưu ý:** Hệ thống không có chức năng đăng ký công khai. Chỉ Admin mới được tạo tài khoản nhân viên.

## Cấu trúc thư mục

```
quanly_nhansu/
├── admin/                  # Module Admin
│   ├── dashboard.php
│   ├── nhan-vien.php
│   ├── phong-ban.php
│   ├── chuc-vu.php
│   ├── cham-cong.php
│   ├── don-xin-nghi.php
│   ├── luong.php
│   └── profile.php
├── employee/               # Module Nhân viên
│   ├── dashboard.php
│   ├── cham-cong.php
│   ├── don-xin-nghi.php
│   ├── luong.php
│   └── profile.php
├── auth/
│   ├── login.php
│   ├── logout.php
│   └── change-password.php
├── config/
│   └── database.php
├── includes/
│   ├── auth.php            # Kiểm tra đăng nhập & phân quyền
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
├── sql/                    # Schema & dữ liệu mẫu
└── README.md
```

## Công thức tính lương

```
Tổng ngày công  = Số ngày đi làm thực tế + Số ngày nghỉ phép được duyệt
Tiền phạt       = Số lần đi muộn × 50.000 VNĐ
Lương thực lĩnh = (Lương cơ bản / 26 × Tổng ngày công) + Phụ cấp chức vụ − Tiền phạt
```

### Quy tắc chấm công
- Giờ vào / giờ ra chuẩn do Admin cấu hình.
- Check-in sau giờ vào chuẩn → **Đi muộn**
- Check-out trước giờ ra chuẩn → **Về sớm**

## Phân quyền truy cập

| Hàm                  | Mô tả                                      |
|----------------------|--------------------------------------------|
| `require_login()`    | Bắt buộc đã đăng nhập                      |
| `require_admin()`    | Chỉ Admin được vào                         |
| `require_employee()` | Chỉ Nhân viên được vào                     |

Khi truy cập sai quyền, hệ thống chuyển về dashboard tương ứng và hiển thị thông báo cảnh báo.

## Bảo mật

- Mật khẩu được mã hóa bằng `password_hash()` và kiểm tra bằng `password_verify()`.
- Sử dụng **PDO Prepared Statements** để chống SQL Injection.
- Phân quyền theo session (`vai_tro`: `admin` / `nhan_vien`).
- Timezone: `Asia/Ho_Chi_Minh`.

## Ghi chú phát triển

- Không sử dụng framework (Laravel, CodeIgniter...).
- Giao diện dùng Bootstrap 5 + modal cho form Thêm/Sửa/Xóa.
- Biểu đồ thống kê dùng Chart.js (CDN).
- Hỗ trợ phân trang trên các trang danh sách.

## License

Dự án phục vụ mục đích học tập / đồ án.

---

**Tác giả:** Đồ án Quản lý Nhân sự – PHP + Bootstrap 5 + MySQL
