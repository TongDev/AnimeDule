<?php
session_start();
require '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'ต้องเข้าสู่ระบบก่อน']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$anime_id = isset($input['anime_id']) ? (int)$input['anime_id'] : 0;
$user_id = (int)$_SESSION['user'];

if ($anime_id <= 0) {
    echo json_encode(['error' => 'ข้อมูลอนิเมะไม่ถูกต้อง']);
    exit;
}

// ตรวจสอบว่าอนิเมะนี้อยู่ใน favorites แล้วหรือยัง
$stmtCheck = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND anime_id = ?");
$stmtCheck->execute([$user_id, $anime_id]);
$exists = $stmtCheck->fetchColumn();

if ($exists) {
    // ลบ favorite
    $stmtDel = $pdo->prepare("DELETE FROM favorites WHERE id = ?");
    $stmtDel->execute([$exists]);
    echo json_encode(['status' => 'removed']);
} else {
    // เพิ่ม favorite
    $stmtAdd = $pdo->prepare("INSERT INTO favorites (user_id, anime_id, created_at) VALUES (?, ?, NOW())");
    $stmtAdd->execute([$user_id, $anime_id]);
    echo json_encode(['status' => 'added']);
}
