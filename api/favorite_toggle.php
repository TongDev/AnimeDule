<?php
session_start();
header('Content-Type: application/json');
require '../config/database.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$user_id = $_SESSION['user'];
$data = json_decode(file_get_contents("php://input"), true);
$anime_id = (int)($data['anime_id'] ?? 0);

if (!$anime_id) {
    echo json_encode(['error' => 'Anime ไม่ถูกต้อง']);
    exit;
}

// หาว่าเคย favorite ไปยัง
$stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND anime_id = ?");
$stmt->execute([$user_id, $anime_id]);
$fav = $stmt->fetch();

if ($fav) {
    // ลบ
    $pdo->prepare("DELETE FROM favorites WHERE id = ?")->execute([$fav['id']]);
    echo json_encode(['status' => 'removed']);
} else {
    // ดึง platform_id แรกของ anime เพื่อผูก (หรือกำหนด default)
    $platform_stmt = $pdo->prepare("SELECT platform_id FROM anime_platforms WHERE anime_id = ? LIMIT 1");
    $platform_stmt->execute([$anime_id]);
    $platform_id = $platform_stmt->fetchColumn();

    if (!$platform_id) {
        echo json_encode(['error' => 'Anime นี้ไม่มีข้อมูลแพลตฟอร์ม']);
        exit;
    }

    $insert = $pdo->prepare("INSERT INTO favorites (user_id, anime_id, platform_id) VALUES (?, ?, ?)");
    $insert->execute([$user_id, $anime_id, $platform_id]);

    echo json_encode(['status' => 'added']);
}
