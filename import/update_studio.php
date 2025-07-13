<?php
require_once __DIR__ . '/../config/database.php';

echo "Updating studios for anime...\n";

$seasons = ['Winter', 'Spring', 'Summer', 'Fall'];
$years = [2023, 2024, 2025];

foreach ($years as $year) {
    foreach ($seasons as $season) {
        $page = 1;
        $perPage = 50;

        $query = '
        query ($season: MediaSeason, $seasonYear: Int, $page: Int, $perPage: Int) {
            Page(page: $page, perPage: $perPage) {
                media(season: $season, seasonYear: $seasonYear, type: ANIME, isAdult: false) {
                    id
                    studios {
                        nodes {
                            name
                        }
                    }
                }
            }
        }';

        $variables = [
            'season' => strtoupper($season),
            'seasonYear' => $year,
            'page' => $page,
            'perPage' => $perPage
        ];

        $headers = ['Content-Type: application/json'];
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode(['query' => $query, 'variables' => $variables])
            ]
        ];

        $context = stream_context_create($opts);
        $result = json_decode(file_get_contents('https://graphql.anilist.co', false, $context), true);

        if (!isset($result['data']['Page']['media'])) continue;

        foreach ($result['data']['Page']['media'] as $anime) {
            $anilistId = $anime['id'];
            $studioNodes = $anime['studios']['nodes'] ?? [];

            // หา anime_id ในฐานข้อมูลของเรา
            $stmtAnime = $pdo->prepare("SELECT id FROM anime WHERE anilist_id = ?");
            $stmtAnime->execute([$anilistId]);
            $animeId = $stmtAnime->fetchColumn();

            if (!$animeId) continue;

            foreach ($studioNodes as $studio) {
                $studioName = trim($studio['name']);
                if ($studioName === '') continue;

                // ตรวจสอบ หรือเพิ่ม studio
                $stmt = $pdo->prepare("SELECT id FROM studios WHERE name = ?");
                $stmt->execute([$studioName]);
                $studioId = $stmt->fetchColumn();

                if (!$studioId) {
                    $stmtInsert = $pdo->prepare("INSERT INTO studios (name) VALUES (?)");
                    $stmtInsert->execute([$studioName]);
                    $studioId = $pdo->lastInsertId();
                }

                // เชื่อมโยงใน anime_studios (กันซ้ำ)
                $stmtLink = $pdo->prepare("INSERT IGNORE INTO anime_studios (anime_id, studio_id) VALUES (?, ?)");
                $stmtLink->execute([$animeId, $studioId]);
            }

            echo "Updated anime_id = $animeId (anilist_id = $anilistId)\n";
        }
    }
}

echo "All studios updated successfully.\n";
