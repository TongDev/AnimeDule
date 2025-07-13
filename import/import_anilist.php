<?php
require_once __DIR__ . '/../config/database.php';

function fetchSeasonAnimes($season, $year, $pdo) {
    echo "Importing $season $year...\n";

    $query = '
    query ($season: MediaSeason, $seasonYear: Int) {
      Page(page: 1, perPage: 50) {
        media(season: $season, seasonYear: $seasonYear, type: ANIME) {
          id
          title {
            english
            romaji
            native
          }
          coverImage {
            large
          }
          format
          status
          episodes
          source
          season
          seasonYear
          description(asHtml: false)
          studios {
            nodes {
              name
            }
          }
          genres
        }
      }
    }';

    $variables = [
        'season' => strtoupper($season),
        'seasonYear' => (int)$year,
    ];

    $data = apiRequest($query, $variables);
    if (!$data || !isset($data['Page']['media'])) {
        echo "Failed to fetch data for $season $year\n";
        return;
    }

    foreach ($data['Page']['media'] as $anime) {
        $anilist_id = $anime['id'];

        // ✅ ข้ามถ้ามี genre เป็น Ecchi หรือ Hentai
        $genres = $anime['genres'] ?? [];
        if (in_array("Ecchi", $genres) || in_array("Hentai", $genres)) {
            echo "ข้าม anime ID $anilist_id เพราะมี genre เป็น Ecchi หรือ Hentai\n";
            continue;
        }

        // ✅ ดึงชื่อ 3 ภาษา
        $title_en = $anime['title']['english'] ?? null;
        $title_romaji = $anime['title']['romaji'] ?? null;
        $title_native = $anime['title']['native'] ?? null;

        // ✅ อย่างน้อยต้องมีชื่อสักภาษา
        if (!$title_en && !$title_romaji && !$title_native) {
            echo "ข้าม anime ID $anilist_id เพราะไม่มีชื่อเลย\n";
            continue;
        }

        // ✅ ข้อมูลอื่น ๆ
        $cover_image = $anime['coverImage']['large'] ?? null;
        $format = $anime['format'] ?? null;
        $status = strtolower($anime['status'] ?? '');
        $episodes = $anime['episodes'] ?? null;
        $source = $anime['source'] ?? null;
        $season_name = $anime['season'] ?? null;
        $season_year = $anime['seasonYear'] ?? null;
        $synopsis = strip_tags($anime['description'] ?? '');
        $studio_name = $anime['studios']['nodes'][0]['name'] ?? null;

        // ✅ Season ID
        $season_id = null;
        if ($season_name && $season_year) {
            $stmt = $pdo->prepare("SELECT id FROM seasons WHERE season = ? AND year = ?");
            $stmt->execute([$season_name, $season_year]);
            $season_id = $stmt->fetchColumn();
            if (!$season_id) {
                $stmt = $pdo->prepare("INSERT INTO seasons (season, year) VALUES (?, ?)");
                $stmt->execute([$season_name, $season_year]);
                $season_id = $pdo->lastInsertId();
            }
        }

        // ✅ Source ID
        $source_id = null;
        if ($source) {
            $stmt = $pdo->prepare("SELECT id FROM sources WHERE code = ?");
            $stmt->execute([$source]);
            $source_id = $stmt->fetchColumn();
            if (!$source_id) {
                $stmt = $pdo->prepare("INSERT INTO sources (code) VALUES (?)");
                $stmt->execute([$source]);
                $source_id = $pdo->lastInsertId();
            }
        }

        // ✅ Status ID
        $status_id = null;
        if ($status) {
            $stmt = $pdo->prepare("SELECT id FROM statuses WHERE code = ?");
            $stmt->execute([$status]);
            $status_id = $stmt->fetchColumn();
            if (!$status_id) {
                $stmt = $pdo->prepare("INSERT INTO statuses (code) VALUES (?)");
                $stmt->execute([$status]);
                $status_id = $pdo->lastInsertId();
            }
        }

        // ✅ Studio ID
        $studio_id = null;
        if ($studio_name) {
            $stmt = $pdo->prepare("SELECT id FROM studios WHERE name = ?");
            $stmt->execute([$studio_name]);
            $studio_id = $stmt->fetchColumn();
            if (!$studio_id) {
                $stmt = $pdo->prepare("INSERT INTO studios (name) VALUES (?)");
                $stmt->execute([$studio_name]);
                $studio_id = $pdo->lastInsertId();
            }
        }

        // ✅ ตรวจสอบว่ามี anime นี้อยู่หรือยัง
        $stmtCheck = $pdo->prepare("SELECT id FROM anime WHERE anilist_id = ?");
        $stmtCheck->execute([$anilist_id]);
        $exists = $stmtCheck->fetchColumn();

        if ($exists) {
            // ✅ อัปเดต
            $stmt = $pdo->prepare("
                UPDATE anime SET
                    title_en = ?, title_romaji = ?, title_native = ?,
                    cover_image = ?, format = ?, status_id = ?, total_episodes = ?,
                    source_id = ?, season_id = ?, synopsis = ?, studio_id = ?
                WHERE anilist_id = ?
            ");
            $stmt->execute([
                $title_en, $title_romaji, $title_native,
                $cover_image, $format, $status_id, $episodes,
                $source_id, $season_id, $synopsis, $studio_id,
                $anilist_id
            ]);
        } else {
            // ✅ เพิ่มใหม่
            $stmt = $pdo->prepare("
                INSERT INTO anime (
                    anilist_id, title_en, title_romaji, title_native,
                    cover_image, format, status_id, total_episodes,
                    source_id, season_id, synopsis, studio_id
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $anilist_id, $title_en, $title_romaji, $title_native,
                $cover_image, $format, $status_id, $episodes,
                $source_id, $season_id, $synopsis, $studio_id
            ]);
        }

        // ✅ ลบ genre เก่าก่อนเพิ่มใหม่
        $stmt = $pdo->prepare("DELETE FROM anime_genres WHERE anime_id = (SELECT id FROM anime WHERE anilist_id = ?)");
        $stmt->execute([$anilist_id]);

        // ✅ ใส่ genre ใหม่
        foreach ($genres as $genreName) {
            $stmt = $pdo->prepare("SELECT id FROM genres WHERE name = ?");
            $stmt->execute([$genreName]);
            $genre_id = $stmt->fetchColumn();

            if (!$genre_id) {
                $stmt = $pdo->prepare("INSERT INTO genres (name) VALUES (?)");
                $stmt->execute([$genreName]);
                $genre_id = $pdo->lastInsertId();
            }

            $stmtAnimeId = $pdo->prepare("SELECT id FROM anime WHERE anilist_id = ?");
            $stmtAnimeId->execute([$anilist_id]);
            $anime_db_id = $stmtAnimeId->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO anime_genres (anime_id, genre_id) VALUES (?, ?)");
            $stmt->execute([$anime_db_id, $genre_id]);
        }

        echo "✔ Imported anime ID $anilist_id: " . ($title_en ?? $title_romaji ?? $title_native) . "\n";
    }
}

function apiRequest($query, $variables = []) {
    $url = 'https://graphql.anilist.co';
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode(['query' => $query, 'variables' => $variables]),
            'timeout' => 20
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === FALSE) {
        return null;
    }
    $json = json_decode($result, true);
    return $json['data'] ?? null;
}

// เรียกใช้
$seasons = ['WINTER', 'SPRING', 'SUMMER', 'FALL'];
$years = [2023, 2024, 2025];

foreach ($years as $year) {
    foreach ($seasons as $season) {
        fetchSeasonAnimes($season, $year, $pdo);
    }
}

echo "🎉 Import สำเร็จเรียบร้อย\n";
