<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php");
    exit;
}

require_once 'config.db.php';

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

// Verificar se o usuário é SuperAdmin
$is_superadmin = isset($_SESSION['is_superadmin']) ? $_SESSION['is_superadmin'] : 0;

// Buscar domínios permitidos
$domains = [];
if ($is_superadmin) {
    $domains_query = "SELECT domain FROM domain WHERE active = 1";
} else {
    $username = $conn->real_escape_string($_SESSION['username']);
    $domains_query = "SELECT d.domain FROM domain d INNER JOIN admin_domains ad ON d.domain = ad.domain WHERE ad.admin_username = '$username' AND d.active = 1";
}

$domains_result = $conn->query($domains_query);
if ($domains_result && $domains_result->num_rows > 0) {
    while ($row = $domains_result->fetch_assoc()) {
        $domains[] = $row['domain'];
    }
} else {
    if ($is_superadmin) {
        die("Erro ao buscar domínios: " . $conn->error);
    } else {
        die("Acesso negado. Você não tem permissão para gerenciar grupos.");
    }
}

// Determinar o domínio selecionado
if (isset($_POST['domain']) && in_array($_POST['domain'], $domains)) {
    $_SESSION['selected_domain'] = $_POST['domain'];
}
$selected_domain = isset($_SESSION['selected_domain']) && in_array($_SESSION['selected_domain'], $domains) ? $_SESSION['selected_domain'] : (isset($_GET['domain']) && in_array($_GET['domain'], $domains) ? $_GET['domain'] : (isset($domains[0]) ? $domains[0] : ''));

// Verificar se o domínio selecionado está na lista de domínios permitidos
if (!in_array($selected_domain, $domains)) {
    die("Acesso negado. Você não tem permissão para gerenciar este domínio.");
}

// Listar grupos apenas do domínio selecionado com funcion = 'G'
$groups_query = "SELECT address, goto, domain, active FROM alias WHERE domain = '" . $conn->real_escape_string($selected_domain) . "' AND funcion = 'G'";
$groups_result = $conn->query($groups_query);

// Excluir grupo
if (isset($_GET['delete'])) {
    $address_to_delete = $conn->real_escape_string($_GET['delete']);
    $delete_query = "DELETE FROM alias WHERE address = '$address_to_delete'";
    if ($conn->query($delete_query) === TRUE) {
        $success = "Grupo $address_to_delete excluído com sucesso!";
        $groups_result = $conn->query($groups_query);
    } else {
        $error = "Erro ao excluir grupo: " . $conn->error;
    }
}

// Ativar/Desativar grupo
if (isset($_GET['toggle_active'])) {
    $address_to_toggle = $conn->real_escape_string($_GET['toggle_active']);
    $current_status_query = "SELECT active FROM alias WHERE address = '$address_to_toggle'";
    $status_result = $conn->query($current_status_query);
    if ($status_result && $status_result->num_rows > 0) {
        $row = $status_result->fetch_assoc();
        $new_status = $row['active'] ? 0 : 1;
        $update_query = "UPDATE alias SET active = $new_status WHERE address = '$address_to_toggle'";
        if ($conn->query($update_query) === TRUE) {
            $success = "Status do grupo $address_to_toggle alterado com sucesso!";
            $groups_result = $conn->query($groups_query);
        } else {
            $error = "Erro ao alterar status: " . $conn->error;
        }
    }
}

// Editar grupo
if (isset($_POST['edit_group']) && isset($_POST['edit_address'])) {
    $edit_address = $conn->real_escape_string($_POST['edit_address']);
    $new_goto = trim($_POST['goto']);
    $goto_emails = array_map('trim', explode(',', $new_goto));
    $valid_emails = true;

    foreach ($goto_emails as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $valid_emails = false;
            break;
        }
    }

    if (count($goto_emails) < 2) {
        $error = "O grupo deve ter pelo menos dois e-mails de destino.";
    } elseif (!$valid_emails) {
        $error = "Um ou mais e-mails de destino são inválidos.";
    } else {
        $update_query = "UPDATE alias SET goto = '" . $conn->real_escape_string($new_goto) . "', funcion = 'G' WHERE address = '$edit_address'";
        if ($conn->query($update_query) === TRUE) {
            $success = "Grupo $edit_address editado com sucesso!";
            $groups_result = $conn->query($groups_query);
        } else {
            $error = "Erro ao editar grupo: " . $conn->error;
        }
    }
}

