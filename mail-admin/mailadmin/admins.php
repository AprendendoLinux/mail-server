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
    die("Acesso negado. Apenas SuperAdmins podem gerenciar administradores.");
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
$domains_query = "SELECT domain FROM domain WHERE active = 1";
$domains_result = $conn->query($domains_query);
$domains = [];
if ($domains_result && $domains_result->num_rows > 0) {
    while ($row = $domains_result->fetch_assoc()) {
        $domains[] = $row['domain'];
    }
}

// Listar administradores com seus domínios associados, status e e-mail
$admins_query = "SELECT a.username, a.email, a.active, a.is_superadmin, GROUP_CONCAT(ad.domain SEPARATOR ', ') as domains FROM admins a LEFT JOIN admin_domains ad ON a.username = ad.admin_username GROUP BY a.username, a.email, a.active, a.is_superadmin";
$admins_result = $conn->query($admins_query);

// Adicionar novo administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $access_level = $_POST['access_level'] ?? 'domain_admin'; // Padrão é Admin de Domínio
    $is_superadmin = ($access_level === 'superadmin') ? 1 : 0;
    $selected_domains = isset($_POST['domains']) ? $_POST['domains'] : [];

    // Validação dos campos
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "O nome de usuário, o e-mail, a senha e a confirmação de senha são obrigatórios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "O e-mail informado não é válido.";
    } elseif ($password !== $confirm_password) {
        $error = "As senhas não coincidem.";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*])[^\s]{8,}$/', $password)) {
        $error = "A senha deve ter no mínimo 8 caracteres, com pelo menos uma letra maiúscula, uma letra minúscula, um número e um caractere especial.";
    } elseif ($is_superadmin && !empty($selected_domains)) {
        $error = "Um SuperAdmin não pode ser vinculado a domínios específicos.";
    } else {
        // Verificar se o administrador já existe
        $check_admin_query = "SELECT username FROM admins WHERE username = '" . $conn->real_escape_string($username) . "'";
        $check_admin_result = $conn->query($check_admin_query);
        if ($check_admin_result && $check_admin_result->num_rows > 0) {
            $error = "O administrador $username já existe.";
        } else {
            // Verificar se o e-mail já está em uso
            $check_email_query = "SELECT email FROM admins WHERE email = '" . $conn->real_escape_string($email) . "'";
            $check_email_result = $conn->query($check_email_query);
            if ($check_email_result && $check_email_result->num_rows > 0) {
                $error = "O e-mail $email já está em uso.";
            } else {
                // Hash da senha
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Inserir administrador com active = 1 e e-mail
                $insert_admin_query = "INSERT INTO admins (username, email, password, active, is_superadmin) VALUES ('" . $conn->real_escape_string($username) . "', '" . $conn->real_escape_string($email) . "', '$hashed_password', 1, $is_superadmin)";
                if ($conn->query($insert_admin_query) === TRUE) {
                    // Associar domínios (apenas se não for SuperAdmin)
                    if (!$is_superadmin) {
                        foreach ($selected_domains as $domain) {
                            $insert_domain_query = "INSERT INTO admin_domains (admin_username, domain) VALUES ('" . $conn->real_escape_string($username) . "', '" . $conn->real_escape_string($domain) . "')";
                            $conn->query($insert_domain_query);
                        }
                    }
                    $success = "Administrador $username criado com sucesso!";
                    $admins_result = $conn->query($admins_query);
                } else {
                    $error = "Erro ao criar administrador: " . $conn->error;
                }
            }
        }
    }
}

