#!/bin/bash
# set -ex

# PWD=`pwd`

if  [[ "$1" == apache2* || "$1" == php-fpm || "$1" == bin* ]]; then
  INSTALLDIR=`pwd`
  # docroot is empty
  if ! [ -e index.php -a -e bin/installto.sh ]; then
    echo >&2 "roundcubemail not found in $PWD - copying now..."
    if [ "$(ls -A)" ]; then
      echo >&2 "WARNING: $PWD is not empty - press Ctrl+C now if this is an error!"
      ( set -x; ls -A; sleep 10 )
    fi
    tar cf - --one-file-system -C /usr/src/roundcubemail . | tar xf -
    echo >&2 "Complete! ROUNDCUBEMAIL has been successfully copied to $INSTALLDIR"
  # update Roundcube in docroot
  else
    echo >&2 "roundcubemail found in $INSTALLDIR - installing update..."
    (cd /usr/src/roundcubemail && bin/installto.sh -y $INSTALLDIR)
    # Re-install composer modules (including plugins)
    composer \
          --working-dir=${INSTALLDIR} \
          --prefer-dist \
          --no-dev \
          --no-interaction \
          --optimize-autoloader \
          install
  fi

  if [ -f /run/secrets/roundcube_db_user ]; then
    ROUNDCUBEMAIL_DB_USER=`cat /run/secrets/roundcube_db_user`
  fi
  if [ -f /run/secrets/roundcube_db_password ]; then
    ROUNDCUBEMAIL_DB_PASSWORD=`cat /run/secrets/roundcube_db_password`
  fi
  if [ -f /run/secrets/roundcube_oauth_client_secret ]; then
    ROUNDCUBEMAIL_OAUTH_CLIENT_SECRET=`cat /run/secrets/roundcube_oauth_client_secret`
  fi

  if [ ! -z "${!POSTGRES_ENV_POSTGRES_*}" ] || [ "$ROUNDCUBEMAIL_DB_TYPE" == "pgsql" ]; then
    : "${ROUNDCUBEMAIL_DB_TYPE:=pgsql}"
    : "${ROUNDCUBEMAIL_DB_HOST:=postgres}"
    : "${ROUNDCUBEMAIL_DB_PORT:=5432}"
    : "${ROUNDCUBEMAIL_DB_USER:=${POSTGRES_ENV_POSTGRES_USER}}"
    : "${ROUNDCUBEMAIL_DB_PASSWORD:=${POSTGRES_ENV_POSTGRES_PASSWORD}}"
    : "${ROUNDCUBEMAIL_DB_NAME:=${POSTGRES_ENV_POSTGRES_DB:-roundcubemail}}"
    : "${ROUNDCUBEMAIL_DSNW:=${ROUNDCUBEMAIL_DB_TYPE}://${ROUNDCUBEMAIL_DB_USER}:${ROUNDCUBEMAIL_DB_PASSWORD}@${ROUNDCUBEMAIL_DB_HOST}:${ROUNDCUBEMAIL_DB_PORT}/${ROUNDCUBEMAIL_DB_NAME}}"

    /wait-for-it.sh ${ROUNDCUBEMAIL_DB_HOST}:${ROUNDCUBEMAIL_DB_PORT} -t 30
  elif [ ! -z "${!MYSQL_ENV_MYSQL_*}" ] || [ "$ROUNDCUBEMAIL_DB_TYPE" == "mysql" ]; then
    : "${ROUNDCUBEMAIL_DB_TYPE:=mysql}"
    : "${ROUNDCUBEMAIL_DB_HOST:=${MAILSERVER_SQL_HOST:-mysql}}"
    : "${ROUNDCUBEMAIL_DB_PORT:=${MAILSERVER_SQL_PORT:-3306}}"
    : "${ROUNDCUBEMAIL_DB_USER:=${MAILSERVER_SQL_USER:-root}}"
    if [ "$ROUNDCUBEMAIL_DB_USER" = 'root' ]; then
      : "${ROUNDCUBEMAIL_DB_PASSWORD:=${MYSQL_ENV_MYSQL_ROOT_PASSWORD}}"
    else
      : "${ROUNDCUBEMAIL_DB_PASSWORD:=${MAILSERVER_SQL_PASSWD}}"
    fi
    : "${ROUNDCUBEMAIL_DB_NAME:=${MAILSERVER_SQL_DB:-roundcubemail}}"
    : "${ROUNDCUBEMAIL_DSNW:=${ROUNDCUBEMAIL_DB_TYPE}://${ROUNDCUBEMAIL_DB_USER}:${ROUNDCUBEMAIL_DB_PASSWORD}@${ROUNDCUBEMAIL_DB_HOST}:${ROUNDCUBEMAIL_DB_PORT}/${ROUNDCUBEMAIL_DB_NAME}}"

    /wait-for-it.sh ${ROUNDCUBEMAIL_DB_HOST}:${ROUNDCUBEMAIL_DB_PORT} -t 30
  else
    # use local SQLite DB in /var/roundcube/db
    : "${ROUNDCUBEMAIL_DB_TYPE:=sqlite}"
    : "${ROUNDCUBEMAIL_DB_DIR:=/var/roundcube/db}"
    : "${ROUNDCUBEMAIL_DB_NAME:=sqlite}"
    : "${ROUNDCUBEMAIL_DSNW:=${ROUNDCUBEMAIL_DB_TYPE}:///$ROUNDCUBEMAIL_DB_DIR/${ROUNDCUBEMAIL_DB_NAME}.db?mode=0666}"

    mkdir -p $ROUNDCUBEMAIL_DB_DIR
    chown www-data:www-data $ROUNDCUBEMAIL_DB_DIR
  fi

  : "${ROUNDCUBEMAIL_DEFAULT_HOST:=}"
  : "${ROUNDCUBEMAIL_DEFAULT_PORT:=}"
  : "${ROUNDCUBEMAIL_SMTP_SERVER:=}"
  : "${ROUNDCUBEMAIL_SMTP_PORT:=}"
  : "${ROUNDCUBEMAIL_PLUGINS:=password,archive,zipdownload}"
  : "${ROUNDCUBEMAIL_SKIN:=elastic}"
  : "${ROUNDCUBEMAIL_TEMP_DIR:=/tmp/roundcube-temp}"
  : "${ROUNDCUBEMAIL_REQUEST_PATH:=/}"
  : "${ROUNDCUBEMAIL_COMPOSER_PLUGINS_FOLDER:=$INSTALLDIR}"
  : "${ROUNDCUBEMAIL_USERNAME_DOMAIN:=firenettelecom.online}"
  : "${ROUNDCUBEMAIL_SUPPORT_URL:=}"
  : "${ROUNDCUBEMAIL_PASSWORD_DB_DSN:=${ROUNDCUBEMAIL_DSNW}}"
  : "${ROUNDCUBEMAIL_PASSWORD_MINIMUM_LENGTH:=8}"
  : "${ROUNDCUBEMAIL_PASSWORD_REQUIRE_NONALPHA:=true}"
  : "${ROUNDCUBEMAIL_PASSWORD_CONFIRM_CURRENT:=true}"
  : "${ROUNDCUBEMAIL_PASSWORD_FORCE_SAVE:=true}"
  : "${ROUNDCUBEMAIL_PASSWORD_SQL_DEBUG:=true}"

  if [ ! -z "${ROUNDCUBEMAIL_COMPOSER_PLUGINS}" ]; then
    echo "Installing plugins from the list"
    echo "Plugins: ${ROUNDCUBEMAIL_COMPOSER_PLUGINS}"

    # Change ',' into a space
    ROUNDCUBEMAIL_COMPOSER_PLUGINS_SH=`echo "${ROUNDCUBEMAIL_COMPOSER_PLUGINS}" | tr ',' ' '`

    composer \
      --working-dir=${ROUNDCUBEMAIL_COMPOSER_PLUGINS_FOLDER} \
      --prefer-dist \
      --prefer-stable \
      --update-no-dev \
      --no-interaction \
      --optimize-autoloader \
      require \
      -- \
      ${ROUNDCUBEMAIL_COMPOSER_PLUGINS_SH};
  fi

  if [ ! -d skins/${ROUNDCUBEMAIL_SKIN} ]; then
    # Installing missing skin
    echo "Installing missing skin: ${ROUNDCUBEMAIL_SKIN}"
    composer \
      --working-dir=${INSTALLDIR} \
      --prefer-dist \
      --prefer-stable \
      --update-no-dev \
      --no-interaction \
      --optimize-autoloader \
      require \
      -- \
      roundcube/${ROUNDCUBEMAIL_SKIN};
  fi

  # Verify and fix plugin directories
  echo "Verifying and fixing plugin directories..."
  for plugin in archive zipdownload; do
    if [ ! -d "$INSTALLDIR/plugins/$plugin" ]; then
      echo "Plugin $plugin directory not found, attempting to create..."
      mkdir -p "$INSTALLDIR/plugins/$plugin"
      chown -R www-data:www-data "$INSTALLDIR/plugins/$plugin"
      chmod -R 755 "$INSTALLDIR/plugins/$plugin"
    fi
    if [ ! -f "$INSTALLDIR/plugins/$plugin/$plugin.php" ]; then
      echo "Downloading $plugin plugin manually..."
      wget -O "/tmp/$plugin.tar.gz" "https://github.com/roundcube/$plugin/archive/refs/tags/1.6.tar.gz"
      tar -xzf "/tmp/$plugin.tar.gz" -C "$INSTALLDIR/plugins/$plugin" --strip-components=1
      rm "/tmp/$plugin.tar.gz"
      chown -R www-data:www-data "$INSTALLDIR/plugins/$plugin"
      chmod -R 755 "$INSTALLDIR/plugins/$plugin"
    fi
  done

  # Convert comma-separated plugins into a PHP array
  PLUGINS_ARRAY=$(echo "${ROUNDCUBEMAIL_PLUGINS}" | awk -F',' '{for(i=1;i<=NF;i++) printf "\x27%s\x27%s", $i, (i==NF ? "" : ", ")}')

  GENERATED_DES_KEY=`head /dev/urandom | base64 | head -c 24`
  echo "Write root config to $PWD/config/config.inc.php"
  echo "<?php
  \$config['db_dsnw'] = '${ROUNDCUBEMAIL_DSNW}';
  \$config['log_driver'] = 'stdout';
  \$config['imap_host'] = '${ROUNDCUBEMAIL_DEFAULT_HOST}';
  \$config['smtp_host'] = '${ROUNDCUBEMAIL_SMTP_SERVER}';
  \$config['support_url'] = '${ROUNDCUBEMAIL_SUPPORT_URL}';
  \$config['temp_dir'] = '${ROUNDCUBEMAIL_TEMP_DIR}';
  \$config['des_key'] = '${GENERATED_DES_KEY}';
  \$config['username_domain'] = '${ROUNDCUBEMAIL_USERNAME_DOMAIN}';
  \$config['request_path'] = '${ROUNDCUBEMAIL_REQUEST_PATH}';
  \$config['plugins'] = [${PLUGINS_ARRAY}];
  \$config['enable_spellcheck'] = true;
  \$config['spellcheck_engine'] = 'pspell';
  \$config['password_driver'] = 'custom';
  \$config['password_db_dsn'] = '${ROUNDCUBEMAIL_PASSWORD_DB_DSN}';
  \$config['password_minimum_length'] = ${ROUNDCUBEMAIL_PASSWORD_MINIMUM_LENGTH};
  \$config['password_require_nonalpha'] = ${ROUNDCUBEMAIL_PASSWORD_REQUIRE_NONALPHA};
  \$config['password_confirm_current'] = ${ROUNDCUBEMAIL_PASSWORD_CONFIRM_CURRENT};
  \$config['password_force_save'] = ${ROUNDCUBEMAIL_PASSWORD_FORCE_SAVE};
  \$config['password_sql_debug'] = ${ROUNDCUBEMAIL_PASSWORD_SQL_DEBUG};
  \$config['zipdownload_selection'] = true;
  \$config['archive_type'] = 'zip';
  \$config['zipdownload_max_size'] = 10485760; // 10MB limit
  include(__DIR__ . '/config.docker.inc.php');
  " > config/config.inc.php

  echo "Write Docker config to $PWD/config/config.docker.inc.php"
  echo "<?php
  \$config['db_dsnw'] = '${ROUNDCUBEMAIL_DSNW}';
  \$config['db_dsnr'] = '${ROUNDCUBEMAIL_DSNR}';
  \$config['imap_host'] = '${ROUNDCUBEMAIL_DEFAULT_HOST}';
  \$config['smtp_host'] = '${ROUNDCUBEMAIL_SMTP_SERVER}';
  \$config['username_domain'] = '${ROUNDCUBEMAIL_USERNAME_DOMAIN}';
  \$config['temp_dir'] = '${ROUNDCUBEMAIL_TEMP_DIR}';
  \$config['skin'] = '${ROUNDCUBEMAIL_SKIN}';
  \$config['request_path'] = '${ROUNDCUBEMAIL_REQUEST_PATH}';
  if (!isset(\$config['plugins']) || !is_array(\$config['plugins'])) {
      \$config['plugins'] = [];
  }
  \$config['plugins'] = array_filter(array_unique(array_merge(\$config['plugins'], [${PLUGINS_ARRAY}])));
  \$config['debug_level'] = 1;  // Apenas erros graves
  \$config['sql_debug'] = false;  // Desativa logs de SQL
  \$config['imap_debug'] = false;  // Desativa logs de IMAP
  \$config['smtp_debug'] = false;  // Desativa logs de SMTP
  " > config/config.docker.inc.php

  if [ -e /run/secrets/roundcube_des_key ]; then
    echo "\$config['des_key'] = file_get_contents('/run/secrets/roundcube_des_key');" >> config/config.docker.inc.php
  elif [ ! -z "${ROUNDCUBEMAIL_DES_KEY}" ]; then
    echo "\$config['des_key'] = getenv('ROUNDCUBEMAIL_DES_KEY');" >> config/config.docker.inc.php
  fi

  if [ -e /run/secrets/roundcube_oauth_client_secret ]; then
    echo "\$config['oauth_client_secret'] = file_get_contents('/run/secrets/roundcube_oauth_client_secret');" >> config/config.docker.inc.php
  elif [ ! -z "${ROUNDCUBEMAIL_OAUTH_CLIENT_SECRET}" ]; then
    echo "\$config['oauth_client_secret'] = '${ROUNDCUBEMAIL_OAUTH_CLIENT_SECRET}';" >> config/config.docker.inc.php
  fi

  if [ ! -z "${ROUNDCUBEMAIL_SPELLCHECK_URI}" ]; then
    echo "\$config['spellcheck_engine'] = 'googie';" >> config/config.docker.inc.php
    echo "\$config['spellcheck_uri'] = '${ROUNDCUBEMAIL_SPELLCHECK_URI}';" >> config/config.docker.inc.php
  fi

  # If the "enigma" plugin is enabled but has no storage configured, inject a default value for the mandatory setting.
  if $(echo $ROUNDCUBEMAIL_PLUGINS | grep -Eq '\benigma\b') && ! grep -qr enigma_pgp_homedir /var/roundcube/config/; then
    echo "\$config['enigma_pgp_homedir'] = '/var/roundcube/enigma';" >> config/config.docker.inc.php
  fi

  # include custom config files
  for fn in `ls /var/roundcube/config/*.php 2>/dev/null || true`; do
    echo "include('$fn');" >> config/config.docker.inc.php
  done

  # Generate custom.php for password plugin with debugging
  echo "Creating custom.php for password plugin in $PWD/plugins/password/drivers/"
  mkdir -p $PWD/plugins/password/drivers
  cat << 'EOF' > $PWD/plugins/password/drivers/custom.php
