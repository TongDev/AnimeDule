<?php
require 'config/database.php';

$search = $_GET['search'] ?? '';
$genres = $_GET['genres'] ?? [];
$statuses = $_GET['statuses'] ?? [];
$platforms = $_GET['platforms'] ?? [];
$season_id = $_GET['season_id'] ?? '';

$sql = "
    SELECT DISTINCT a.*, s.season, s.year
    FROM anime a
    LEFT JOIN seasons s ON a.season_id = s.id
    LEFT JOIN anime_genres ag ON a.id = ag.anime_id
    LEFT JOIN genres g ON ag.genre_id = g.id
    LEFT JOIN anime_platforms ap ON a.id = ap.anime_id
    WHERE 1
";

$params = [];

// 🔍 ค้นหาชื่อ
if (!empty($search)) {
    $sql .= " AND a.title_en LIKE ?";
    $params[] = "%$search%";
}

// 🎯 Filter: แนว
if (!empty($genres)) {
    $in = str_repeat('?,', count($genres) - 1) . '?';
    $sql .= " AND g.name IN ($in)";
    $params = array_merge($params, $genres);
}

// 🎯 Filter: สถานะ
if (!empty($statuses)) {
    $in = str_repeat('?,', count($statuses) - 1) . '?';
    $sql .= " AND a.status_id IN ($in)";
    $params = array_merge($params, $statuses);
}

// 🎯 Filter: แพลตฟอร์ม
if (!empty($platforms)) {
    $in = str_repeat('?,', count($platforms) - 1) . '?';
    $sql .= " AND ap.platform_id IN ($in)";
    $params = array_merge($params, $platforms);
}

// 🎯 Filter: ฤดูกาล
if (!empty($season_id)) {
    $sql .= " AND a.season_id = ?";
    $params[] = $season_id;
}

$sql .= " ORDER BY a.title_en ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$animes = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($animes);
