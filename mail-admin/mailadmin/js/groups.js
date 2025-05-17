console.log('Iniciando groups.js');

// Variável para habilitar/desabilitar logs de depuração
const DEBUG = true;

// Função para log condicional
function log(message) {
    if (DEBUG) {
        console.log('[Groups.js] ' + message);
    }
}

// Definir funções no escopo global imediatamente
window.openAddModal = function () {
    log('Tentando abrir o modal de adicionar grupo');
    const addModal = document.getElementById('addModal');
    if (addModal) {
        addModal.style.display = 'flex';
        addModal.classList.add('show');
        log('Modal de adicionar grupo aberto com sucesso');
    } else {
        log('Erro: Elemento #addModal não encontrado');
        return;
    }

    // Inicializar elementos do modal de adicionar
    const addChipContainer = document.getElementById('addChipContainer');
    const addEmailInput = document.getElementById('addEmailInput');
    const addGotoHidden = document.getElementById('addGotoHidden');
    let addEmails = [];

    if (!addChipContainer || !addEmailInput || !addGotoHidden) {
        log('Erro: Um ou mais elementos do modal de adicionar não foram encontrados');
        return;
    }

    function addAddChip(email) {
        log('Adicionando chip no modal de adicionar: ' + email);
        email = email.trim();
        if (email === '') return;

        if (addEmails.includes(email)) {
            log('E-mail duplicado ignorado: ' + email);
            return;
        }

        addEmails.push(email);
        const chip = document.createElement('div');
        chip.className = 'chip';
        chip.innerHTML = `<span>${email}</span><button type="button" class="remove-chip">×</button>`;
        chip.querySelector('.remove-chip').addEventListener('click', () => {
            log('Removendo chip no modal de adicionar: ' + email);
            chip.remove();
            addEmails = addEmails.filter(e => e !== email);
            updateAddHiddenInput();
        });
        addChipContainer.insertBefore(chip, addEmailInput);
        addEmailInput.value = '';
        updateAddHiddenInput();
        log('Chip adicionado com sucesso');
    }

    function updateAddHiddenInput() {
        log('Atualizando campo oculto de adicionar com: ' + addEmails.join(','));
        addGotoHidden.value = addEmails.join(',');
    }

    // Remover eventos anteriores para evitar múltiplos listeners
    addEmailInput.removeEventListener('keydown', handleAddKeydown);
    addEmailInput.removeEventListener('blur', handleAddBlur);

    function handleAddKeydown(event) {
        log('Tecla pressionada no input de adicionar: ' + event.key);
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const email = addEmailInput.value.trim();
            if (email) {
                addAddChip(email);
            } else {
                log('E-mail vazio ignorado');
            }
        }
    }

    function handleAddBlur() {
        log('Input de adicionar perdeu foco');
        const email = addEmailInput.value.trim();
        if (email) {
            addAddChip(email);
        }
    }

    addEmailInput.addEventListener('keydown', handleAddKeydown);
    addEmailInput.addEventListener('blur', handleAddBlur);

    // Preencher chips se houver dados do POST
    const initialAddEmails = addGotoHidden.value ? addGotoHidden.value.split(',') : [];
    if (initialAddEmails.length > 0) {
        log('Preenchendo chips iniciais no modal de adicionar: ' + initialAddEmails);
        initialAddEmails.forEach(email => addAddChip(email));
    }
};

window.closeAddModal = function () {
    log('Fechando modal de adicionar grupo');
    const addModal = document.getElementById('addModal');
    if (addModal) {
        addModal.style.display = 'none';
        addModal.classList.remove('show');
        log('Modal de adicionar grupo fechado com sucesso');
    } else {
        log('Erro: Elemento #addModal não encontrado');
    }
    const groupName = document.getElementById('group_name');
    const addEmailInput = document.getElementById('addEmailInput');
    const addChipContainer = document.getElementById('addChipContainer');
    const addGotoHidden = document.getElementById('addGotoHidden');
    if (groupName) groupName.value = '';
    if (addEmailInput) addEmailInput.value = '';
    if (addChipContainer) {
        while (addChipContainer.children.length > 1) {
            addChipContainer.removeChild(addChipContainer.firstChild);
        }
    }
    if (addGotoHidden) addGotoHidden.value = '';
    log('Campos limpos no modal de adicionar');
};

