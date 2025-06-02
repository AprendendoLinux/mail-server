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
        die("Acesso negado. Você não tem permissão para gerenciar aliases.");
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

// Listar apenas aliases com funcion = 'A'
$aliases_query = "SELECT address, goto, domain, active, funcion FROM alias WHERE domain = '" . $conn->real_escape_string($selected_domain) . "' AND funcion = 'A'";
$aliases_result = $conn->query($aliases_query);

// Excluir alias
if (isset($_GET['delete'])) {
    $address_to_delete = $conn->real_escape_string($_GET['delete']);
    $delete_query = "DELETE FROM alias WHERE address = '$address_to_delete'";
    if ($conn->query($delete_query) === TRUE) {
        $success = "Alias $address_to_delete excluído com sucesso!";
        $aliases_result = $conn->query($aliases_query);
    } else {
        $error = "Erro ao excluir alias: " . $conn->error;
    }
}

// Ativar/Desativar alias
if (isset($_GET['toggle_active'])) {
    $address_to_toggle = $conn->real_escape_string($_GET['toggle_active']);
    $current_status_query = "SELECT active FROM alias WHERE address = '$address_to_toggle'";
    $status_result = $conn->query($current_status_query);
    if ($status_result && $status_result->num_rows > 0) {
        $row = $status_result->fetch_assoc();
        $new_status = $row['active'] ? 0 : 1;
        $update_query = "UPDATE alias SET active = $new_status WHERE address = '$address_to_toggle'";
        if ($conn->query($update_query) === TRUE) {
            $success = "Status do alias $address_to_toggle alterado com sucesso!";
            $aliases_result = $conn->query($aliases_query);
        } else {
            $error = "Erro ao alterar status: " . $conn->error;
        }
    }
}

// Editar alias (apenas o destino)
if (isset($_POST['edit_alias']) && isset($_POST['edit_address'])) {
    $edit_address = $conn->real_escape_string($_POST['edit_address']);
    $new_goto = trim($_POST['goto']);

    if (empty($new_goto)) {
        $error = "O campo de destino é obrigatório.";
    } elseif (!filter_var($new_goto, FILTER_VALIDATE_EMAIL)) {
        $error = "O e-mail de destino é inválido.";
    } else {
        $update_query = "UPDATE alias SET goto = '" . $conn->real_escape_string($new_goto) . "' WHERE address = '$edit_address'";
        if ($conn->query($update_query) === TRUE) {
            $success = "Alias $edit_address editado com sucesso!";
            $aliases_result = $conn->query($aliases_query);
        } else {
            $error = "Erro ao editar alias: " . $conn->error;
        }
    }
}

