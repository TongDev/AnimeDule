<?php
session_start();
require 'config/database.php';

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

// ดึงรายการ Anime ที่ Favorite พร้อม studios และข้อมูลแพลตฟอร์ม
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

// ดึงลิงก์แพลตฟอร์ม
$animeIds = array_column($favoriteAnime, 'id');
$platformLinks = [];
if ($animeIds) {
    $inQuery = implode(',', array_fill(0, count($animeIds), '?'));
    $stmtPlatforms = $pdo->prepare("
        SELECT ap.anime_id, p.name AS platform_name, ap.url
        FROM anime_platforms ap
        JOIN platforms p ON ap.platform_id = p.id
        WHERE ap.anime_id IN ($inQuery)
    ");
    $stmtPlatforms->execute($animeIds);
    while ($row = $stmtPlatforms->fetch(PDO::FETCH_ASSOC)) {
        $platformLinks[$row['anime_id']][] = [
            'platform_name' => $row['platform_name'],
            'url' => $row['url']
        ];
    }
}

// ดึง Anime ที่จะฉายในวันนี้
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

// ดึง Anime แนะนำ
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

// CSS ปุ่ม platform (เฉพาะคลาส ไม่รวม style.css)
function getPlatformClass($platformName) {
    $map = [
        'Netflix' => 'btn-netflix',
        'Bilibili' => 'btn-bilibili',
        'YouTube' => 'btn-youtube',
        'Amazon Prime' => 'btn-amazon',
        'Disney+' => 'btn-disney',
        'Crunchyroll' => 'btn-crunchyroll',
        'Hulu' => 'btn-hulu',
    ];
    return $map[$platformName] ?? 'btn-outline-secondary';
}
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
                    $animeId = $anime['id'];
                    $platformBtns = '';
                    if (!empty($platformLinks[$animeId])) {
                        foreach ($platformLinks[$animeId] as $link) {
                            $class = getPlatformClass($link['platform_name']);
                            $platformBtns .= '<a href="' . htmlspecialchars($link['url']) . '" class="btn btn-sm ' . $class . ' me-1 mb-1" target="_blank">' . htmlspecialchars($link['platform_name']) . '</a>';
                        }
                    }
                ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= htmlspecialchars($title) ?></strong><br>
                            <small class="text-muted">
                                สถานะ: <?= htmlspecialchars($anime['status'] ?? '-') ?> |
                                ต้นฉบับ: <?= htmlspecialchars($anime['source'] ?? '-') ?> |
                                สตูดิโอ: <?= $anime['studios'] ? htmlspecialchars($anime['studios']) : 'ไม่ทราบ' ?>
                            </small><br>
                            <?= $platformBtns ?: '<span class="text-muted small">ไม่มีลิงก์</span>' ?>
                        </div>
                    </div>
                </div>
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
