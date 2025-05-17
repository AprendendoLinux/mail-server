// Variável para habilitar/desabilitar logs de depuração
const DEBUG = true; // Altere para false para desativar os logs

// Função para abrir o modal de adição
function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
    if (DEBUG) console.log('Modal aberto, chamando updateSubmitButton');
    updateSubmitButton(); // Atualizar estado do botão ao abrir
}

// Função para fechar o modal de adição
function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
    document.getElementById('username').value = '';
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    document.getElementById('confirm_password').value = '';
    document.getElementById('access_level').value = 'domain_admin'; // Resetar para Admin de Domínio
    document.getElementById('password-error').style.display = 'none'; // Esconder mensagem de erro
    toggleDomains(); // Garantir que a seção de domínios seja exibida no reset
    if (DEBUG) console.log('Modal fechado, estado resetado, chamando updateSubmitButton');
    updateSubmitButton(); // Atualizar estado do botão ao fechar
}

// Função para alternar a visibilidade da seção de domínios no modal de adição
function toggleDomains() {
    const accessLevel = document.getElementById('access_level').value;
    const domainsSection = document.getElementById('domains_section');
    const domainsSelect = document.getElementById('domains');
    if (DEBUG) console.log('toggleDomains chamado, accessLevel:', accessLevel);
    if (accessLevel === 'superadmin') {
        domainsSection.style.display = 'none';
        // Limpar seleção de domínios quando SuperAdmin é selecionado
        for (let option of domainsSelect.options) {
            option.selected = false;
        }
        if (DEBUG) console.log('SuperAdmin selecionado, domínios ocultos e limpos');
    } else {
        domainsSection.style.display = 'block';
        if (DEBUG) console.log('Admin de Domínio selecionado, domínios visíveis');
    }
    updateSubmitButton(); // Atualizar estado do botão ao mudar o nível de acesso
}

// Função para validar a senha ao clicar no botão no modal de adição
function validateOnSubmit(event) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const passwordError = document.getElementById('password-error');

    if (DEBUG) console.log('validateOnSubmit chamado, password:', password, 'confirmPassword:', confirmPassword);

    // Regex para validar: mínimo 8 caracteres, letra maiúscula, minúscula, número e caractere especial
    const passwordRegex = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*])[^\s]{8,}$/;
    if (DEBUG) console.log('Testando regex com password:', password, 'Resultado:', passwordRegex.test(password));

    if (!passwordRegex.test(password)) {
        if (DEBUG) console.log('Senha inválida, exibindo erro');
        passwordError.style.display = 'block';
        passwordError.textContent = 'A senha precisa ter no mínimo 8 caracteres, incluindo letras maiúsculas, minúsculas, números e caracteres especiais.';
        event.preventDefault(); // Impedir o envio do formulário
    } else if (password !== confirmPassword) {
        if (DEBUG) console.log('Senhas não coincidem, exibindo erro');
        passwordError.style.display = 'block';
        passwordError.textContent = 'As senhas não coincidem.';
        event.preventDefault(); // Impedir o envio do formulário
    } else {
        if (DEBUG) console.log('Senha válida e coincidente, permitindo envio');
        passwordError.style.display = 'none';
        passwordError.textContent = '';
    }
}

