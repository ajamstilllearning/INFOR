<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';

$errors = [];
$old    = ['nama' => '', 'email' => '', 'role' => 'penyewa', 'no_wa' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $nama       = trim($_POST['nama'] ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $password   = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';
    $role       = $_POST['role'] ?? 'penyewa';
    $noWaRaw    = trim($_POST['no_wa'] ?? '');

    $old = ['nama' => $nama, 'email' => $email, 'role' => $role, 'no_wa' => $noWaRaw];

    // ---- Validasi dasar ----
    if (mb_strlen($nama) < 3) {
        $errors[] = 'Nama minimal 3 karakter.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }
    if ($password !== $konfirmasi) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }
    if (!in_array($role, ['pemilik', 'penyewa'], true)) {
        $errors[] = 'Role tidak valid.';
    }

    $noWa = '';
    if ($role === 'pemilik') {
        $noWa = normalize_no_wa($noWaRaw);
        if (strlen($noWa) < 10) {
            $errors[] = 'Nomor WhatsApp pemilik wajib diisi dengan benar.';
        }
    }

    // ---- CEK DUPLIKASI EMAIL (PDO Prepared Statement) ----
    // Email yang belum terverifikasi tidak dihitung: datanya ditimpa pendaftaran baru.
    $userLamaId = null;
    if (!$errors) {
        hapus_akun_belum_verifikasi($pdo);

        $stmt = $pdo->prepare('SELECT id, is_verified FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $userLama = $stmt->fetch();
        if ($userLama && (int)$userLama['is_verified'] === 1) {
            $errors[] = 'Email sudah terdaftar. Silakan gunakan email lain.';
        } elseif ($userLama) {
            $userLamaId = (int)$userLama['id'];
        }
    }

    // ---- Simpan user baru + generate OTP ----
    if (!$errors) {
        $otp      = (string) random_int(100000, 999999);       // 6 digit acak
        $hash     = password_hash($password, PASSWORD_DEFAULT); // bcrypt
        $expired  = date('Y-m-d H:i:s', time() + OTP_TTL);
        $params   = [
            ':nama'     => $nama,
            ':email'    => $email,
            ':password' => $hash,
            ':role'     => $role,
            ':no_wa'    => $noWa !== '' ? $noWa : null,
            ':otp'      => $otp,
            ':expired'  => $expired,
        ];

        try {
            if ($userLamaId !== null) {
                $stmt = $pdo->prepare(
                    'UPDATE users SET nama = :nama, email = :email, password = :password, role = :role,
                            no_wa = :no_wa, otp_code = :otp, otp_expired_at = :expired
                     WHERE id = :id AND is_verified = 0'
                );
                $stmt->execute($params + [':id' => $userLamaId]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (nama, email, password, role, no_wa, otp_code, otp_expired_at, is_verified)
                     VALUES (:nama, :email, :password, :role, :no_wa, :otp, :expired, 0)'
                );
                $stmt->execute($params);
            }
        } catch (PDOException $e) {
            // UNIQUE constraint (race condition saat dua user daftar bersamaan)
            if ($e->getCode() === '23000') {
                $errors[] = 'Email sudah terdaftar. Silakan gunakan email lain.';
            } else {
                error_log('[REGISTER ERROR] ' . $e->getMessage());
                $errors[] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        }

        if (!$errors) {
            $terkirim = kirim_email_otp($email, $nama, $otp);
            redirect('verifikasi_otp.php?email=' . urlencode($email) . ($terkirim ? '' : '&kirim=gagal'));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Akun</title>
    <style>
        body{font-family:system-ui,sans-serif;background:#f1f5f9;margin:0;padding:40px}
        .card{max-width:480px;margin:auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08)}
        label{display:block;margin:12px 0 4px;font-weight:600;font-size:14px}
        input,select{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
        button{margin-top:18px;width:100%;padding:12px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-weight:600;cursor:pointer}
        .err{background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:14px}
    </style>
</head>
<body>
<div class="card">
    <h2>Daftar Akun</h2>

    <?php foreach ($errors as $err): ?>
        <div class="err"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" autocomplete="off">
        <?= csrf_field() ?>

        <label>Nama Lengkap</label>
        <input type="text" name="nama" value="<?= e($old['nama']) ?>" required minlength="3">

        <label>Email</label>
        <input type="email" name="email" value="<?= e($old['email']) ?>" required>

        <label>Password (min. 8 karakter)</label>
        <input type="password" name="password" required minlength="8">

        <label>Konfirmasi Password</label>
        <input type="password" name="konfirmasi_password" required minlength="8">

        <label>Daftar Sebagai</label>
        <select name="role" id="role" onchange="toggleWa()">
            <option value="penyewa" <?= $old['role'] === 'penyewa' ? 'selected' : '' ?>>Penyewa</option>
            <option value="pemilik" <?= $old['role'] === 'pemilik' ? 'selected' : '' ?>>Pemilik</option>
        </select>

        <div id="wrapWa" style="display:<?= $old['role'] === 'pemilik' ? 'block' : 'none' ?>">
            <label>Nomor WhatsApp (untuk pembayaran)</label>
            <input type="text" name="no_wa" value="<?= e($old['no_wa']) ?>" placeholder="08xxxxxxxxxx">
        </div>

        <button type="submit">Daftar & Kirim OTP</button>
    </form>
</div>
<script>
function toggleWa() {
    document.getElementById('wrapWa').style.display =
        document.getElementById('role').value === 'pemilik' ? 'block' : 'none';
}
</script>
</body>
</html>