// Adicionar novo alias
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_alias'])) {
    $alias_name = trim($_POST['alias_name']);
    $goto = trim($_POST['goto']);
    $domain = $selected_domain;
    $address = "$alias_name@$domain";

    if (empty($alias_name) || empty($goto)) {
        $error = "Todos os campos são obrigatórios.";
    } elseif (!filter_var($goto, FILTER_VALIDATE_EMAIL)) {
        $error = "O e-mail de destino é inválido.";
    } elseif (!in_array($domain, $domains)) {
        $error = "Domínio inválido.";
    } else {
        // Verificar se o alias existe na tabela mailbox
        $check_mailbox_query = "SELECT username FROM mailbox WHERE username = '" . $conn->real_escape_string($address) . "'";
        $check_mailbox_result = $conn->query($check_mailbox_query);
        if ($check_mailbox_result && $check_mailbox_result->num_rows > 0) {
            $error = "Não é possível criar o alias porque o endereço $address já existe como usuário na tabela mailbox.";
        } else {
            // Verificar se o alias já existe na tabela alias e determinar o tipo
            $check_alias_query = "SELECT address, funcion FROM alias WHERE address = '" . $conn->real_escape_string($address) . "'";
            $check_alias_result = $conn->query($check_alias_query);
            if ($check_alias_result && $check_alias_result->num_rows > 0) {
                $alias_row = $check_alias_result->fetch_assoc();
                $funcion = $alias_row['funcion'];
                if ($funcion === 'G') {
                    $error = "Não é possível criar o alias porque o endereço $address já é um Grupo.";
                } elseif ($funcion === 'R') {
                    $error = "Não é possível criar o alias porque o endereço $address já é um Redirecionamento.";
                } else {
                    $error = "O alias $address já existe.";
                }
            } else {
                $escaped_address = $conn->real_escape_string($address);
                $escaped_goto = $conn->real_escape_string($goto);
                $escaped_domain = $conn->real_escape_string($domain);

                // Inserir o alias com funcion = 'A'
                $insert_query = "INSERT INTO alias (address, goto, domain, active, funcion) VALUES ('$escaped_address', '$escaped_goto', '$escaped_domain', 1, 'A')";
                if ($conn->query($insert_query) === TRUE) {
                    $success = "Alias $address criado com sucesso!";
                    $aliases_result = $conn->query($aliases_query);
                } else {
                    $error = "Erro ao inserir alias: " . $conn->error;
                }
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
    <title>Aliases - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/aliases.css">
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
                <h2>Lista de Aliases</h2>
                <button onclick="openAddModal()">Adicionar Alias</button>
            </div>
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if ($aliases_result && $aliases_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Ação</th>
                                <th>Alias</th>
                                <th>Destino</th>
                                <th>Domínio</th>
                                <th>Ativo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($alias = $aliases_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="#" class="edit-icon" onclick="openEditModal('<?php echo htmlspecialchars($alias['address']); ?>', '<?php echo htmlspecialchars($alias['goto']); ?>')">✏️</a>
                                        <a href="?delete=<?php echo urlencode($alias['address']); ?>&domain=<?php echo urlencode($selected_domain); ?>" class="delete-icon" onclick="return confirm('Tem certeza que deseja excluir o alias <?php echo htmlspecialchars($alias['address']); ?>?');">🗑️</a>
                                        <a href="?toggle_active=<?php echo urlencode($alias['address']); ?>&domain=<?php echo urlencode($selected_domain); ?>" class="toggle-icon"><?php echo $alias['active'] ? '🔘' : '⭕'; ?></a>
                                    </td>
                                    <td><?php echo htmlspecialchars($alias['address']); ?></td>
                                    <td><?php echo htmlspecialchars($alias['goto']); ?></td>
                                    <td><?php echo htmlspecialchars($alias['domain']); ?></td>
                                    <td><?php echo $alias['active'] ? 'Sim' : 'Não'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Nenhum alias encontrado para o domínio selecionado.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Adicionar Alias -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAddModal()">×</span>
            <h2>Adicionar Novo Alias</h2>
            <form method="POST" action="">
                <input type="hidden" name="domain" value="<?php echo htmlspecialchars($selected_domain); ?>">
                <div class="form-group">
                    <label for="alias_name">Alias:</label>
                    <div class="input-with-domain">
                        <input type="text" id="alias_name" name="alias_name" value="<?php echo isset($_POST['alias_name']) ? htmlspecialchars($_POST['alias_name']) : ''; ?>" required>
                        <span class="domain-suffix">@<?php echo htmlspecialchars($selected_domain); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="goto">Destino:</label>
                    <input type="email" id="goto" name="goto" value="<?php echo isset($_POST['goto']) ? htmlspecialchars($_POST['goto']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <input type="submit" name="add_alias" value="Adicionar Alias" class="modal-submit-btn">
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Editar Alias -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">×</span>
            <h2>Editar Alias</h2>
            <form method="POST" action="">
                <input type="hidden" name="domain" value="<?php echo htmlspecialchars($selected_domain); ?>">
                <input type="hidden" name="edit_address" id="editAddress">
                <div class="form-group">
                    <label>Alias:</label>
                    <div class="input-with-domain">
                        <input type="text" id="editAliasName" name="alias_name" disabled>
                        <span class="domain-suffix">@<?php echo htmlspecialchars($selected_domain); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="editGoto">Destino:</label>
                    <input type="email" id="editGoto" name="goto" required>
                </div>
                <div class="form-group">
                    <input type="submit" name="edit_alias" value="Salvar Alterações" class="modal-submit-btn">
                </div>
            </form>
        </div>
    </div>

    <script src="js/aliases.js"></script>
    <script src="js/topo.js"></script>
</body>
<?php
// Fechar a conexão apenas após todas as operações
$conn->close();
?>
</html>