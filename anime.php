<?php
session_start();
require 'config/database.php';

$user_id = $_SESSION['user'] ?? null;

// ✅ เตรียม $user เพื่อให้ navbar.php ใช้งานได้
$user = null;
if ($user_id) {
    $stmt_user = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
}

// ตรวจสอบ id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ไม่พบ Anime ที่คุณต้องการดู");
}
$anime_id = (int)$_GET['id'];

// ดึง Favorite ของ user
$user_favorites = [];
if ($user_id) {
    $fav_stmt = $pdo->prepare("SELECT platform_id FROM favorites WHERE user_id = ? AND anime_id = ?");
    $fav_stmt->execute([$user_id, $anime_id]);
    $user_favorites = $fav_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ดึงข้อมูล Anime พร้อมข้อมูลจากตารางอื่น
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        s.season, 
        s.year,
        src.code AS source,
        st.code AS status,
        stu.name AS studio
    FROM anime a
    JOIN seasons s ON a.season_id = s.id
    LEFT JOIN sources src ON a.source_id = src.id
    LEFT JOIN statuses st ON a.status_id = st.id
    LEFT JOIN studios stu ON a.studio_id = stu.id
    WHERE a.id = ?
");
$stmt->execute([$anime_id]);
$anime = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$anime) {
    die("ไม่พบ Anime นี้ในระบบ");
}

// ดึงแนว
$genre_stmt = $pdo->prepare("
    SELECT g.name FROM anime_genres ag
    JOIN genres g ON ag.genre_id = g.id
    WHERE ag.anime_id = ?
");
$genre_stmt->execute([$anime_id]);
$genres = $genre_stmt->fetchAll(PDO::FETCH_COLUMN);

// ดึงแพลตฟอร์ม
$platform_stmt = $pdo->prepare("
    SELECT p.id, p.name, ap.episode_time, ap.watch_url
    FROM anime_platforms ap
    JOIN platforms p ON ap.platform_id = p.id
    WHERE ap.anime_id = ?
");
$platform_stmt->execute([$anime_id]);
$platforms = $platform_stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงคะแนนเฉลี่ยและจำนวนรีวิว
$avg_stmt = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE anime_id = ?");
$avg_stmt->execute([$anime_id]);
$avg_data = $avg_stmt->fetch();

// ดึงรีวิว
$reviews_stmt = $pdo->prepare("
    SELECT r.rating, r.review_text, r.created_at, u.email 
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.anime_id = ?
    ORDER BY r.created_at DESC
");
$reviews_stmt->execute([$anime_id]);
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <title><?= htmlspecialchars($anime['title_en']) ?> - AnimeDule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css" />
</head>

<body class="bg-light">

<?php include 'includes/navbar.php'; ?>

<div class="container py-4">

    <!-- ✅ Flash Message -->
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-4">
            <img src="<?= htmlspecialchars($anime['cover_image']) ?>" alt="Cover" class="img-fluid rounded shadow">
        </div>
        <div class="col-md-8">
            <h2><?= htmlspecialchars($anime['title_en']) ?></h2>
            <!-- ลบชื่ออื่น (title_alt) ออกตามคำขอ -->

            <p><strong>แนว:</strong> <?= $genres ? implode(", ", $genres) : "-" ?></p>
            <p><strong>ต้นฉบับจาก:</strong> <?= htmlspecialchars($anime['source'] ?? '-') ?></p>
            <p><strong>ผลิตโดย:</strong> <?= htmlspecialchars($anime['studio'] ?? '-') ?></p>
            <p><strong>จำนวนตอน:</strong> <?= htmlspecialchars($anime['total_episodes'] ?? '-') ?></p>
            <p><strong>สถานะ:</strong> <?= ($anime['status'] ?? '') === 'ongoing' ? 'ยังฉายอยู่' : 'จบแล้ว' ?></p>
            <p><strong>ฤดูกาล:</strong> <?= htmlspecialchars($anime['season']) ?> <?= htmlspecialchars($anime['year']) ?></p>

            <hr>
            <h5>เรื่องย่อ:</h5>
            <p><?= nl2br(htmlspecialchars($anime['synopsis'] ?? '-')) ?></p>

            <hr>
            <h5>รับชมบนแพลตฟอร์ม:</h5>
            <?php if ($platforms): ?>
                <ul class="list-group">
                    <?php foreach ($platforms as $p): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars($p['name']) ?> - ฉายเวลา <?= substr($p['episode_time'], 0, 5) ?>
                            <a href="<?= htmlspecialchars($p['watch_url']) ?>" target="_blank" class="btn btn-sm btn-outline-success">ดูบน <?= htmlspecialchars($p['name']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="alert alert-warning mt-2">ยังไม่มีข้อมูลการฉายบนแพลตฟอร์ม</div>
            <?php endif; ?>

            <?php if ($user_id): ?>
                <hr>
                <h5>เพิ่มใน Favorite</h5>
                <form method="POST" action="favorite_action.php" class="mb-3">
                    <input type="hidden" name="anime_id" value="<?= $anime_id ?>">
                    <div class="mb-3">
                        <label for="platform" class="form-label">เลือกแพลตฟอร์มที่ชื่นชอบ</label>
                        <select name="platform_id" id="platform" class="form-select" required>
                            <option value="">-- เลือกแพลตฟอร์ม --</option>
                            <?php foreach ($platforms as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= in_array($p['id'], $user_favorites) ? 'selected disabled' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" <?= count($user_favorites) === count($platforms) ? 'disabled' : '' ?>>
                        <?= count($user_favorites) === count($platforms) ? 'เพิ่มแล้ว' : 'เพิ่ม Favorite' ?>
                    </button>
                </form>

                <hr>
                <h5>รีวิวและให้คะแนน</h5>
                <form method="POST" action="review_action.php">
                    <input type="hidden" name="anime_id" value="<?= $anime_id ?>">
                    <div class="mb-3">
                        <label for="rating" class="form-label">ให้คะแนน (1-5 ดาว)</label>
                        <select name="rating" id="rating" class="form-select" required>
                            <option value="">-- เลือกคะแนน --</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> ดาว</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="review_text" class="form-label">รีวิว (ไม่บังคับ)</label>
                        <textarea name="review_text" id="review_text" rows="4" class="form-control"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">ส่งรีวิว</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info">
                    กรุณา <a href="login.php">เข้าสู่ระบบ</a> เพื่อเพิ่ม Favorite หรือรีวิว
                </div>
            <?php endif; ?>

            <hr>
            <h5>คะแนนเฉลี่ย: <?= number_format($avg_data['avg_rating'] ?? 0, 1) ?> จาก <?= $avg_data['review_count'] ?> รีวิว</h5>

            <?php if ($reviews): ?>
                <div class="list-group">
                    <?php foreach ($reviews as $rev): ?>
                        <div class="list-group-item">
                            <strong><?= htmlspecialchars($rev['email']) ?></strong> ให้ <?= $rev['rating'] ?> ดาว
                            <small class="text-muted"><?= date('d M Y', strtotime($rev['created_at'])) ?></small>
                            <p><?= nl2br(htmlspecialchars($rev['review_text'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>ยังไม่มีรีวิวสำหรับ Anime เรื่องนี้</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
