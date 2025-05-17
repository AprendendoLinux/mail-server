<?php
// Não precisamos de session_start() aqui, pois já foi chamado no arquivo principal
$username = $_SESSION['username'];
$is_superadmin = isset($_SESSION['is_superadmin']) ? $_SESSION['is_superadmin'] : 0;
?>

<div class="header">
    <div class="logo-container">
        <img src="imagens/Logo.png" alt="Logo do Sistema">
    </div>
</div>
<div class="menu">
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Usuários</a>
    <a href="aliases.php">Aliases</a>
    <a href="groups.php">Grupos</a>
    <a href="redirects.php">Redirecionamentos</a>
    <?php if ($is_superadmin): ?>
        <a href="domains.php">Domínios</a>
        <a href="admins.php">Administradores</a>
    <?php endif; ?>
    <a href="perfil.php">Perfil</a>
    <a href="logout.php">Sair (<?php echo htmlspecialchars($username); ?>)</a>
</div>
