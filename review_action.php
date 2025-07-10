<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user'];
$anime_id = $_POST['anime_id'] ?? null;
$rating = $_POST['rating'] ?? null;
$review_text = $_POST['review_text'] ?? '';

if (!$anime_id || !$rating) {
    $_SESSION['flash'] = "ข้อมูลไม่ครบถ้วน";
    header("Location: anime.php?id=$anime_id");
    exit;
}

// ตรวจสอบว่าผู้ใช้เคยรีวิวเรื่องนี้หรือไม่
$stmt = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND anime_id = ?");
$stmt->execute([$user_id, $anime_id]);
$existing_review = $stmt->fetch();

if ($existing_review) {
    // อัปเดตรีวิวเดิม
    $update = $pdo->prepare("UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE id = ?");
    $update->execute([$rating, $review_text, $existing_review['id']]);
} else {
    // เพิ่มรีวิวใหม่
    $insert = $pdo->prepare("INSERT INTO reviews (user_id, anime_id, rating, review_text) VALUES (?, ?, ?, ?)");
    $insert->execute([$user_id, $anime_id, $rating, $review_text]);
}

$_SESSION['flash'] = "บันทึกรีวิวเรียบร้อยแล้ว";
header("Location: anime.php?id=$anime_id");
exit;
