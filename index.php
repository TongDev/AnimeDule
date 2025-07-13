<?php
session_start();
require 'config/database.php';

$user = null;
if (isset($_SESSION['user'])) {
    $stmtUser = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user']]);
    $user = $stmtUser->fetch();
}

// ดึงข้อมูลปีทั้งหมดจาก seasons
$years = $pdo->query("SELECT DISTINCT year FROM seasons ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);

$validSeasons = ['Winter', 'Spring', 'Summer', 'Fall'];

// ดึง genres ทั้งหมด
$genres = $pdo->query("SELECT id, name FROM genres ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// ดึง statuses ทั้งหมด (สมมติใช้ code กับ name_th)
$statuses = $pdo->query("SELECT code, name_th FROM statuses ORDER BY name_th ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <title>AnimeDule - หน้าแรก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css" />
    <style>
        #searchResults {
            max-height: 300px;
            overflow-y: auto;
            z-index: 1050;
        }
        .filter-section {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            background: #fff;
        }
        .filter-label {
            font-weight: 600;
            margin-top: 1rem;
        }
        /* Loading spinner ตรงกลาง */
        #loadingSpinner {
            display: none;
            text-align: center;
            padding: 30px 0;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/navbar.php'; ?>

<div class="container py-4">
    <h1 class="mb-4">ตาราง Anime ประจำฤดูกาล</h1>

    <form id="filterForm" class="mb-4">
        <div class="row gy-2 gx-3 align-items-center">
            <div class="col-md-2">
                <select name="year" id="filterYear" class="form-select" required>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= htmlspecialchars($year) ?>" <?= $year == date("Y") ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="season" id="filterSeason" class="form-select" required>
                    <?php foreach ($validSeasons as $s): ?>
                        <option value="<?= $s ?>" <?= $s == 'Summer' ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 position-relative">
                <input type="text" id="searchBox" name="search" class="form-control" placeholder="ค้นหา Anime..." autocomplete="off" />
                <div id="searchResults" class="list-group shadow position-absolute w-100" style="display:none;"></div>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary">กรอง</button>
            </div>
            <div class="col-md-12">
                <div class="row">
                    <div class="col-md-6">
                        <label class="filter-label">ประเภท (Genres)</label>
                        <div class="filter-section" id="genresFilter">
                            <?php foreach ($genres as $g): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="genre_<?= $g['id'] ?>" name="genres[]" value="<?= $g['id'] ?>">
                                    <label class="form-check-label" for="genre_<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="filter-label">สถานะ (Status)</label>
                        <div class="filter-section" id="statusesFilter">
                            <?php foreach ($statuses as $st): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="status_<?= htmlspecialchars($st['code']) ?>" name="statuses[]" value="<?= htmlspecialchars($st['code']) ?>">
                                    <label class="form-check-label" for="status_<?= htmlspecialchars($st['code']) ?>"><?= htmlspecialchars($st['name_th']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Loading Spinner -->
    <div id="loadingSpinner">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="animeList">
        <!-- รายการ Anime จะโหลดมาที่นี่ -->
        <div class="text-center py-5 text-muted">กำลังโหลดข้อมูล...</div>
    </div>

    <!-- Pagination -->
    <nav>
        <ul id="pagination" class="pagination justify-content-center"></ul>
    </nav>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('filterForm');
    const animeList = document.getElementById('animeList');
    const searchBox = document.getElementById('searchBox');
    const searchResults = document.getElementById('searchResults');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const pagination = document.getElementById('pagination');

    let currentPage = 1;

    // ฟังก์ชัน render รายการ Anime
    function renderAnimeList(animes) {
    if (animes.length === 0) {
        animeList.innerHTML = '<div class="alert alert-warning">ไม่พบ Anime ที่ตรงกับเงื่อนไข</div>';
        return;
    }
    let html = '<div class="row row-cols-1 row-cols-sm-2 row-cols-md-5 g-2">';
    animes.forEach(anime => {
        const title = anime.title || 'ไม่ระบุชื่อ';
        const cover = anime.cover_image || 'assets/img/default_cover.png';
        const totalEpisodes = anime.total_episodes || '-';
        const studios = anime.studios || 'ไม่ทราบ';

        // ตรวจสอบสถานะ favorite จากข้อมูล (สมมติ: anime.favorite === true/false)
        const isFav = anime.favorite === true;

        html += `
        <div class="col">
            <div class="card h-100 shadow-sm position-relative">
                <img src="${cover}" class="card-img-top" alt="Cover" style="height: 300px; object-fit: cover;">
                <div class="card-body">
                    <h5 class="card-title">${title}</h5>
                    <p class="card-text small">
                        <strong>Season:</strong> ${anime.season} ${anime.year}<br>
                        <strong>ตอน:</strong> ${totalEpisodes} | 
                        <strong>ผลิตโดย:</strong> ${studios}
                    </p>
                    <a href="anime.php?id=${anime.id}" class="btn btn-sm btn-outline-primary">ดูรายละเอียด</a>
                    <button class="btn btn-sm btn-outline-danger favorite-btn" 
                        data-id="${anime.id}" aria-label="Favorite" 
                        title="${isFav ? 'ยกเลิก Favorite' : 'เพิ่ม Favorite'}"
                        style="position: absolute; top: 10px; right: 10px;">
                        <i class="bi ${isFav ? 'bi-heart-fill' : 'bi-heart'}"></i>
                    </button>
                </div>
            </div>
        </div>`;
    });
    html += '</div>';
    animeList.innerHTML = html;
}

    // ฟังก์ชันแสดง pagination
    function renderPagination(totalPages) {
        if (totalPages <= 1) {
            pagination.innerHTML = '';
            return;
        }
        let html = '';

        // ปุ่มก่อนหน้า
        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">&laquo;</a>
                 </li>`;

        for (let p = 1; p <= totalPages; p++) {
            html += `<li class="page-item ${p === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${p}">${p}</a>
                    </li>`;
        }

        // ปุ่มถัดไป
        html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Next">&raquo;</a>
                 </li>`;

        pagination.innerHTML = html;
    }

    // โหลดข้อมูลผ่าน AJAX
    async function loadAnimeList(params) {
        try {
            loadingSpinner.style.display = 'block';
            animeList.innerHTML = '';
            pagination.innerHTML = '';

            const urlParams = new URLSearchParams(params);
            const res = await fetch('api/search_filter.php?' + urlParams.toString());
            if (!res.ok) throw new Error('Network error');
            const json = await res.json();

            if (json.error) {
                animeList.innerHTML = `<div class="alert alert-danger">${json.error}</div>`;
                loadingSpinner.style.display = 'none';
                return;
            }

            renderAnimeList(json.data);
            renderPagination(json.total_pages);

            loadingSpinner.style.display = 'none';
        } catch (error) {
            animeList.innerHTML = `<div class="alert alert-danger">เกิดข้อผิดพลาด: ${error.message}</div>`;
            loadingSpinner.style.display = 'none';
        }
    }

    // เริ่มโหลดข้อมูลตอนเปิดหน้า (ใช้ปี-ฤดูกาลเริ่มต้น)
    function loadCurrentFilters() {
        const formData = new FormData(filterForm);
        const params = {};

        params.year = formData.get('year');
        params.season = formData.get('season');
        const search = formData.get('search')?.trim();
        if (search) params.search = search;

        const genres = formData.getAll('genres[]');
        if (genres.length > 0) params.genres = genres;

        const statuses = formData.getAll('statuses[]');
        if (statuses.length > 0) params.statuses = statuses;

        params.page = currentPage;

        loadAnimeList(params);
    }

    // ฟอร์ม submit กรอง
    filterForm.addEventListener('submit', e => {
        e.preventDefault();
        currentPage = 1;
        loadCurrentFilters();
    });

    // pagination click
    pagination.addEventListener('click', e => {
        if (e.target.tagName !== 'A') return;
        e.preventDefault();
        const page = Number(e.target.dataset.page);
        if (page < 1) return;
        currentPage = page;
        loadCurrentFilters();
        window.scrollTo({top: 0, behavior: 'smooth'});
    });

    // เรียกโหลดตอนเปิดหน้า
    loadCurrentFilters();


    // --- Search box autocomplete (เหมือนเดิม) ---
    searchBox.addEventListener('input', async () => {
        const query = searchBox.value.trim();
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        try {
            const res = await fetch(`api/search_anime.php?q=${encodeURIComponent(query)}`);
            if (!res.ok) throw new Error('Network response was not ok');
            const data = await res.json();
            if (!Array.isArray(data)) throw new Error('Invalid JSON response');
            
            if (data.length === 0) {
                searchResults.innerHTML = '<div class="list-group-item text-muted">ไม่พบผลลัพธ์</div>';
            } else {
                searchResults.innerHTML = data.map(anime => 
                    `<a href="anime.php?id=${anime.id}" class="list-group-item list-group-item-action">${anime.title_en || anime.title_romaji || anime.title_native}</a>`
                ).join('');
            }
            searchResults.style.display = 'block';
        } catch (error) {
            console.error('Search Error:', error);
            searchResults.style.display = 'none';
        }
    });

    document.addEventListener('click', e => {
        if (!searchBox.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
