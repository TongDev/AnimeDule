<?php
require '../config/database.php';

$search = $_GET['q'] ?? '';

if (strlen($search) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, title_en FROM anime WHERE title_en LIKE ? ORDER BY title_en LIMIT 10");
$stmt->execute(["%$search%"]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($results);
