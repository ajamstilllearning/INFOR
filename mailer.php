<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// composer require phpmailer/phpmailer
$autoload = __DIR__ . '/vendor/autoload.php';
$phpmailerTersedia = false;
if (file_exists($autoload)) {
    require_once $autoload;
    $phpmailerTersedia = class_exists(PHPMailer::class);
}

/**
 * Kirim kode OTP ke email user.
 * Bila PHPMailer/SMTP belum siap, OTP ditulis ke logs/otp.log (mode development).
 */
function kirim_email_otp(string $emailTujuan, string $namaTujuan, string $otp): bool
{
    global $phpmailerTersedia;

    if ($phpmailerTersedia) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($emailTujuan, $namaTujuan);

            $mail->isHTML(true);
            $mail->Subject = 'Kode OTP Verifikasi Akun Booking Wisata';
            $mail->Body    = '<p>Halo <b>' . htmlspecialchars($namaTujuan) . '</b>,</p>'
                           . '<p>Kode OTP verifikasi akun Anda adalah:</p>'
                           . '<h2 style="letter-spacing:6px">' . $otp . '</h2>'
                           . '<p>Kode berlaku selama 5 menit. Jangan bagikan kode ini kepada siapa pun.</p>';
            $mail->AltBody = "Halo {$namaTujuan}, kode OTP Anda: {$otp} (berlaku 5 menit).";

            return $mail->send();
        } catch (Exception $e) {
            error_log('[MAIL ERROR] ' . $mail->ErrorInfo);
            return false;
        }
    }

    // ---- Fallback development: catat OTP ke file log ----
    if (!is_dir(__DIR__ . '/logs')) {
        @mkdir(__DIR__ . '/logs', 0755, true);
    }
    @file_put_contents(
        __DIR__ . '/logs/otp.log',
        sprintf("[%s] %s => OTP: %s\n", date('Y-m-d H:i:s'), $emailTujuan, $otp),
        FILE_APPEND
    );
    return false;
}