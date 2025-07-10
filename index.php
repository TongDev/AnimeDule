<?php
require 'config/database.php';

// ดึงปีและฤดูกาลจาก query string
$selected_year = $_GET['year'] ?? date("Y");
$selected_season = $_GET['season'] ?? "Summer";

// ดึงรายการ Season ทั้งหมดจาก DB (ปีล่าสุดก่อน)
$season_stmt = $pdo->query("SELECT DISTINCT year FROM seasons ORDER BY year DESC");
$years = $season_stmt->fetchAll(PDO::FETCH_COLUMN);

// ดึง Anime ที่ตรงกับ Season และปี
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
<html>

<head>
    <title>AnimeDule - หน้าแรก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php">AnimeDule</a>
        </div>
        <li class="nav-item dropdown">
            <a class="nav-link position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                🔔
                <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">0</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifDropdown" style="max-height:300px; overflow-y:auto;" id="notifList">
                <li><span class="dropdown-item-text">ไม่มีแจ้งเตือนใหม่</span></li>
            </ul>
        </li>
    </nav>

    <div class="container">
        <h1 class="mb-4">ตาราง Anime ประจำฤดูกาล</h1>

        <!-- ฟอร์มเลือกปีและฤดูกาล -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
                <select name="year" class="form-select" required>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= $year ?>" <?= $year == $selected_year ? 'selected' : '' ?>><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="season" class="form-select" required>
                    <?php foreach (['Winter', 'Spring', 'Summer', 'Fall'] as $s): ?>
                        <option value="<?= $s ?>" <?= $s == $selected_season ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">ดูตาราง</button>
            </div>
        </form>

        <!-- แสดงรายการ Anime -->
        <?php if (count($animes) > 0): ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4">
                <?php foreach ($animes as $anime): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <img src="<?= htmlspecialchars($anime['cover_image']) ?>" class="card-img-top" alt="Cover" style="height: 300px; object-fit: cover;">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($anime['title_en']) ?></h5>
                                <p class="card-text text-truncate"><?= htmlspecialchars($anime['synopsis']) ?></p>
                                <a href="anime.php?id=<?= $anime['id'] ?>" class="btn btn-sm btn-outline-primary">ดูรายละเอียด</a>
                            </div>
                            <div class="card-footer text-muted small">
                                <?= $anime['season'] ?> <?= $anime['year'] ?> • <?= $anime['studio'] ?>
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

        // เรียกครั้งแรก
        fetchNotifications();

        // เรียกทุก 60 วินาที
        setInterval(fetchNotifications, 60000);
    </script>
</body>

</html>