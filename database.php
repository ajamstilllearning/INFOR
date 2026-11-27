<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$dsn = 'mysql:host=localhost;dbname=booking_wisata;charset=utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // error dilempar sebagai Exception
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,                  // prepared statement ASLI di MySQL
    PDO::ATTR_STRINGIFY_FETCHES  => false,
];

try {
    $pdo = new PDO($dsn, 'root', '', $options);
} catch (PDOException $e) {
    // JANGAN tampilkan detail error ke user (bisa bocorkan kredensial)
    error_log('[DB ERROR] ' . $e->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Silakan hubungi administrator.');
}