<?php
define('GOOGLE_API_KEY', 'AIzaSyDZfbE8FmPZK2uUK_4NpCTZ_PP5AuWV_V0');      // ← ใส่ Google API Key ของคุณ
define('GOOGLE_CX_ID', 'f2a8fb7b0309c49c2');
          // ← ใส่ Custom Search Engine ID ของคุณ

header('Content-Type: application/json');

$title = $_GET['title'] ?? '';
$id    = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$title && !$id) {
    echo json_encode(['error' => 'Missing title or id']);
    exit;
}

// ① ดึงข้อมูลจาก AniList
$aniResult = fetchFromAniList($title, $id);

// ตรวจสอบชื่อ หากใช้ title
if (!$aniResult) {
    echo json_encode(['error' => 'No result from AniList']);
    exit;
}

$aniResult['siteUrl'] = simplifyAniListUrl($aniResult['siteUrl'] ?? '');
$matchedTitle = $aniResult['title']['english'] ?? $aniResult['title']['romaji'] ?? $title;

// ตรวจสอบความคล้าย
if ($title && !isSimilarTitle($matchedTitle, $title)) {
    echo json_encode(['error' => "No close match found", 'matched' => $matchedTitle]);
    exit;
}

// ② ดึงข้อมูลลิงก์จาก Google
$googleResults = fetchFromGoogle($matchedTitle);

// ③ รวมผลลัพธ์
$response = [
    'title' => $title ?: $matchedTitle,
    'matched_title' => $matchedTitle,
    'anilist' => $aniResult,
    'google_results' => $googleResults
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);


// ─────────────── AniList ───────────────
function fetchFromAniList($title = '', $id = null) {
    $query = $id ? <<<GQL
    query (\$id: Int) {
      Media(id: \$id, type: ANIME) {
        id title { romaji english native }
        siteUrl
        description(asHtml: false)
        coverImage { medium }
        streamingEpisodes { site title url }
      }
    }
    GQL
    : <<<GQL
    query (\$search: String) {
      Media(search: \$search, type: ANIME) {
        id title { romaji english native }
        siteUrl
        description(asHtml: false)
        coverImage { medium }
        streamingEpisodes { site title url }
      }
    }
    GQL;

    $variables = $id ? ['id' => $id] : ['search' => $title];
    $payload = json_encode(['query' => $query, 'variables' => $variables]);

    $ch = curl_init('https://graphql.anilist.co');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $result = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($result, true);
    return $data['data']['Media'] ?? null;
}

function simplifyAniListUrl($url) {
    if (preg_match('/(https:\/\/anilist\.co\/anime\/\d+)/', $url, $match)) {
        return $match[1];
    }
    return $url;
}

function isSimilarTitle($fetched, $searched) {
    $f = mb_strtolower($fetched);
    $s = mb_strtolower($searched);
    similar_text($f, $s, $percent);
    return $percent >= 60; // ปรับเปอร์เซ็นต์ตามความเหมาะสม
}


// ─────────────── Google CSE ───────────────
function fetchFromGoogle($searchTitle) {
    // สร้าง query สำหรับค้นหา
    // รวมคำว่า "พากย์ไทย" หรือ "ซับไทย" หรือ "ซับ english"
    // จำกัดเฉพาะเว็บไซต์แพลตฟอร์มที่ถูกลิขสิทธิ์ในไทยหลัก ๆ
    $queryTerms = [
        "\"$searchTitle\"",
        "(พากย์ไทย OR ซับไทย OR \"ซับ english\")"
    ];

    $siteFilters = [
        '(site:netflix.com OR site:crunchyroll.com OR site:bilibili.tv OR site:iq.com OR site:museacg.com)'
    ];

    $query = implode(' ', $queryTerms) . ' ' . implode(' ', $siteFilters);
    $encodedQuery = urlencode($query);

    $url = "https://www.googleapis.com/customsearch/v1?q=$encodedQuery&key=" . GOOGLE_API_KEY . "&cx=" . GOOGLE_CX_ID;

    $opts = ["http" => ["method" => "GET", "header" => "User-Agent: Mozilla/5.0\r\n"]];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    $data = json_decode($response, true);

    $links = [];
    foreach ($data['items'] ?? [] as $item) {
        $link = $item['link'];
        $platform = detectPlatform($link);
        if (!$platform) continue;

        $titleLower = mb_strtolower($item['title'] ?? '');
        $descLower = mb_strtolower($item['snippet'] ?? '');

        // Logic กรองเพิ่มเติมตาม platform และ subtitle/dub language
        if ($platform === 'Crunchyroll') {
            // รับได้ทั้ง ซับไทย หรือ ซับอังกฤษ (ซับ eng จาก crunchyroll เท่านั้น)
            if (strpos($titleLower, 'sub english') !== false || strpos($titleLower, 'english sub') !== false || 
                strpos($descLower, 'sub english') !== false || strpos($descLower, 'english sub') !== false || 
                strpos($titleLower, 'ซับไทย') !== false || strpos($descLower, 'ซับไทย') !== false ||
                strpos($titleLower, 'พากย์ไทย') !== false || strpos($descLower, 'พากย์ไทย') !== false) {
                $links[] = [
                    'title' => $item['title'],
                    'platform' => $platform,
                    'link' => $link
                ];
            }
        } else {
            // สำหรับแพลตฟอร์มอื่นๆ รับเฉพาะพากย์ไทย หรือซับไทย เท่านั้น
            if (strpos($titleLower, 'ซับไทย') !== false || strpos($descLower, 'ซับไทย') !== false ||
                strpos($titleLower, 'พากย์ไทย') !== false || strpos($descLower, 'พากย์ไทย') !== false) {
                $links[] = [
                    'title' => $item['title'],
                    'platform' => $platform,
                    'link' => $link
                ];
            }
        }
    }
    return $links;
}

function detectPlatform($url) {
    $host = strtolower(parse_url($url, PHP_URL_HOST));
    $u = strtolower($url);
    if (strpos($host, 'netflix.com') !== false) return 'Netflix';
    if (strpos($host, 'crunchyroll.com') !== false) return 'Crunchyroll';
    if (strpos($host, 'bilibili.tv') !== false) return 'Bilibili';
    if (strpos($host, 'iq.com') !== false) return 'iQIYI';
    if (strpos($host, 'museacg.com') !== false) return 'Muse';
    return null;
}
?>
