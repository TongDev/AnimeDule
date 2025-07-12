<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user'];

$stmtUser = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ดึงรายการโปรดพร้อม platform info
$stmt = $pdo->prepare("
    SELECT 
        fa.id AS favorite_id,
        a.id AS anime_id,
        a.title_en,
        a.cover_image,
        a.next_episode_air_time,
        p.name AS platform_name,
        p.url AS platform_url
    FROM favorites fa
    JOIN anime a ON fa.anime_id = a.id
    JOIN platforms p ON fa.platform_id = p.id
    WHERE fa.user_id = ?
    ORDER BY a.title_en ASC
");
$stmt->execute([$user_id]);
$favorites = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['favorite_id'])) {
    $favorite_id = (int)$_POST['favorite_id'];

    $stmtCheck = $pdo->prepare("SELECT id FROM favorites WHERE id = ? AND user_id = ?");
    $stmtCheck->execute([$favorite_id, $user_id]);
    if ($stmtCheck->fetch()) {
        $stmtDelete = $pdo->prepare("DELETE FROM favorites WHERE id = ?");
        $stmtDelete->execute([$favorite_id]);
        header("Location: favorite.php");
        exit;
    } else {
        $error = "ไม่สามารถลบรายการโปรดนี้ได้";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>รายการโปรด - AnimeDule</title>
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
    <h1>รายการโปรดของ <?= htmlspecialchars($user['name']) ?></h1>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (count($favorites) > 0): ?>
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>รูปปก</th>
                    <th>ชื่อ Anime</th>
                    <th>ตอนต่อไป</th>
                    <th>แพลตฟอร์ม</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($favorites as $fav): ?>
                    <tr>
                        <td style="width: 100px;">
                            <img src="<?= htmlspecialchars($fav['cover_image']) ?>" alt="Cover" class="img-fluid" style="max-height: 100px;">
                        </td>
                        <td>
                            <a href="anime.php?id=<?= (int)$fav['anime_id'] ?>"><?= htmlspecialchars($fav['title_en']) ?></a>
                        </td>
                        <td>
                            <?= $fav['next_episode_air_time'] ? date('d M Y H:i', strtotime($fav['next_episode_air_time'])) : 'ไม่ระบุ' ?>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars($fav['platform_url']) ?>" target="_blank" rel="noopener noreferrer">
                                <?= htmlspecialchars($fav['platform_name']) ?>
                            </a>
                        </td>
                        <td>
                            <form method="POST" onsubmit="return confirm('ต้องการลบรายการโปรดนี้หรือไม่?');">
                                <input type="hidden" name="favorite_id" value="<?= (int)$fav['favorite_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-info">คุณยังไม่มีรายการโปรดในระบบ</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
