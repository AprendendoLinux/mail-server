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
    // SuperAdmins podem ver todos os domínios ativos
    $domains_query = "SELECT domain FROM domain WHERE active = 1";
} else {
    // Administradores normais só veem os domínios aos quais estão vinculados
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
        die("Acesso negado. Você não tem permissão para gerenciar usuários.");
    }
}

// Determinar o domínio selecionado (padrão é o primeiro domínio da lista)
$selected_domain = isset($_POST['domain']) ? $_POST['domain'] : (isset($_GET['domain']) ? $_GET['domain'] : (isset($domains[0]) ? $domains[0] : ''));

// Verificar se o domínio selecionado está na lista de domínios permitidos
if (!in_array($selected_domain, $domains)) {
    die("Acesso negado. Você não tem permissão para gerenciar este domínio.");
}

// Listar usuários apenas do domínio selecionado
$users_query = "SELECT username, name, active, quota FROM mailbox WHERE domain = '" . $conn->real_escape_string($selected_domain) . "'";
$users_result = $conn->query($users_query);

// Opções de cota
$quota_options = [
    '2GB' => 2 * 1073741824,
    '15GB' => 15 * 1073741824,
    '50GB' => 50 * 1073741824,
    '100GB' => 100 * 1073741824,
    '200GB' => 200 * 1073741824,
    'ILIMITADO' => 0
];

function bytes_to_quota($bytes, $quota_options) {
    foreach ($quota_options as $label => $value) {
        if ($bytes == $value) {
            return $label;
        }
    }
    return 'ILIMITADO';
}

// Excluir usuário
if (isset($_GET['delete'])) {
    $username_to_delete = $conn->real_escape_string($_GET['delete']);
    $delete_query = "DELETE FROM mailbox WHERE username = '$username_to_delete'";
    if ($conn->query($delete_query) === TRUE) {
        $success = "Usuário $username_to_delete excluído com sucesso!";
        $users_result = $conn->query($users_query);
    } else {
        $error = "Erro ao excluir usuário: " . $conn->error;
    }
}

// Ativar/Desativar usuário
if (isset($_GET['toggle_active'])) {
    $username_to_toggle = $conn->real_escape_string($_GET['toggle_active']);
    $current_status_query = "SELECT active FROM mailbox WHERE username = '$username_to_toggle'";
    $status_result = $conn->query($current_status_query);
    if ($status_result && $status_result->num_rows > 0) {
        $row = $status_result->fetch_assoc();
        $current_status = $row['active'];
        $new_status = $current_status ? 0 : 1;
        $update_query = "UPDATE mailbox SET active = $new_status WHERE username = '$username_to_toggle'";
        if ($conn->query($update_query) === TRUE) {
            $success = "Status do usuário $username_to_toggle alterado com sucesso!";
            $users_result = $conn->query($users_query);
        } else {
            $error = "Erro ao alterar status: " . $conn->error;
        }
    }
}

// Editar usuário (Nome e Cota)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $username = $conn->real_escape_string($_POST['edit_username']);
    $name = trim($_POST['edit_name']);
    $quota_option = trim($_POST['edit_quota']);

    if (!array_key_exists($quota_option, $quota_options)) {
        $error = "Cota inválida.";
    } else {
        $quota = $quota_options[$quota_option];
        $update_query = "UPDATE mailbox SET quota = $quota";
        if (!empty($name)) {
            $escaped_name = $conn->real_escape_string($name);
            $update_query .= ", name = '$escaped_name'";
        }
        $update_query .= " WHERE username = '$username'";
        if ($conn->query($update_query) === TRUE) {
            $success = "Usuário $username atualizado com sucesso!";
            $users_result = $conn->query($users_query);
        } else {
            $error = "Erro ao atualizar usuário: " . $conn->error;
        }
    }
}

