<?php
// config/mail.php

// From address used for all outgoing mails
if (!defined('MAIL_FROM_EMAIL')) {
    define('MAIL_FROM_EMAIL', 'noreply@zenevo.in');
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', 'Zenevo Support');
}

// SMTP settings for better email delivery
if (!defined('MAIL_DRIVER')) {
    define('MAIL_DRIVER', 'smtp'); // values: 'mail' | 'smtp'
}
if (!defined('SMTP_HOST')) { define('SMTP_HOST', 'smtp.gmail.com'); }
if (!defined('SMTP_PORT')) { define('SMTP_PORT', 587); }
if (!defined('SMTP_USERNAME')) { define('SMTP_USERNAME', 'your-email@gmail.com'); }
if (!defined('SMTP_PASSWORD')) { define('SMTP_PASSWORD', 'your-app-password'); }
if (!defined('SMTP_ENCRYPTION')) { define('SMTP_ENCRYPTION', 'tls'); }

// Alias mapping for From addresses
if (!function_exists('alias_to_email')) {
    function alias_to_email(string $alias): string {
        switch ($alias) {
            case 'info': return 'info@zenevo.in';
            case 'careers': return 'careers@zenevo.in';
            case 'billing': return 'billing@zenevo.in';
            case 'support':
            default: return 'noreply@zenevo.in';
        }
    }
}