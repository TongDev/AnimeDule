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
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ - AnimeDule</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />

</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow border-0">
                    <div class="card-body p-4">
                        <h3 class="mb-4 text-center"><i class="bi bi-person-circle me-2"></i>เข้าสู่ระบบ</h3>

                        <?php if (isset($_GET['registered']) && $_GET['registered'] == 1): ?>
                            <div class="alert alert-success">สมัครสมาชิกเรียบร้อยแล้ว! กรุณาเข้าสู่ระบบ</div>
                        <?php endif; ?>

                        <?php if (isset($error)) : ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">อีเมล</label>
                                <input type="email" id="email" name="email" class="form-control" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">รหัสผ่าน</label>
                                <input type="password" id="password" name="password" class="form-control" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ
                                </button>
                                <a href="register.php" class="btn btn-outline-secondary">ยังไม่มีบัญชี? สมัครสมาชิก</a>
                            </div>
                        </form>
                    </div>
                </div>
                <p class="text-center text-muted mt-3">&copy; <?= date("Y") ?> AnimeDule</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
