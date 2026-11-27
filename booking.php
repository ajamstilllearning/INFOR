<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

require_login();
$user = current_user();

/* =========================================================
   BAGIAN A: TAMPILKAN DETAIL RESERVASI + TOMBOL WHATSAPP
   ========================================================= */
if (isset($_GET['id'])) {
    $bookingId = (int)$_GET['id'];

    $stmt = $pdo->prepare(
        'SELECT b.id, b.tanggal_mulai, b.tanggal_selesai, b.total_harga, b.status,
                p.nama_properti, p.harga_per_malam,
                u.nama AS nama_pemilik, u.no_wa AS wa_pemilik
         FROM bookings b
         INNER JOIN properti p ON p.id = b.properti_id
         INNER JOIN users u     ON u.id = p.pemilik_id
         WHERE b.id = :id AND b.penyewa_id = :uid
         LIMIT 1'
    );
    $stmt->execute([':id' => $bookingId, ':uid' => $user['id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        http_response_code(404);
        exit('Reservasi tidak ditemukan.');
    }

    $malam = (int)((strtotime($booking['tanggal_selesai']) - strtotime($booking['tanggal_mulai'])) / 86400);

    // ---- Bangun pesan WhatsApp otomatis (URL Encoded) ----
    $pesanWa =
        "Halo {$booking['nama_pemilik']}, saya ingin melakukan pembayaran reservasi.\n\n" .
        "Nama Penyewa   : {$user['nama']}\n" .
        "Nama Properti  : {$booking['nama_properti']}\n" .
        "Tanggal Sewa   : " . date('d-m-Y', strtotime($booking['tanggal_mulai'])) .
        " s/d " . date('d-m-Y', strtotime($booking['tanggal_selesai'])) . " ({$malam} malam)\n" .
        "Total Harga    : " . rupiah((float)$booking['total_harga']) . "\n\n" .
        "Mohon informasi rekening / metode pembayaran selanjutnya. Terima kasih.";

    $waNomor = normalize_no_wa((string)$booking['wa_pemilik']);
    $waUrl   = 'https://wa.me/' . $waNomor . '?text=' . rawurlencode($pesanWa);
    ?>
    <!DOCTYPE html>
    <html lang="id"><head><meta charset="UTF-8"><title>Detail Reservasi</title>
    <style>
        body{font-family:system-ui;background:#f1f5f9;padding:40px}
        .card{max-width:520px;margin:auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08)}
        .row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #e2e8f0;font-size:14px}
        .total{font-size:18px;font-weight:700;color:#059669;margin-top:12px}
        .wa{display:block;text-align:center;margin-top:20px;padding:14px;background:#25D366;color:#fff;
            text-decoration:none;border-radius:10px;font-weight:700}
        .warn{background:#fef3c7;color:#92400e;padding:10px;border-radius:8px;font-size:14px;margin-top:16px}
    </style></head><body>
    <div class="card">
        <h2>Reservasi Berhasil 🎉</h2>
        <p style="color:#64748b;font-size:14px">Kode Booking: <b>#<?= (int)$booking['id'] ?></b></p>

        <div class="row"><span>Properti</span><b><?= e($booking['nama_properti']) ?></b></div>
        <div class="row"><span>Pemilik</span><b><?= e($booking['nama_pemilik']) ?></b></div>
        <div class="row"><span>Check-in</span><b><?= date('d-m-Y', strtotime($booking['tanggal_mulai'])) ?></b></div>
        <div class="row"><span>Check-out</span><b><?= date('d-m-Y', strtotime($booking['tanggal_selesai'])) ?></b></div>
        <div class="row"><span>Durasi</span><b><?= $malam ?> malam</b></div>
        <div class="row"><span>Status</span><b><?= e(strtoupper($booking['status'])) ?></b></div>

        <div class="total">Total: <?= e(rupiah((float)$booking['total_harga'])) ?></div>

        <?php if ($waNomor !== ''): ?>
            <a class="wa" href="<?= e($waUrl) ?>" target="_blank" rel="noopener">
                💬 Bayar Sekarang via WhatsApp
            </a>
        <?php else: ?>
            <div class="warn">Nomor WhatsApp pemilik belum tersedia. Silakan hubungi admin.</div>
        <?php endif; ?>

        <p style="text-align:center;margin-top:16px">
            <a href="index.php" style="color:#2563eb;font-size:14px">← Kembali ke daftar properti</a>
        </p>
    </div>
    </body></html>
    <?php
    exit;
}

/* =========================================================
   BAGIAN B: FORM BOOKING + LOGIKA ANTI DOUBLE-BOOKING
   ========================================================= */
$propertiId = (int)($_POST['properti_id'] ?? $_GET['properti_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT p.*, u.nama AS nama_pemilik
     FROM properti p INNER JOIN users u ON u.id = p.pemilik_id
     WHERE p.id = :id LIMIT 1'
);
$stmt->execute([':id' => $propertiId]);
$properti = $stmt->fetch();

if (!$properti) {
    http_response_code(404);
    exit('Properti tidak ditemukan.');
}
if ($user['role'] !== 'penyewa') {
    exit('Hanya akun penyewa yang dapat melakukan booking.');
}
if ((int)$properti['pemilik_id'] === (int)$user['id']) {
    exit('Anda tidak dapat memesan properti milik sendiri.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $tglMulai   = trim($_POST['tanggal_mulai'] ?? '');
    $tglSelesai = trim($_POST['tanggal_selesai'] ?? '');

    // ---- Validasi format tanggal (Y-m-d) ----
    $dMulai   = DateTime::createFromFormat('Y-m-d', $tglMulai);
    $dSelesai = DateTime::createFromFormat('Y-m-d', $tglSelesai);
    $hariIni  = new DateTime('today');

    if (!$dMulai || $dMulai->format('Y-m-d') !== $tglMulai) {
        $errors[] = 'Tanggal mulai tidak valid.';
    }
    if (!$dSelesai || $dSelesai->format('Y-m-d') !== $tglSelesai) {
        $errors[] = 'Tanggal selesai tidak valid.';
    }
    if (!$errors) {
        if ($dMulai < $hariIni) {
            $errors[] = 'Tanggal mulai tidak boleh di masa lalu.';
        }
        if ($dSelesai <= $dMulai) {
            $errors[] = 'Tanggal selesai harus setelah tanggal mulai (minimal 1 malam).';
        }
    }

    if (!$errors) {
        $malam = (int)$dMulai->diff($dSelesai)->days;
        $total = $malam * (float)$properti['harga_per_malam'];

        try {
            $pdo->beginTransaction();

            // ---- LOCK baris properti (mencegah race condition 2 booking bersamaan) ----
            $lock = $pdo->prepare('SELECT id FROM properti WHERE id = :id FOR UPDATE');
            $lock->execute([':id' => $propertiId]);
            if (!$lock->fetch()) {
                throw new RuntimeException('Properti tidak ditemukan.');
            }

            // ---- QUERY ANTI DOUBLE-BOOKING ----
            // Overlap terjadi jika: (mulai_baru <= selesai_lama) AND (selesai_baru >= mulai_lama)
            $cek = $pdo->prepare(
                'SELECT id FROM bookings
                 WHERE properti_id = :properti_id
                   AND status <> :batal
                   AND (tanggal_mulai <= :selesai_baru AND tanggal_selesai >= :mulai_baru)
                 LIMIT 1'
            );
            $cek->execute([
                ':properti_id'  => $propertiId,
                ':batal'        => 'dibatalkan',
                ':selesai_baru' => $tglSelesai,
                ':mulai_baru'   => $tglMulai,
            ]);
            $bentrok = $cek->fetch();

            if ($bentrok) {
                $pdo->rollBack();
                $errors[] = 'Maaf, jadwal tersebut sudah dipesan. Silakan pilih tanggal lain.';
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO bookings (properti_id, penyewa_id, tanggal_mulai, tanggal_selesai, total_harga, status)
                     VALUES (:pid, :uid, :mulai, :selesai, :total, :status)'
                );
                $ins->execute([
                    ':pid'     => $propertiId,
                    ':uid'     => $user['id'],
                    ':mulai'   => $tglMulai,
                    ':selesai' => $tglSelesai,
                    ':total'   => $total,
                    ':status'  => 'pending',
                ]);
                $bookingId = (int)$pdo->lastInsertId();

                $pdo->commit();
                // PRG pattern: redirect agar tidak double-submit saat refresh
                redirect('booking.php?id=' . $bookingId);
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[BOOKING ERROR] ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

// Daftar tanggal yang sudah dibooking (untuk ditampilkan ke penyewa)
$stmt = $pdo->prepare(
    'SELECT tanggal_mulai, tanggal_selesai FROM bookings
     WHERE properti_id = :id AND status <> :batal ORDER BY tanggal_mulai'
);
$stmt->execute([':id' => $propertiId, ':batal' => 'dibatalkan']);
$jadwalTerisi = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Booking <?= e($properti['nama_properti']) ?></title>
    <style>
        body{font-family:system-ui,sans-serif;background:#f1f5f9;margin:0;padding:32px}
        .card{max-width:560px;margin:auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08)}
        img{width:100%;height:240px;object-fit:cover;border-radius:10px}
        label{display:block;margin:12px 0 4px;font-weight:600;font-size:14px}
        input{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
        button{margin-top:16px;width:100%;padding:12px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-weight:600;cursor:pointer}
        .err{background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;margin-bottom:8px;font-size:14px}
        .harga{font-size:20px;font-weight:700;color:#059669;margin-top:8px}
        .jadwal{font-size:13px;color:#475569;background:#f8fafc;padding:10px;border-radius:8px;margin-top:10px}
    </style>
</head>
<body>
<div class="card">
    <img src="<?= e(UPLOAD_URL . $properti['gambar']) ?>" alt="">
    <h2><?= e($properti['nama_properti']) ?></h2>
    <p style="color:#64748b;font-size:14px"><?= nl2br(e($properti['deskripsi'])) ?></p>
    <div class="harga"><?= e(rupiah((float)$properti['harga_per_malam'])) ?> / malam</div>
    <p style="font-size:14px;color:#64748b">Pemilik: <?= e($properti['nama_pemilik']) ?></p>

    <?php if ($jadwalTerisi): ?>
        <div class="jadwal">
            <b>Jadwal sudah terisi:</b><br>
            <?php foreach ($jadwalTerisi as $j): ?>
                • <?= date('d-m-Y', strtotime($j['tanggal_mulai'])) ?> s/d <?= date('d-m-Y', strtotime($j['tanggal_selesai'])) ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $err): ?>
        <div class="err" style="margin-top:14px"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="properti_id" value="<?= (int)$propertiId ?>">

        <label>Tanggal Mulai (Check-in)</label>
        <input type="date" name="tanggal_mulai" required min="<?= date('Y-m-d') ?>"
               value="<?= e($_POST['tanggal_mulai'] ?? '') ?>">

        <label>Tanggal Selesai (Check-out)</label>
        <input type="date" name="tanggal_selesai" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
               value="<?= e($_POST['tanggal_selesai'] ?? '') ?>">

        <button type="submit">Booking Sekarang</button>
    </form>
</div>
</body>
</html>