<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';

$email  = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ''));
$errors = [];
$info   = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['kirim'] ?? '') === 'gagal') {
    $errors[] = 'Gagal mengirim email OTP. Periksa konfigurasi SMTP lalu klik "Kirim ulang kode OTP".';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit('Email tidak valid. Silakan ulangi pendaftaran.');
}

// Ambil user berdasarkan email (prepared statement)
$stmt = $pdo->prepare('SELECT id, nama, otp_code, otp_expired_at, is_verified FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user) {
    exit('Akun tidak ditemukan.');
}
if ((int)$user['is_verified'] === 1) {
    redirect('login.php?status=verified');
}

// ---------- Aksi: KIRIM ULANG OTP ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'kirim_ulang') {
    csrf_verify();
    $otpBaru  = (string) random_int(100000, 999999);
    $expired  = date('Y-m-d H:i:s', time() + OTP_TTL);

    $upd = $pdo->prepare('UPDATE users SET otp_code = :otp, otp_expired_at = :exp WHERE id = :id');
    $upd->execute([':otp' => $otpBaru, ':exp' => $expired, ':id' => $user['id']]);

    if (kirim_email_otp($email, $user['nama'], $otpBaru)) {
        $info = 'Kode OTP baru telah dikirim ke email Anda.';
    } else {
        $errors[] = 'Gagal mengirim email OTP. Periksa konfigurasi SMTP lalu coba lagi.';
    }
}

// ---------- Aksi: VERIFIKASI OTP ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'verifikasi') {
    csrf_verify();
    $otpInput = trim($_POST['otp'] ?? '');

    if (!preg_match('/^\d{6}$/', $otpInput)) {
        $errors[] = 'Kode OTP harus 6 digit angka.';
    } elseif ($user['otp_code'] === null || !hash_equals((string)$user['otp_code'], $otpInput)) {
        $errors[] = 'Kode OTP salah.';
    } elseif (strtotime((string)$user['otp_expired_at']) < time()) {
        $errors[] = 'Kode OTP sudah kedaluwarsa. Silakan kirim ulang.';
    }

    if (!$errors) {
        // Verifikasi sukses → aktifkan akun & hapus OTP
        $upd = $pdo->prepare(
            'UPDATE users SET is_verified = 1, otp_code = NULL, otp_expired_at = NULL WHERE id = :id'
        );
        $upd->execute([':id' => $user['id']]);

        redirect('login.php?status=verified');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Verifikasi OTP</title>
    <style>
        body{font-family:system-ui,sans-serif;background:#f1f5f9;margin:0;padding:40px}
        .card{max-width:420px;margin:auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08);text-align:center}
        input{width:100%;padding:14px;font-size:22px;text-align:center;letter-spacing:10px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
        button{margin-top:16px;width:100%;padding:12px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-weight:600;cursor:pointer}
        .err{background:#fee2e2;color:#991b1b;padding:10px;border-radius:8px;margin-bottom:10px;font-size:14px}
        .info{background:#dbeafe;color:#1e40af;padding:10px;border-radius:8px;margin-bottom:10px;font-size:14px}
        .link{background:none;border:0;color:#2563eb;cursor:pointer;text-decoration:underline;font-size:14px;margin-top:12px}
    </style>
</head>
<body>
<div class="card">
    <h2>Verifikasi Email</h2>
    <p style="color:#64748b;font-size:14px">Kode 6 digit telah dikirim ke<br><b><?= e($email) ?></b></p>

    <?php foreach ($errors as $err): ?>
        <div class="err"><?= e($err) ?></div>
    <?php endforeach; ?>
    <?php if ($info): ?>
        <div class="info"><?= e($info) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <input type="hidden" name="aksi" value="verifikasi">
        <input type="text" name="otp" maxlength="6" inputmode="numeric" pattern="\d{6}"
               placeholder="000000" required autofocus>
        <button type="submit">Verifikasi</button>
    </form>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <input type="hidden" name="aksi" value="kirim_ulang">
        <button type="submit" class="link">Kirim ulang kode OTP</button>
    </form>
</div>
</body>
</html>