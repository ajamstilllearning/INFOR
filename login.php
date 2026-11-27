<?php // ================= login.php =================
declare(strict_types=1);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $errors[] = 'Email atau password salah.';   // pesan generik (anti user-enumeration)
    } elseif ((int)$user['is_verified'] !== 1) {
        redirect('verifikasi_otp.php?email=' . urlencode($email));
    } else {
        session_regenerate_id(true);                // cegah session fixation
        $_SESSION['user_id'] = (int)$user['id'];
        redirect($user['role'] === 'pemilik' ? 'upload_properti.php' : 'index.php');
    }
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Login</title>
<style>body{font-family:system-ui;background:#f1f5f9;padding:40px}
.card{max-width:400px;margin:auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08)}
input{width:100%;padding:10px;margin:6px 0;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
button{width:100%;padding:12px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-weight:600;margin-top:10px;cursor:pointer}
.err{background:#fee2e2;color:#991b1b;padding:10px;border-radius:8px;font-size:14px;margin-bottom:8px}</style></head>
<body><div class="card"><h2>Masuk</h2>
<?php if (($_GET['status'] ?? '') === 'verified'): ?>
  <div style="background:#dcfce7;color:#166534;padding:10px;border-radius:8px;margin-bottom:8px">Akun berhasil diverifikasi. Silakan masuk.</div>
<?php endif; ?>
<?php foreach ($errors as $err): ?><div class="err"><?= e($err) ?></div><?php endforeach; ?>
<form method="post"><?= csrf_field() ?>
  <input type="email" name="email" placeholder="Email" required>
  <input type="password" name="password" placeholder="Password" required>
  <button type="submit">Masuk</button>
</form>
<p style="font-size:14px">Belum punya akun? <a href="register.php">Daftar</a></p>
</div></body></html>