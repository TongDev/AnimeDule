<?php
require_once __DIR__ . '/../config/database.php';

// ดึง AniList ID ของอนิเมะทั้งหมดในฐานข้อมูลเรา
$stmtAnime = $pdo->query("SELECT id, anilist_id FROM anime WHERE anilist_id IS NOT NULL");
$animes = $stmtAnime->fetchAll(PDO::FETCH_ASSOC);

$query = '
query ($id: Int) {
  Media(id: $id, type: ANIME) {
    genres
  }
}
';

$headers = ['Content-Type: application/json'];

foreach ($animes as $anime) {
    $variables = ['id' => (int)$anime['anilist_id']];
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => json_encode(['query' => $query, 'variables' => $variables]),
            'timeout' => 10
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents('https://graphql.anilist.co', false, $context);
    if (!$response) {
        echo "Failed to fetch genres for AniList ID {$anime['anilist_id']}\n";
        continue;
    }

    $result = json_decode($response, true);
    if (empty($result['data']['Media']['genres'])) {
        echo "No genres found for AniList ID {$anime['anilist_id']}\n";
        continue;
    }

    $genres = $result['data']['Media']['genres'];

    foreach ($genres as $genreName) {
        // ตรวจสอบว่ามี genre นี้ในฐานข้อมูลหรือยัง
        $stmtCheck = $pdo->prepare("SELECT id FROM genres WHERE name = ?");
        $stmtCheck->execute([$genreName]);
        $genreId = $stmtCheck->fetchColumn();

        if (!$genreId) {
            // เพิ่ม genre ใหม่
            $stmtInsert = $pdo->prepare("INSERT INTO genres (name) VALUES (?)");
            $stmtInsert->execute([$genreName]);
            $genreId = $pdo->lastInsertId();
            echo "Inserted new genre: {$genreName}\n";
        }

        // เช็คว่ามีการเชื่อมโยง anime_genres หรือยัง
        $stmtLinkCheck = $pdo->prepare("SELECT 1 FROM anime_genres WHERE anime_id = ? AND genre_id = ?");
        $stmtLinkCheck->execute([$anime['id'], $genreId]);
        if (!$stmtLinkCheck->fetch()) {
            // เชื่อมโยง genre กับ anime
            $stmtLink = $pdo->prepare("INSERT INTO anime_genres (anime_id, genre_id) VALUES (?, ?)");
            $stmtLink->execute([$anime['id'], $genreId]);
            echo "Linked genre '{$genreName}' to anime ID {$anime['id']}\n";
        }
    }
}

echo "Import genres and linking completed.\n";
