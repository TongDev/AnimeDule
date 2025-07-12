<?php
session_start();
require 'config/database.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password_raw = $_POST["password"];

    // ตรวจสอบรูปแบบอีเมล
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลไม่ถูกต้อง";
    } elseif (strlen($password_raw) < 6) {
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } else {
        $password = password_hash($password_raw, PASSWORD_DEFAULT);

        // ตรวจสอบอีเมลซ้ำ
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $emailExists = $stmt->fetchColumn();

        if ($emailExists > 0) {
            $error = "อีเมลนี้ถูกใช้ไปแล้ว กรุณาใช้อีเมลอื่น";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$name, $email, $password]);

                // ส่งไปหน้า login พร้อมข้อความสำเร็จ
                header("Location: login.php?registered=1");
                exit;

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
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow border-0">
                    <div class="card-body p-4">
                        <h3 class="mb-4 text-center"><i class="bi bi-person-plus me-2"></i>สมัครสมาชิก</h3>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST" novalidate>
                            <div class="mb-3">
                                <label for="name" class="form-label">ชื่อ</label>
                                <input id="name" type="text" name="name" class="form-control" required value="<?= isset($name) ? htmlspecialchars($name) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">อีเมล</label>
                                <input id="email" type="email" name="email" class="form-control" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">รหัสผ่าน</label>
                                <input id="password" type="password" name="password" class="form-control" required minlength="6" />
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> สมัครสมาชิก
                                </button>
                                <a href="login.php" class="btn btn-outline-secondary">มีบัญชีแล้ว? เข้าสู่ระบบ</a>
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
