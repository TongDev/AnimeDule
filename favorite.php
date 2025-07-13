<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user'];

// ✅ เตรียม $user สำหรับ navbar
$stmtUser = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ✅ ลบรายการโปรด
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

// ✅ ดึงรายการโปรด (รองรับฐานข้อมูลใหม่)
$stmt = $pdo->prepare("
    SELECT 
        fa.id AS favorite_id,
        a.id AS anime_id,
        a.title_en,
        a.cover_image,
        a.next_episode_air_time,
        st.code AS status,
        src.code AS source,
        p.name AS platform_name,
        p.url AS platform_url,
        GROUP_CONCAT(s.name SEPARATOR ', ') AS studios
    FROM favorites fa
    JOIN anime a ON fa.anime_id = a.id
    LEFT JOIN statuses st ON a.status_id = st.id
    LEFT JOIN sources src ON a.source_id = src.id
    LEFT JOIN anime_studios ast ON a.id = ast.anime_id
    LEFT JOIN studios s ON ast.studio_id = s.id
    JOIN platforms p ON fa.platform_id = p.id
    WHERE fa.user_id = ?
    GROUP BY fa.id
    ORDER BY a.title_en ASC
");
$stmt->execute([$user_id]);
$favorites = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>รายการโปรด - AnimeDule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>

<!-- ✅ ใช้ navbar ส่วนกลาง -->
<?php include 'includes/navbar.php'; ?>

<div class="container py-4">
    <h1>รายการโปรดของ <?= htmlspecialchars($user['name']) ?></h1>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (count($favorites) > 0): ?>
        <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>รูปปก</th>
                    <th>ชื่อ Anime</th>
                    <th>สถานะ</th>
                    <th>สตูดิโอ</th>
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
                        <td><?= htmlspecialchars($fav['status'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($fav['studios'] ?? '-') ?></td>
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
        </div>
    <?php else: ?>
        <div class="alert alert-info">คุณยังไม่มีรายการโปรดในระบบ</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
