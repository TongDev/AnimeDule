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
$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ดึงรายการ Anime ที่ผู้ใช้ Favorite
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

// ดึง Anime แนะนำ (สมมติ)
$stmt = $pdo->query("SELECT id, title_en FROM anime ORDER BY created_at DESC LIMIT 5");
$recommendedAnime = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>Dashboard - AnimeDule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/style.css" />
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="index.php">AnimeDule</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu"
      aria-controls="navmenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navmenu">
      <ul class="navbar-nav ms-auto align-items-center">
        <?php if (isset($user) && $user): ?>
        <!-- ถ้า login แล้ว -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
             data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['name']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
            <li><a class="dropdown-item" href="favorite.php">รายการโปรด</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php">ออกจากระบบ</a></li>
          </ul>
        </li>
        <?php else: ?>
        <!-- ถ้ายังไม่ได้ login -->
        <li class="nav-item"><a class="nav-link" href="login.php">เข้าสู่ระบบ</a></li>
        <li class="nav-item"><a class="nav-link" href="register.php">สมัครสมาชิก</a></li>
        <?php endif; ?>
                <!-- 🔔 Notification -->
        <li class="nav-item dropdown me-3">
          <a class="nav-link position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            🔔
            <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">0</span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifDropdown" style="max-height:300px; overflow-y:auto;" id="notifList">
            <li><span class="dropdown-item-text">ไม่มีแจ้งเตือนใหม่</span></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>


<div class="container py-4">
  <h1>สวัสดี, <?= htmlspecialchars($user['name']) ?></h1>
  <p>อีเมล: <?= htmlspecialchars($user['email']) ?></p>

  <hr>

  <h2>รายการ Anime ที่คุณติดตาม</h2>
  <?php if (count($favoriteAnime) > 0): ?>
    <div class="list-group">
      <?php foreach ($favoriteAnime as $anime): ?>
        <a href="anime.php?id=<?= (int)$anime['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
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
        <a href="anime.php?id=<?= (int)$anime['id'] ?>"><?= htmlspecialchars($anime['title_en']) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
