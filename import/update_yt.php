<?php
require_once __DIR__ . '/../config/database.php';

define('YOUTUBE_API_KEY', 'AIzaSyBUDgHyeLyBLq4a9fNO3GiBgzET3ZUakn0');

// ช่องที่อนุญาตให้ค้นหา
$channelIds = [
    'Ani-One Thailand' => 'UC0VOyT2OCBKdQhGM3h5j3pA',
    'Muse Thailand' => 'UCgdwtyqBunlRb-i-7PnCssQ'
];

// Keyword ที่ไว้ตรวจสอบว่า Playlist น่าจะเป็นเนื้อหาหลัก (ไม่ใช่ Trailer)
$keywordWhitelist = ['ep', 'episode', 'ตอนที่', 'เต็มเรื่อง', 'watch'];

function findValidYouTubePlaylist($title) {
    global $channelIds, $keywordWhitelist;

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

// ดึงอนิเมะทั้งหมดในฐานข้อมูล
$stmt = $pdo->prepare("SELECT id, title_en FROM anime WHERE youtube_link IS NULL");
$stmt->execute();
$animes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($animes as $anime) {
    $title = $anime['title_en'];
    $animeId = $anime['id'];

    $playlistData = findValidYouTubePlaylist($title);

    if ($playlistData) {
        $youtubeLink = $playlistData['url'];

        // บันทึกในตาราง anime (youtube_link)
        $updateStmt = $pdo->prepare("UPDATE anime SET youtube_link = ? WHERE id = ?");
        $updateStmt->execute([$youtubeLink, $animeId]);

        echo "✅ Updated: {$title} with YouTube playlist link: {$youtubeLink}\n";
    } else {
        echo "❌ No valid playlist found for {$title}\n";
    }
}

echo "🎬 YouTube playlist update completed.\n";
