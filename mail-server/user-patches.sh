#!/bin/bash

# Verificar se as variáveis de ambiente do MySQL estão definidas
if [ -z "$MAILSERVER_SQL_HOST" ] || [ -z "$MAILSERVER_SQL_DB" ] || [ -z "$MAILSERVER_SQL_USER" ] || [ -z "$MAILSERVER_SQL_PASSWD" ]; then
    echo "Erro: Variáveis de ambiente MAILSERVER_SQL_HOST, MAILSERVER_SQL_DB, MAILSERVER_SQL_USER ou MAILSERVER_SQL_PASSWD não definidas."
    exit 1
fi

# Configurar Postfix para usar MySQL
cat > /etc/postfix/mysql-virtual-mailbox-maps.cf << EOF
user = $MAILSERVER_SQL_USER
password = $MAILSERVER_SQL_PASSWD
hosts = $MAILSERVER_SQL_HOST
dbname = $MAILSERVER_SQL_DB
query = SELECT username FROM mailbox WHERE username='%s' AND active=1
EOF

cat > /etc/postfix/mysql-virtual-alias-maps.cf << EOF
user = $MAILSERVER_SQL_USER
password = $MAILSERVER_SQL_PASSWD
hosts = $MAILSERVER_SQL_HOST
dbname = $MAILSERVER_SQL_DB
query = SELECT goto FROM alias WHERE address='%s' AND active=1
EOF

cat > /etc/postfix/mysql-virtual-domains-maps.cf << EOF
user = $MAILSERVER_SQL_USER
password = $MAILSERVER_SQL_PASSWD
hosts = $MAILSERVER_SQL_HOST
dbname = $MAILSERVER_SQL_DB
query = SELECT domain FROM domain WHERE domain='%s' AND active=1
EOF

# Configurar main.cf do Postfix para usar mapas MySQL
postconf -e "virtual_mailbox_maps = mysql:/etc/postfix/mysql-virtual-mailbox-maps.cf"
postconf -e "virtual_alias_maps = mysql:/etc/postfix/mysql-virtual-alias-maps.cf"
postconf -e "virtual_mailbox_domains = mysql:/etc/postfix/mysql-virtual-domains-maps.cf"

# Configurar Postfix para consultar o serviço quota-status do Dovecot
postconf -e "smtpd_recipient_restrictions = check_policy_service inet:127.0.0.1:65265, permit_sasl_authenticated, permit_mynetworks, reject_unauth_destination"

# Configurar Dovecot para usar MySQL
cat > /etc/dovecot/dovecot-sql.conf.ext << EOF
driver = mysql
connect = host=$MAILSERVER_SQL_HOST dbname=$MAILSERVER_SQL_DB user=$MAILSERVER_SQL_USER password=$MAILSERVER_SQL_PASSWD
default_pass_scheme = SHA512-CRYPT
password_query = SELECT username AS user, password FROM mailbox WHERE username='%u' AND active=1
user_query = SELECT '/var/mail/%d/%n' AS home, 'maildir:/var/mail/%d/%n' AS mail, 5000 AS uid, 5000 AS gid, concat('*:bytes=', quota) AS quota_rule FROM mailbox WHERE username='%u' AND active=1
EOF

# Configurar Dovecot para usar backend SQL
cat > /etc/dovecot/conf.d/10-auth.conf << EOF
disable_plaintext_auth = yes
auth_mechanisms = plain login
!include auth-sql.conf.ext
EOF

# Configurar plugin de cota
cat > /etc/dovecot/conf.d/90-quota.conf << EOF
plugin {
    quota = maildir:User quota
    quota_vsizes = yes
    quota_max_mail_size = 10M
    quota_rule2 = Trash:storage=+50M
    quota_grace = 10%%
    quota_warning = storage=95%% quota-warning 95 %u %d
    quota_warning2 = storage=80%% quota-warning 80 %u %d
    quota_warning3 = -storage=100%% quota-warning below %u %d
    quota_status_success = DUNNO
    quota_status_nouser = DUNNO
    quota_status_overquota = "552 5.2.2 Mailbox is full"
}

service quota-warning {
    executable = script /usr/local/bin/quota-warning
    unix_listener quota-warning {
        user = dovecot
        group = dovecot
        mode = 0660
    }
}

service quota-status {
    executable = quota-status -p postfix
    inet_listener {
        address = 127.0.0.1
        port = 65265
    }
    client_limit = 1
}
EOF

# Configurar plugins de cota para IMAP, LMTP e POP3 (se habilitado)
cat > /etc/dovecot/conf.d/20-imap.conf << EOF
protocol imap {
    mail_plugins = \$mail_plugins quota imap_quota
}
EOF

cat > /etc/dovecot/conf.d/20-lmtp.conf << EOF
protocol lmtp {
    mail_plugins = \$mail_plugins quota
}
EOF

cat > /etc/dovecot/conf.d/20-pop3.conf << EOF
protocol pop3 {
    mail_plugins = \$mail_plugins quota
}
EOF

# Configurar logs detalhados do Dovecot
cat > /etc/dovecot/conf.d/10-logging.conf << EOF
log_path = /var/log/dovecot.log
info_log_path = /var/log/dovecot-info.log
debug_log_path = /var/log/dovecot-debug.log
log_timestamp = "%Y-%m-%d %H:%M:%S "
auth_verbose = yes
auth_debug = yes
mail_debug = yes
verbose_ssl = yes
EOF

# Sobrescrever dovecot-quotas.cf para garantir que esteja vazio ou desativado
: > /etc/dovecot/dovecot-quotas.cf

# Verificar conexão MySQL
mysql -h "$MAILSERVER_SQL_HOST" -u "$MAILSERVER_SQL_USER" -p"$MAILSERVER_SQL_PASSWD" -D "$MAILSERVER_SQL_DB" -e "SELECT 1" || {
    echo "Erro: Falha na conexão com o MySQL. Verifique as variáveis de ambiente ou o contêiner $MAILSERVER_SQL_HOST."
    exit 1
}

# Garantir permissões corretas
chmod 640 /etc/dovecot/dovecot-sql.conf.ext
chown dovecot:dovecot /etc/dovecot/dovecot-sql.conf.ext
chmod 644 /etc/dovecot/conf.d/90-quota.conf
chown root:root /etc/dovecot/conf.d/90-quota.conf
chmod 644 /etc/dovecot/conf.d/10-auth.conf
chown root:root /etc/dovecot/conf.d/10-auth.conf
chmod 644 /etc/dovecot/conf.d/20-imap.conf
chown root:root /etc/dovecot/conf.d/20-imap.conf
chmod 644 /etc/dovecot/conf.d/20-lmtp.conf
chown root:root /etc/dovecot/conf.d/20-lmtp.conf
chmod 644 /etc/dovecot/conf.d/20-pop3.conf
chown root:root /etc/dovecot/conf.d/20-pop3.conf
chmod 644 /etc/dovecot/conf.d/10-logging.conf
chown root:root /etc/dovecot/conf.d/10-logging.conf
chmod 644 /etc/dovecot/dovecot-quotas.cf
chown root:root /etc/dovecot/dovecot-quotas.cf
