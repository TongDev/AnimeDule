<?php
session_start();
require 'config/database.php';

// ✅ ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user'];
    $anime_id = isset($_POST['anime_id']) ? (int)$_POST['anime_id'] : null;
    $platform_id = isset($_POST['platform_id']) ? (int)$_POST['platform_id'] : null;

    if (!$anime_id || !$platform_id) {
        $_SESSION['flash'] = "ข้อมูลไม่ครบถ้วน กรุณาลองใหม่อีกครั้ง";
        header("Location: anime.php?id=" . $anime_id);
        exit;
    }

    // ✅ ตรวจสอบว่า anime_id และ platform_id มีอยู่จริงในระบบ
    $stmtValidate = $pdo->prepare("
        SELECT a.id FROM anime a
        JOIN anime_platforms ap ON a.id = ap.anime_id
        WHERE a.id = ? AND ap.platform_id = ?
    ");
    $stmtValidate->execute([$anime_id, $platform_id]);

    if (!$stmtValidate->fetch()) {
        $_SESSION['flash'] = "ไม่สามารถเพิ่มรายการโปรดได้: ไม่พบข้อมูล Anime หรือ Platform ที่เกี่ยวข้อง";
        header("Location: anime.php?id=" . $anime_id);
        exit;
    }

    // ✅ ตรวจสอบว่ามีอยู่ใน favorites แล้วหรือยัง
    $stmtCheck = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND anime_id = ? AND platform_id = ?");
    $stmtCheck->execute([$user_id, $anime_id, $platform_id]);
    $favorite = $stmtCheck->fetch();

    if ($favorite) {
        // 🔁 ลบ Favorite (Toggle OFF)
        $stmtDel = $pdo->prepare("DELETE FROM favorites WHERE id = ?");
        $stmtDel->execute([$favorite['id']]);
        $_SESSION['flash'] = "ลบ Anime ออกจากรายการโปรดแล้ว";
    } else {
        // ➕ เพิ่ม Favorite
        $stmtAdd = $pdo->prepare("INSERT INTO favorites (user_id, anime_id, platform_id) VALUES (?, ?, ?)");
        $stmtAdd->execute([$user_id, $anime_id, $platform_id]);
        $_SESSION['flash'] = "เพิ่ม Anime ในรายการโปรดเรียบร้อยแล้ว";
    }

    header("Location: anime.php?id=" . $anime_id);
    exit;
}

// ⛔ หากเข้าผ่าน GET หรือวิธีอื่น
header("Location: index.php");
exit;
