<?php
session_start();
require 'config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password"])) {
        $_SESSION["user"] = $user["id"];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
    }
}

if ($user && password_verify($password, $user["password"])) {
    if (!$user['is_verified']) {
        $error = "กรุณายืนยันอีเมลก่อนเข้าสู่ระบบ";
    } else {
        $_SESSION["user"] = $user["id"];
        header("Location: dashboard.php");
        exit;
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
    <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST">
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
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
