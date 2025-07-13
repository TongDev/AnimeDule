<?php
session_start();
require 'config/database.php';

// ✅ ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user'];

// ✅ รับข้อมูลจากฟอร์ม
$anime_id = isset($_POST['anime_id']) ? (int)$_POST['anime_id'] : null;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
$review_text = trim($_POST['review_text'] ?? '');

if (!$anime_id || !$rating || $rating < 1 || $rating > 5) {
    $_SESSION['flash'] = "กรุณาเลือกคะแนนที่ถูกต้อง (1-5 ดาว)";
    header("Location: anime.php?id=$anime_id");
    exit;
}

// ✅ ตรวจสอบว่า Anime นี้มีอยู่จริงหรือไม่
$stmtCheckAnime = $pdo->prepare("SELECT id FROM anime WHERE id = ?");
$stmtCheckAnime->execute([$anime_id]);
if (!$stmtCheckAnime->fetch()) {
    $_SESSION['flash'] = "ไม่พบ Anime ที่ต้องการรีวิว";
    header("Location: index.php");
    exit;
}

// ✅ ตรวจสอบว่าผู้ใช้เคยรีวิว Anime นี้แล้วหรือยัง
$stmtCheck = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND anime_id = ?");
$stmtCheck->execute([$user_id, $anime_id]);
$existing_review = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if ($existing_review) {
    // 🔁 อัปเดตรีวิวเดิม
    $stmtUpdate = $pdo->prepare("
        UPDATE reviews
        SET rating = ?, review_text = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmtUpdate->execute([$rating, $review_text, $existing_review['id']]);
    $_SESSION['flash'] = "อัปเดตรายการรีวิวเรียบร้อยแล้ว";
} else {
    // ➕ เพิ่มรีวิวใหม่
    $stmtInsert = $pdo->prepare("
        INSERT INTO reviews (user_id, anime_id, rating, review_text)
        VALUES (?, ?, ?, ?)
    ");
    $stmtInsert->execute([$user_id, $anime_id, $rating, $review_text]);
    $_SESSION['flash'] = "บันทึกรีวิวเรียบร้อยแล้ว";
}

header("Location: anime.php?id=$anime_id");
exit;
