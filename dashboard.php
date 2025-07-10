<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - AnimeDule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
    <h2>ยินดีต้อนรับสู่ AnimeDule 🎉</h2>
    <p>คุณเข้าสู่ระบบแล้ว</p>
    <a href="logout.php" class="btn btn-danger">ออกจากระบบ</a>
</body>
</html>