// Adicionar novo grupo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_group'])) {
    $group_name = trim($_POST['group_name']);
    $goto = trim($_POST['goto']);
    $domain = $selected_domain;
    $address = "$group_name@$domain";

    if (empty($group_name) || empty($goto)) {
        $error = "Todos os campos são obrigatórios.";
    } else {
        // Verificar se goto tem pelo menos 2 e-mails
        $goto_emails = array_map('trim', explode(',', $goto));
        $valid_emails = true;
        foreach ($goto_emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid_emails = false;
                break;
            }
        }
        if (count($goto_emails) < 2) {
            $error = "O grupo deve ter pelo menos dois e-mails de destino.";
        } elseif (!$valid_emails) {
            $error = "Um ou mais e-mails de destino são inválidos.";
        } elseif (!in_array($domain, $domains)) {
            $error = "Domínio inválido.";
        } else {
            // Verificar se o grupo existe na tabela mailbox (como usuário)
            $check_mailbox_query = "SELECT username FROM mailbox WHERE username = '" . $conn->real_escape_string($address) . "'";
            $check_mailbox_result = $conn->query($check_mailbox_query);
            if ($check_mailbox_result && $check_mailbox_result->num_rows > 0) {
                $error = "Não é possível criar o grupo porque o endereço $address já é um Usuário.";
            } else {
                // Verificar se o grupo já existe na tabela alias e determinar o tipo
                $check_alias_query = "SELECT address, funcion FROM alias WHERE address = '" . $conn->real_escape_string($address) . "'";
                $check_alias_result = $conn->query($check_alias_query);
                if ($check_alias_result && $check_alias_result->num_rows > 0) {
                    $alias_row = $check_alias_result->fetch_assoc();
                    $funcion = $alias_row['funcion'];
                    if ($funcion === 'A') {
                        $error = "Não é possível criar o grupo porque o endereço $address já é um Alias.";
                    } elseif ($funcion === 'R') {
                        $error = "Não é possível criar o grupo porque o endereço $address já é um Redirecionamento.";
                    } else {
                        $error = "Não é possível criar o grupo porque o endereço $address já é outro Grupo.";
                    }
                } else {
                    $escaped_address = $conn->real_escape_string($address);
                    $escaped_goto = $conn->real_escape_string($goto);
                    $escaped_domain = $conn->real_escape_string($domain);

                    // Inserir o grupo com funcion = 'G'
                    $insert_query = "INSERT INTO alias (address, goto, domain, active, funcion) VALUES ('$escaped_address', '$escaped_goto', '$escaped_domain', 1, 'G')";
                    if ($conn->query($insert_query) === TRUE) {
                        $success = "Grupo $address criado com sucesso!";
                        $groups_result = $conn->query($groups_query);
                    } else {
                        $error = "Erro ao inserir grupo: " . $conn->error;
                    }
                }
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
    <title>Grupos - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/groups.css">
    <link rel="stylesheet" href="css/topo.css">
</head>
<body>
    <?php include 'topo.php'; ?>
    <div class="content">
        <?php if (count($domains) > 1): ?>
            <div class="domain-selector">
                <form method="POST" action="">
                    <label for="domain">Domínio:</label>
                    <select id="domain" name="domain" onchange="this.form.submit()">
                        <?php foreach ($domains as $domain): ?>
                            <option value="<?php echo htmlspecialchars($domain); ?>" <?php echo ($selected_domain === $domain) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($domain); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="refresh" value="1">
                </form>
            </div>
        <?php endif; ?>
        <div class="table-container">
            <div class="table-header">
                <h2>Lista de Grupos</h2>
                <button onclick="openAddModal()">Adicionar Grupo</button>
            </div>
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if ($groups_result && $groups_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Ação</th>
                                <th>Grupo</th>
                                <th>Destinos</th>
                                <th>Domínio</th>
                                <th>Ativo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($group = $groups_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="#" class="edit-icon" onclick="openEditModal('<?php echo htmlspecialchars($group['address']); ?>', '<?php echo htmlspecialchars($group['goto']); ?>')">✏️</a>
                                        <a href="?delete=<?php echo urlencode($group['address']); ?>&domain=<?php echo urlencode($selected_domain); ?>" class="delete-icon" onclick="return confirm('Tem certeza que deseja excluir o grupo <?php echo htmlspecialchars($group['address']); ?>?');">🗑️</a>
                                        <a href="?toggle_active=<?php echo urlencode($group['address']); ?>&domain=<?php echo urlencode($selected_domain); ?>" class="toggle-icon"><?php echo $group['active'] ? '🔘' : '⭕'; ?></a>
                                    </td>
                                    <td><?php echo htmlspecialchars($group['address']); ?></td>
                                    <td><?php echo htmlspecialchars($group['goto']); ?></td>
                                    <td><?php echo htmlspecialchars($group['domain']); ?></td>
                                    <td><?php echo $group['active'] ? 'Sim' : 'Não'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Nenhum grupo encontrado para o domínio selecionado.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Adicionar Grupo -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAddModal()">×</span>
            <h2>Adicionar Novo Grupo</h2>
            <form method="POST" action="">
                <input type="hidden" name="domain" value="<?php echo htmlspecialchars($selected_domain); ?>">
                <div class="form-group">
                    <label for="group_name">Grupo:</label>
                    <div class="input-with-domain">
                        <input type="text" id="group_name" name="group_name" value="<?php echo isset($_POST['group_name']) ? htmlspecialchars($_POST['group_name']) : ''; ?>" required>
                        <span class="domain-suffix">@<?php echo htmlspecialchars($selected_domain); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="goto">Destinos (pressione Enter ou Espaço para adicionar):</label>
                    <div class="chip-container" id="addChipContainer">
                        <input type="text" id="addEmailInput" placeholder="Digite o e-mail e pressione Enter ou Espaço">
                    </div>
                    <input type="hidden" name="goto" id="addGotoHidden">
                </div>
                <div class="form-group">
                    <input type="submit" name="add_group" value="Adicionar Grupo" class="modal-submit-btn">
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Editar Grupo -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">×</span>
            <h2>Editar Grupo</h2>
            <form method="POST" action="">
                <input type="hidden" name="domain" value="<?php echo htmlspecialchars($selected_domain); ?>">
                <input type="hidden" name="edit_address" id="editAddress">
                <div class="form-group">
                    <label>Grupo:</label>
                    <div class="input-with-domain">
                        <input type="text" id="editGroupName" name="group_name" disabled>
                        <span class="domain-suffix">@<?php echo htmlspecialchars($selected_domain); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="editGoto">Destinos (pressione Enter ou Espaço para adicionar):</label>
                    <div class="chip-container" id="editChipContainer">
                        <input type="text" id="editEmailInput" placeholder="Digite o e-mail e pressione Enter ou Espaço">
                    </div>
                    <input type="hidden" name="goto" id="editGotoHidden">
                </div>
                <div class="form-group">
                    <input type="submit" name="edit_group" value="Salvar Alterações" class="modal-submit-btn">
                </div>
            </form>
        </div>
    </div>

    <script src="js/groups.js"></script>
    <script src="js/topo.js"></script>
    <script>
        console.log('Script groups.js incluído no groups.php');
    </script>
</body>
</html>