<?php
session_start();
header('Content-Type: application/json');
require '../config/database.php'; // ปรับ path ให้ถูกต้องตามโครงสร้างโฟลเดอร์ของคุณ

if (!isset($_SESSION['user'])) {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาเข้าสู่ระบบก่อนจึงจะสามารถเพิ่มรายการโปรดได้'
    ]);
    exit;
}

$userId = $_SESSION['user'];
$animeId = isset($_POST['anime_id']) ? (int)$_POST['anime_id'] : 0;

if (!$animeId) {
    echo json_encode([
        'success' => false,
        'message' => 'anime_id ไม่ถูกต้อง'
    ]);
    exit;
}

try {
    // ตรวจสอบว่ามี favorite อยู่แล้วหรือไม่
    $stmtCheck = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND anime_id = ?");
    $stmtCheck->execute([$userId, $animeId]);
    $favorite = $stmtCheck->fetch();

    if ($favorite) {
        // ลบ favorite
        $stmtDel = $pdo->prepare("DELETE FROM favorites WHERE id = ?");
        $stmtDel->execute([$favorite['id']]);
        echo json_encode([
            'success' => true,
            'is_favorite' => false,
            'message' => 'ลบออกจากรายการโปรดแล้ว'
        ]);
    } else {
        // เพิ่ม favorite
        $stmtAdd = $pdo->prepare("INSERT INTO favorites (user_id, anime_id, created_at) VALUES (?, ?, NOW())");
        $stmtAdd->execute([$userId, $animeId]);
        echo json_encode([
            'success' => true,
            'is_favorite' => true,
            'message' => 'เพิ่มในรายการโปรดแล้ว'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดกับฐานข้อมูล: ' . $e->getMessage()
    ]);
}
