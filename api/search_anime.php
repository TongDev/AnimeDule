<?php
session_start();
require '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$q = $_GET['q'] ?? '';
$q = trim($q);

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

// ใช้ LIKE search แบบปลอดภัย ป้องกัน SQL Injection ด้วย prepared statement
$searchTerm = "%$q%";

$stmt = $pdo->prepare("
    SELECT id, title_en, title_romaji, title_native 
    FROM anime 
    WHERE title_en LIKE ? OR title_romaji LIKE ? OR title_native LIKE ?
    ORDER BY title_en ASC
    LIMIT 10
");
$stmt->execute([$searchTerm, $searchTerm, $searchTerm]);

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);
