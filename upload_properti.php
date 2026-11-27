<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

require_login();
require_role('pemilik');          // HANYA role 'pemilik' yang boleh akses

$user    = current_user();
$errors  = [];
$sukses  = '';
$old     = ['nama_properti' => '', 'deskripsi' => '', 'harga_per_malam' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $nama      = trim($_POST['nama_properti'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $harga     = $_POST['harga_per_malam'] ?? '';

    $old = ['nama_properti' => $nama, 'deskripsi' => $deskripsi, 'harga_per_malam' => $harga];

    // ---- Validasi input teks ----
    if (mb_strlen($nama) < 3) {
        $errors[] = 'Nama properti minimal 3 karakter.';
    }
    if (!is_numeric($harga) || (float)$harga <= 0) {
        $errors[] = 'Harga per malam harus berupa angka lebih dari 0.';
    }

    // ---- Validasi & simpan gambar (aman) ----
    $namaFile = null;
    if (!$errors) {
        try {
            $namaFile = simpan_gambar_aman($_FILES['gambar'] ?? []);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    // ---- Simpan ke database ----
    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO properti (pemilik_id, nama_properti, deskripsi, harga_per_malam, gambar)
             VALUES (:pemilik_id, :nama, :deskripsi, :harga, :gambar)'
        );
        $stmt->execute([
            ':pemilik_id' => $user['id'],
            ':nama'       => $nama,
            ':deskripsi'  => $deskripsi !== '' ? $deskripsi : null,
            ':harga'      => (float)$harga,
            ':gambar'     => $namaFile,
        ]);

        $sukses = 'Properti berhasil diunggah.';
        $old    = ['nama_properti' => '', 'deskripsi' => '', 'harga_per_malam' => ''];
    }
}

// Daftar properti milik pemilik ini
$stmt = $pdo->prepare('SELECT * FROM properti WHERE pemilik_id = :id ORDER BY id DESC');
$stmt->execute([':id' => $user['id']]);
$propertiSaya = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Upload Properti</title>
    <style>
        body{font-family:system-ui,sans-serif;background:#f1f5f9;margin:0;padding:32px}
        .card{max-width:640px;margin:auto 0 24px;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08)}
        label{display:block;margin:12px 0 4px;font-weight:600;font-size:14px}
        input,textarea{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
        button{margin-top:16px;padding:12px 24px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-weight:600;cursor:pointer}
        .err{background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;margin-bottom:8px;font-size:14px}
        .ok{background:#dcfce7;color:#166534;padding:10px 14px;border-radius:8px;margin-bottom:8px;font-size:14px}
        table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
        th,td{padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:14px;text-align:left}
        th{background:#f8fafc}
        img.thumb{width:64px;height:48px;object-fit:cover;border-radius:6px}
    </style>
</head>
<body>
<div class="card">
    <h2>Upload Properti / Wisata / Penginapan</h2>

    <?php foreach ($errors as $err): ?>
        <div class="err"><?= e($err) ?></div>
    <?php endforeach; ?>
    <?php if ($sukses): ?>
        <div class="ok"><?= e($sukses) ?></div>
    <?php endif; ?>

    <!-- enctype multipart WAJIB untuk upload file -->
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <label>Nama Properti</label>
        <input type="text" name="nama_properti" value="<?= e($old['nama_properti']) ?>" required minlength="3">

        <label>Deskripsi</label>
        <textarea name="deskripsi" rows="4"><?= e($old['deskripsi']) ?></textarea>

        <label>Harga per Malam (Rp)</label>
        <input type="number" name="harga_per_malam" value="<?= e($old['harga_per_malam']) ?>" min="1" step="1000" required>

        <label>Foto Properti (JPG/JPEG/PNG, maks 2MB)</label>
        <input type="file" name="gambar" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>

        <button type="submit">Unggah Properti</button>
    </form>
</div>

<div class="card">
    <h3>Properti Saya</h3>
    <?php if (!$propertiSaya): ?>
        <p style="color:#64748b">Belum ada properti yang diunggah.</p>
    <?php else: ?>
        <table>
            <tr><th>Foto</th><th>Nama</th><th>Harga/Malam</th></tr>
            <?php foreach ($propertiSaya as $p): ?>
                <tr>
                    <td><img class="thumb" src="<?= e(UPLOAD_URL . $p['gambar']) ?>" alt=""></td>
                    <td><?= e($p['nama_properti']) ?></td>
                    <td><?= e(rupiah((float)$p['harga_per_malam'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
</body>
</html>