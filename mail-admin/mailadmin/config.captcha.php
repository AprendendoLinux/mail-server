<?php
// Configurações do Google reCAPTCHA
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY'));
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY'));

// Ativar ou desativar o reCAPTCHA (true para ativar, false para desativar)
$ENABLE_RECAPTCHA = getenv('ENABLE_RECAPTCHA') === 'true';
?>