// Editar administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin'])) {
    $old_username = $conn->real_escape_string($_POST['edit_old_username']);
    $new_username = trim($_POST['edit_username']);
    $email = trim($_POST['edit_email']);
    $access_level = $_POST['edit_access_level'] ?? 'domain_admin';
    $is_superadmin = ($access_level === 'superadmin') ? 1 : 0;
    $selected_domains = isset($_POST['edit_domains']) ? $_POST['edit_domains'] : [];

    // Verificar se o administrador existe
    $check_admin_query = "SELECT username FROM admins WHERE username = '$old_username'";
    $check_admin_result = $conn->query($check_admin_query);
    if (!$check_admin_result || $check_admin_result->num_rows == 0) {
        $error = "Administrador $old_username não encontrado.";
    } elseif ($old_username === $_SESSION['username']) {
        $error = "Você não pode editar a si mesmo.";
    } elseif ($is_superadmin && !empty($selected_domains)) {
        $error = "Um SuperAdmin não pode ser vinculado a domínios específicos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "O e-mail informado não é válido.";
    } else {
        // Verificar se o e-mail já está em uso por outro administrador
        $check_email_query = "SELECT email FROM admins WHERE email = '" . $conn->real_escape_string($email) . "' AND username != '$old_username'";
        $check_email_result = $conn->query($check_email_query);
        if ($check_email_result && $check_email_result->num_rows > 0) {
            $error = "O e-mail $email já está em uso por outro administrador.";
        } else {
            // Iniciar transação para garantir consistência
            $conn->begin_transaction();
            try {
                // Atualizar e-mail e is_superadmin (username não é mais editável)
                $update_admin_query = "UPDATE admins SET email = '" . $conn->real_escape_string($email) . "', is_superadmin = $is_superadmin WHERE username = '$old_username'";
                if (!$conn->query($update_admin_query)) {
                    throw new Exception("Erro ao atualizar administrador: " . $conn->error);
                }

                // Excluir associações de domínios existentes
                $delete_domains_query = "DELETE FROM admin_domains WHERE admin_username = '" . $conn->real_escape_string($old_username) . "'";
                if (!$conn->query($delete_domains_query)) {
                    throw new Exception("Erro ao excluir associações de domínios: " . $conn->error);
                }

                // Se não for SuperAdmin, adicionar novas associações de domínios
                if (!$is_superadmin && !empty($selected_domains)) {
                    foreach ($selected_domains as $domain) {
                        $insert_domain_query = "INSERT INTO admin_domains (admin_username, domain) VALUES ('" . $conn->real_escape_string($old_username) . "', '" . $conn->real_escape_string($domain) . "')";
                        if (!$conn->query($insert_domain_query)) {
                            throw new Exception("Erro ao associar domínios: " . $conn->error);
                        }
                    }
                }

                // Confirmar transação
                $conn->commit();
                $success = "Administrador $old_username atualizado com sucesso!";
                $admins_result = $conn->query($admins_query);
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Alterar senha do administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $username = $conn->real_escape_string($_POST['change_username']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_new_password']);

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Os campos de senha são obrigatórios.";
    } elseif ($new_password !== $confirm_password) {
        $error = "As senhas não coincidem.";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*])[^\s]{8,}$/', $new_password)) {
        $error = "A senha deve ter no mínimo 8 caracteres, com pelo menos uma letra maiúscula, uma letra minúscula, um número e um caractere especial.";
    } elseif ($username === $_SESSION['username']) {
        $error = "Você não pode alterar sua própria senha aqui.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_password_query = "UPDATE admins SET password = '" . $conn->real_escape_string($hashed_password) . "' WHERE username = '$username'";
        if ($conn->query($update_password_query) === TRUE) {
            if ($conn->affected_rows > 0) {
                $success = "Senha do administrador $username alterada com sucesso!";
                $admins_result = $conn->query($admins_query);
            } else {
                $error = "Administrador $username não encontrado.";
            }
        } else {
            $error = "Erro ao alterar senha: " . $conn->error;
        }
    }
}

// Ativar/Desativar administrador
if (isset($_GET['toggle_active'])) {
    $username_to_toggle = $conn->real_escape_string($_GET['toggle_active']);
    $current_user = $_SESSION['username'];

    if ($username_to_toggle === $current_user) {
        $error = "Você não pode desativar a si mesmo.";
    } else {
        $current_status_query = "SELECT active FROM admins WHERE username = '$username_to_toggle'";
        $status_result = $conn->query($current_status_query);
        if ($status_result && $status_result->num_rows > 0) {
            $row = $status_result->fetch_assoc();
            $current_status = $row['active'];
            $new_status = $current_status ? 0 : 1;

            $update_query = "UPDATE admins SET active = $new_status WHERE username = '$username_to_toggle'";
            if ($conn->query($update_query) === TRUE) {
                $success = "Status do administrador $username_to_toggle alterado com sucesso!";
                $admins_result = $conn->query($admins_query);
            } else {
                $error = "Erro ao alterar status: " . $conn->error;
            }
        }
    }
}

