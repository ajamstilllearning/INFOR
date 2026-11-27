<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/** Escape output HTML (anti XSS) */
function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirect + hentikan eksekusi */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Format Rupiah */
function rupiah(float $n): string
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

/* ==================== CSRF PROTECTION ==================== */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Sesi form kedaluwarsa. Silakan muat ulang halaman.');
    }
}

/* ==================== AUTENTIKASI & OTORISASI ==================== */
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        return $cache = null;
    }
    $stmt = $pdo->prepare(
        'SELECT id, nama, email, role, no_wa, is_verified FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        return $cache = null;
    }
    return $cache = $user;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login.php');
    }
}

function require_role(string $role): void
{
    $u = current_user();
    if (!$u || $u['role'] !== $role) {
        http_response_code(403);
        exit('Akses ditolak: halaman ini hanya untuk ' . e($role) . '.');
    }
}

/* ==================== AKUN BELUM TERVERIFIKASI ==================== */
/** Hapus akun is_verified = 0 yang OTP-nya sudah lama kedaluwarsa. */
function hapus_akun_belum_verifikasi(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM users WHERE is_verified = 0 AND (otp_expired_at IS NULL OR otp_expired_at < :batas)'
    );
    $stmt->execute([':batas' => date('Y-m-d H:i:s', time() - UNVERIFIED_TTL)]);
}

/* ==================== NORMALISASI NOMOR WHATSAPP ==================== */
function normalize_no_wa(string $nomor): string
{
    $nomor = preg_replace('/\D/', '', $nomor) ?? '';
    if (str_starts_with($nomor, '0')) {
        $nomor = '62' . substr($nomor, 1);
    } elseif (str_starts_with($nomor, '8')) {
        $nomor = '62' . $nomor;
    }
    return $nomor;
}

/* ==================== UPLOAD GAMBAR AMAN ==================== */
/**
 * Validasi & simpan file gambar. Return nama file tersimpan.
 * @throws RuntimeException bila validasi gagal
 */
function simpan_gambar_aman(array $file): string
{
    // 1. Cek error bawaan PHP
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Parameter upload tidak valid.');
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('File gambar wajib diunggah.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Ukuran file melebihi batas server.');
        default:
            throw new RuntimeException('Gagal mengunggah file.');
    }

    // 2. Batas ukuran 2MB
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Ukuran gambar maksimal 2MB.');
    }

    // 3. Pastikan file benar-benar hasil upload (bukan path palsu)
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('File tidak valid.');
    }

    // 4. Validasi MIME asli (bukan dari ekstensi yang bisa dipalsukan)
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!array_key_exists($mime, $allowed)) {
        throw new RuntimeException('Format gambar harus JPG, JPEG, atau PNG.');
    }

    // 5. Validasi header gambar asli (anti file PHP yang di-rename)
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        throw new RuntimeException('File bukan gambar yang valid.');
    }

    // 6. Pastikan folder upload ada
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException('Folder upload tidak dapat dibuat.');
    }

    // 7. Rename unik: uniqid + time + ekstensi dari MIME (bukan dari nama user)
    $namaFile = 'prp_' . str_replace('.', '', uniqid('', true)) . '_' . time() . '.' . $allowed[$mime];
    $tujuan   = UPLOAD_DIR . $namaFile;

    if (!move_uploaded_file($file['tmp_name'], $tujuan)) {
        throw new RuntimeException('Gagal menyimpan file gambar.');
    }
    @chmod($tujuan, 0644); // tidak executable

    return $namaFile;
}