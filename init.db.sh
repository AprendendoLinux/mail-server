#!/bin/bash
set -e

# Exibe as variáveis para debug (opcional)
echo "Criando banco de dados com:"
echo "  Banco: $ROUNDCUBE_DB_NAME"
echo "  Usuário: $ROUNDCUBE_DB_USER"

# Executa comandos SQL com o cliente mysql, usando a senha do root
mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<-EOSQL
  CREATE DATABASE IF NOT EXISTS \`$ROUNDCUBE_DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
  
  CREATE USER IF NOT EXISTS '$ROUNDCUBE_DB_USER'@'%' IDENTIFIED BY '$ROUNDCUBE_DB_PASSWORD';

  GRANT ALL PRIVILEGES ON \`$ROUNDCUBE_DB_NAME\`.* TO '$ROUNDCUBE_DB_USER'@'%';
  
  FLUSH PRIVILEGES;
EOSQL