<?php
/**
 * Driver personalizado para troca de senhas no Roundcube
 * Compatível com o banco de dados MySQL do docker-mailserver
 */
class rcube_custom_password {
    private $db;

    private function init_db() {
        $user = getenv('MAILSERVER_SQL_USER') ?: '';
        $passwd = getenv('MAILSERVER_SQL_PASSWD') ?: '';
        $host = getenv('MAILSERVER_SQL_HOST') ?: '';
        $port = getenv('MAILSERVER_SQL_PORT') ?: '';
        $db = getenv('MAILSERVER_SQL_DB') ?: '';
        $dsn = "mysql://$user:$passwd@$host:$port/$db";
        $this->db = rcube_db::factory($dsn, '', false);
        $this->db->db_connect('w');
        if ($this->db->is_error()) {
            error_log("Password plugin: Database connection failed - " . $this->db->get_error_message());
            raise_error(array(
                'code' => 600,
                'type' => 'db',
                'message' => "Conexão com o banco de dados falhou: " . $this->db->get_error_message()
            ), true, true);
            return false;
        }
        error_log("Password plugin: Database connection successful");
        return true;
    }

    public function save($currpass, $newpass) {
        if (!$this->init_db()) {
            error_log("Password plugin: init_db failed");
            return PASSWORD_ERROR;
        }

        $rcube = rcube::get_instance();
        $username = $rcube->user->get_username();
        error_log("Password plugin: Attempting to save password for user: $username");

        $sql = "SELECT password FROM mailbox WHERE username = ? AND active = 1";
        $result = $this->db->query($sql, $username);
        if (!$result) {
            error_log("Password plugin: Query failed - " . $this->db->get_error_message());
            return PASSWORD_ERROR;
        }

        $row = $this->db->fetch_assoc($result);
        if (!$row) {
            error_log("Password plugin: No user found for username: $username");
            return PASSWORD_ERROR;
        }

        if (!isset($row['password'])) {
            error_log("Password plugin: Password field not found in result");
            return PASSWORD_ERROR;
        }

        $stored_hash = $row['password'];
        $test_hash = crypt($currpass, $stored_hash);
        if ($test_hash !== $stored_hash) {
            error_log("Password plugin: Current password mismatch");
            return PASSWORD_ERROR;
        }

        // Gera o novo hash SHA512-CRYPT
        $salt = substr(sha1(random_bytes(16)), 0, 16);
        $prefix = '$6$rounds=5000$' . $salt;
        $new_hash = crypt($newpass, $prefix);

        $sql = "UPDATE mailbox SET password = ? WHERE username = ? AND active = 1";
        $result = $this->db->query($sql, $new_hash, $username);

        if ($result && $this->db->affected_rows($result) > 0) {
            error_log("Password plugin: Password updated successfully for user: $username");
            return PASSWORD_SUCCESS;
        } else {
            error_log("Password plugin: Password update failed - " . $this->db->get_error_message());
            return PASSWORD_ERROR;
        }
    }
}
?>
EOF
  # Set permissions for custom.php and plugin directory
  chown -R www-data:www-data $PWD/plugins/password
  chmod -R 755 $PWD/plugins/password
  chown www-data:www-data $PWD/plugins/password/drivers/custom.php
  chmod 644 $PWD/plugins/password/drivers/custom.php

  # Fix permissions for Roundcube directories and root
  echo "Fixing permissions for Roundcube directories and root..."
  chown -R www-data:www-data $PWD $PWD/public_html
  chmod -R 755 $PWD $PWD/public_html

  # Check and fix symbolic links
  echo "Checking and fixing symbolic links..."
  for dir in skins plugins program; do
    if [ -L "$PWD/public_html/$dir" ]; then
      echo "Symbolic link found: $PWD/public_html/$dir"
      TARGET=$(readlink -f "$PWD/public_html/$dir")
      if [ -d "$TARGET" ]; then
        echo "Target $TARGET exists, fixing permissions..."
        chown -R www-data:www-data "$TARGET"
        chmod -R 755 "$TARGET"
      else
        echo "Target $TARGET does not exist, removing broken symlink..."
        rm "$PWD/public_html/$dir"
        ln -s "$PWD/$dir" "$PWD/public_html/$dir"
        chown www-data:www-data "$PWD/public_html/$dir"
        chmod 755 "$PWD/public_html/$dir"
      fi
    else
      echo "Creating symbolic link for $dir..."
      ln -s "$PWD/$dir" "$PWD/public_html/$dir"
      chown www-data:www-data "$PWD/public_html/$dir"
      chmod 755 "$PWD/public_html/$dir"
    fi
  done

  # Enable FollowSymLinks and disable restrictive options in Apache configuration
  echo "Enabling FollowSymLinks and disabling restrictive options for Apache..."
  cat << 'EOF' > /etc/apache2/conf-enabled/roundcube-symlinks.conf
