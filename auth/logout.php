<?php
session_start();
session_unset();
session_destroy();
header('Location: /quanly_nhansu/auth/login.php');
exit;