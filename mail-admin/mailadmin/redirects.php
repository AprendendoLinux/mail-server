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
        die("Acesso negado. Você não tem permissão para gerenciar redirecionamentos.");
    }
}

// Determinar o domínio selecionado (padrão é o primeiro domínio da lista)
$selected_domain = isset($_POST['domain']) ? $_POST['domain'] : (isset($_GET['domain']) ? $_GET['domain'] : (isset($domains[0]) ? $domains[0] : ''));

// Verificar se o domínio selecionado está na lista de domínios permitidos
if (!in_array($selected_domain, $domains)) {
    die("Acesso negado. Você não tem permissão para gerenciar este domínio.");
}

// Listar redirecionamentos apenas do domínio selecionado com funcion = 'R'
$redirects_query = "SELECT address, goto, domain, active FROM alias WHERE domain = '" . $conn->real_escape_string($selected_domain) . "' AND funcion = 'R'";
$redirects_result = $conn->query($redirects_query);

// Excluir redirecionamento
if (isset($_GET['delete'])) {
    $address_to_delete = $conn->real_escape_string($_GET['delete']);
    $delete_query = "DELETE FROM alias WHERE address = '$address_to_delete'";
    if ($conn->query($delete_query) === TRUE) {
        $success = "Redirecionamento $address_to_delete excluído com sucesso!";
        $redirects_result = $conn->query($redirects_query);
    } else {
        $error = "Erro ao excluir redirecionamento: " . $conn->error;
    }
}

// Ativar/Desativar redirecionamento
if (isset($_GET['toggle_active'])) {
    $address_to_toggle = $conn->real_escape_string($_GET['toggle_active']);
    $current_status_query = "SELECT active FROM alias WHERE address = '$address_to_toggle'";
    $status_result = $conn->query($current_status_query);
    if ($status_result && $status_result->num_rows > 0) {
        $row = $status_result->fetch_assoc();
        $new_status = $row['active'] ? 0 : 1;
        $update_query = "UPDATE alias SET active = $new_status WHERE address = '$address_to_toggle'";
        if ($conn->query($update_query) === TRUE) {
            $success = "Status do redirecionamento $address_to_toggle alterado com sucesso!";
            $redirects_result = $conn->query($redirects_query);
        } else {
            $error = "Erro ao alterar status: " . $conn->error;
        }
    }
}

// Editar redirecionamento (apenas o destino)
if (isset($_POST['edit_redirect']) && isset($_POST['edit_address'])) {
    $edit_address = $conn->real_escape_string($_POST['edit_address']);
    $new_goto = trim($_POST['goto']);

    if (empty($new_goto)) {
        $error = "O campo de destino é obrigatório.";
    } elseif (!filter_var($new_goto, FILTER_VALIDATE_EMAIL)) {
        $error = "O e-mail de destino é inválido.";
    } else {
        $update_query = "UPDATE alias SET goto = '" . $conn->real_escape_string($new_goto) . "' WHERE address = '$edit_address'";
        if ($conn->query($update_query) === TRUE) {
            $success = "Redirecionamento $edit_address editado com sucesso!";
            $redirects_result = $conn->query($redirects_query);
        } else {
            $error = "Erro ao editar redirecionamento: " . $conn->error;
        }
    }
}

