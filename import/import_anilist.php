<?php
require_once __DIR__ . '/../config/database.php';

define('YOUTUBE_API_KEY', 'AIzaSyBUDgHyeLyBLq4a9fNO3GiBgzET3ZUakn0');
define('GOOGLE_API_KEY', 'AIzaSyDZfbE8FmPZK2uUK_4NpCTZ_PP5AuWV_V0');
define('GOOGLE_CSE_ID', 'f2a8fb7b0309c49c2');

function getYoutubePlaylistLink($title) {
    $channelIds = [
        'Ani-One Thailand' => 'UC0VOyT2OCBKdQhGM3h5j3pA',
        'Muse Thailand' => 'UCgdwtyqBunlRb-i-7PnCssQ'
    ];
    $keywordWhitelist = ['ep', 'episode', 'ตอนที่', 'เต็มเรื่อง', 'watch'];

    foreach ($channelIds as $channelName => $channelId) {
        $url = "https://www.googleapis.com/youtube/v3/search?" . http_build_query([
            'part' => 'snippet',
            'channelId' => $channelId,
            'q' => $title,
            'type' => 'playlist',
            'maxResults' => 5,
            'key' => YOUTUBE_API_KEY,
        ]);

        $response = @file_get_contents($url);
        if (!$response) continue;

        $data = json_decode($response, true);
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $playlistId = $item['id']['playlistId'] ?? null;
                $playlistTitle = strtolower($item['snippet']['title'] ?? '');

                foreach ($keywordWhitelist as $keyword) {
                    if (strpos($playlistTitle, $keyword) !== false) {
                        return [
                            'platform' => 'YouTube',
                            'url' => "https://www.youtube.com/playlist?list={$playlistId}",
                            'source' => $channelName
                        ];
                    }
                }
            }
        }
    }
    return null;
}

function getStreamingLinkFromGoogleCSE($title) {
    $searchQuery = urlencode($title . ' anime watch site');
    $url = "https://www.googleapis.com/customsearch/v1?q={$searchQuery}&key=" . GOOGLE_API_KEY . "&cx=" . GOOGLE_CSE_ID . "&num=3";

    $response = json_decode(file_get_contents($url), true);
    if (!empty($response['items'])) {
        foreach ($response['items'] as $item) {
            $link = $item['link'];
            if (strpos($link, 'netflix.com') !== false) {
                return ['platform' => 'Netflix', 'url' => $link, 'source' => 'cse'];
            } elseif (strpos($link, 'bilibili.tv') !== false) {
                return ['platform' => 'Bilibili', 'url' => $link, 'source' => 'cse'];
            }
        }
    }
    return null;
}

