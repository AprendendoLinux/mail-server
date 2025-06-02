<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php");
    exit;
}

require_once 'config.db.php';
require_once 'vendor/autoload.php'; // Carregar bibliotecas do Composer

use OTPHP\TOTP;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Definir o fuso horário do PHP
date_default_timezone_set('America/Sao_Paulo');

// Inicializar mensagens
$error = '';
$success = '';
$two_factor_message = '';

// Conectar ao banco de dados
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, DB_PORT);

// Verificar conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Configurar charset UTF-8 na conexão
if (!$conn->set_charset("utf8mb4")) {
    die("Erro ao configurar o charset UTF-8: " . $conn->error);
}

// Sincronizar o fuso horário do banco de dados
$conn->query("SET time_zone = '-03:00'");

// Obter dados do administrador logado
$username = $conn->real_escape_string($_SESSION['username']);
$query = "SELECT email, password, totp_secret, two_factor_enabled FROM admins WHERE username = '$username'";
$result = $conn->query($query);
if (!$result || $result->num_rows === 0) {
    die("Erro: Usuário não encontrado.");
}
$admin = $result->fetch_assoc();
$current_password_hash = $admin['password'];
$email = $admin['email'];
$totp_secret = $admin['totp_secret'];
$two_factor_enabled = $admin['two_factor_enabled'];

// Lógica para requisição AJAX para gerar o QR Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_generate_qr'])) {
    header('Content-Type: application/json');
    try {
        $totp = TOTP::create();
        $totp->setLabel($username . '@sistema-email');
        $totp->setIssuer('Sistema de E-mail');
        $totp->setPeriod(30);
        $totp->setDigits(6);
        $totp->setDigest('sha1');
        $temp_totp_secret = $totp->getSecret();
        $_SESSION['temp_totp_secret'] = $temp_totp_secret;
        $_SESSION['totp_instance'] = serialize($totp); // Armazenar a instância completa
        $totp_uri = $totp->getProvisioningUri();

        $qrCode = QrCode::create($totp_uri)->setSize(200);
        $writer = new PngWriter();
        $qrImage = $writer->write($qrCode)->getDataUri();

        // Gerar o HTML para o modal
        ob_start();
        ?>
        <p>Escaneie o QR Code abaixo com seu aplicativo autenticador (ex.: Google Authenticator):</p>
        <img src="<?php echo $qrImage; ?>" alt="QR Code para 2FA" class="qr-code">
        <p>Ou insira manualmente a chave: <strong class="totp-secret"><?php echo htmlspecialchars($temp_totp_secret); ?></strong> <button type="button" class="copy-btn" data-clipboard-text="<?php echo htmlspecialchars($temp_totp_secret); ?>">Copiar</button></p>
        <p class="copy-feedback" style="display: none; color: #4CAF50; margin-top: 5px;">Copiado!</p>
        <form method="POST" action="" id="confirm-2fa-form">
            <div class="form-group">
                <label for="totp_code">Código TOTP:</label>
                <input type="text" id="totp_code" name="totp_code" required maxlength="6">
            </div>
            <div class="form-group">
                <input type="submit" name="confirm_2fa" value="Confirmar" class="modal-submit-btn">
            </div>
        </form>
        <?php
        $html = ob_get_clean();
        echo json_encode(['success' => true, 'html' => $html]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao gerar QR Code: ' . $e->getMessage()]);
    }
    exit;
}

// Lógica para alterar senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_new_password']);

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Todos os campos são obrigatórios.";
    } elseif (!password_verify($current_password, $current_password_hash)) {
        $error = "A senha atual está incorreta.";
    } elseif ($new_password !== $confirm_password) {
        $error = "As senhas não coincidem.";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*])[^\s]{8,}$/', $new_password)) {
        $error = "A senha deve ter no mínimo 8 caracteres, com pelo menos uma letra maiúscula, uma letra minúscula, um número e um caractere especial.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_password_query = "UPDATE admins SET password = '" . $conn->real_escape_string($hashed_password) . "' WHERE username = '$username'";
        if ($conn->query($update_password_query) === TRUE && $conn->affected_rows > 0) {
            $success = "Senha alterada com sucesso!";
        } else {
            $error = "Erro ao alterar senha: " . $conn->error;
        }
    }
}

// Lógica para alterar e-mail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_email'])) {
    $new_email = trim($_POST['new_email']);
    $current_password = trim($_POST['current_password_email']);

    if (empty($new_email) || empty($current_password)) {
        $error = "Todos osCampos são obrigatórios.";
    } elseif (!password_verify($current_password, $current_password_hash)) {
        $error = "A senha atual está incorreta.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Formato de e-mail inválido.";
    } else {
        $email_check_query = "SELECT username FROM admins WHERE email = '" . $conn->real_escape_string($new_email) . "' AND username != '$username'";
        $email_check_result = $conn->query($email_check_query);
        if ($email_check_result->num_rows > 0) {
            $error = "Este e-mail já está em uso.";
        } else {
            // Excluir registros de password_resets para o e-mail atual
            $delete_password_reset_query = "DELETE FROM password_resets WHERE email = '" . $conn->real_escape_string($email) . "'";
            $conn->query($delete_password_reset_query);

            $update_email_query = "UPDATE admins SET email = '" . $conn->real_escape_string($new_email) . "' WHERE username = '$username'";
            if ($conn->query($update_email_query) === TRUE && $conn->affected_rows > 0) {
                $success = "E-mail alterado com sucesso!";
                $email = $new_email;
            } else {
                $error = "Erro ao alterar e-mail: " . $conn->error;
            }
        }
    }
}

