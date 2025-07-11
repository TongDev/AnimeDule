<?php
session_start();
require 'config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if (!password_verify($password, $user["password"])) {
            $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
        } elseif ($user["status"] !== 'active') {
            $error = "กรุณายืนยันอีเมลก่อนเข้าสู่ระบบ";
        } else {
            $_SESSION["user"] = $user["id"];
            header("Location: dashboard.php");
            exit;
        }
    } else {
        $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - AnimeDule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
    <h2>เข้าสู่ระบบ</h2>
    <?php if (isset($error)) : ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" novalidate>
        <div class="mb-3">
            <label>อีเมล</label>
            <input type="email" name="email" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label>รหัสผ่าน</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-success">เข้าสู่ระบบ</button>
        <a href="register.php" class="btn btn-link">สมัครสมาชิก</a>
    </form>
</body>
</html>