window.openEditModal = function (address, goto) {
    log('Tentando abrir o modal de edição para o grupo: ' + address);
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.style.display = 'flex';
        editModal.classList.add('show');
        log('Modal de edição aberto com sucesso');
    } else {
        log('Erro: Elemento #editModal não encontrado');
        return;
    }

    const editAddress = document.getElementById('editAddress');
    const editGroupName = document.getElementById('editGroupName');
    const editChipContainer = document.getElementById('editChipContainer');
    const editEmailInput = document.getElementById('editEmailInput');
    const editGotoHidden = document.getElementById('editGotoHidden');
    let editEmails = [];

    if (!editAddress || !editGroupName || !editChipContainer || !editEmailInput || !editGotoHidden) {
        log('Erro: Um ou mais elementos do modal de edição não foram encontrados');
        return;
    }

    editAddress.value = address;
    editGroupName.value = address.split('@')[0];
    editEmailInput.value = '';
    while (editChipContainer.children.length > 1) {
        editChipContainer.removeChild(editChipContainer.firstChild);
    }
    editGotoHidden.value = '';

    // Preencher chips existentes
    const initialEmails = goto.split(',');
    log('E-mails iniciais para edição: ' + initialEmails);
    initialEmails.forEach(email => addEditChip(email.trim()));

    function addEditChip(email) {
        log('Adicionando chip no modal de edição: ' + email);
        email = email.trim();
        if (email === '') return;

        if (editEmails.includes(email)) {
            log('E-mail duplicado ignorado: ' + email);
            return;
        }

        editEmails.push(email);
        const chip = document.createElement('div');
        chip.className = 'chip';
        chip.innerHTML = `<span>${email}</span><button type="button" class="remove-chip">×</button>`;
        chip.querySelector('.remove-chip').addEventListener('click', () => {
            log('Removendo chip no modal de edição: ' + email);
            chip.remove();
            editEmails = editEmails.filter(e => e !== email);
            updateEditHiddenInput();
        });
        editChipContainer.insertBefore(chip, editEmailInput);
        editEmailInput.value = '';
        updateEditHiddenInput();
        log('Chip adicionado com sucesso');
    }

    function updateEditHiddenInput() {
        log('Atualizando campo oculto de edição com: ' + editEmails.join(','));
        editGotoHidden.value = editEmails.join(',');
    }

    // Remover eventos anteriores para evitar múltiplos listeners
    editEmailInput.removeEventListener('keydown', handleEditKeydown);
    editEmailInput.removeEventListener('blur', handleEditBlur);

    function handleEditKeydown(event) {
        log('Tecla pressionada no input de edição: ' + event.key);
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const email = editEmailInput.value.trim();
            if (email) {
                addEditChip(email);
            } else {
                log('E-mail vazio ignorado');
            }
        }
    }

    function handleEditBlur() {
        log('Input de edição perdeu foco');
        const email = editEmailInput.value.trim();
        if (email) {
            addEditChip(email);
        }
    }

    editEmailInput.addEventListener('keydown', handleEditKeydown);
    editEmailInput.addEventListener('blur', handleEditBlur);
};

window.closeEditModal = function () {
    log('Fechando modal de edição');
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.style.display = 'none';
        editModal.classList.remove('show');
        log('Modal de edição fechado com sucesso');
    } else {
        log('Erro: Elemento #editModal não encontrado');
    }
    const editEmailInput = document.getElementById('editEmailInput');
    const editChipContainer = document.getElementById('editChipContainer');
    const editGotoHidden = document.getElementById('editGotoHidden');
    if (editEmailInput) editEmailInput.value = '';
    if (editChipContainer) {
        while (editChipContainer.children.length > 1) {
            editChipContainer.removeChild(editChipContainer.firstChild);
        }
    }
    if (editGotoHidden) editGotoHidden.value = '';
    log('Campos limpos no modal de edição');
};

// Evento para fechar os modais ao clicar fora
document.addEventListener('DOMContentLoaded', function () {
    log('DOM totalmente carregado, adicionando evento de clique global');
    window.onclick = function (event) {
        log('Clicou fora do modal, verificando...');
        const addModal = document.getElementById('addModal');
        const editModal = document.getElementById('editModal');
        if (event.target === addModal) {
            window.closeAddModal();
        }
        if (event.target === editModal) {
            window.closeEditModal();
        }
    };
});

console.log('groups.js carregado com sucesso');
