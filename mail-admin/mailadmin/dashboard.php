<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php");
    exit;
}

require_once 'config.db.php';

// Conexão com o banco de dados
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, DB_PORT);
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Obter os domínios gerenciados pelo administrador
$username = $_SESSION['username'];
$domains = [];
$selected_domain = isset($_POST['domain']) ? $_POST['domain'] : (isset($_GET['domain']) ? $_GET['domain'] : (isset($domains[0]) ? $domains[0] : ''));
$is_superadmin = isset($_SESSION['is_superadmin']) ? $_SESSION['is_superadmin'] : 0;

if ($is_superadmin) {
    // SuperAdmin gerencia todos os domínios
    $result = $conn->query("SELECT domain FROM domain WHERE active = 1");
    while ($row = $result->fetch_assoc()) {
        $domains[$row['domain']] = $row['domain'];
    }
    // Contagem total de domínios
    $domainCount = count($domains);
} else {
    // Admin normal só vê os domínios que gerencia
    $stmt = $conn->prepare("SELECT domain FROM admin_domains WHERE admin_username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $domains[$row['domain']] = $row['domain'];
    }
    $stmt->close();
    $domainCount = count($domains);
}

// Se não houver domínios, definir contagens como 0
$userCount = $aliasCount = $redirectCount = $groupCount = $adminCount = 0;

// Filtrar dados com base no domínio selecionado
$domainFilter = '';
if (!empty($selected_domain)) {
    $domainFilter = "AND domain = '" . $conn->real_escape_string($selected_domain) . "'";
}
if (!empty($domains)) {
    $domainList = "'" . implode("','", array_keys($domains)) . "'";

    // Usuários (tabela mailbox)
    $query = "SELECT COUNT(*) as count FROM mailbox WHERE active = 1 AND domain IN ($domainList) " . $domainFilter;
    $userCount = $conn->query($query)->fetch_assoc()['count'];

    // Aliases (tabela alias, funcion = 'A')
    $query = "SELECT COUNT(*) as count FROM alias WHERE active = 1 AND funcion = 'A' AND domain IN ($domainList) " . $domainFilter;
    $aliasCount = $conn->query($query)->fetch_assoc()['count'];

    // Redirecionamentos (tabela alias, funcion = 'R')
    $query = "SELECT COUNT(*) as count FROM alias WHERE active = 1 AND funcion = 'R' AND domain IN ($domainList) " . $domainFilter;
    $redirectCount = $conn->query($query)->fetch_assoc()['count'];

    // Grupos (tabela alias, funcion = 'G')
    $query = "SELECT COUNT(*) as count FROM alias WHERE active = 1 AND funcion = 'G' AND domain IN ($domainList) " . $domainFilter;
    $groupCount = $conn->query($query)->fetch_assoc()['count'];
}

// Contagem de admins (apenas para SuperAdmin, sem filtro de domínio)
if ($is_superadmin) {
    $adminCount = $conn->query("SELECT COUNT(*) as count FROM admins WHERE active = 1")->fetch_assoc()['count'];
}

// Obter administradores vinculados por domínio (apenas para SuperAdmin)
$adminDomains = [];
if ($is_superadmin && !empty($selected_domain)) {
    $stmt = $conn->prepare("SELECT admin_username FROM admin_domains WHERE domain = ?");
    $stmt->bind_param("s", $selected_domain);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $adminDomains[] = $row['admin_username'];
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/topo.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'topo.php'; ?>
    <div class="content">
        <?php if (count($domains) > 1): ?>
            <div class="domain-selector">
                <form method="POST" action="">
                    <select name="domain" onchange="this.form.submit()">
                        <?php if ($is_superadmin): ?>
                            <option value="">Todos os Domínios</option>
                        <?php endif; ?>
                        <?php foreach ($domains as $domain): ?>
                            <option value="<?php echo htmlspecialchars($domain); ?>" <?php echo $selected_domain === $domain ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($domain); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        <?php endif; ?>
        <?php if ($is_superadmin): ?>
            <div class="widgets">
                <button class="widget-btn" disabled>
                    <i class="fas fa-globe"></i> Domínios
                    <span class="status">Total</span>
                    <p><?php echo $domainCount; ?></p>
                </button>
                <?php if (empty($selected_domain)): ?>
                    <button class="widget-btn" disabled>
                        <i class="fas fa-user-shield"></i> Admins
                        <span class="status">Total</span>
                        <p><?php echo $adminCount; ?></p>
                    </button>
                <?php endif; ?>
                <?php if (!empty($selected_domain)): ?>
                    <button class="widget-btn" disabled>
                        <i class="fas fa-users"></i> Administradores Vinculados (<?php echo htmlspecialchars($selected_domain); ?>)
                        <span class="status">Total</span>
                        <p><?php echo implode(', ', array_map('htmlspecialchars', $adminDomains)); ?></p>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="widgets">
            <button class="widget-btn" disabled>
                <i class="fas fa-users"></i> Usuários
                <span class="status">Ativos</span>
                <p><?php echo $userCount; ?></p>
            </button>
            <button class="widget-btn" disabled>
                <i class="fas fa-at"></i> Aliases
                <span class="status">Ativos</span>
                <p><?php echo $aliasCount; ?></p>
            </button>
            <button class="widget-btn" disabled>
                <i class="fas fa-share"></i> Redirecionamentos
                <span class="status">Ativos</span>
                <p><?php echo $redirectCount; ?></p>
            </button>
            <button class="widget-btn" disabled>
                <i class="fas fa-users-cog"></i> Grupos
                <span class="status">Ativos</span>
                <p><?php echo $groupCount; ?></p>
            </button>
        </div>
    </div>
    <script src="js/topo.js"></script>
    <script src="js/dashboard.js"></script>
</body>
</html>