<?php
session_start();
require_once 'config.db.php';
require_once 'vendor/autoload.php';

use OTPHP\TOTP;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Verificar se o usuário está logado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php");
    exit;
}

// Verificar se o 2FA já está habilitado
if (isset($_SESSION['temp_two_factor_enabled']) && $_SESSION['temp_two_factor_enabled'] == 1) {
    header("Location: dashboard.php");
    exit;
}

// Conectar ao banco de dados
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, DB_PORT);
if ($conn->connect_error) {
    error_log("Conexão falhou: " . $conn->connect_error);
    die(json_encode(['success' => false, 'message' => "Conexão ao banco de dados falhou."]));
}

// Configurar charset UTF-8 na conexão
if (!$conn->set_charset("utf8mb4")) {
    error_log("Erro ao configurar charset UTF-8: " . $conn->error);
    die(json_encode(['success' => false, 'message' => "Erro ao configurar o charset UTF-8."]));
}

// Lógica AJAX para gerar QR Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_generate_qr_index'])) {
    header('Content-Type: application/json');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    try {
        $username = $_SESSION['username'];
        if (!$username) {
            throw new Exception('Usuário não encontrado na sessão.');
        }
        $totp = TOTP::create();
        $totp->setLabel($username . '@sistema-email');
        $totp->setIssuer('Sistema de E-mail');
        $totp->setPeriod(30);
        $totp->setDigits(6);
        $totp->setDigest('sha1');
        $temp_totp_secret = $totp->getSecret();
        $_SESSION['temp_totp_secret'] = $temp_totp_secret;
        $_SESSION['totp_instance'] = serialize($totp);
        $totp_uri = $totp->getProvisioningUri();

        $qrCode = QrCode::create($totp_uri)->setSize(200);
        $writer = new PngWriter();
        $qrImage = $writer->write($qrCode)->getDataUri();

        $html = '';
        ob_start();
        ?>
        <p>Escaneie o QR Code abaixo com seu aplicativo autenticador (ex.: Google Authenticator):</p>
        <img src="<?php echo $qrImage; ?>" alt="QR Code para 2FA" class="qr-code">
        <p>Ou insira manualmente a chave: <strong class="totp-secret"><?php echo htmlspecialchars($temp_totp_secret); ?></strong> <button type="button" class="copy-btn" data-clipboard-text="<?php echo htmlspecialchars($temp_totp_secret); ?>">Copiar</button></p>
        <p class="copy-feedback" style="display: none;">Copiado!</p>
        <form method="POST" action="" id="confirm-2fa-form">
            <div class="form-group">
                <label for="totp_code">Código TOTP:</label>
                <input type="text" id="totp_code" name="totp_code" required maxlength="6">
            </div>
            <div class="form-group">
                <input type="submit" name="confirm_2fa_index" value="Confirmar" class="modal-submit-btn">
            </div>
        </form>
        <?php
        $html = ob_get_clean();
        echo json_encode(['success' => true, 'html' => $html]);
    } catch (Exception $e) {
        error_log("Erro ao gerar QR Code: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao gerar QR Code: ' . $e->getMessage()]);
    }
    ob_end_flush();
    exit;
}

// Lógica para confirmar código TOTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_2fa_index'])) {
    $totp_code = trim($_POST['totp_code'] ?? '');
    $temp_totp_secret = $_SESSION['temp_totp_secret'] ?? '';
    $totp_instance = unserialize($_SESSION['totp_instance'] ?? '');

    if (empty($totp_code)) {
        $error = "O código TOTP é obrigatório.";
    } else {
        if (!empty($temp_totp_secret) && $totp_instance instanceof TOTP) {
            $totp = $totp_instance;
            $isValid = $totp->verify($totp_code, time(), 1);
            if ($isValid) {
                $update_2fa_query = "UPDATE admins SET totp_secret = '" . $conn->real_escape_string($temp_totp_secret) . "', two_factor_enabled = 1 WHERE username = '" . $conn->real_escape_string($_SESSION['username']) . "'";
                if ($conn->query($update_2fa_query) === TRUE) {
                    $_SESSION['temp_two_factor_enabled'] = 1;
                    unset($_SESSION['temp_totp_secret']);
                    unset($_SESSION['totp_instance']);
                    header("Location: 2fa_successfully_enabled.php");
                    exit;
                } else {
                    $error = "Erro ao habilitar 2FA: " . $conn->error;
                }
            } else {
                $error = "Código TOTP inválido.";
            }
        } else {
            $error = "Erro: Chave secreta ou instância não definida.";
        }
    }
}

// Lógica para dispensar o alerta 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss_2fa_alert'])) {
    $_SESSION['dismiss_2fa_alert'] = true;
    echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habilitar 2FA - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/enable_2fa.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <img src="imagens/Logo.png" alt="Logo do Sistema">
        <h2>Autenticação em Duas Etapas</h2>
        <?php if (isset($error) && !empty($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <div class="twofa-alert">
            <p>Proteja sua conta! Ative a autenticação em duas etapas (2FA) para maior segurança.</p>
            <button id="activate-2fa-btn" class="twofa-button">Ativar 2FA Agora</button>
            <a href="#" class="dismiss-link" onclick="dismiss2FAAlert(event)">Ignorar por enquanto</a>
        </div>
    </div>

    <!-- Modal para Configurar 2FA -->
    <div id="twoFactorModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">×</span>
            <h3>Configurar 2FA</h3>
            <div id="two-factor-content">
                <div class="form-group">
                    <button type="button" id="generate-qr-btn" class="modal-submit-btn">Gerar QR Code e chave</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.11/dist/clipboard.min.js"></script>
    <script src="js/enable_2fa.js"></script>
</body>
</html>