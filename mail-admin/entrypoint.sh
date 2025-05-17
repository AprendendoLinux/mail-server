#!/bin/sh

# Definir tempo limite máximo para esperar o MySQL (em segundos)
MAX_WAIT=120
COUNTER=0

# Aguardar o MySQL estar disponível
echo "Aguardando o MySQL estar pronto (host: $DB_HOST, port: $DB_PORT, user: $DB_USER)..."
while ! /usr/bin/mariadb-admin ping -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" --ssl-verify-server-cert=0 --silent 2>/tmp/mysql_error.log; do
    echo "MySQL não está pronto, tentando novamente em 1 segundo..."
    if [ -s /tmp/mysql_error.log ]; then
        echo "Erro mariadb-admin: $(cat /tmp/mysql_error.log)"
    fi
    # Teste alternativo com mariadb
    /usr/bin/mariadb -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" --ssl-verify-server-cert=0 -e "SELECT 1" 2>/tmp/mysql_test_error.log || {
        echo "Teste alternativo falhou: $(cat /tmp/mysql_test_error.log)"
    }
    sleep 1
    COUNTER=$((COUNTER + 1))
    if [ $COUNTER -ge $MAX_WAIT ]; then
        echo "Erro: MySQL não ficou disponível após $MAX_WAIT segundos"
        if [ -s /tmp/mysql_error.log ]; then
            echo "Último erro mariadb-admin: $(cat /tmp/mysql_error.log)"
        fi
        if [ -s /tmp/mysql_test_error.log ]; then
            echo "Último erro mariadb: $(cat /tmp/mysql_test_error.log)"
        fi
        exit 1
    fi
done
echo "MySQL está pronto!"

# Executar o script PHP para inicializar o banco
echo "Executando inicialização do banco de dados..."
php84 /var/www/html/init_db.php
if [ $? -ne 0 ]; then
    echo "Erro ao executar init_db.php"
    exit 1
fi

# Iniciar o Apache em primeiro plano
echo "Iniciando o Apache..."
exec /usr/sbin/httpd -DFOREGROUND
