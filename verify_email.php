<?php
require 'config/database.php';

$token = $_GET['token'] ?? null;

if ($token) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // ✅ แก้ไขตรงนี้: เปลี่ยน is_verified เป็น status = 'active'
        $pdo->prepare("UPDATE users SET status = 'active', verification_token = NULL WHERE id = ?")
            ->execute([$user['id']]);
        echo "✅ ยืนยันอีเมลเรียบร้อยแล้ว! <a href='login.php'>เข้าสู่ระบบ</a>";
        exit;
    }
}

echo "❌ ลิงก์ยืนยันไม่ถูกต้องหรือหมดอายุ";
?>
