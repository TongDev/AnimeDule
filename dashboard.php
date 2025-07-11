<?php
session_start();
require 'config/database.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user'];

// ดึงข้อมูลผู้ใช้
$stmt = $pdo->prepare("SELECT id, name, email, status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$isVerified = ($user['status'] === 'active');

// ดึงรายการ Anime ที่ผู้ใช้ Favorite จากตาราง favorites
$stmt = $pdo->prepare("
    SELECT a.id, a.title_en, a.next_episode_air_time
    FROM favorites fa
    JOIN anime a ON fa.anime_id = a.id
    WHERE fa.user_id = ?
    ORDER BY a.next_episode_air_time ASC
");
$stmt->execute([$user_id]);
$favoriteAnime = $stmt->fetchAll();

// ดึง Anime ที่จะฉายในวันนี้ (แจ้งเตือน)
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT a.title_en, a.next_episode_air_time
    FROM favorites fa
    JOIN anime a ON fa.anime_id = a.id
    WHERE fa.user_id = ? AND DATE(a.next_episode_air_time) = ?
    ORDER BY a.next_episode_air_time ASC
");
$stmt->execute([$user_id, $today]);
$todayAnimes = $stmt->fetchAll();

// ดึง Anime แนะนำ (สมมติดึง 5 เรื่องล่าสุด)
$stmt = $pdo->query("SELECT id, title_en FROM anime ORDER BY created_at DESC LIMIT 5");
$recommendedAnime = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>Dashboard - AnimeDule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="#">AnimeDule</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu"
      aria-controls="navmenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navmenu">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="profile.php">โปรไฟล์</a></li>
        <li class="nav-item"><a class="nav-link" href="favorite.php">รายการโปรด</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">ออกจากระบบ</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-4">
  <h1>สวัสดี, <?= htmlspecialchars($user['name']) ?></h1>
  <p>อีเมล: <?= htmlspecialchars($user['email']) ?></p>
  <p>สถานะอีเมล: 
    <?php if ($isVerified): ?>
      <span class="badge bg-success">ยืนยันแล้ว</span>
    <?php else: ?>
      <span class="badge bg-warning text-dark">ยังไม่ยืนยัน</span>
    <?php endif; ?>
  </p>

  <hr>

  <h2>รายการ Anime ที่คุณติดตาม</h2>
  <?php if (count($favoriteAnime) > 0): ?>
    <div class="list-group">
      <?php foreach ($favoriteAnime as $anime): ?>
        <a href="anime_detail.php?id=<?= $anime['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
          <?= htmlspecialchars($anime['title_en']) ?>
          <small class="text-muted">
            ตอนต่อไป: <?= $anime['next_episode_air_time'] ? date('d M Y H:i', strtotime($anime['next_episode_air_time'])) : 'ไม่ระบุ' ?>
          </small>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p>คุณยังไม่มีรายการโปรดในระบบ</p>
  <?php endif; ?>

  <hr>

  <h2>แจ้งเตือนวันนี้</h2>
  <?php if (count($todayAnimes) > 0): ?>
    <ul class="list-group mb-4">
      <?php foreach ($todayAnimes as $anime): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <?= htmlspecialchars($anime['title_en']) ?>
          <span class="badge bg-info"><?= date('H:i', strtotime($anime['next_episode_air_time'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <div class="alert alert-info">วันนี้ไม่มี Anime ที่คุณติดตามฉาย</div>
  <?php endif; ?>

  <hr>

  <h2>Anime แนะนำ</h2>
  <ul class="list-group">
    <?php foreach ($recommendedAnime as $anime): ?>
      <li class="list-group-item">
        <a href="anime.php?id=<?= $anime['id'] ?>"><?= htmlspecialchars($anime['title_en']) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
