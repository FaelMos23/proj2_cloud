# -*- mode: ruby -*-
# vi: set ft=ruby :
#
# Uso:
#   vagrant up                      # cria e provisiona a VM
#   http://localhost:8080           # interface web (ou http://192.168.56.10)

REPO_URL    = ENV.fetch("PROJ2_REPO",   "https://github.com/Lu-CLopes/proj2_cloud.git")
REPO_BRANCH = ENV.fetch("PROJ2_BRANCH", "main")
APP_DIR     = "/opt/proj2_cloud"

Vagrant.configure("2") do |config|
  config.vm.box      = "ubuntu/jammy64"   # Ubuntu 22.04 — kernel 5.15, cgroup v2 por padrão
  config.vm.hostname = "proj2-cloud"

  # Interface web: host:8080 -> VM:80 (Apache)
  config.vm.network "forwarded_port", guest: 80, host: 8080, host_ip: "127.0.0.1"
  config.vm.network "private_network", ip: "192.168.56.10"

  # O código vem do git clone no provision.
  config.vm.synced_folder ".", "/vagrant", disabled: true

  # Requisito mínimo: 1 core e 4096 MB de memória
  config.vm.provider "virtualbox" do |vb|
    vb.name   = "proj2-cloud"
    vb.cpus   = 1
    vb.memory = 4096
  end

  # Provisionamento: instala git, clona (ou atualiza) o repositório e executa
  config.vm.provision "shell",
    name: "git-clone-e-bootstrap",
    env: { "REPO_URL" => REPO_URL, "REPO_BRANCH" => REPO_BRANCH, "APP_DIR" => APP_DIR },
    inline: <<-SHELL
      set -euo pipefail
      export DEBIAN_FRONTEND=noninteractive

      apt-get update -y
      apt-get install -y git ca-certificates

      if [ -d "$APP_DIR/.git" ]; then
        echo ">> Atualizando repositório em $APP_DIR (branch $REPO_BRANCH)"
        git -C "$APP_DIR" fetch --all --prune
        git -C "$APP_DIR" checkout "$REPO_BRANCH"
        git -C "$APP_DIR" reset --hard "origin/$REPO_BRANCH"
      else
        echo ">> Clonando $REPO_URL (branch $REPO_BRANCH) em $APP_DIR"
        git clone --branch "$REPO_BRANCH" "$REPO_URL" "$APP_DIR"
      fi

      bash "$APP_DIR/provision/bootstrap.sh"
    SHELL
end