// Alterar senha do usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_password'])) {
    $username = $conn->real_escape_string($_POST['change_username'] ?? '');
    $new_password = trim($_POST['change_password'] ?? '');
    $confirm_password = trim($_POST['change_confirm_password'] ?? '');

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Os campos de senha são obrigatórios.";
    } elseif (strcmp($new_password, $confirm_password) !== 0) {
        $error = "As senhas não coincidem.";
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        if ($hash === false) {
            $error = "Erro ao gerar o hash da senha.";
        } else {
            $escaped_hash = $conn->real_escape_string($hash);
            $update_query = "UPDATE mailbox SET password = '$escaped_hash' WHERE username = '$username'";
            $result = $conn->query($update_query);
            if ($result === TRUE) {
                if ($conn->affected_rows > 0) {
                    $success = "Senha do usuário $username alterada com sucesso!";
                } else {
                    $error = "Nenhum usuário encontrado com o username '$username'.";
                }
            } else {
                $error = "Erro ao alterar senha: " . $conn->error;
            }
        }
    }
}

// Adicionar novo usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $user = trim($_POST['user']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $name = trim($_POST['name']);
    $domain = $selected_domain; // Usar o domínio selecionado
    $quota_option = trim($_POST['quota']);
    $username = "$user@$domain";

    if (empty($user) || empty($password) || empty($confirm_password) || empty($name)) {
        $error = "Todos os campos são obrigatórios.";
    } elseif ($password !== $confirm_password) {
        $error = "As senhas não coincidem.";
    } elseif (!in_array($domain, $domains)) {
        $error = "Domínio inválido.";
    } elseif (!array_key_exists($quota_option, $quota_options)) {
        $error = "Cota inválida.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $quota = $quota_options[$quota_option];

        $escaped_username = $conn->real_escape_string($username);
        $escaped_hash = $conn->real_escape_string($hash);
        $escaped_name = $conn->real_escape_string($name);
        $escaped_domain = $conn->real_escape_string($domain);

        // Verificar se o username já existe na tabela mailbox
        $check_mailbox_query = "SELECT username FROM mailbox WHERE username='$escaped_username'";
        $check_mailbox_result = $conn->query($check_mailbox_query);
        if ($check_mailbox_result && $check_mailbox_result->num_rows > 0) {
            $error = "O nome de usuário $username já está sendo usado na área de usuários (mailbox).";
        } else {
            // Verificar se o username já existe na tabela alias e determinar o tipo de uso
            $check_alias_query = "SELECT address, funcion FROM alias WHERE address='$escaped_username'";
            $check_alias_result = $conn->query($check_alias_query);
            if ($check_alias_result && $check_alias_result->num_rows > 0) {
                $alias_row = $check_alias_result->fetch_assoc();
                $funcion = $alias_row['funcion'];

                if ($funcion === 'G') {
                    $error = "O nome de usuário $username já está sendo usado como grupo.";
                } elseif ($funcion === 'R') {
                    $error = "O nome de usuário $username já está sendo usado como redirecionamento.";
                } elseif ($funcion === 'A' || $funcion === NULL) {
                    $error = "O nome de usuário $username já está sendo usado como alias.";
                }
            } else {
                $insert_query = "INSERT INTO mailbox (username, password, name, domain, active, quota) VALUES ('$escaped_username', '$escaped_hash', '$escaped_name', '$escaped_domain', 1, $quota)";
                if ($conn->query($insert_query) === TRUE) {
                    $success = "Usuário $username criado com sucesso!";
                    $users_result = $conn->query($users_query);
                } else {
                    $error = "Erro ao inserir usuário: " . $conn->error;
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
    <title>Usuários - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/manage_users.css">
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
                    <input type="hidden" name="refresh" value="1"> <!-- Garante que a submissão seja detectada -->
                </form>
            </div>
        <?php endif; ?>
        <div class="table-container">
            <div class="table-header">
                <h2>Lista de Usuários</h2>
                <button onclick="openAddModal()">Adicionar Usuário</button>
            </div>
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if ($users_result && $users_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Ação</th>
                                <th>Usuário</th>
                                <th>Nome</th>
                                <th>Cota</th>
                                <th>Ativo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="#" class="edit-icon" onclick="openEditModal('<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo bytes_to_quota($user['quota'], $quota_options); ?>')">✏️</a>
                                        <a href="#" class="password-icon" onclick="openPasswordModal('<?php echo htmlspecialchars($user['username']); ?>')">🔒</a>
                                        <a href="?delete=<?php echo urlencode($user['username']); ?>&domain=<?php echo urlencode($selected_domain); ?>" class="delete-icon" onclick="return confirm('Tem certeza que deseja excluir o usuário <?php echo htmlspecialchars($user['username']); ?>?');">🗑️</a>
                                        <a href="?toggle_active=<?php echo urlencode($user['username']); ?>&domain=<?php echo urlencode($selected_domain); ?>" class="toggle-icon"><?php echo $user['active'] ? '🔘' : '⭕'; ?></a>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td>
                                        <?php
                                        $quota_bytes = $user['quota'];
                                        if ($quota_bytes == 0) {
                                            echo 'ILIMITADO';
                                        } else {
                                            $quota_gb = $quota_bytes / 1073741824;
                                            echo number_format($quota_gb, 0) . 'GB';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $user['active'] ? 'Sim' : 'Não'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Nenhum usuário encontrado para o domínio selecionado.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Adicionar Usuário -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAddModal()">×</span>
            <h2>Adicionar Novo Usuário</h2>
            <form method="POST" action="">
                <input type="hidden" name="domain" value="<?php echo htmlspecialchars($selected_domain); ?>"> <!-- Passar o domínio selecionado -->
                <div class="form-group">
                    <label for="user">Usuário:</label>
                    <div class="input-with-domain">
                        <input type="text" id="user" name="user" value="<?php echo isset($_POST['user']) ? htmlspecialchars($_POST['user']) : ''; ?>" required>
                        <span class="domain-suffix">@<?php echo htmlspecialchars($selected_domain); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmação de Senha:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="form-group">
                    <label for="name">Nome Completo:</label>
                    <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="quota">Cota:</label>
                    <select id="quota" name="quota" required>
                        <?php foreach (array_keys($quota_options) as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (isset($_POST['quota']) && $_POST['quota'] === $option) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <input type="submit" name="add_user" value="Adicionar Usuário" class="modal-submit-btn">
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Editar Usuário (Nome e Cota) -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">×</span>
            <h2>Editar Usuário</h2>
            <form method="POST" action="">
                <input type="hidden" id="edit_username" name="edit_username">
                <div class="form-group">
                    <label for="edit_name">Nome Completo (Deixe em branco para manter o atual):</label>
                    <input type="text" id="edit_name" name="edit_name">
                </div>
                <div class="form-group">
                    <label for="edit_quota">Cota:</label>
                    <select id="edit_quota" name="edit_quota" required>
                        <?php foreach (array_keys($quota_options) as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <input type="submit" name="edit_user" value="Salvar Alterações" class="modal-submit-btn">
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Alterar Senha -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closePasswordModal()">×</span>
            <h2>Alterar Senha</h2>
            <form method="POST" action="">
                <input type="hidden" id="change_username" name="change_username">
                <div class="form-group">
                    <label for="change_password">Nova Senha:</label>
                    <input type="password" id="change_password" name="change_password" required>
                </div>
                <div class="form-group">
                    <label for="change_confirm_password">Confirmação de Nova Senha:</label>
                    <input type="password" id="change_confirm_password" name="change_confirm_password" required>
                </div>
                <div class="form-group">
                    <input type="submit" name="submit_password" value="Alterar Senha" class="modal-submit-btn">
                </div>
            </form>
        </div>
    </div>

    <script src="js/manage_users.js"></script>
    <script src="js/topo.js"></script>
</body>
</html>