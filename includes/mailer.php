<?php
// includes/mailer.php

/**
 * Sends an HTML email. Swap implementation to SMTP later if needed.
 */
require_once __DIR__ . '/../config/mail.php';

function send_mail_html(string $toEmail, string $subject, string $htmlBody, string $fromEmail = MAIL_FROM_EMAIL, string $fromName = MAIL_FROM_NAME): bool {
    if (MAIL_DRIVER === 'mail') {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        return mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));
    }

    // Basic SMTP without external library
    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $fp = stream_socket_client((SMTP_ENCRYPTION === 'ssl' ? 'ssl://' : 'tcp://') . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) { return false; }
    $read = function() use ($fp) { return fgets($fp, 515); };
    $write = function($cmd) use ($fp) { fwrite($fp, $cmd."\r\n"); };
    $read();
    $write('EHLO localhost'); $read();
    if (SMTP_ENCRYPTION === 'tls') { $write('STARTTLS'); $read(); stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT); $write('EHLO localhost'); $read(); }
    if (SMTP_USERNAME) { $write('AUTH LOGIN'); $read(); $write(base64_encode(SMTP_USERNAME)); $read(); $write(base64_encode(SMTP_PASSWORD)); $read(); }
    $write('MAIL FROM: <'.$fromEmail.'>'); $read();
    $write('RCPT TO: <'.$toEmail.'>'); $read();
    $write('DATA'); $read();
    $headers = 'From: '.$fromName.' <'.$fromEmail.">\r\n".'MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n';
    $data = 'Subject: '.$subject."\r\n".$headers."\r\n".$htmlBody."\r\n.";
    $write($data); $read();
    $write('QUIT');
    fclose($fp);
    return true;
}