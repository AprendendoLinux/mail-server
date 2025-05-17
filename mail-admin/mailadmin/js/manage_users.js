function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
    document.getElementById('user').value = '';
    document.getElementById('password').value = '';
    document.getElementById('confirm_password').value = '';
    document.getElementById('name').value = '';
    document.getElementById('quota').value = '2GB';
}

function openEditModal(username, name, quota) {
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_name').value = name || '';
    document.getElementById('edit_quota').value = quota;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.getElementById('edit_username').value = '';
    document.getElementById('edit_name').value = '';
    document.getElementById('edit_quota').value = '2GB';
}

function openPasswordModal(username) {
    document.getElementById('change_username').value = username;
    document.getElementById('passwordModal').style.display = 'flex';
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
    document.getElementById('change_username').value = '';
    document.getElementById('change_password').value = '';
    document.getElementById('change_confirm_password').value = '';
}

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
