<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php");
    exit;
}

require_once 'config.db.php';

// Verificar se o usuário é SuperAdmin
$is_superadmin = isset($_SESSION['is_superadmin']) ? $_SESSION['is_superadmin'] : 0;

if (!$is_superadmin) {
    die("Acesso negado. Apenas SuperAdmins podem gerenciar domínios.");
}

// Salvar domínio selecionado se enviado via POST
$domains = [];
$temp_conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, DB_PORT);
if ($temp_conn->connect_error) {
    die("Conexão temporária falhou: " . $temp_conn->connect_error);
}
$domains_query = "SELECT domain FROM domain WHERE active = 1";
$domains_result_temp = $temp_conn->query($domains_query);
if ($domains_result_temp && $domains_result_temp->num_rows > 0) {
    while ($row = $domains_result_temp->fetch_assoc()) {
        $domains[] = $row['domain'];
    }
}
$temp_conn->close();
if (isset($_POST['domain']) && in_array($_POST['domain'], $domains)) {
    $_SESSION['selected_domain'] = $_POST['domain'];
}

// Inicializar mensagens
$error = '';
$success = '';

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

// Listar domínios
$domains_query = "SELECT domain, active FROM domain";
$domains_result = $conn->query($domains_query);

// Excluir domínio
if (isset($_GET['delete'])) {
    $domain_to_delete = $conn->real_escape_string($_GET['delete']);
    // Verificar se há contas associadas na tabela mailbox
    $check_mailbox_query = "SELECT username FROM mailbox WHERE domain = '$domain_to_delete'";
    $check_mailbox_result = $conn->query($check_mailbox_query);
    $has_accounts = $check_mailbox_result && $check_mailbox_result->num_rows > 0;
    $account_count = $has_accounts ? $check_mailbox_result->num_rows : 0;

    // Verificar se há aliases, grupos ou redirecionamentos na tabela alias
    $check_alias_query = "SELECT address FROM alias WHERE domain = '$domain_to_delete'";
    $check_alias_result = $conn->query($check_alias_query);
    $has_aliases = $check_alias_result && $check_alias_result->num_rows > 0;

    // Verificar se há administradores (não SuperAdmins) associados na tabela admin_domains
    $check_admin_domains_query = "SELECT a.admin_username 
                                 FROM admin_domains a 
                                 JOIN admins ad ON a.admin_username = ad.username 
                                 WHERE a.domain = '$domain_to_delete' AND ad.is_superadmin = 0";
    $check_admin_domains_result = $conn->query($check_admin_domains_query);
    $has_domain_admins = $check_admin_domains_result && $check_admin_domains_result->num_rows > 0;
    $admin_count = $has_domain_admins ? $check_admin_domains_result->num_rows : 0;
    $admin_usernames = [];
    if ($has_domain_admins) {
        while ($row = $check_admin_domains_result->fetch_assoc()) {
            $admin_usernames[] = $row['admin_username'];
        }
    }

    if ($has_accounts || $has_aliases || $admin_count > 1) {
        // Bloquear exclusão se houver usuários, aliases ou mais de um administrador
        $message = [];
        if ($has_accounts) $message[] = "existem $account_count contas associadas";
        if ($has_aliases) $message[] = "existem aliases, grupos ou redirecionamentos associados";
        if ($admin_count > 1) $message[] = "existem $admin_count administradores de domínio associados";
        $error = "Não é possível excluir o domínio $domain_to_delete, pois " . implode(" e ", $message) . ".";
    } elseif ($admin_count == 1) {
        // Se houver exatamente um administrador e nenhuma outra dependência, excluir administrador e domínio
        $conn->begin_transaction();
        try {
            // Excluir o administrador da tabela admins (admin_domains será limpo automaticamente por ON DELETE CASCADE)
            $delete_admin_query = "DELETE FROM admins WHERE username = '" . $conn->real_escape_string($admin_usernames[0]) . "'";
            if (!$conn->query($delete_admin_query)) {
                throw new Exception("Erro ao excluir o administrador associado: " . $conn->error);
            }

            // Excluir o domínio da tabela domain
            $delete_domain_query = "DELETE FROM domain WHERE domain = '$domain_to_delete'";
            if (!$conn->query($delete_domain_query)) {
                throw new Exception("Erro ao excluir domínio: " . $conn->error);
            }

            // Confirmar transação
            $conn->commit();
            $success = "Domínio $domain_to_delete e o administrador associado (" . htmlspecialchars($admin_usernames[0]) . ") foram excluídos com sucesso!";
            $domains_result = $conn->query($domains_query);
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    } else {
        // Se não houver usuários, aliases nem administradores, excluir apenas o domínio
        $delete_query = "DELETE FROM domain WHERE domain = '$domain_to_delete'";
        if ($conn->query($delete_query) === TRUE) {
            $success = "Domínio $domain_to_delete excluído com sucesso!";
            $domains_result = $conn->query($domains_query);
        } else {
            $error = "Erro ao excluir domínio: " . $conn->error;
        }
    }
}

// Ativar/Desativar domínio
if (isset($_GET['toggle_active'])) {
    $domain_to_toggle = $conn->real_escape_string($_GET['toggle_active']);
    $current_status_query = "SELECT active FROM domain WHERE domain = '$domain_to_toggle'";
    $status_result = $conn->query($current_status_query);
    if ($status_result && $status_result->num_rows > 0) {
        $row = $status_result->fetch_assoc();
        $current_status = $row['active'];
        $new_status = $current_status ? 0 : 1;

        // Se estamos tentando desabilitar (active de 1 para 0), verificar dependências
        if ($current_status == 1) {
            // Verificar se há usuários habilitados na tabela mailbox
            $check_active_mailbox_query = "SELECT username FROM mailbox WHERE domain = '$domain_to_toggle' AND active = 1";
            $check_active_mailbox_result = $conn->query($check_active_mailbox_query);
            $has_active_accounts = $check_active_mailbox_result && $check_active_mailbox_result->num_rows > 0;

            // Verificar se há aliases ou grupos ativos na tabela alias
            $check_active_alias_query = "SELECT address FROM alias WHERE domain = '$domain_to_toggle' AND active = 1";
            $check_active_alias_result = $conn->query($check_active_alias_query);
            $has_active_aliases = $check_active_alias_result && $check_active_alias_result->num_rows > 0;

            if ($has_active_accounts || $has_active_aliases) {
                $message = [];
                if ($has_active_accounts) $message[] = "existem contas ativas";
                if ($has_active_aliases) $message[] = "existem aliases ou grupos ativos";
                $error = "Não é possível desativar o domínio $domain_to_toggle, pois " . implode(" e ", $message) . ".";
            } else {
                $update_query = "UPDATE domain SET active = $new_status WHERE domain = '$domain_to_toggle'";
                if ($conn->query($update_query) === TRUE) {
                    $success = "Status do domínio $domain_to_toggle alterado com sucesso!";
                    $domains_result = $conn->query($domains_query);
                } else {
                    $error = "Erro ao alterar status: " . $conn->error;
                }
            }
        } else {
            // Ativar o domínio
            $update_query = "UPDATE domain SET active = $new_status WHERE domain = '$domain_to_toggle'";
            if ($conn->query($update_query) === TRUE) {
                $success = "Status do domínio $domain_to_toggle alterado com sucesso!";
                $domains_result = $conn->query($domains_query);
            } else {
                $error = "Erro ao alterar status: " . $conn->error;
            }
        }
    }
}

// Adicionar novo domínio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_domain'])) {
    $domain = trim($_POST['domain']);
    $active = 1; // Domínio sempre adicionado como ativo

    if (empty($domain)) {
        $error = "O nome do domínio é obrigatório.";
    } elseif (!preg_match('/^[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*$/', $domain)) {
        $error = "Formato de domínio inválido.";
    } else {
        // Verificar se o domínio já existe
        $check_domain_query = "SELECT domain FROM domain WHERE domain = '" . $conn->real_escape_string($domain) . "'";
        $check_domain_result = $conn->query($check_domain_query);
        if ($check_domain_result && $check_domain_result->num_rows > 0) {
            $error = "O domínio $domain já existe.";
        } else {
            $insert_query = "INSERT INTO domain (domain, active) VALUES ('" . $conn->real_escape_string($domain) . "', $active)";
            if ($conn->query($insert_query) === TRUE) {
                $success = "Domínio $domain adicionado com sucesso!";
                $domains_result = $conn->query($domains_query);
            } else {
                $error = "Erro ao adicionar domínio: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domínios - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/domains.css">
    <link rel="stylesheet" href="css/topo.css">
</head>
<body>
    <?php include 'topo.php'; ?>
    <div class="content">
        <div class="table-container">
            <div class="table-header">
                <h2>Lista de Domínios</h2>
                <button onclick="openAddModal()">Adicionar Domínio</button>
            </div>
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if ($domains_result && $domains_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Ação</th>
                                <th>Domínio</th>
                                <th>Ativo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($domain = $domains_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="?delete=<?php echo urlencode($domain['domain']); ?><?php echo isset($_SESSION['selected_domain']) ? '&domain=' . urlencode($_SESSION['selected_domain']) : ''; ?>" class="delete-icon" onclick="return confirm('Tem certeza que deseja excluir o domínio <?php echo htmlspecialchars($domain['domain']); ?>?');" <?php echo ($domain['domain'] === 'default' || $domain['active'] == 0) ? 'disabled' : ''; ?>>🗑️</a>
                                        <a href="?toggle_active=<?php echo urlencode($domain['domain']); ?><?php echo isset($_SESSION['selected_domain']) ? '&domain=' . urlencode($_SESSION['selected_domain']) : ''; ?>" class="toggle-icon"><?php echo $domain['active'] ? '🔘' : '⭕'; ?></a>
                                    </td>
                                    <td><?php echo htmlspecialchars($domain['domain']); ?></td>
                                    <td><?php echo $domain['active'] ? 'Sim' : 'Não'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Nenhum domínio encontrado.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Adicionar Domínio -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAddModal()">×</span>
            <h2>Adicionar Novo Domínio</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="domain">Domínio:</label>
                    <input type="text" id="domain" name="domain" value="<?php echo isset($_POST['domain']) ? htmlspecialchars($_POST['domain']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <input type="submit" name="add_domain" value="Adicionar Domínio" class="modal-submit-btn">
                </div>
            </form>
        </div>
    </div>

    <script src="js/domains.js"></script>
    <script src="js/topo.js"></script>
</body>
<?php
// Fechar a conexão apenas após todas as operações
$conn->close();
?>
</html>