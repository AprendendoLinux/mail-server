document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.widget-btn');
    const modals = document.querySelectorAll('.modal');
    const closeButtons = document.querySelectorAll('.close-btn');
    const cancelButtons = document.querySelectorAll('.modal-cancel-btn');
    const generateQrButton = document.getElementById('generate-qr-btn');
    const twoFactorContent = document.getElementById('two-factor-content');

    // Função para abrir um modal
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }
    }

    // Verificar se há um parâmetro 'modal' na URL e abrir o modal correspondente
    const urlParams = new URLSearchParams(window.location.search);
    const modalToOpen = urlParams.get('modal');
    if (modalToOpen) {
        openModal(modalToOpen);
        // Limpar o parâmetro da URL sem recarregar a página
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    // Função para recarregar a página com o parâmetro do modal
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            // Redirecionar para a mesma página com o parâmetro 'modal'
            window.location.href = `perfil.php?modal=${modalId}`;
        });
    });

    // Função para fechar modais
    closeButtons.forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        });
    });

    cancelButtons.forEach(cancelBtn => {
        cancelBtn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        });
    });

    modals.forEach(modal => {
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.classList.remove('show');
                setTimeout(() => modal.style.display = 'none', 300);
            }
        });
    });

    // Lógica para gerar o QR Code via AJAX
    if (generateQrButton) {
        generateQrButton.addEventListener('click', function() {
            fetch('perfil.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax_generate_qr=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    twoFactorContent.innerHTML = data.html;

                    // Reatribuir eventos para os novos botões de copiar
                    const copyButtons = twoFactorContent.querySelectorAll('.copy-btn');
                    copyButtons.forEach(button => {
                        button.addEventListener('click', function() {
                            const textToCopy = this.getAttribute('data-clipboard-text');
                            navigator.clipboard.writeText(textToCopy).then(() => {
                                const feedback = this.parentElement.nextElementSibling;
                                feedback.style.display = 'block';
                                setTimeout(() => {
                                    feedback.style.display = 'none';
                                }, 2000);
                            }).catch(err => {
                                console.error('Erro ao copiar texto: ', err);
                            });
                        });
                    });
                } else {
                    twoFactorContent.innerHTML = `<p class="error">${data.message}</p>`;
                }
            })
            .catch(error => {
                twoFactorContent.innerHTML = `<p class="error">Erro ao gerar QR Code: ${error.message}</p>`;
            });
        });
    }
});
