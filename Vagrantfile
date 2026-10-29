# -*- mode: ruby -*-
# vi: set ft=ruby :
#
# Uso (macOS, Windows ou Linux):
#   vagrant up                      # cria e provisiona a VM
#   http://localhost:8080           # interface web
#
# Hosts suportados (provider VirtualBox):
#   * Windows / Linux / Mac Intel (x86_64)  -> box amd64
#   * Mac Apple Silicon (arm64)             -> box arm64 (VirtualBox 7.1+)
# A box bento/ubuntu-22.04 é publicada para as duas arquiteturas; o Vagrant
# (2.4+) baixa automaticamente a que corresponde ao host.
#
# Variáveis opcionais (no host, antes do `vagrant up` / `vagrant provision`):
#   PROJ2_REPO   — URL do repositório (padrão: repositório do grupo)
#   PROJ2_BRANCH — branch a ser clonada (padrão: main)
#   PROJ2_IP     — IP de rede privada (ex.: 192.168.56.10); vazio = só o forward 8080
#   PROJ2_ARCH   — força a arquitetura da box: amd64 | arm64 (padrão: automático)
#   PROJ2_PORT   — porta do host para a interface web (padrão: 8080)
#
#   macOS/Linux:  PROJ2_BRANCH=feature/cgroups-namespaces vagrant up
#   PowerShell:   $env:PROJ2_BRANCH="feature/cgroups-namespaces"; vagrant up
#   cmd.exe:      set PROJ2_BRANCH=feature/cgroups-namespaces && vagrant up

# box_architecture e a escolha automática de arquitetura existem desde o Vagrant 2.4
Vagrant.require_version ">= 2.4.0"

REPO_URL    = ENV.fetch("PROJ2_REPO",   "https://github.com/FaelMos23/proj2_cloud.git")
REPO_BRANCH = ENV.fetch("PROJ2_BRANCH", "main")
PRIVATE_IP  = ENV.fetch("PROJ2_IP",     "")
BOX_ARCH    = ENV.fetch("PROJ2_ARCH",   "")
HOST_PORT   = Integer(ENV.fetch("PROJ2_PORT", "8080"))
APP_DIR     = "/opt/proj2_cloud"

Vagrant.configure("2") do |config|
  # Ubuntu 22.04 (kernel 5.15, cgroup v2 por padrão) — amd64 e arm64
  config.vm.box      = "bento/ubuntu-22.04"
  config.vm.box_architecture = BOX_ARCH.empty? ? :auto : BOX_ARCH
  config.vm.hostname = "proj2-cloud"

  # Interface web: host:8080 -> VM:80 (Apache)
  config.vm.network "forwarded_port", guest: 80, host: HOST_PORT, host_ip: "127.0.0.1"
  # Rede privada opcional: a host-only network do VirtualBox costuma falhar no
  # macOS e pode pedir permissão de administrador no Windows, então ela só é
  # criada se PROJ2_IP for definido.
  config.vm.network "private_network", ip: PRIVATE_IP unless PRIVATE_IP.empty?

  # O código NÃO é compartilhado do host: ele vem do git clone no provision.
  config.vm.synced_folder ".", "/vagrant", disabled: true

  # Requisito mínimo: 1 core e 4096 MB de memória
  config.vm.provider "virtualbox" do |vb|
    vb.name   = "proj2-cloud"
    vb.cpus   = 1
    vb.memory = 4096
    vb.gui    = false
  end

  # Provisionamento: instala git, clona o repositório e executa
  config.vm.provision "shell",
    name: "git-clone-e-bootstrap",
    binary: false,
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

  config.vm.post_up_message = "Proj2 Cloud pronto: http://localhost:#{HOST_PORT}"
end
