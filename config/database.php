<?php
// config/database.php

date_default_timezone_set('Asia/Ho_Chi_Minh');
$host = 'localhost';
$dbname = 'quanly_nhansu';
$username = 'root';      // mặc định XAMPP
$password = '';          // mặc định XAMPP để trống
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}