// Adicionar novo redirecionamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_redirect'])) {
    $username = trim($_POST['username']);
    $goto = trim($_POST['goto']);
    $domain = $selected_domain;
    $address = "$username@$domain";

    if (empty($username) || empty($goto)) {
        $error = "Todos os campos são obrigatórios.";
    } elseif (!filter_var($goto, FILTER_VALIDATE_EMAIL)) {
        $error = "O e-mail de destino é inválido.";
    } elseif (!in_array($domain, $domains)) {
        $error = "Domínio inválido.";
    } else {
        // Verificar se o endereço existe na tabela mailbox
        $check_mailbox_query = "SELECT username FROM mailbox WHERE username = '" . $conn->real_escape_string($address) . "'";
        $check_mailbox_result = $conn->query($check_mailbox_query);
        if (!$check_mailbox_result || $check_mailbox_result->num_rows == 0) {
            $error = "Não é possível criar o redirecionamento porque o usuário $address não existe na tabela mailbox. Certifique-se de que o usuário foi criado antes de configurar um redirecionamento.";
        } else {
            // Verificar se o endereço já existe na tabela alias e determinar o tipo
            $check_alias_query = "SELECT address, funcion FROM alias WHERE address = '" . $conn->real_escape_string($address) . "'";
            $check_alias_result = $conn->query($check_alias_query);
            if ($check_alias_result && $check_alias_result->num_rows > 0) {
                $alias_row = $check_alias_result->fetch_assoc();
                $funcion = $alias_row['funcion'];
                if ($funcion === 'A') {
                    $error = "O endereço $address não pode ser usado como Redirecionamento porque ele já é um Alias.";
                } elseif ($funcion === 'G') {
                    $error = "O endereço $address não pode ser usado como Redirecionamento porque ele já é um Grupo.";
                } else {
                    $error = "O redirecionamento $address já existe.";
                }
            } else {
                $escaped_address = $conn->real_escape_string($address);
                $escaped_goto = $conn->real_escape_string($goto);
                $escaped_domain = $conn->real_escape_string($domain);

                // Inserir o redirecionamento com funcion = 'R'
                $insert_query = "INSERT INTO alias (address, goto, domain, active, funcion) VALUES ('$escaped_address', '$escaped_goto', '$escaped_domain', 1, 'R')";
                if ($conn->query($insert_query) === TRUE) {
                    $success = "Redirecionamento $address criado com sucesso!";
                    $redirects_result = $conn->query($redirects_query);
                } else {
                    $error = "Erro ao inserir redirecionamento: " . $conn->error;
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
    <title>Redirecionamentos - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/redirects.css">
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
                <h2>Lista de Redirecionamentos</h2>
                <button onclick="openAddModal()">Adicionar Redirecionamento</button>
            </div>
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if ($redirects_result && $redirects_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Ação</th>
                                <th>Redirecionamento</th>
                                <th>Destino</th>
                                <th>Domínio</th>
                                <th>Ativo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($redirect = $redirects_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="#" class="edit-icon" onclick="openEditModal('<?php echo htmlspecialchars($redirect['address']); ?>', '<?php echo htmlspecialchars($redirect['goto']); ?>')">✏️</a>
                                        <a href="?delete=<?php echo urlencode($redirect['address']); ?>&domain=<?php echo urlencode($selected_domain); ?>" class="delete-icon" onclick="return confirm('Tem certeza que deseja excluir o redirecionamento <?php echo htmlspecialchars($redirect['address']); ?>?');">🗑️</a>
                                        <a href="?toggle_active=<?php echo urlencode($redirect['address']); ?>&domain=<?php echo urlencode($selected_domain); ?>" class="toggle-icon"><?php echo $redirect['active'] ? '🔘' : '⭕'; ?></a>
                                    </td>
                                    <td><?php echo htmlspecialchars($redirect['address']); ?></td>
                                    <td><?php echo htmlspecialchars($redirect['goto']); ?></td>
                                    <td><?php echo htmlspecialchars($redirect['domain']); ?></td>
                                    <td><?php echo $redirect['active'] ? 'Sim' : 'Não'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Nenhum redirecionamento encontrado para o domínio selecionado.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Adicionar Redirecionamento -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAddModal()">×</span>
            <h2>Adicionar Novo Redirecionamento</h2>
            <form method="POST" action="">
                <input type="hidden" name="domain" value="<?php echo htmlspecialchars($selected_domain); ?>">
                <div class="form-group">
                    <label for="username">Usuário:</label>
                    <div class="input-with-domain">
                        <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        <span class="domain-suffix">@<?php echo htmlspecialchars($selected_domain); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="goto">Destino:</label>
                    <input type="email" id="goto" name="goto" value="<?php echo isset($_POST['goto']) ? htmlspecialchars($_POST['goto']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <input type="submit" name="add_redirect" value="Adicionar Redirecionamento" class="modal-submit-btn">
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Editar Redirecionamento -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">×</span>
            <h2>Editar Redirecionamento</h2>
            <form method="POST" action="">
                <input type="hidden" name="domain" value="<?php echo htmlspecialchars($selected_domain); ?>">
                <input type="hidden" name="edit_address" id="editAddress">
                <div class="form-group">
                    <label>Usuário:</label>
                    <div class="input-with-domain">
                        <input type="text" id="editUsername" name="username" disabled>
                        <span class="domain-suffix">@<?php echo htmlspecialchars($selected_domain); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="editGoto">Destino:</label>
                    <input type="email" id="editGoto" name="goto" required>
                </div>
                <div class="form-group">
                    <input type="submit" name="edit_redirect" value="Salvar Alterações" class="modal-submit-btn">
                </div>
            </form>
        </div>
    </div>

    <script src="js/redirects.js"></script>
    <script src="js/topo.js"></script>
</body>
</html>