<?php
session_start();
require 'config/database.php';
require 'send_email.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password_raw = $_POST["password"];

    // ตรวจสอบรูปแบบอีเมลเบื้องต้น
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลไม่ถูกต้อง";
    } elseif (strlen($password_raw) < 6) {
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } else {
        $password = password_hash($password_raw, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));

        // ตรวจสอบ email ซ้ำ
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $emailExists = $stmt->fetchColumn();

        if ($emailExists > 0) {
            $error = "อีเมลนี้ถูกใช้ไปแล้ว กรุณาใช้อีเมลอื่น";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, verification_token, status)
                    VALUES (?, ?, ?, ?, 'inactive')
                ");
                $stmt->execute([$name, $email, $password, $token]);

                $verify_link = "http://localhost/AnimeDule/verify_email.php?token=$token";

                if (sendVerificationEmail($email, $verify_link)) {
                    header("Location: check_email.php");
                    exit;
                } else {
                    $error = "❌ ไม่สามารถส่งอีเมลยืนยันได้ กรุณาลองใหม่ภายหลัง";
                }
            } catch (PDOException $e) {
                $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>สมัครสมาชิก - AnimeDule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="container py-5">
    <h2>สมัครสมาชิก</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <div class="mb-3">
            <label for="name">ชื่อ</label>
            <input id="name" type="text" name="name" class="form-control" required value="<?= isset($name) ? htmlspecialchars($name) : '' ?>">
        </div>
        <div class="mb-3">
            <label for="email">อีเมล</label>
            <input id="email" type="email" name="email" class="form-control" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
        </div>
        <div class="mb-3">
            <label for="password">รหัสผ่าน</label>
            <input id="password" type="password" name="password" class="form-control" required minlength="6" />
        </div>
        <button type="submit" class="btn btn-primary">สมัครสมาชิก</button>
        <a href="login.php" class="btn btn-link">มีบัญชีแล้ว? เข้าสู่ระบบ</a>
    </form>
</body>
</html>
