<?php
session_start();
require 'config/database.php';

$user = null;
$favorites = [];

if (isset($_SESSION['user'])) {
    $stmtUser = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user']]);
    $user = $stmtUser->fetch();

    if ($user) {
        $stmtFav = $pdo->prepare("SELECT anime_id FROM favorites WHERE user_id = ?");
        $stmtFav->execute([$user['id']]);
        $favorites = $stmtFav->fetchAll(PDO::FETCH_COLUMN);
    }
}

// ดึงข้อมูลปี ฤดูกาล สถานะ
$years = $pdo->query("SELECT DISTINCT year FROM seasons ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);
$validSeasons = ['Winter', 'Spring', 'Summer', 'Fall'];
$statusOptions = $pdo->query("SELECT id, name_th FROM statuses ORDER BY name_th ASC")->fetchAll(PDO::FETCH_KEY_PAIR);

$perPage = 20;
$page = max((int)($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $perPage;

$q = trim($_GET['q'] ?? '');
$filterYear = in_array($_GET['year'] ?? '', $years) ? $_GET['year'] : '';
$filterSeason = in_array($_GET['season'] ?? '', $validSeasons) ? $_GET['season'] : '';
$filterStatus = array_key_exists($_GET['status'] ?? '', $statusOptions) ? $_GET['status'] : '';

$whereClauses = [];
$params = [];

if ($q !== '') {
    $whereClauses[] = "(a.title_en LIKE :q OR a.title_romaji LIKE :q)";
    $params[':q'] = "%$q%";
}
if ($filterYear !== '') {
    $whereClauses[] = "se.year = :year";
    $params[':year'] = $filterYear;
}
if ($filterSeason !== '') {
    $whereClauses[] = "se.season = :season";
    $params[':season'] = $filterSeason;
}
if ($filterStatus !== '') {
    $whereClauses[] = "a.status_id = :status";
    $params[':status'] = $filterStatus;
}
$whereSQL = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : '';

// นับจำนวนแอนิเมะทั้งหมด (distinct)
$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT a.id)
    FROM anime a
    LEFT JOIN seasons se ON a.season_id = se.id
    $whereSQL
");
$countStmt->execute($params);
$totalAnime = (int)$countStmt->fetchColumn();
$totalPages = max(ceil($totalAnime / $perPage), 1);

// ดึงข้อมูล anime พร้อม studios, season, status
$stmt = $pdo->prepare("
    SELECT a.id, a.title_en, a.title_romaji, a.cover_image, a.total_episodes,
           se.season, se.year, st.name_th AS status_name,
           GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS studio_name
    FROM anime a
    LEFT JOIN anime_studios ast ON a.id = ast.anime_id
    LEFT JOIN studios s ON ast.studio_id = s.id
    LEFT JOIN seasons se ON a.season_id = se.id
    LEFT JOIN statuses st ON a.status_id = st.id
    $whereSQL
    GROUP BY a.id
    ORDER BY se.year DESC, FIELD(se.season, 'Winter','Spring','Summer','Fall'), a.title_en ASC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$animeList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ฟังก์ชันดึง platform ลิงก์สำหรับ anime หนึ่งเรื่อง
function getPlatforms($pdo, $animeId) {
    $stmt = $pdo->prepare("
        SELECT p.name, ap.url 
        FROM anime_platforms ap
        JOIN platforms p ON ap.platform_id = p.id
        WHERE ap.anime_id = ?
        ORDER BY p.name ASC
    ");
    $stmt->execute([$animeId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'รายการ Anime';
if ($q) $pageTitle .= ' - ค้นหา: "' . htmlspecialchars($q) . '"';
if ($filterYear) $pageTitle .= " - ปี: $filterYear";
if ($filterSeason) $pageTitle .= " - ฤดู: $filterSeason";
if ($filterStatus) $pageTitle .= " - สถานะ: " . htmlspecialchars($statusOptions[$filterStatus]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>AnimeDule - <?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body class="bg-light">
<?php include 'includes/navbar.php'; ?>
<div class="container py-4">
    <h1 class="mb-4"><?= $pageTitle ?></h1>

    <!-- ฟอร์มค้นหา -->
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3"><input name="q" class="form-control" placeholder="ค้นหา..." value="<?= htmlspecialchars($q) ?>"></div>
        <div class="col-md-2">
            <select name="year" class="form-select">
                <option value="">-- ปี --</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?= $year ?>" <?= $filterYear == $year ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="season" class="form-select">
                <option value="">-- ฤดู --</option>
                <?php foreach ($validSeasons as $s): ?>
                    <option value="<?= $s ?>" <?= $filterSeason == $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">-- สถานะ --</option>
                <?php foreach ($statusOptions as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $filterStatus == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-grid">
            <button class="btn btn-primary">ค้นหา / กรอง</button>
        </div>
    </form>

    <?php if (empty($animeList)): ?>
        <div class="alert alert-warning">ไม่พบรายการ Anime</div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-5 g-4">
            <?php foreach ($animeList as $anime): ?>
                <?php
                    $animeId = $anime['id'];
                    $title = $anime['title_romaji'] ?: ($anime['title_en'] ?: 'ไม่ระบุชื่อ');
                    $isFav = in_array($animeId, $favorites);
                    $platforms = getPlatforms($pdo, $animeId);
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <img src="<?= htmlspecialchars($anime['cover_image'] ?? 'assets/img/default_cover.png') ?>" 
                             class="card-img-top" style="height: 300px; object-fit: cover;" alt="cover">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title d-flex justify-content-between">
                                <?= htmlspecialchars($title) ?>
                                <?php if ($user): ?>
                                    <button class="btn btn-link text-danger p-0 favorite-btn" 
                                            data-anime-id="<?= $animeId ?>"
                                            title="<?= $isFav ? 'ลบออกจาก Favorite' : 'เพิ่มลง Favorite' ?>">
                                        <i class="bi <?= $isFav ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                                    </button>
                                <?php endif; ?>
                            </h5>

                            <p class="card-text small">
                                <strong>Season:</strong> <?= htmlspecialchars($anime['season']) ?> <?= htmlspecialchars($anime['year']) ?><br>
                                <strong>ตอน:</strong> <?= htmlspecialchars($anime['total_episodes'] ?? '-') ?><br>
                                <strong>ผลิตโดย:</strong> <?= htmlspecialchars($anime['studio_name'] ?? '-') ?><br>
                                <strong>สถานะ:</strong> <?= htmlspecialchars($anime['status_name'] ?? '-') ?>
                            </p>
                            <div class="mt auto">

                            <?php if (!empty($platforms)): ?>
                                <div class="mb-2">
                                    <?php foreach ($platforms as $platform): ?>
                                        <a href="<?= htmlspecialchars($platform['url']) ?>" 
                                           class="btn btn-sm btn-success platform-btn" 
                                           target="_blank" rel="noopener noreferrer" 
                                           title="ดูบน <?= htmlspecialchars($platform['name']) ?>">
                                            <?= htmlspecialchars($platform['name']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <a href="anime.php?id=<?= $animeId ?>" class="btn btn-sm btn-outline-primary mt-auto" style="max-width: 140px;">ดูรายละเอียด</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">ก่อนหน้า</a>
                </li>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">ถัดไป</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".favorite-btn").forEach(button => {
        button.addEventListener("click", async () => {
            const animeId = button.dataset.animeId;
            const icon = button.querySelector("i");

            try {
                const res = await fetch('api/favorite_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'anime_id=' + encodeURIComponent(animeId)
                });
                const data = await res.json();

                if (data.success) {
                    icon.classList.toggle('bi-heart');
                    icon.classList.toggle('bi-heart-fill');
                } else {
                    alert(data.message || "เกิดข้อผิดพลาด");
                }
            } catch (err) {
                alert("ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์");
            }
        });
    });
});
</script>
</body>
</html>