<?php
// Não precisamos de session_start() aqui, pois já foi chamado no arquivo principal
$username = $_SESSION['username'];
$is_superadmin = isset($_SESSION['is_superadmin']) ? $_SESSION['is_superadmin'] : 0;
// Definir query string para o domínio selecionado, se existir
$domain_query = isset($_SESSION['selected_domain']) ? '?domain=' . urlencode($_SESSION['selected_domain']) : '';
?>

<div class="header">
    <div class="logo-container">
        <img src="imagens/Logo.png" alt="Logo do Sistema">
    </div>
</div>
<div class="menu">
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php<?php echo $domain_query; ?>">Usuários</a>
    <a href="aliases.php<?php echo $domain_query; ?>">Aliases</a>
    <a href="groups.php<?php echo $domain_query; ?>">Grupos</a>
    <a href="redirects.php<?php echo $domain_query; ?>">Redirecionamentos</a>
    <?php if ($is_superadmin): ?>
        <a href="domains.php<?php echo $domain_query; ?>">Domínios</a>
        <a href="admins.php">Administradores</a>
    <?php endif; ?>
    <a href="perfil.php">Perfil</a>
    <a href="logout.php">Sair (<?php echo htmlspecialchars($username); ?>)</a>
</div>