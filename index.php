<?php
session_start();
require 'config/database.php';

$user = null;
if (isset($_SESSION['user'])) {
    $stmtUser = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user']]);
    $user = $stmtUser->fetch();
}

$selected_year = $_GET['year'] ?? date("Y");
$selected_season = $_GET['season'] ?? "Summer";
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

if (!preg_match('/^\d{4}$/', $selected_year)) $selected_year = date("Y");

$validSeasons = ['Winter', 'Spring', 'Summer', 'Fall'];
if (!in_array($selected_season, $validSeasons)) $selected_season = "Summer";

$years = $pdo->query("SELECT DISTINCT year FROM seasons ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);

$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT a.id) FROM anime a
    JOIN seasons s ON a.season_id = s.id
    WHERE s.year = ? AND s.season = ?");
$countStmt->execute([$selected_year, $selected_season]);
$total_anime = $countStmt->fetchColumn();
$total_pages = ceil($total_anime / $limit);

$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        s.season, 
        s.year,
        src.code AS source,
        st.code AS status,
        GROUP_CONCAT(DISTINCT stu.name SEPARATOR ', ') AS studios
    FROM anime a
    JOIN seasons s ON a.season_id = s.id
    LEFT JOIN sources src ON a.source_id = src.id
    LEFT JOIN statuses st ON a.status_id = st.id
    LEFT JOIN anime_studios ast ON a.id = ast.anime_id
    LEFT JOIN studios stu ON ast.studio_id = stu.id
    WHERE s.year = ? AND s.season = ?
    GROUP BY a.id
    ORDER BY COALESCE(a.title_en, a.title_romaji, a.title_native) ASC
    LIMIT $limit OFFSET $offset");
$stmt->execute([$selected_year, $selected_season]);
$animes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userFavorites = [];
if ($user) {
    $stmtFav = $pdo->prepare("SELECT anime_id FROM favorites WHERE user_id = ?");
    $stmtFav->execute([$user['id']]);
    $userFavorites = $stmtFav->fetchAll(PDO::FETCH_COLUMN);
}

function getPlatformsByAnimeId($pdo, $anime_id) {
    $stmt = $pdo->prepare("SELECT p.name, p.logo, ap.url FROM anime_platforms ap JOIN platforms p ON ap.platform_id = p.id WHERE ap.anime_id = ?");
    $stmt->execute([$anime_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
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

<?php include 'includes/navbar.php'; ?>

<div class="container py-4">
    <h1 class="mb-4">ตาราง Anime ประจำฤดูกาล</h1>

    <form method="GET" class="row gy-2 gx-3 align-items-center mb-4">
        <div class="col-md-3">
            <select name="year" class="form-select">
                <?php foreach ($years as $year): ?>
                    <option value="<?= htmlspecialchars($year) ?>" <?= $year == $selected_year ? 'selected' : '' ?>>
                        <?= htmlspecialchars($year) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="season" class="form-select">
                <?php foreach ($validSeasons as $s): ?>
                    <option value="<?= $s ?>" <?= $s == $selected_season ? 'selected' : '' ?>>
                        <?= $s ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" id="searchBox" class="form-control" placeholder="ค้นหา Anime..." autocomplete="off">
            <div id="searchResults" class="list-group shadow position-absolute w-100"></div>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">ดูตาราง</button>
        </div>
    </form>

    <?php if (count($animes) > 0): ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-5 g-2">
            <?php foreach ($animes as $anime): ?>
                <?php
                    $title = $anime['title_en'] ?: ($anime['title_romaji'] ?: $anime['title_native']);
                    $platforms = getPlatformsByAnimeId($pdo, $anime['id']);
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <img src="<?= $anime['cover_image'] ? htmlspecialchars($anime['cover_image']) : 'assets/img/default_cover.png' ?>" class="card-img-top" alt="Cover" style="height: 300px; object-fit: cover;">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($title) ?></h5>
                            <p class="card-text small">
                                <strong>Season:</strong> <?= htmlspecialchars($anime['season']) ?> <?= htmlspecialchars($anime['year']) ?><br>
                                <strong>ตอน:</strong> <?= (int)$anime['total_episodes'] ?> | 
                                <strong>ผลิตโดย:</strong> <?= $anime['studios'] ? htmlspecialchars($anime['studios']) : 'ไม่ทราบ' ?>
                            </p>
                            <?php foreach ($platforms as $p): ?>
                                <a href="<?= htmlspecialchars($p['url']) ?>" class="btn btn-sm btn-outline-dark me-1 mb-1" target="_blank">
                                    <?= htmlspecialchars($p['name']) ?>
                                </a>
                            <?php endforeach; ?>
                            <a href="anime.php?id=<?= (int)$anime['id'] ?>" class="btn btn-sm btn-outline-primary">ดูรายละเอียด</a>
                            <?php $isFav = in_array($anime['id'], $userFavorites); ?>
                            <button class="btn btn-sm <?= $isFav ? 'btn-danger' : 'btn-outline-danger' ?> favorite-btn" data-anime-id="<?= $anime['id'] ?>">
                                <i class="bi <?= $isFav ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?year=<?= $selected_year ?>&season=<?= $selected_season ?>&page=<?= $p ?>">
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php else: ?>
        <div class="alert alert-warning">ไม่พบ Anime สำหรับฤดูกาลนี้</div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchBox = document.getElementById('searchBox');
    const searchResults = document.getElementById('searchResults');

    searchBox.addEventListener('input', async () => {
        const query = searchBox.value.trim();
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        try {
            const res = await fetch(`api/search_anime.php?q=${encodeURIComponent(query)}`);
            const data = await res.json();
            searchResults.innerHTML = data.length === 0
                ? '<div class="list-group-item text-muted">ไม่พบผลลัพธ์</div>'
                : data.map(anime => `<a href="anime.php?id=${anime.id}" class="list-group-item list-group-item-action">${anime.title_en}</a>`).join('');
            searchResults.style.display = 'block';
        } catch (error) {
            console.error('Search Error:', error);
        }
    });

    document.addEventListener('click', (e) => {
        if (!searchBox.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });
});

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".favorite-btn").forEach(button => {
        button.addEventListener("click", async () => {
            const animeId = button.dataset.animeId;
            const icon = button.querySelector("i");

            try {
                const res = await fetch('api/favorite_toggle.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ anime_id: animeId })
                });

                const result = await res.json();

                if (result.status === 'added') {
                    button.classList.remove('btn-outline-danger');
                    button.classList.add('btn-danger');
                    icon.classList.remove('bi-heart');
                    icon.classList.add('bi-heart-fill');
                } else if (result.status === 'removed') {
                    button.classList.add('btn-outline-danger');
                    button.classList.remove('btn-danger');
                    icon.classList.add('bi-heart');
                    icon.classList.remove('bi-heart-fill');
                } else if (result.error) {
                    alert(result.error);
                }
            } catch (e) {
                console.error('Favorite toggle error:', e);
                alert("เกิดข้อผิดพลาดในการเชื่อมต่อ");
            }
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
