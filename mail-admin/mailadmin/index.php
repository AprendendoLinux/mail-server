<?php
session_start();
require_once 'config.db.php';
require_once 'config.captcha.php';
require_once 'vendor/autoload.php';

use OTPHP\TOTP;

// Verificar se o usuário está logado e redirecionar para o dashboard ou enable_2fa
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_SESSION['temp_two_factor_enabled']) && $_SESSION['temp_two_factor_enabled'] == 0) {
        header("Location: enable_2fa.php");
        exit;
    } else {
        header("Location: dashboard.php");
        exit;
    }
}

// Inicializar mensagens
$error = '';
$show_totp_form = false;
$temp_username = '';

// Conectar ao banco de dados
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, DB_PORT);
if ($conn->connect_error) {
    $error = "Conexão falhou: " . $conn->connect_error;
}

// Configurar charset UTF-8 na conexão
if (!$conn->set_charset("utf8mb4")) {
    $error = "Erro ao configurar o charset UTF-8: " . $conn->error;
}

// Função para gerar um token seguro
function generateTrustedDeviceToken() {
    return bin2hex(random_bytes(32));
}

// Determina se estamos em HTTPS, considerando proxies
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           $_SERVER['SERVER_PORT'] == 443 ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Função para validar o reCAPTCHA
function validateRecaptcha($response, $secret_key) {
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secret_key,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $result_json = json_decode($result);
    return $result_json->success;
}

