<?php
// Inclusão das dependências
require_once 'config.db.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// Importa os namespaces necessários
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configurações SMTP para PHPMailer
$settings = [
    'smtp_host' => getenv('SMTP_HOST'),
    'smtp_port' => (int) getenv('SMTP_PORT'),
    'smtp_auth' => getenv('SMTP_AUTH'),
    'smtp_username' => getenv('SMTP_USERNAME'),
    'smtp_password' => getenv('SMTP_PASSWORD'),
    'smtp_encryption' => getenv('SMTP_ENCRYPTION'),
    'smtp_from_email' => getenv('SMTP_FROM_EMAIL'),
    'smtp_from_name' => getenv('SMTP_FROM_NAME'),
];

$settings['smtp_keep_alive'] = getenv('SMTP_KEEP_ALIVE') === 'true';
$settings['smtp_debug'] = (int) getenv('SMTP_DEBUG');
$settings['smtp_debug_output'] = getenv('SMTP_DEBUG_OUTPUT');
$settings['smtp_options'] = [
    'ssl' => [
        'verify_peer' => getenv('SMTP_SSL_VERIFY_PEER') === 'true',
        'verify_peer_name' => getenv('SMTP_SSL_VERIFY_PEER_NAME') === 'true',
        'allow_self_signed' => getenv('SMTP_SSL_ALLOW_SELF_SIGNED') === 'true',
    ]
];

$settings['charset'] = getenv('SMTP_CHARSET');
$settings['encoding'] = getenv('SMTP_ENCODING');
?>
