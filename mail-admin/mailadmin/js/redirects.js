function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
    document.getElementById('username').value = '';
    document.getElementById('goto').value = '';
}

function openEditModal(address, goto) {
    document.getElementById('editModal').style.display = 'flex';
    document.getElementById('editAddress').value = address;
    document.getElementById('editUsername').value = address.split('@')[0];
    document.getElementById('editGoto').value = goto;
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.getElementById('editAddress').value = '';
    document.getElementById('editUsername').value = '';
    document.getElementById('editGoto').value = '';
}

// Fechar o modal ao clicar fora dele
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeAddModal();
        closeEditModal();
    }
};