// Excluir administrador
if (isset($_GET['delete'])) {
    $username_to_delete = $conn->real_escape_string($_GET['delete']);
    $current_user = $_SESSION['username'];

    if ($username_to_delete === $current_user) {
        $error = "Você não pode se autoexcluir.";
    } else {
        // Verificar se o administrador a ser excluído existe
        $check_admin_query = "SELECT username FROM admins WHERE username = '$username_to_delete'";
        $check_admin_result = $conn->query($check_admin_query);
        if ($check_admin_result && $check_admin_result->num_rows > 0) {
            // Excluir associações de domínios primeiro
            $delete_domains_query = "DELETE FROM admin_domains WHERE admin_username = '$username_to_delete'";
            if ($conn->query($delete_domains_query) === TRUE) {
                // Excluir o administrador
                $delete_query = "DELETE FROM admins WHERE username = '$username_to_delete'";
                if ($conn->query($delete_query) === TRUE) {
                    $success = "Administrador $username_to_delete excluído com sucesso!";
                    $admins_result = $conn->query($admins_query);
                } else {
                    $error = "Erro ao excluir administrador: " . $conn->error;
                }
            } else {
                $error = "Erro ao excluir associações de domínios: " . $conn->error;
            }
        } else {
            $error = "Administrador $username_to_delete não encontrado.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administradores - Sistema de E-mail</title>
    <link rel="stylesheet" href="css/admins.css">
    <link rel="stylesheet" href="css/topo.css">
</head>
<body>
    <?php include 'topo.php'; ?>
    <div class="content">
        <div class="table-container">
            <div class="table-header">
                <h2>Lista de Administradores</h2>
                <button onclick="openAddModal()">Adicionar Administrador</button>
            </div>
            <?php if ($success): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if ($admins_result && $admins_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Ação</th>
                                <th>Usuário</th>
                                <th>Domínios Vinculados</th>
                                <th>SuperAdmin</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($admin = $admins_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="#" class="edit-icon" onclick="openEditModal('<?php echo htmlspecialchars($admin['username']); ?>', '<?php echo $admin['is_superadmin'] ? 'superadmin' : 'domain_admin'; ?>', '<?php echo htmlspecialchars($admin['domains'] ?? ''); ?>', '<?php echo htmlspecialchars($admin['email']); ?>')">✏️</a>
                                        <a href="#" class="password-icon" onclick="openPasswordModal('<?php echo htmlspecialchars($admin['username']); ?>')">🔒</a>
                                        <?php if ($admin['username'] !== $_SESSION['username']): ?>
                                            <a href="?delete=<?php echo urlencode($admin['username']); ?>" class="delete-icon" onclick="return confirm('Tem certeza que deseja excluir o administrador <?php echo htmlspecialchars($admin['username']); ?>?');">🗑️</a>
                                            <a href="?toggle_active=<?php echo urlencode($admin['username']); ?>" class="toggle-icon"><?php echo $admin['active'] ? '🔘' : '⭕'; ?></a>
                                        <?php else: ?>
                                            <span class="disabled-icon">🗑️</span>
                                            <span class="disabled-icon"><?php echo $admin['active'] ? '🔘' : '⭕'; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                    <td><?php echo $admin['domains'] ? htmlspecialchars($admin['domains']) : 'Nenhum'; ?></td>
                                    <td><?php echo $admin['is_superadmin'] ? 'Sim' : 'Não'; ?></td>
                                    <td><?php echo $admin['active'] ? 'Ativo' : 'Inativo'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Nenhum administrador encontrado.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Adicionar Administrador -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin']) && $error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <span class="close-btn" onclick="closeAddModal()">×</span>
            <h2>Adicionar Novo Administrador</h2>
            <form method="POST" action="" onsubmit="validateOnSubmit(event)">
                <div class="form-group">
                    <label for="username">Usuário:</label>
                    <input type="text" id="username" name="username" required oninput="updateSubmitButton()">
                </div>
                <div class="form-group">
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" required oninput="updateSubmitButton()">
                </div>
                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" id="password" name="password" required oninput="updateSubmitButton()">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmação de Senha:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required oninput="updateSubmitButton()">
                </div>
                <div id="password-error" class="error" style="display: none;"></div>
                <div class="form-group">
                    <label for="access_level">Nível de Acesso:</label>
                    <select id="access_level" name="access_level" onchange="toggleDomains()">
                        <option value="domain_admin" selected>Admin de Domínio</option>
                        <option value="superadmin">SuperAdmin</option>
                    </select>
                </div>
                <div class="form-group" id="domains_section">
                    <label for="domains">Domínios Vinculados (selecione apenas se for Admin de Domínio):</label>
                    <select id="domains" name="domains[]" multiple onchange="updateSubmitButton()">
                        <?php foreach ($domains as $domain): ?>
                            <option value="<?php echo htmlspecialchars($domain); ?>"><?php echo htmlspecialchars($domain); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <input type="submit" id="submit-btn" name="add_admin" value="Adicionar Administrador" class="modal-submit-btn" disabled>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Editar Administrador -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin']) && $error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <span class="close-btn" onclick="closeEditModal()">×</span>
            <h2>Editar Administrador</h2>
            <form method="POST" action="">
                <input type="hidden" id="edit_old_username" name="edit_old_username">
                <div class="form-group">
                    <label for="edit_username">Usuário:</label>
                    <input type="text" id="edit_username" name="edit_username" readonly>
                </div>
                <div class="form-group">
                    <label for="edit_email">E-mail:</label>
                    <input type="email" id="edit_email" name="edit_email" required>
                </div>
                <div class="form-group">
                    <label for="edit_access_level">Nível de Acesso:</label>
                    <select id="edit_access_level" name="edit_access_level" onchange="toggleEditDomains()">
                        <option value="domain_admin">Admin de Domínio</option>
                        <option value="superadmin">SuperAdmin</option>
                    </select>
                </div>
                <div class="form-group" id="edit_domains_section">
                    <label for="edit_domains">Domínios Vinculados (selecione apenas se for Admin de Domínio):</label>
                    <select id="edit_domains" name="edit_domains[]" multiple>
                        <?php foreach ($domains as $domain): ?>
                            <option value="<?php echo htmlspecialchars($domain); ?>"><?php echo htmlspecialchars($domain); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <input type="submit" name="edit_admin" value="Salvar Alterações" class="modal-submit-btn">
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Alterar Senha -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']) && $error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <span class="close-btn" onclick="closePasswordModal()">×</span>
            <h2>Alterar Senha</h2>
            <form method="POST" action="">
                <input type="hidden" id="change_username" name="change_username">
                <div class="form-group">
                    <label for="new_password">Nova Senha:</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_new_password">Confirmação de Nova Senha:</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                </div>
                <div class="form-group">
                    <input type="submit" name="change_password" value="Alterar Senha" class="modal-submit-btn">
                </div>
            </form>
        </div>
    </div>

    <?php
    // Script para reabrir o modal se houver erro, ou fechar se houver sucesso
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
        if ($error) {
            echo "<script>document.getElementById('addModal').style.display = 'flex';</script>";
        } elseif ($success) {
            echo "<script>closeAddModal();</script>";
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin'])) {
        if ($error) {
            echo "<script>document.getElementById('editModal').style.display = 'flex';</script>";
        } elseif ($success) {
            echo "<script>closeEditModal();</script>";
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        if ($error) {
            echo "<script>document.getElementById('passwordModal').style.display = 'flex';</script>";
        } elseif ($success) {
            echo "<script>closePasswordModal();</script>";
        }
    }
    ?>

    <script src="js/admins.js"></script>
    <script src="js/topo.js"></script>
</body>
<?php
// Fechar a conexão apenas após todas as operações
$conn->close();
?>
