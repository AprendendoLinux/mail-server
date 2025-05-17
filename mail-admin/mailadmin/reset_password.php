<?php
require_once 'config.smtp.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, DB_PORT);
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_GET['token'] ?? '';
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validar token
    $query = "SELECT email FROM password_resets WHERE token = '" . $conn->real_escape_string($token) . "' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $result = $conn->query($query);
    if ($result->num_rows === 0) {
        $error = "Token inválido ou expirado.";
    } else {
        $row = $result->fetch_assoc();
        $email = $row['email'];

        // Validar senhas
        if ($new_password !== $confirm_password) {
            $error = "As senhas não coincidem.";
        } elseif (strlen($new_password) < 6) {
            $error = "A senha deve ter pelo menos 6 caracteres.";
        } else {
            // Atualizar senha
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE admins SET password = '" . $conn->real_escape_string($hashed_password) . "' WHERE email = '" . $conn->real_escape_string($email) . "'";
            if ($conn->query($update_query) === TRUE) {
                // Remover token após uso
                $delete_query = "DELETE FROM password_resets WHERE token = '" . $conn->real_escape_string($token) . "'";
                $conn->query($delete_query);
                $success = "Senha redefinida com sucesso.";
            } else {
                $error = "Erro ao redefinir a senha: " . $conn->error;
            }
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
    <title>Redefinir Senha - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/reset_password.css">
</head>
<body>
    <div class="reset-container">
        <h2>Redefinir Senha</h2>
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <input type="button" value="Voltar ao Login" class="back-to-login" onclick="window.location.href='index.php'">
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="new_password">Nova Senha</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Digite sua nova senha" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar Senha</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirme sua nova senha" required>
                </div>
                <input type="submit" value="Redefinir Senha" class="reset-submit">
                <input type="button" value="Voltar ao Login" class="back-to-login" onclick="window.location.href='index.php'">
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
