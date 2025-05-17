# Servidor de E-mail com Docker

Este projeto, disponível em [AprendendoLinux/mail-server](https://github.com/AprendendoLinux/mail-server), fornece uma solução completa para configurar um servidor de e-mail seguro e robusto utilizando o Docker e o Docker Compose. Ele integra múltiplos serviços, incluindo um servidor de e-mail (baseado no [docker-mailserver](https://github.com/docker-mailserver/docker-mailserver)), um banco de dados MySQL, certificados SSL/TLS gerados pelo Certbot com integração ao Cloudflare, uma interface administrativa para gerenciamento de contas de e-mail e o Roundcube como cliente de e-mail baseado na web. Esta documentação detalha a arquitetura, os pré-requisitos, a instalação, a configuração e o uso do projeto.

---

## Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura do Projeto](#arquitetura-do-projeto)
3. [Pré-requisitos](#pré-requisitos)
4. [Instalação](#instalação)
5. [Configuração](#configuração)
6. [Uso](#uso)
7. [Estrutura do docker-compose.yml](#estrutura-do-docker-composeyml)
8. [Serviços Incluídos](#serviços-incluídos)
9. [Segurança](#segurança)
10. [Manutenção e Atualizações](#manutenção-e-atualizações)
11. [Solução de Problemas](#solução-de-problemas)
12. [Contribuição](#contribuição)
13. [Licença](#licença)

---

## Visão Geral

O projeto **AprendendoLinux/mail-server** é uma solução de código aberto para hospedar um servidor de e-mail completo, ideal para pequenas e médias empresas, organizações ou indivíduos que desejam gerenciar seus próprios serviços de e-mail. Ele utiliza contêineres Docker para garantir portabilidade, escalabilidade e facilidade de manutenção. Os principais recursos incluem:

- **Servidor de E-mail**: Suporte a protocolos SMTP, IMAP, POP3 com autenticação segura (TLS/SSL).
- **Gerenciamento de Certificados**: Integração com Certbot e Cloudflare para geração automática de certificados SSL/TLS.
- **Interface Administrativa**: Ferramenta web para gerenciamento de contas de e-mail e configurações.
- **Cliente Webmail**: Roundcube para acesso a e-mails via navegador.
- **Segurança Avançada**: Suporte a DKIM, DMARC, SPF, filtragem de spam (Rspamd, SpamAssassin), antivírus (ClamAV) e mais.
- **Banco de Dados**: MySQL para armazenamento de dados do servidor de e-mail e do Roundcube.

O projeto é configurado via um arquivo `docker-compose.yml`, que orquestra todos os serviços necessários.

---

## Arquitetura do Projeto

A arquitetura do projeto é composta por cinco serviços principais, todos conectados através de uma rede Docker personalizada (`mail-network`):

1. **mail-db**: Banco de dados MySQL que armazena configurações e dados do servidor de e-mail e do Roundcube.
2. **certbot**: Serviço para geração e renovação de certificados SSL/TLS usando o Certbot com integração ao Cloudflare.
3. **mail-server**: Servidor de e-mail baseado no `docker-mailserver`, responsável por SMTP, IMAP, POP3 e filtragem de e-mails.
4. **mail-admin**: Interface web para gerenciamento de contas de e-mail, domínios e configurações.
5. **roundcube**: Cliente de e-mail baseado na web, permitindo que os usuários acessem suas caixas de correio via navegador.

Os serviços se comunicam através da rede `mail-network` (sub-rede 192.168.22.0/24), com o `mail-server` configurado com um IP fixo (192.168.22.254) para facilitar a resolução de nomes.

---

## Pré-requisitos

Antes de iniciar, certifique-se de que os seguintes requisitos estão atendidos:

- **Sistema Operacional**: Linux (recomendado Ubuntu 20.04+ ou Debian 10+), macOS ou Windows com WSL2.
- **Docker**: Versão 20.10 ou superior.
- **Docker Compose**: Versão 2.0 ou superior.
- **Acesso Root**: Necessário para instalar pacotes e configurar permissões.
- **Domínio Configurado**: Um domínio com registros DNS configurados (MX, A, TXT para SPF/DKIM/DMARC).
- **Conta Cloudflare**: Para geração de certificados SSL/TLS via Certbot.
- **Espaço em Disco**: Pelo menos 10 GB para dados de e-mails, logs e banco de dados.
- **Memória RAM**: Mínimo de 4 GB (8 GB recomendado para desempenho ideal).
- **Portas Abertas**: As portas 25, 465, 587, 993, 995 (e-mail), 8080 (Roundcube) e 8081 (admin) devem estar acessíveis.

---

## Instalação

Siga os passos abaixo para instalar e configurar o projeto:

1. **Clone o Repositório**:
   ```bash
   git clone https://github.com/AprendendoLinux/mail-server.git
   cd mail-server
   ```

2. **Crie os Diretórios Necessários**:
   Crie os diretórios para persistência de dados no host:
   ```bash
   mkdir -p /srv/mail/{db,data,state,logs,certs/data,opendkim}
   ```

3. **Configure as Variáveis de Ambiente**:
   Edite o arquivo `docker-compose.yml` e substitua as variáveis sensíveis, como:
   - `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `ROUNDCUBE_DB_PASSWORD`: Use senhas seguras.
   - `CERTBOT_DOMAINS`, `CERTBOT_BASE_DOMAIN`, `CERTBOT_EMAIL`, `CLOUDFLARE_EMAIL`, `CLOUDFLARE_API_KEY`: Configure com base no seu domínio e conta Cloudflare.
   - `SMTP_USERNAME`, `SMTP_PASSWORD` (no serviço `mail-admin`): Configure com credenciais SMTP válidas.
   - `OVERRIDE_HOSTNAME`, `POSTMASTER_ADDRESS`: Ajuste para seu domínio.

4. **Crie o Script de Inicialização do Banco de Dados** (se necessário):
   Crie o arquivo `init.db.sh` no diretório do projeto com permissões de execução:
   ```bash
   touch init.db.sh
   chmod +x init.db.sh
   ```
   Adicione scripts SQL para inicialização do banco, se necessário.

5. **Inicie os Contêineres**:
   Execute o comando abaixo para iniciar todos os serviços:
   ```bash
   docker-compose up -d
   ```

6. **Verifique os Logs**:
   Monitore os logs para garantir que os serviços iniciaram corretamente:
   ```bash
   docker-compose logs -f
   ```

---

## Configuração

### Configuração de DNS

Para que o servidor de e-mail funcione corretamente, configure os seguintes registros DNS no seu domínio:

- **Registro MX**:
  ```
  @  MX  10  mail.dominiobase.com.br
  ```

- **Registro A**:
  ```
  mail  A  <SEU_IP_PÚBLICO>
  ```

- **Registro TXT (SPF)**:
  ```
  @  TXT  v=spf1 mx a:mail.dominiobase.com.br ~all
  ```

- **Registro TXT (DKIM)**:
  Após iniciar o serviço `mail-server`, obtenha a chave DKIM em `/srv/mail/opendkim` e adicione ao DNS:
  ```
  mail._domainkey  TXT  v=DKIM1; k=rsa; p=<CHAVE_PÚBLICA>
  ```

- **Registro TXT (DMARC)**:
  ```
  _dmarc  TXT  v=DMARC1; p=quarantine; rua=mailto:dmarc@dominiobase.com.br;
  ```

### Configuração de Certificados SSL/TLS

O serviço `certbot` gera certificados automaticamente para os domínios especificados em `CERTBOT_DOMAINS`. Certifique-se de que:
- O `CLOUDFLARE_API_KEY` está correto.
- Os domínios listados são gerenciados pela Cloudflare.
- O serviço `certbot` iniciou sem erros (verifique os logs).

Os certificados são armazenados em `/srv/mail/certs/data` e usados pelo `mail-server`.

### Configuração do Roundcube

O Roundcube está configurado para se conectar ao `mail-server` via IMAPS (porta 993) e SMTP (porta 587 com STARTTLS). Ajuste as variáveis de ambiente no `docker-compose.yml` se necessário, especialmente:
- `ROUNDCUBEMAIL_DEFAULT_HOST`
- `ROUNDCUBEMAIL_SMTP_SERVER`
- `ROUNDCUBEMAIL_LANGUAGE` (para outros idiomas)

### Configuração do Mail Admin

A interface administrativa (`mail-admin`) permite gerenciar contas de e-mail e domínios. Configure as variáveis SMTP no `docker-compose.yml` para envio de notificações:
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`
- Habilite o reCAPTCHA, se desejar, configurando `RECAPTCHA_SITE_KEY` e `RECAPTCHA_SECRET_KEY`.

---

## Uso

### Acessando o Webmail (Roundcube)

Acesse o Roundcube em:
```
http://<SEU_IP>:8080
```
Use as credenciais de um usuário de e-mail configurado no `mail-server`.

### Acessando a Interface Administrativa

Acesse a interface administrativa em:
```
http://<SEU_IP>:8081
```
Use as credenciais configuradas no banco de dados `mailserver`.

### Enviando e Recebendo E-mails

- **Clientes de E-mail**: Configure clientes como Outlook ou Thunderbird com:
  - IMAP: `mail.dominiobase.com.br`, porta 993, SSL/TLS
  - SMTP: `mail.dominiobase.com.br`, porta 587, STARTTLS
  - Credenciais: E-mail completo (e.g., `user@dominiobase.com.br`) e senha.

- **Teste de Envio**: Envie um e-mail de teste para verificar a configuração.

---

## Estrutura do docker-compose.yml

O arquivo `docker-compose.yml` define os cinco serviços, suas configurações, volumes, portas e redes. Aqui está um resumo:

- **Redes**:
  - `mail-network`: Rede bridge com sub-rede 192.168.22.0/24.

- **Volumes**:
  - `/srv/mail/db`: Dados do MySQL.
  - `/srv/mail/data`: Caixas de correio.
  - `/srv/mail/state`: Estado do Postfix.
  - `/srv/mail/logs`: Logs do servidor.
  - `/srv/mail/certs/data`: Certificados SSL/TLS.
  - `/srv/mail/opendkim`: Configurações DKIM.

- **Portas**:
  - 25, 465, 587, 993, 995: Protocolos de e-mail.
  - 8080: Roundcube.
  - 8081: Mail Admin.

---

## Serviços Incluídos

### mail-db (MySQL)

- **Imagem**: `mysql:9`
- **Função**: Armazena dados do `mail-server` e do `roundcube`.
- **Configurações**:
  - Bancos: `mailserver` e `roundcube`.
  - Usuários: `mailuser` e `roundcube`.
  - Persistência: `/srv/mail/db`.

### certbot

- **Imagem**: `aprendendolinux/certbot-cloudflare:latest`
- **Função**: Gera e renova certificados SSL/TLS.
- **Integração**: Cloudflare para validação DNS.
- **Persistência**: `/srv/mail/certs/data`.

### mail-server

- **Imagem**: `aprendendolinux/mailserver:latest`
- **Função**: Servidor de e-mail completo com SMTP, IMAP, POP3.
- **Recursos de Segurança**:
  - DKIM, DMARC, SPF.
  - Rspamd, SpamAssassin, ClamAV.
  - TLS com certificados do Certbot.
- **Persistência**: Dados, estado, logs e configurações DKIM.

### mail-admin

- **Imagem**: `aprendendolinux/mail-admin:latest`
- **Função**: Interface web para gerenciamento de contas e domínios.
- **Configurações**: SMTP para notificações, conexão ao banco `mailserver`.

### roundcube

- **Imagem**: `aprendendolinux/roundcube:latest`
- **Função**: Cliente de e-mail baseado na web.
- **Configurações**: IMAPS e SMTP configurados para o `mail-server`.

---

## Segurança

O projeto implementa várias camadas de segurança:

- **Criptografia**: Certificados SSL/TLS para todos os protocolos (IMAPS, SMTPS, etc.).
- **Autenticação**: Suporte a autenticação segura via MySQL.
- **Filtragem de Spam**: Rspamd e SpamAssassin com configurações ajustáveis.
- **Antivírus**: ClamAV para escaneamento de e-mails.
- **DKIM/DMARC/SPF**: Previne spoofing e melhora a entregabilidade.
- **Postscreen**: Proteção contra bots e spammers.

**Recomendações**:
- Use senhas fortes para todos os serviços.
- Monitore logs regularmente (`/srv/mail/logs`).
- Mantenha backups dos volumes (`/srv/mail`).

---

## Manutenção e Atualizações

### Atualizando Imagens

Para atualizar as imagens Docker:
```bash
docker-compose pull
docker-compose up -d
```

### Rotação de Logs

Os logs são rotacionados semanalmente (`LOGROTATE_INTERVAL=weekly`), mantendo 4 arquivos antigos (`LOGROTATE_COUNT=4`).

### Renovação de Certificados

O serviço `certbot` renova certificados automaticamente. Verifique os logs para erros:
```bash
docker logs certbot
```

### Backup

Faça backup regular dos diretórios:
- `/srv/mail/db`
- `/srv/mail/data`
- `/srv/mail/certs/data`

---

## Solução de Problemas

- **Erro de Conexão ao Banco**:
  - Verifique as credenciais em `MYSQL_USER`, `MYSQL_PASSWORD`, etc.
  - Confirme que o serviço `mail-db` está ativo (`docker ps`).

- **Certificados Não Gerados**:
  - Verifique o `CLOUDFLARE_API_KEY` e os domínios em `CERTBOT_DOMAINS`.
  - Consulte os logs do `certbot`.

- **E-mails Não Entregues**:
  - Cheque os registros DNS (MX, SPF, DKIM, DMARC).
  - Verifique os logs em `/srv/mail/logs`.

- **Roundcube Não Conecta**:
  - Confirme que `ROUNDCUBEMAIL_DEFAULT_HOST` e `ROUNDCUBEMAIL_SMTP_SERVER` estão corretos.
  - Teste a conexão manualmente com `telnet mail.dominiobase.com.br 993`.

---

## Contribuição

Contribuições são bem-vindas! Siga os passos abaixo:

1. Faça um fork do repositório.
2. Crie uma branch para sua feature (`git checkout -b feature/nova-funcionalidade`).
3. Commit suas alterações (`git commit -m "Adiciona nova funcionalidade"`).
4. Envie para o repositório remoto (`git push origin feature/nova-funcionalidade`).
5. Abra um Pull Request.

---

## Licença

Este projeto é licenciado sob a [MIT License](LICENSE). Veja o arquivo `LICENSE` para detalhes.

---

**Autor**: [Henrique Fagundes](https://aprendendolinux.com.br)  
**Repositório**: [https://github.com/AprendendoLinux/mail-server](https://github.com/AprendendoLinux/mail-server)  
**Última Atualização**: Maio de 2025
