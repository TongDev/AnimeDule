<?php
$host = "localhost";
$dbname = "animedule";  // ใช้ชื่อนี้!
$username = "root";
$password = ""; // XAMPP ปกติจะไม่ตั้งรหัสผ่าน

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}
?>