function savePlatformLink($pdo, $animeId, $linkData) {
    $stmtPlatform = $pdo->prepare("SELECT id FROM platforms WHERE name = ?");
    $stmtPlatform->execute([$linkData['platform']]);
    $platformId = $stmtPlatform->fetchColumn();

    if (!$platformId) {
        $stmtInsertPlatform = $pdo->prepare("INSERT INTO platforms (name) VALUES (?)");
        $stmtInsertPlatform->execute([$linkData['platform']]);
        $platformId = $pdo->lastInsertId();
    }

    $stmtCheck = $pdo->prepare("SELECT id FROM anime_platforms WHERE anime_id = ? AND platform_id = ? AND url = ?");
    $stmtCheck->execute([$animeId, $platformId, $linkData['url']]);
    $exists = $stmtCheck->fetchColumn();

    if (!$exists) {
        $stmtInsertAnimePlatform = $pdo->prepare("
            INSERT INTO anime_platforms (anime_id, platform_id, url, source)
            VALUES (?, ?, ?, ?)
        ");
        $stmtInsertAnimePlatform->execute([$animeId, $platformId, $linkData['url'], $linkData['source']]);
    }
}

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
                    title {
                        romaji
                        english
                        native
                    }
                    episodes
                    startDate {
                        year
                        month
                        day
                    }
                    studios {
                        nodes {
                            name
                        }
                    }
                    source
                    status
                    nextAiringEpisode {
                        airingAt
                    }
                    genres
                    format
                    description
                    coverImage {
                        large
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

        if (isset($result['data']['Page']['media'])) {
            $animeList = array_slice($result['data']['Page']['media'], 0, 5);

            foreach ($animeList as $anime) {
                if (in_array('Ecchi', $anime['genres']) || in_array('Hentai', $anime['genres'])) {
                    continue;
                }

                $title_en = $anime['title']['english'] ?? $anime['title']['romaji'] ?? $anime['title']['native'] ?? 'Unknown Title';
                $title_native = $anime['title']['native'] ?? null;
                $title_romaji = $anime['title']['romaji'] ?? null;
                $cover_image = $anime['coverImage']['large'] ?? null;
                $episodes = $anime['episodes'] ?? null;
                $nextAirTime = isset($anime['nextAiringEpisode']['airingAt']) ? date('Y-m-d H:i:s', $anime['nextAiringEpisode']['airingAt']) : null;
                $format = $anime['format'] ?? null;
                $synopsis = $anime['description'] ?? null;

                $studioNodes = $anime['studios']['nodes'] ?? [];
                $studioIds = [];

                foreach ($studioNodes as $studio) {
                    $studioName = trim($studio['name']);
                    if ($studioName === '') continue;

                    $stmt = $pdo->prepare("SELECT id FROM studios WHERE name = ?");
                    $stmt->execute([$studioName]);
                    $studioId = $stmt->fetchColumn();

                    if (!$studioId) {
                        $stmtInsert = $pdo->prepare("INSERT INTO studios (name) VALUES (?)");
                        $stmtInsert->execute([$studioName]);
                        $studioId = $pdo->lastInsertId();
                    }

                    $studioIds[] = $studioId;
                }

                $statusName = $anime['status'] ?? 'UNKNOWN';
                $stmtStatus = $pdo->prepare("SELECT id FROM statuses WHERE code = ?");
                $stmtStatus->execute([$statusName]);
                $statusId = $stmtStatus->fetchColumn();
                if (!$statusId) {
                    $stmtInsertStatus = $pdo->prepare("INSERT INTO statuses (code, name_th) VALUES (?, ?)");
                    $stmtInsertStatus->execute([$statusName, null]);
                    $statusId = $pdo->lastInsertId();
                }

                $sourceName = $anime['source'] ?? 'UNKNOWN';
                $stmtSource = $pdo->prepare("SELECT id FROM sources WHERE code = ?");
                $stmtSource->execute([$sourceName]);
                $sourceId = $stmtSource->fetchColumn();
                if (!$sourceId) {
                    $stmtInsertSource = $pdo->prepare("INSERT INTO sources (code, name_th) VALUES (?, ?)");
                    $stmtInsertSource->execute([$sourceName, null]);
                    $sourceId = $pdo->lastInsertId();
                }

                $stmtSeason = $pdo->prepare("SELECT id FROM seasons WHERE season = ? AND year = ?");
                $stmtSeason->execute([$season, $year]);
                $seasonId = $stmtSeason->fetchColumn();
                if (!$seasonId) {
                    $stmtInsertSeason = $pdo->prepare("INSERT INTO seasons (season, year) VALUES (?, ?)");
                    $stmtInsertSeason->execute([$season, $year]);
                    $seasonId = $pdo->lastInsertId();
                }

                $stmtCheckAnime = $pdo->prepare("SELECT id FROM anime WHERE anilist_id = ?");
                $stmtCheckAnime->execute([$anime['id']]);
                $existingAnimeId = $stmtCheckAnime->fetchColumn();

                if ($existingAnimeId) {
                    $animeId = $existingAnimeId;
                } else {
                    $stmtInsertAnime = $pdo->prepare("
                        INSERT INTO anime (anilist_id, title_en, title_native, title_romaji, cover_image, total_episodes, season_id, next_episode_air_time, source_id, status_id, synopsis, format)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtInsertAnime->execute([
                        $anime['id'],
                        $title_en,
                        $title_native,
                        $title_romaji,
                        $cover_image,
                        $episodes,
                        $seasonId,
                        $nextAirTime,
                        $sourceId,
                        $statusId,
                        $synopsis,
                        $format
                    ]);
                    $animeId = $pdo->lastInsertId();
                }

                // เชื่อมโยง anime กับทุก studio
                foreach ($studioIds as $studioId) {
                    $stmtLink = $pdo->prepare("INSERT IGNORE INTO anime_studios (anime_id, studio_id) VALUES (?, ?)");
                    $stmtLink->execute([$animeId, $studioId]);
                }

                $linkData = getYoutubePlaylistLink($title_en);
                if (!$linkData) {
                    $linkData = getStreamingLinkFromGoogleCSE($title_en);
                }

                if ($linkData) {
                    savePlatformLink($pdo, $animeId, $linkData);
                }
            }
        }
    }
}

echo "Import completed.\n";
