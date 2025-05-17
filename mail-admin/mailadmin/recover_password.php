<?php
require_once 'config.db.php';
require_once 'config.smtp.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, DB_PORT);
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['reset_email']);
    // Verificar se o e-mail existe e está associado a um administrador ativo
    $query = "SELECT username FROM admins WHERE email = '" . $conn->real_escape_string($email) . "' AND active = 1";
    $result = $conn->query($query);
    if ($result->num_rows === 0) {
        $error = "E-mail não encontrado ou administrador inativo.";
    } else {
        // Gerar um token único
        $token = bin2hex(random_bytes(32));
        $created_at = date('Y-m-d H:i:s');
        
        // Salvar o token no banco de dados
        $delete_query = "DELETE FROM password_resets WHERE email = '" . $conn->real_escape_string($email) . "'";
        $conn->query($delete_query); // Remover tokens antigos
        
        $insert_query = "INSERT INTO password_resets (email, token, created_at) VALUES ('" . $conn->real_escape_string($email) . "', '$token', '$created_at')";
        if ($conn->query($insert_query) === TRUE) {
            // Inicializa o objeto PHPMailer
            $mail = new PHPMailer(true); // Habilita exceções para melhor depuração

            try {
                // Configurações do servidor SMTP
                $mail->isSMTP(); // Define que usará SMTP
                $mail->Host = $settings['smtp_host'] ?? 'localhost';
                $mail->SMTPAuth = ($settings['smtp_auth'] ?? '0') == '1';
                $mail->Username = $settings['smtp_username'] ?? '';
                $mail->Password = $settings['smtp_password'] ?? '';
                $mail->SMTPSecure = $settings['smtp_encryption'] ?? '';
                $mail->Port = $settings['smtp_port'] ?? 25;

                // Configurações de segurança adicionais
                $mail->SMTPKeepAlive = $settings['smtp_keep_alive'] ?? false;
                $mail->SMTPOptions = $settings['smtp_options'] ?? [];

                // Configurações de depuração
                $mail->SMTPDebug = $settings['smtp_debug'] ?? 0;
                $mail->Debugoutput = $settings['smtp_debug_output'] ?? 'html';

                // Configurações de codificação
                $mail->CharSet = $settings['charset'] ?? 'UTF-8';
                $mail->Encoding = $settings['encoding'] ?? 'base64';

                // Configurações do remetente
                $fromEmail = $settings['smtp_from_email'] ?? 'no-reply@seusite.com';
                $fromName = $settings['smtp_from_name'] ?? 'Sistema';
                if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->setFrom($fromEmail, $fromName);
                } else {
                    error_log("E-mail de remetente inválido: " . $fromEmail);
                    $mail->setFrom('no-reply@seusite.com', 'Sistema');
                }

                // Configurações do destinatário
                $mail->addAddress($email);

                // Conteúdo do e-mail
                $mail->isHTML(false);
                $mail->Subject = "Redefinição de Senha - Sistema de E-mail";
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=$token";
                $mail->Body = "Olá,\n\nVocê solicitou a redefinição de sua senha. Clique no link abaixo para redefinir sua senha:\n$reset_link\n\nEste link é válido por 1 hora.\n\nSe você não solicitou isso, ignore este e-mail.\n\nAtenciosamente,\nSistema de E-mail";

                // Enviar e-mail
                $mail->send();
                $success = "Um link de redefinição foi enviado para o seu e-mail.";
            } catch (Exception $e) {
                $error = "Falha ao enviar o e-mail: " . $mail->ErrorInfo;
            }
        } else {
            $error = "Erro ao gerar o link de redefinição: " . $conn->error;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="recover-container">
        <h2>Recuperar Senha</h2>
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <input type="button" value="Voltar para o Login" class="back-to-login" onclick="window.location.href='index.php'">
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label>E-mail Cadastrado</label>
                    <input type="email" id="reset_email" name="reset_email" placeholder="Digite seu e-mail" required>
                </div>
                <input type="submit" value="Enviar Link de Redefinição" class="recover-submit">
                <a href="index.php" class="back-to-login">Voltar para o Login</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
