<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /**
     * Send an email via SMTP. Throws \RuntimeException on failure.
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $body
    ): void {
        $mail = new PHPMailer(true); // true = throw exceptions

        try {
            // ── SMTP settings ────────────────────────────────────────────────
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USERNAME'];
            $mail->Password   = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS on port 587
            $mail->Port       = (int) ($_ENV['MAIL_PORT'] ?? 587);

            // ── Sender & recipient ───────────────────────────────────────────
            $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_FROM_NAME'] ?? 'PetConnect');
            $mail->addAddress($toEmail, $toName);

            // ── Content ──────────────────────────────────────────────────────
            $mail->isHTML(false); // plain text only — no HTML injection risk
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();

        } catch (Exception $e) {
            error_log('Mailer error: ' . $mail->ErrorInfo);
            throw new \RuntimeException('Email could not be sent.');
        }
    }
}
