<?php
// config/mail.php

// From address used for all outgoing mails
if (!defined('MAIL_FROM_EMAIL')) {
    define('MAIL_FROM_EMAIL', 'support@zenevo.in');
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', 'Zenevo Support');
}

// Placeholder SMTP settings (not used by default). Set DRIVER to 'smtp' later if implementing SMTP.
if (!defined('MAIL_DRIVER')) {
    define('MAIL_DRIVER', 'mail'); // values: 'mail' | 'smtp'
}
if (!defined('SMTP_HOST')) { define('SMTP_HOST', ''); }
if (!defined('SMTP_PORT')) { define('SMTP_PORT', 587); }
if (!defined('SMTP_USERNAME')) { define('SMTP_USERNAME', ''); }
if (!defined('SMTP_PASSWORD')) { define('SMTP_PASSWORD', ''); }
if (!defined('SMTP_ENCRYPTION')) { define('SMTP_ENCRYPTION', 'tls'); }