<?php
session_start();
require 'config/database.php';
require 'send_email.php'; // <-- รวมฟังก์ชันส่งอีเมลด้วย PHPMailer

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(32));

    // ตรวจสอบว่า email ถูกใช้แล้วหรือยัง
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $emailExists = $stmt->fetchColumn();

    if ($emailExists > 0) {
        $error = "อีเมลนี้ถูกใช้ไปแล้ว กรุณาใช้อีเมลอื่น";
    } else {
        try {
            // บันทึกข้อมูลผู้ใช้ใหม่
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, verification_token, status)
                VALUES (?, ?, ?, ?, 'inactive')
            ");
            $stmt->execute([$name, $email, $password, $token]);

            $user_id = $pdo->lastInsertId();
            $_SESSION["user"] = $user_id;

            $verify_link = "http://localhost/AnimeDule/verify_email.php?token=$token";

            // ส่งอีเมลยืนยัน
            if (sendVerificationEmail($email, $verify_link)) {
                header("Location: check_email.php");
                exit;
            } else {
                $error = "❌ ไม่สามารถส่งอีเมลยืนยันได้ กรุณาลองใหม่ภายหลัง";
            }
        } catch (PDOException $e) {
            $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>สมัครสมาชิก - AnimeDule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
    <h2>สมัครสมาชิก</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label>ชื่อ</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>อีเมล</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>รหัสผ่าน</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">สมัครสมาชิก</button>
        <a href="login.php" class="btn btn-link">มีบัญชีแล้ว? เข้าสู่ระบบ</a>
    </form>
</body>
</html>
