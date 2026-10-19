#!/usr/bin/env bash
#
# bootstrap.sh — instalação e configuração dos componentes da VM.
# Executado pelo provision do Vagrantfile, logo após o git clone.
#
#   * Apache 2 + PHP  (interface web)
#   * MySQL           (armazenamento permanente)
#
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/proj2_cloud}"
DB_NAME="proj2"
DB_USER="proj2"
DB_PASS="proj2pass"

export DEBIAN_FRONTEND=noninteractive

log() { echo ">> [bootstrap] $*"; }

# ---------------------------------------------------------------------------
# 1. Pacotes
# ---------------------------------------------------------------------------
log "Instalando pacotes (apache2, php, mysql)"
apt-get install -y apache2 php libapache2-mod-php php-mysql php-mbstring mysql-server

# ---------------------------------------------------------------------------
# 2. MySQL — banco, usuário e esquema
# ---------------------------------------------------------------------------
log "Configurando MySQL"
systemctl enable --now mysql

mysql <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

log "Aplicando esquema do banco (db/schema.sql)"
mysql "${DB_NAME}" < "${APP_DIR}/db/schema.sql"

# ---------------------------------------------------------------------------
# 3. Apache — VirtualHost apontando para web/
# ---------------------------------------------------------------------------
log "Configurando Apache"
cat > /etc/apache2/sites-available/proj2.conf <<EOF
<VirtualHost *:80>
    ServerName proj2-cloud
    DocumentRoot ${APP_DIR}/web

    <Directory ${APP_DIR}/web>
        Options -Indexes
        AllowOverride None
        Require all granted
        DirectoryIndex index.php
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/proj2_error.log
    CustomLog \${APACHE_LOG_DIR}/proj2_access.log combined
</VirtualHost>
EOF

a2dissite -q 000-default || true
a2ensite -q proj2
systemctl enable apache2
systemctl restart apache2

log "Pronto! Acesse http://localhost:8080 (host) ou http://192.168.56.10"