<Directory /var/www/html/public_html>
    Options +FollowSymLinks -SymLinksIfOwnerMatch -MultiViews
    AllowOverride All
    Require all granted
</Directory>

<Directory /var/www/html>
    Options +FollowSymLinks -SymLinksIfOwnerMatch -MultiViews
    AllowOverride All
    Require all granted
</Directory>
EOF

  # Adjust Apache logging to minimize access logs
  if [ "${APACHE_LOG_LEVEL}" = "error" ]; then
    echo "CustomLog /dev/null common" > /etc/apache2/conf-enabled/custom-log.conf
    echo "ErrorLog /proc/self/fd/2" >> /etc/apache2/conf-enabled/custom-log.conf
  fi

  # initialize or update DB
  bin/initdb.sh --dir=$PWD/SQL --update || echo "Failed to initialize/update the database. Please start with an empty database and restart the container."

  if [ ! -z "${ROUNDCUBEMAIL_TEMP_DIR}" ]; then
    mkdir -p ${ROUNDCUBEMAIL_TEMP_DIR} && chown www-data ${ROUNDCUBEMAIL_TEMP_DIR}
  fi

  if [ ! -z "${ROUNDCUBEMAIL_UPLOAD_MAX_FILESIZE}" ]; then
    echo "upload_max_filesize=${ROUNDCUBEMAIL_UPLOAD_MAX_FILESIZE}" >> /usr/local/etc/php/conf.d/roundcube-override.ini
    echo "post_max_size=${ROUNDCUBEMAIL_UPLOAD_MAX_FILESIZE}" >> /usr/local/etc/php/conf.d/roundcube-override.ini
  fi

  : "${ROUNDCUBEMAIL_LOCALE:=en_US.UTF-8 UTF-8}"

  if [ -e /usr/sbin/locale-gen ] && [ ! -f /etc/locale.gen ] && [ ! -z "${ROUNDCUBEMAIL_LOCALE}" ]; then
    echo "${ROUNDCUBEMAIL_LOCALE}" > /etc/locale.gen && /usr/sbin/locale-gen
  fi

  if [ ! -z "${ROUNDCUBEMAIL_ASPELL_DICTS}" ]; then
    ASPELL_PACKAGES=`echo -n "aspell-${ROUNDCUBEMAIL_ASPELL_DICTS}" | sed -E "s/[, ]+/ aspell-/g"`
    which apt-get && apt-get update && apt-get install -y $ASPELL_PACKAGES
    which apk && apk add --no-cache $ASPELL_PACKAGES
  fi

fi

exec "$@"

