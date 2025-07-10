<?php
session_start();
require '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user'];

// เช็คแจ้งเตือนที่ยังไม่อ่าน
$stmt = $pdo->prepare("SELECT n.id, a.title_en, p.name AS platform_name, n.message, n.notified_at
  FROM notifications n
  JOIN anime a ON n.anime_id = a.id
  JOIN platforms p ON n.platform_id = p.id
  WHERE n.user_id = ? AND n.is_read = 0
  ORDER BY n.notified_at DESC
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['notifications' => $notifications]);
