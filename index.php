<?php
session_start();
require 'config/database.php';

// ตรวจสอบ session user
$user = null;
if (isset($_SESSION['user'])) {
    $stmtUser = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user']]);
    $user = $stmtUser->fetch();
}

$selected_year = $_GET['year'] ?? date("Y");
$selected_season = $_GET['season'] ?? "Summer";

if (!preg_match('/^\d{4}$/', $selected_year)) {
    $selected_year = date("Y");
}

$validSeasons = ['Winter', 'Spring', 'Summer', 'Fall'];
if (!in_array($selected_season, $validSeasons)) {
    $selected_season = "Summer";
}

$season_stmt = $pdo->query("SELECT DISTINCT year FROM seasons ORDER BY year DESC");
$years = $season_stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("
    SELECT anime.*, seasons.season, seasons.year
    FROM anime
    JOIN seasons ON anime.season_id = seasons.id
    WHERE seasons.year = ? AND seasons.season = ?
    ORDER BY anime.title_en ASC
");
$stmt->execute([$selected_year, $selected_season]);
$animes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <title>AnimeDule - หน้าแรก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css" />
</head>

<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php">AnimeDule</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu"
            aria-controls="navmenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navmenu">
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if ($user): ?>
                    <!-- Logged in: show username and Logout -->
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
                    <!-- Not logged in: show Login and Register -->
                    <li class="nav-item"><a class="nav-link" href="login.php">เข้าสู่ระบบ</a></li>
                    <li class="nav-item"><a class="nav-link" href="register.php">สมัครสมาชิก</a></li>
                <?php endif; ?>
                                <!-- Notification -->
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

<div class="container">
    <h1 class="mb-4">ตาราง Anime ประจำฤดูกาล</h1>

    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-3">
            <select name="year" class="form-select" required>
                <?php foreach ($years as $year): ?>
                    <option value="<?= htmlspecialchars($year) ?>" <?= $year == $selected_year ? 'selected' : '' ?>><?= htmlspecialchars($year) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="season" class="form-select" required>
                <?php foreach ($validSeasons as $s): ?>
                    <option value="<?= $s ?>" <?= $s == $selected_season ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary">ดูตาราง</button>
        </div>
    </form>

    <?php if (count($animes) > 0): ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4">
            <?php foreach ($animes as $anime): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <img src="<?= htmlspecialchars($anime['cover_image']) ?>" class="card-img-top" alt="Cover" style="height: 300px; object-fit: cover;">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($anime['title_en']) ?></h5>
                            <p class="card-text text-truncate"><?= htmlspecialchars($anime['synopsis']) ?></p>
                            <a href="anime.php?id=<?= (int)$anime['id'] ?>" class="btn btn-sm btn-outline-primary">ดูรายละเอียด</a>
                        </div>
                        <div class="card-footer text-muted small">
                            <?= htmlspecialchars($anime['season']) ?> <?= htmlspecialchars($anime['year']) ?> • <?= htmlspecialchars($anime['studio']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">ไม่พบ Anime สำหรับฤดูกาลนี้</div>
    <?php endif; ?>
</div>

<script>
    async function fetchNotifications() {
        try {
            let res = await fetch('api/notifications.php');
            if (!res.ok) throw new Error('Network response was not ok');
            let data = await res.json();
            const badge = document.getElementById('notifBadge');
            const list = document.getElementById('notifList');

            if (data.error) {
                badge.style.display = 'none';
                list.innerHTML = '<li><span class="dropdown-item-text text-danger">กรุณาเข้าสู่ระบบ</span></li>';
                return;
            }

            if (data.notifications.length > 0) {
                badge.style.display = 'inline-block';
                badge.textContent = data.notifications.length;

                list.innerHTML = '';
                data.notifications.forEach(notif => {
                    const li = document.createElement('li');
                    li.innerHTML = `<a href="anime.php?id=${notif.anime_id}" class="dropdown-item">
                        <strong>${notif.title_en}</strong> (${notif.platform_name})<br>
                        ${notif.message} <br>
                        <small class="text-muted">${new Date(notif.notified_at).toLocaleString()}</small>
                    </a>`;
                    list.appendChild(li);
                });
            } else {
                badge.style.display = 'none';
                list.innerHTML = '<li><span class="dropdown-item-text">ไม่มีแจ้งเตือนใหม่</span></li>';
            }
        } catch (error) {
            console.error('Fetch notifications error:', error);
        }
    }

    fetchNotifications();
    setInterval(fetchNotifications, 60000);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
