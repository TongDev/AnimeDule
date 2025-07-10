<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user'];
    $anime_id = $_POST['anime_id'] ?? null;
    $platform_id = $_POST['platform_id'] ?? null;

    if (!$anime_id || !$platform_id) {
        die("ข้อมูลไม่ครบถ้วน");
    }

    // เช็คว่ามีรายการนี้ใน favorites แล้วหรือยัง
    $checkStmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND anime_id = ? AND platform_id = ?");
    $checkStmt->execute([$user_id, $anime_id, $platform_id]);
    if ($checkStmt->fetch()) {
        // ลบ Favorite (toggle favorite)
        $delStmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND anime_id = ? AND platform_id = ?");
        $delStmt->execute([$user_id, $anime_id, $platform_id]);
        $_SESSION['flash'] = "ลบ Anime จาก Favorite เรียบร้อยแล้ว";
    } else {
        // เพิ่ม Favorite
        $insertStmt = $pdo->prepare("INSERT INTO favorites (user_id, anime_id, platform_id) VALUES (?, ?, ?)");
        $insertStmt->execute([$user_id, $anime_id, $platform_id]);
        $_SESSION['flash'] = "เพิ่ม Anime ใน Favorite เรียบร้อยแล้ว";
    }

    header("Location: anime.php?id=$anime_id");
    exit;
} else {
    header("Location: index.php");
    exit;
}
