<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user'];

// ดึงข้อมูล Favorite ของ user พร้อมรายละเอียด Anime และ Platform
$stmt = $pdo->prepare("
    SELECT f.id as favorite_id, a.id as anime_id, a.title_en, a.cover_image, p.name as platform_name, ap.watch_url
    FROM favorites f
    JOIN anime a ON f.anime_id = a.id
    JOIN platforms p ON f.platform_id = p.id
    JOIN anime_platforms ap ON ap.anime_id = a.id AND ap.platform_id = p.id
    WHERE f.user_id = ?
    ORDER BY a.title_en
");
$stmt->execute([$user_id]);
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ถ้าลบ favorite ผ่าน POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['favorite_id'])) {
    $delStmt = $pdo->prepare("DELETE FROM favorites WHERE id = ? AND user_id = ?");
    $delStmt->execute([$_POST['favorite_id'], $user_id]);
    $_SESSION['flash'] = "ลบ Favorite เรียบร้อยแล้ว";
    header('Location: favorites.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Favorite Anime - AnimeDule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
    <h2>รายการ Favorite Anime ของคุณ</h2>

    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-success"><?= $_SESSION['flash'] ?></div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <?php if (count($favorites) === 0): ?>
        <div class="alert alert-info">คุณยังไม่มี Favorite Anime</div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php foreach ($favorites as $fav): ?>
                <div class="col">
                    <div class="card h-100">
                        <img src="<?= htmlspecialchars($fav['cover_image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($fav['title_en']) ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($fav['title_en']) ?></h5>
                            <p>แพลตฟอร์ม: <?= htmlspecialchars($fav['platform_name']) ?></p>
                            <a href="<?= htmlspecialchars($fav['watch_url']) ?>" target="_blank" class="btn btn-success btn-sm mb-2">ดู Anime</a>

                            <form method="POST" onsubmit="return confirm('คุณต้องการลบ Favorite เรื่องนี้หรือไม่?');">
                                <input type="hidden" name="favorite_id" value="<?= $fav['favorite_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">ลบ Favorite</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>
