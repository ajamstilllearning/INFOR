<?php
declare(strict_types=1);

// ---- Session hardening ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
    ]);
    session_start();
}

// ---- Konstanta aplikasi ----
define('BASE_URL',     'http://localhost/booking-wisata');
define('UPLOAD_DIR',   __DIR__ . '/uploads/');
define('UPLOAD_URL',   BASE_URL . '/uploads/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB
define('OTP_TTL',      300);                // OTP berlaku 5 menit
define('UNVERIFIED_TTL', 86400);            // akun belum verifikasi dihapus 24 jam setelah OTP kedaluwarsa

// ---- Konfigurasi SMTP (PHPMailer) ----
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'emailanda@gmail.com');
define('SMTP_PASS',      'app-password-anda');   // gunakan App Password Gmail
define('SMTP_FROM',      'no-reply@booking-wisata.test');
define('SMTP_FROM_NAME', 'Booking Wisata');

date_default_timezone_set('Asia/Jakarta');