// Função para habilitar/desabilitar o botão de submissão no modal de adição
function updateSubmitButton() {
    const accessLevel = document.getElementById('access_level').value;
    const domainsSelect = document.getElementById('domains');
    const submitBtn = document.getElementById('submit-btn');
    const username = document.getElementById('username').value;
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (DEBUG) console.log('updateSubmitButton chamado, accessLevel:', accessLevel, 'username:', username, 'email:', email, 'password:', password, 'confirmPassword:', confirmPassword, 'selectedDomains:', Array.from(domainsSelect.selectedOptions).length);

    // Verificar se os campos obrigatórios estão preenchidos
    const fieldsFilled = username !== '' && email !== '' && password !== '' && confirmPassword !== '';

    if (!fieldsFilled) {
        if (DEBUG) console.log('Campos obrigatórios não preenchidos, desabilitando botão');
        submitBtn.disabled = true;
        return;
    }

    if (accessLevel === 'superadmin') {
        if (DEBUG) console.log('SuperAdmin, habilitando botão');
        submitBtn.disabled = false; // SuperAdmin não precisa de domínios
        submitBtn.style.backgroundColor = ''; // Resetar estilo
    } else {
        // Para Admin de Domínio, verificar se pelo menos um domínio está selecionado
        const hasSelectedDomain = Array.from(domainsSelect.selectedOptions).length > 0;
        if (DEBUG) console.log('Admin de Domínio, hasSelectedDomain:', hasSelectedDomain);
        submitBtn.disabled = !hasSelectedDomain;
        submitBtn.style.backgroundColor = hasSelectedDomain ? '' : '#cccccc'; // Cinza se desabilitado
    }
}

// Função para abrir o modal de edição
function openEditModal(username, accessLevel, domains, email) {
    document.getElementById('edit_old_username').value = username;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_access_level').value = accessLevel;

    const domainsSelect = document.getElementById('edit_domains');
    const domainsArray = domains ? domains.split(', ') : [];
    for (let option of domainsSelect.options) {
        option.selected = domainsArray.includes(option.value);
    }

    toggleEditDomains();
    document.getElementById('editModal').style.display = 'flex';
    if (DEBUG) console.log('openEditModal chamado, username:', username, 'accessLevel:', accessLevel, 'domains:', domains, 'email:', email);
}

// Função para fechar o modal de edição
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.getElementById('edit_old_username').value = '';
    document.getElementById('edit_username').value = '';
    document.getElementById('edit_email').value = '';
    document.getElementById('edit_access_level').value = 'domain_admin';

    const domainsSelect = document.getElementById('edit_domains');
    for (let option of domainsSelect.options) {
        option.selected = false;
    }

    toggleEditDomains();
    if (DEBUG) console.log('closeEditModal chamado');
}

// Função para alternar a visibilidade da seção de domínios no modal de edição
function toggleEditDomains() {
    const accessLevel = document.getElementById('edit_access_level').value;
    const domainsSection = document.getElementById('edit_domains_section');
    const domainsSelect = document.getElementById('edit_domains');
    if (DEBUG) console.log('toggleEditDomains chamado, accessLevel:', accessLevel);
    if (accessLevel === 'superadmin') {
        domainsSection.style.display = 'none';
        for (let option of domainsSelect.options) {
            option.selected = false;
        }
        if (DEBUG) console.log('SuperAdmin selecionado, domínios ocultos e limpos');
    } else {
        domainsSection.style.display = 'block';
        if (DEBUG) console.log('Admin de Domínio selecionado, domínios visíveis');
    }
}

// Função para abrir o modal de alteração de senha
function openPasswordModal(username) {
    document.getElementById('change_username').value = username;
    document.getElementById('passwordModal').style.display = 'flex';
    if (DEBUG) console.log('openPasswordModal chamado, username:', username);
}

// Função para fechar o modal de alteração de senha
function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
    document.getElementById('change_username').value = '';
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_new_password').value = '';
    if (DEBUG) console.log('closePasswordModal chamado');
}

// Fechar modais ao clicar fora deles
window.onclick = function(event) {
    if (event.target == document.getElementById('addModal')) {
        closeAddModal();
    }
    if (event.target == document.getElementById('editModal')) {
        closeEditModal();
    }
    if (event.target == document.getElementById('passwordModal')) {
        closePasswordModal();
    }
};

// Chamar a função toggleDomains ao carregar a página para garantir o estado inicial
window.onload = function() {
    if (DEBUG) console.log('Página carregada, chamando toggleDomains');
    toggleDomains();
};