// Verificar código TOTP após login inicial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_totp'])) {
    $totp_code = trim($_POST['totp_code'] ?? '');
    $temp_username = $_SESSION['temp_username'] ?? '';
    $totp_secret = $_SESSION['temp_totp_secret'] ?? '';

    if (empty($totp_code)) {
        $error = "O código TOTP é obrigatório.";
    } else {
        // Criar instância TOTP com a chave secreta
        $totp = TOTP::create($totp_secret); // Usar o método estático create
        // Verificar o código com o timestamp atual
        if ($totp->verify($totp_code, time())) { // Passar o timestamp explicitamente
            // Código válido, finalizar login
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $temp_username;
            $_SESSION['is_superadmin'] = $_SESSION['temp_is_superadmin'];
            
            // Verificar se o checkbox "trust_device" foi marcado
            if (isset($_POST['trust_device']) && $_POST['trust_device'] === '1') {
                $token = generateTrustedDeviceToken();
                $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                $stmt = $conn->prepare("INSERT INTO trusted_devices (username, token, expires_at) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $temp_username, $token, $expires_at);
                $stmt->execute();
                $stmt->close();

                // Definir cookie trusted_device_token
                setcookie('trusted_device_token', $token, [
                    'expires' => time() + (30 * 24 * 60 * 60),
                    'path' => '/',
                    'domain' => $_SERVER['HTTP_HOST'],
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            // Redirecionar com base no status do 2FA
            if ($_SESSION['temp_two_factor_enabled'] == 0) {
                header("Location: enable_2fa.php");
            } else {
                header("Location: dashboard.php");
            }
            unset($_SESSION['temp_username']);
            unset($_SESSION['temp_totp_secret']);
            unset($_SESSION['temp_is_superadmin']);
            unset($_SESSION['temp_two_factor_enabled']);
            exit;
        } else {
            $error = "Código TOTP inválido.";
            $show_totp_form = true;
        }
    }
}

// Lidar com o login inicial (usuário/senha)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_totp'])) {
    $username_or_email = $conn->real_escape_string(trim($_POST['username'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    
    // Validar reCAPTCHA se habilitado
    if ($ENABLE_RECAPTCHA) {
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
        if (empty($recaptcha_response)) {
            $error = "Por favor, complete o reCAPTCHA.";
        } elseif (!validateRecaptcha($recaptcha_response, RECAPTCHA_SECRET_KEY)) {
            $error = "Falha na verificação do reCAPTCHA.";
        }
    }

    if (empty($error) && !empty($username_or_email) && !empty($password)) {
        // Determinar se é e-mail ou usuário
        $is_email = filter_var($username_or_email, FILTER_VALIDATE_EMAIL);
        $query_field = $is_email ? 'email' : 'username';
        
        $query = "SELECT username, password, is_superadmin, totp_secret, two_factor_enabled FROM admins WHERE $query_field = '" . $conn->real_escape_string($username_or_email) . "' AND active = 1";
        $result = $conn->query($query);
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                // Verificar se o dispositivo é confiável
                $isTrustedDevice = false;
                if ($row['two_factor_enabled'] && !empty($row['totp_secret']) && isset($_COOKIE['trusted_device_token'])) {
                    $token = $_COOKIE['trusted_device_token'];
                    $stmt = $conn->prepare("SELECT * FROM trusted_devices WHERE token = ? AND username = ? AND expires_at > NOW()");
                    $stmt->bind_param("ss", $token, $row['username']);
                    $stmt->execute();
                    $trusted_device = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($trusted_device) {
                        $isTrustedDevice = true;
                    } else {
                        // Remover cookie inválido
                        setcookie('trusted_device_token', '', [
                            'expires' => time() - 3600,
                            'path' => '/',
                            'domain' => $_SERVER['HTTP_HOST'],
                            'secure' => $isHttps,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    }
                }

                if ($row['two_factor_enabled'] && !empty($row['totp_secret']) && !$isTrustedDevice) {
                    // 2FA habilitado, exigir código TOTP
                    $show_totp_form = true;
                    $_SESSION['temp_username'] = $row['username'];
                    $_SESSION['temp_totp_secret'] = $row['totp_secret'];
                    $_SESSION['temp_is_superadmin'] = $row['is_superadmin'];
                    $_SESSION['temp_two_factor_enabled'] = $row['two_factor_enabled'];
                } else {
                    // 2FA não habilitado ou dispositivo confiável, prosseguir
                    $_SESSION['loggedin'] = true;
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['is_superadmin'] = $row['is_superadmin'];
                    if ($row['two_factor_enabled'] == 0) {
                        $_SESSION['temp_two_factor_enabled'] = 0;
                        header("Location: enable_2fa.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit;
                }
            } else {
                $error = "Usuário/e-mail ou senha inválidos.";
            }
        } else {
            $error = "Usuário/e-mail ou senha inválidos.";
        }
    } elseif (empty($username_or_email) || empty($password)) {
        $error = "Usuário/e-mail e senha são obrigatórios.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/index.css">
    <?php if ($ENABLE_RECAPTCHA): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
    <?php if ($show_totp_form): ?>
        <div class="login-container">
            <img src="imagens/Logo.png" alt="Logo do Sistema">
            <h2>Verificação de 2FA</h2>
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="totp_code">Código TOTP:</label>
                    <input type="text" id="totp_code" name="totp_code" required>
                </div>
                <div class="trust-device">
                    <input type="checkbox" id="trust_device" name="trust_device" value="1">
                    <label for="trust_device">Marcar este dispositivo como confiável por 30 dias</label>
                </div>
                <input type="submit" name="verify_totp" value="Verificar">
            </form>
        </div>
    <?php else: ?>
        <div class="login-container">
            <img src="imagens/Logo.png" alt="Logo do Sistema">
            <h2>Login</h2>
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Usuário ou e-mail:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <?php if ($ENABLE_RECAPTCHA): ?>
                    <div class="recaptcha-container">
                        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>"></div>
                    </div>
                <?php endif; ?>
                <input type="submit" value="Entrar">
            </form>
            <p class="forgot-password"><a href="recover_password.php">Esqueci a senha</a></p>
            <p class="credits">
                Desenvolvido por <a href="https://www.henrique.tec.br" target="_blank">Henrique Fagundes</a> - Todos os direitos reservados.
            </p>
        </div>
    <?php endif; ?>
</body>
</html>