// Lógica para confirmar código TOTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_2fa'])) {
    $totp_code = trim($_POST['totp_code']);
    // Remover quaisquer espaços do código TOTP
    $totp_code = preg_replace('/\s+/', '', $totp_code);
    $temp_totp_secret = $_SESSION['temp_totp_secret'] ?? '';
    $totp_instance = unserialize($_SESSION['totp_instance'] ?? '');

    if (empty($totp_code)) {
        $error = "O código TOTP é obrigatório.";
        if (!empty($temp_totp_secret)) {
            $totp = TOTP::create($temp_totp_secret);
            $totp->setLabel($username . '@sistema-email');
            $totp->setIssuer('Sistema de E-mail');
            $totp->setPeriod(30);
            $totp->setDigits(6);
            $totp->setDigest('sha1');
            $totp_uri = $totp->getProvisioningUri();
            $qrCode = QrCode::create($totp_uri)->setSize(200);
            $writer = new PngWriter();
            $qrImage = $writer->write($qrCode)->getDataUri();
        }
    } else {
        if (!empty($temp_totp_secret) && $totp_instance instanceof TOTP) {
            $totp = $totp_instance;

            // Depuração: Log temporário para verificar os valores
            $expected_code = $totp->at(time());
            error_log("TOTP Code Submitted: $totp_code, Expected Code: $expected_code, Secret: $temp_totp_secret", 3, '/tmp/2fa_debug.log');

            $serverTime = date('c');
            $isValid = $totp->verify($totp_code, time(), 1); // Alinhar com my-profile.php.txt
            if ($isValid) {
                $update_2fa_query = "UPDATE admins SET totp_secret = '" . $conn->real_escape_string($temp_totp_secret) . "', two_factor_enabled = 1 WHERE username = '$username'";
                if ($conn->query($update_2fa_query) === TRUE) {
                    $two_factor_enabled = 1;
                    $totp_secret = $temp_totp_secret;
                    $two_factor_message = "Autenticação de dois fatores habilitada com sucesso!";
                    unset($_SESSION['temp_totp_secret']);
                    unset($_SESSION['totp_instance']);
                } else {
                    $error = "Erro ao habilitar 2FA: " . $conn->error;
                }
            } else {
                $error = "Código TOTP inválido.";
                $totp_uri = $totp->getProvisioningUri();
                $qrCode = QrCode::create($totp_uri)->setSize(200);
                $writer = new PngWriter();
                $qrImage = $writer->write($qrCode)->getDataUri();
            }
        } else {
            $error = "Erro: Chave secreta ou instância não definida.";
        }
    }
}

// Lógica para desabilitar 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_2fa'])) {
    $update_2fa_query = "UPDATE admins SET two_factor_enabled = 0, totp_secret = NULL WHERE username = '$username'";
    if ($conn->query($update_2fa_query) === TRUE) {
        $two_factor_enabled = 0;
        $totp_secret = null;
        $two_factor_message = "Autenticação de dois fatores desabilitada com sucesso!";
    } else {
        $error = "Erro ao desabilitar 2FA: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/perfil.css">
    <link rel="stylesheet" href="css/topo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'topo.php'; ?>
    <div class="content">
        <div class="profile-container">
            <h2>Perfil do Administrador</h2>

            <!-- Mensagens gerais -->
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if ($two_factor_message): ?>
                <p class="success"><?php echo htmlspecialchars($two_factor_message); ?></p>
            <?php endif; ?>

            <!-- Grid de Botões Widgets -->
            <div class="widget-grid">
                <button class="widget-btn" data-modal="passwordModal">
                    <i class="fas fa-lock"></i> Alterar Senha
                </button>
                <button class="widget-btn" data-modal="emailModal">
                    <i class="fas fa-envelope"></i> Alterar E-mail
                </button>
                <button class="widget-btn" data-modal="<?php echo $two_factor_enabled ? 'twoFactorDisableModal' : 'twoFactorModal'; ?>">
                    <i class="fas fa-shield-alt"></i> 2FA
                    <span class="status"><?php echo $two_factor_enabled ? 'Habilitado' : 'Desabilitado'; ?></span>
                </button>
            </div>

            <!-- Modal para Alterar Senha -->
            <div id="passwordModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn">×</span>
                    <h3>Alterar Senha</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Senha Atual:</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Nova Senha:</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_new_password">Confirmação de Nova Senha:</label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                        </div>
                        <div class="form-group">
                            <input type="submit" name="change_password" value="Alterar Senha" class="modal-submit-btn">
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para Alterar E-mail -->
            <div id="emailModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn">×</span>
                    <h3>Alterar E-mail</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_email">E-mail Atual:</label>
                            <input type="email" id="current_email" name="current_email" value="<?php echo htmlspecialchars($email); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="new_email">Novo E-mail:</label>
                            <input type="email" id="new_email" name="new_email" required>
                        </div>
                        <div class="form-group">
                            <label for="current_password_email">Senha Atual:</label>
                            <input type="password" id="current_password_email" name="current_password_email" required>
                        </div>
                        <div class="form-group">
                            <input type="submit" name="change_email" value="Alterar E-mail" class="modal-submit-btn">
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para Configurar 2FA (quando desabilitado) -->
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

            <!-- Modal para Desabilitar 2FA (quando habilitado) -->
            <div id="twoFactorDisableModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn">×</span>
                    <h3>Autenticação de Dois Fatores</h3>
                    <p>2FA já habilitado, deseja desabilitar (não recomendado)?</p>
                    <div class="button-group">
                        <form method="POST" action="" style="display: inline;">
                            <button type="submit" name="disable_2fa" class="modal-submit-btn warning">Sim</button>
                        </form>
                        <button class="modal-submit-btn modal-cancel-btn">Não</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/perfil.js"></script>
</body>
<?php
$conn->close();
?>
</html>
