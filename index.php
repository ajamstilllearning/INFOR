<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

$properti = $pdo->query(
    'SELECT p.*, u.nama AS nama_pemilik
     FROM properti p INNER JOIN users u ON u.id = p.pemilik_id
     ORDER BY p.id DESC'
)->fetchAll();

$user = current_user();
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Booking Wisata</title>
<style>
 body{font-family:system-ui;background:#f1f5f9;padding:32px;margin:0}
 .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;max-width:1100px;margin:auto}
 .item{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.08)}
 .item img{width:100%;height:170px;object-fit:cover}
 .item .body{padding:14px}
 .btn{display:inline-block;margin-top:8px;padding:8px 16px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-size:14px}
 .top{max-width:1100px;margin:0 auto 20px;display:flex;justify-content:space-between;align-items:center}
</style></head><body>
<div class="top">
  <h2>Daftar Properti Wisata & Penginapan</h2>
  <div>
    <?php if ($user): ?>
      <span style="font-size:14px">Halo, <b><?= e($user['nama']) ?></b></span>
      <a class="btn" href="logout.php">Keluar</a>
    <?php else: ?>
      <a class="btn" href="login.php">Masuk</a>
      <a class="btn" href="register.php">Daftar</a>
    <?php endif; ?>
  </div>
</div>
<div class="grid">
<?php foreach ($properti as $p): ?>
  <div class="item">
    <img src="<?= e(UPLOAD_URL . $p['gambar']) ?>" alt="">
    <div class="body">
      <h3 style="margin:0 0 6px"><?= e($p['nama_properti']) ?></h3>
      <div style="color:#059669;font-weight:700"><?= e(rupiah((float)$p['harga_per_malam'])) ?> / malam</div>
      <div style="font-size:13px;color:#64748b">oleh <?= e($p['nama_pemilik']) ?></div>
      <a class="btn" href="booking.php?properti_id=<?= (int)$p['id'] ?>">Booking</a>
    </div>
  </div>
<?php endforeach; ?>
</div>
</body></html>