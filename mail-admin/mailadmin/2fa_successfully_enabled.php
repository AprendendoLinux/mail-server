<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php");
    exit;
}

// Verificar se o 2FA foi recém-ativado
if (!isset($_SESSION['temp_two_factor_enabled']) || $_SESSION['temp_two_factor_enabled'] != 1) {
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Habilitado - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/2fa_successfully_enabled.css">
</head>
<body>
    <div class="login-container">
        <img src="imagens/Logo.png" alt="Logo do Sistema">
        <h2>Autenticação em Duas Etapas</h2>
        <div class="twofa-alert">
            <p>2FA ativado com sucesso!</p>
            <button id="continue-login-btn" class="twofa-button">Continuar o login</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const continueLoginBtn = document.getElementById('continue-login-btn');
            if (continueLoginBtn) {
                continueLoginBtn.addEventListener('click', function(event) {
                    event.preventDefault();
                    window.location.href = 'dashboard.php';
                });
            }
        });
    </script>
</body>
</html>