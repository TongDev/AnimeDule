<?php
session_start();
require '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// รับ user_id จาก session (ถ้ามี)
$user_id = $_SESSION['user'] ?? null;

$year = $_GET['year'] ?? null;
$season = $_GET['season'] ?? null;
$search = trim($_GET['search'] ?? '');
$genres = $_GET['genres'] ?? [];
if (!is_array($genres)) $genres = [];
$statuses = $_GET['statuses'] ?? [];
if (!is_array($statuses)) $statuses = [];
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$limit = 20;
$offset = ($page - 1) * $limit;

$validSeasons = ['Winter', 'Spring', 'Summer', 'Fall'];
if (!in_array($season, $validSeasons)) $season = null;
if (!preg_match('/^\d{4}$/', $year)) $year = null;

$params = [];
$where = [];
$joins = [];

// YEAR
if ($year) {
    $where[] = "s.year = ?";
    $params[] = $year;
}

// SEASON
if ($season) {
    $where[] = "s.season = ?";
    $params[] = $season;
}

// SEARCH
if ($search !== '') {
    $where[] = "(a.title_en LIKE ? OR a.title_romaji LIKE ? OR a.title_native LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// GENRES
if (!empty($genres)) {
    $joins[] = "JOIN anime_genres ag ON ag.anime_id = a.id";
    $placeholders = implode(',', array_fill(0, count($genres), '?'));
    $where[] = "ag.genre_id IN ($placeholders)";
    foreach ($genres as $g) {
        $params[] = (int)$g;
    }
}

// STATUSES
if (!empty($statuses)) {
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $where[] = "st.code IN ($placeholders)";
    foreach ($statuses as $s) {
        $params[] = $s;
    }
}

// JOIN กับ favorites ถ้ามี user_id
$favJoin = "";
if ($user_id) {
    $favJoin = "LEFT JOIN favorites f ON f.anime_id = a.id AND f.user_id = ?";
    $params[] = $user_id;
}

// COUNT QUERY
$countParams = $params;
if ($user_id) array_pop($countParams); // remove user_id from count query

$countSql = "
    SELECT COUNT(DISTINCT a.id)
    FROM anime a
    JOIN seasons s ON a.season_id = s.id
    LEFT JOIN statuses st ON a.status_id = st.id
    " . implode(' ', $joins) . "
    " . (count($where) ? "WHERE " . implode(' AND ', $where) : "") . "
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$total = $countStmt->fetchColumn();
$total_pages = (int) ceil($total / $limit);

// MAIN QUERY
$sql = "
    SELECT DISTINCT a.id,
           COALESCE(a.title_en, a.title_romaji, a.title_native) AS title,
           a.cover_image,
           a.total_episodes,
           s.season,
           s.year,
           GROUP_CONCAT(DISTINCT stu.name SEPARATOR ', ') AS studios,
           CASE WHEN f.id IS NULL THEN 0 ELSE 1 END AS favorite
    FROM anime a
    JOIN seasons s ON a.season_id = s.id
    LEFT JOIN statuses st ON a.status_id = st.id
    LEFT JOIN anime_studios ast ON a.id = ast.anime_id
    LEFT JOIN studios stu ON ast.studio_id = stu.id
    " . implode(' ', $joins) . "
    $favJoin
    " . (count($where) ? "WHERE " . implode(' AND ', $where) : "") . "
    GROUP BY a.id
    ORDER BY s.year DESC,
             FIELD(s.season, 'Winter', 'Spring', 'Summer', 'Fall') DESC,
             a.created_at DESC
    LIMIT $limit OFFSET $offset
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'data' => $result,
        'total_pages' => $total_pages,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
