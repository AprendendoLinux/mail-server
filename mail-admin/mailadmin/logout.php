<?php
session_start();

// Determina se estamos em HTTPS, considerando proxies
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           $_SERVER['SERVER_PORT'] == 443 ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Preserva o cookie trusted_device_token, se existir
if (isset($_COOKIE['trusted_device_token'])) {
    // Não remove o cookie, apenas mantém
}

// Remove explicitamente selected_domain
unset($_SESSION['selected_domain']);

// Destrói a sessão
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redireciona para a página de login
header("Location: index.php");
exit;
?>