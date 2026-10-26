#!/usr/bin/env bash
#
# bootstrap.sh — instalação e configuração dos componentes da VM.
# Executado pelo provision do Vagrantfile, logo após o git clone.
#
#   * Apache 2 + PHP  (interface web)
#   * MySQL           (armazenamento permanente)
#   * Controlador     (runner/envctl.sh via sudo, usuário proj2run)
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
# 3. Controlador de ambientes (runner/envctl.sh)
# ---------------------------------------------------------------------------
log "Configurando controlador de ambientes"

# Usuário sem privilégios que executa os programas dos usuários
id proj2run >/dev/null 2>&1 || useradd --system --no-create-home --shell /usr/sbin/nologin proj2run

# Diretórios de trabalho
install -d -m 0755 -o root     -g root     /var/lib/proj2
install -d -m 0755 -o www-data -g www-data /var/lib/proj2/jobs   # jobs gerados pela web
install -d -m 0755 -o root     -g root     /var/lib/proj2/envs   # ambientes (output, estado)

# O código é de root: o www-data não pode alterar o controlador que roda via sudo
chown -R root:root "${APP_DIR}"
chmod 0755 "${APP_DIR}/runner/envctl.sh"

# www-data pode executar SOMENTE o envctl.sh como root, sem senha
cat > /etc/sudoers.d/proj2 <<EOF
# Projeto 2 — a aplicação web controla os ambientes via envctl.sh
www-data ALL=(root) NOPASSWD: ${APP_DIR}/runner/envctl.sh
EOF
chmod 0440 /etc/sudoers.d/proj2
visudo -cf /etc/sudoers.d/proj2

# ---------------------------------------------------------------------------
# 4. Apache — VirtualHost apontando para web/
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

log "Pronto! Acesse http://localhost:8080 no host (ou a porta definida em PROJ2_PORT)"
