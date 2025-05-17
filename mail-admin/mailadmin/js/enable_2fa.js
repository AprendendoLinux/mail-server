document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM completamente carregado, iniciando scripts...');

    // Função para abrir o modal de 2FA
    const activate2faBtn = document.getElementById('activate-2fa-btn');
    const twoFactorModal = document.getElementById('twoFactorModal');
    const closeModalBtn = document.querySelector('.close-btn');

    if (activate2faBtn) {
        console.log('Botão "Ativar 2FA Agora" encontrado.');
        activate2faBtn.addEventListener('click', function(event) {
            event.preventDefault();
            console.log('Clicou em "Ativar 2FA Agora".');
            if (twoFactorModal) {
                console.log('Modal encontrado, exibindo...');
                twoFactorModal.style.display = 'flex';
                setTimeout(() => twoFactorModal.classList.add('show'), 10);
            } else {
                console.log('Erro: Modal "twoFactorModal" não encontrado.');
            }
        });
    } else {
        console.log('Erro: Botão "activate2faBtn" não encontrado.');
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function() {
            if (twoFactorModal) {
                twoFactorModal.classList.remove('show');
                setTimeout(() => twoFactorModal.style.display = 'none', 300);
            }
        });
    }

    window.onclick = function(event) {
        if (event.target == twoFactorModal) {
            if (twoFactorModal) {
                twoFactorModal.classList.remove('show');
                setTimeout(() => twoFactorModal.style.display = 'none', 300);
            }
        }
    };

    // Função para gerar o QR Code via AJAX
    const generateQrBtn = document.getElementById('generate-qr-btn');
    if (generateQrBtn) {
        generateQrBtn.addEventListener('click', function() {
            console.log('Clicou em "Gerar QR Code".');
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax_generate_qr_index=1'
            })
            .then(response => {
                console.log('Resposta recebida:', response);
                return response.json();
            })
            .then(data => {
                console.log('Dados recebidos:', data);
                if (data.success) {
                    document.getElementById('two-factor-content').innerHTML = data.html;
                    console.log('Conteúdo do modal atualizado.');

                    // Inicializar ClipboardJS para o botão de copiar
                    const clipboard = new ClipboardJS('.copy-btn');
                    clipboard.on('success', function(e) {
                        console.log('Cópia bem-sucedida:', e.text);
                        const feedback = document.querySelector('.copy-feedback');
                        if (feedback) {
                            feedback.textContent = 'Copiado!';
                            feedback.style.display = 'block';
                            console.log('Mensagem "Copiado!" exibida.');
                            setTimeout(() => {
                                feedback.style.display = 'none';
                                console.log('Mensagem "Copiado!" escondida após 2 segundos.');
                            }, 2000);
                        } else {
                            console.log('Erro: Elemento .copy-feedback não encontrado.');
                        }
                        e.clearSelection();
                    });
                    clipboard.on('error', function(e) {
                        console.error('Erro ao copiar:', e);
                        alert('Erro ao copiar a chave.');
                    });
                } else {
                    console.error('Erro na resposta:', data.message);
                    alert(data.message || 'Erro ao gerar o QR Code.');
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Erro ao processar a solicitação.');
            });
        });
    } else {
        console.log('Erro: Botão "generate-qr-btn" não encontrado.');
    }

    // Função para dispensar o alerta 2FA
    function dismiss2FAAlert(event) {
        event.preventDefault();
        console.log('Clicou em "Ignorar por enquanto".');
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'dismiss_2fa_alert=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Alerta 2FA dispensado, redirecionando para:', data.redirect);
                window.location.href = data.redirect || 'dashboard.php';
            } else {
                console.error('Erro ao dispensar o alerta:', data.message);
                alert('Erro ao dispensar o alerta.');
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            alert('Erro ao processar a solicitação.');
        });
    }

    // Adicionar evento ao link "Ignorar por enquanto"
    const dismissLink = document.querySelector('.dismiss-link');
    if (dismissLink) {
        dismissLink.addEventListener('click', dismiss2FAAlert);
    } else {
        console.log('Erro: Link "dismiss-link" não encontrado.');
    }
});