<?php
// includes/mailer.php

/**
 * Sends an HTML email. Swap implementation to SMTP later if needed.
 */
require_once __DIR__ . '/../config/mail.php';

function send_mail_html(string $toEmail, string $subject, string $htmlBody, string $fromEmail = MAIL_FROM_EMAIL, string $fromName = MAIL_FROM_NAME): bool {
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $fromEmail;
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    return mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));
}