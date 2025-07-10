<?php
session_start();
require 'config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    try {
        $stmt->execute([$name, $email, $password]);
        $_SESSION["user"] = $pdo->lastInsertId();
        header("Location: dashboard.php");
        exit;
    } catch (PDOException $e) {
        $error = "Email นี้ถูกใช้ไปแล้ว";
    }
}
// หลังจาก login หรือ register สำเร็จ
$_SESSION["user"] = $user['id'];  // หรือ lastInsertId()
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - AnimeDule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
    <h2>สมัครสมาชิก</h2>
    <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST">
        <div class="mb-3">
            <label>ชื่อ</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>รหัสผ่าน</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">สมัคร</button>
        <a href="login.php" class="btn btn-link">มีบัญชีแล้ว? เข้าสู่ระบบ</a>
    </form>
</body>
</html>
