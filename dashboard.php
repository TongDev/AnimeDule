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
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ดึงรายการ Anime ที่ผู้ใช้ Favorite (พร้อม studios แบบเดียวกับ index.php)
$stmt = $pdo->prepare("
    SELECT 
        a.id, 
        a.title_en, a.title_romaji, a.title_native,
        a.next_episode_air_time,
        st.code AS status,
        src.code AS source,
        GROUP_CONCAT(DISTINCT stu.name ORDER BY stu.name SEPARATOR ', ') AS studios
    FROM favorites fa
    JOIN anime a ON fa.anime_id = a.id
    LEFT JOIN statuses st ON a.status_id = st.id
    LEFT JOIN sources src ON a.source_id = src.id
    LEFT JOIN anime_studios ast ON a.id = ast.anime_id
    LEFT JOIN studios stu ON ast.studio_id = stu.id
    WHERE fa.user_id = ?
    GROUP BY a.id
    ORDER BY a.next_episode_air_time ASC
");
$stmt->execute([$user_id]);
$favoriteAnime = $stmt->fetchAll();

// ดึง Anime ที่จะฉายในวันนี้ (แจ้งเตือน)
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT 
        a.title_en, a.title_romaji, a.title_native,
        a.next_episode_air_time
    FROM favorites fa
    JOIN anime a ON fa.anime_id = a.id
    WHERE fa.user_id = ? AND DATE(a.next_episode_air_time) = ?
    ORDER BY a.next_episode_air_time ASC
");
$stmt->execute([$user_id, $today]);
$todayAnimes = $stmt->fetchAll();

// ดึง Anime แนะนำ (ล่าสุด 5 เรื่อง) แบบ studios เหมือนกัน
$stmt = $pdo->query("
    SELECT 
        a.id, 
        a.title_en, a.title_romaji, a.title_native,
        GROUP_CONCAT(DISTINCT stu.name ORDER BY stu.name SEPARATOR ', ') AS studios
    FROM anime a
    LEFT JOIN anime_studios ast ON a.id = ast.anime_id
    LEFT JOIN studios stu ON ast.studio_id = stu.id
    WHERE a.title_en IS NOT NULL OR a.title_romaji IS NOT NULL OR a.title_native IS NOT NULL
    GROUP BY a.id
    ORDER BY a.created_at DESC 
    LIMIT 5
");
$recommendedAnime = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>Dashboard - AnimeDule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body class="bg-light">

<?php include 'includes/navbar.php'; ?>

<div class="container py-4">
  <h1>สวัสดี, <?= htmlspecialchars($user['name']) ?></h1>
  <p>อีเมล: <?= htmlspecialchars($user['email']) ?></p>

  <hr>

  <h2>รายการ Anime ที่คุณติดตาม</h2>
  <?php if (count($favoriteAnime) > 0): ?>
    <div class="list-group">
      <?php foreach ($favoriteAnime as $anime): ?>
        <?php
          $title = $anime['title_en'] ?: ($anime['title_romaji'] ?: $anime['title_native'] ?: '-');
        ?>
        <a href="anime.php?id=<?= (int)$anime['id'] ?>" class="list-group-item list-group-item-action">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <strong><?= htmlspecialchars($title) ?></strong><br>
              <small class="text-muted">
                สถานะ: <?= htmlspecialchars($anime['status'] ?? '-') ?> | 
                ต้นฉบับ: <?= htmlspecialchars($anime['source'] ?? '-') ?> | 
                สตูดิโอ: <?= $anime['studios'] ? htmlspecialchars($anime['studios']) : 'ไม่ทราบ' ?>
              </small>
            </div>
            <small class="text-muted">
              ตอนต่อไป: <?= $anime['next_episode_air_time'] ? date('d M Y H:i', strtotime($anime['next_episode_air_time'])) : 'ไม่ระบุ' ?>
            </small>
          </div>
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
        <?php
          $title = $anime['title_en'] ?: ($anime['title_romaji'] ?: $anime['title_native'] ?: '-');
        ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <?= htmlspecialchars($title) ?>
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
      <?php
        $title = $anime['title_en'] ?: ($anime['title_romaji'] ?: $anime['title_native'] ?: '-');
      ?>
      <li class="list-group-item">
        <a href="anime.php?id=<?= (int)$anime['id'] ?>">
          <?= htmlspecialchars($title) ?>
        </a>
        <small class="text-muted d-block">
          ผลิตโดย: <?= $anime['studios'] ? htmlspecialchars($anime['studios']) : 'ไม่ทราบ' ?>
        </small>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
