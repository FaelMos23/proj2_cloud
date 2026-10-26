# proj2_cloud — Ambientes de execução isolados via web

Projeto 2 de **Computação em Nuvem** (Escola Politécnica — PUC Campinas, 2026).

Ambiente web para execução de programas em que o usuário cria ambientes
isolados (namespaces), define os recursos (CPU / Memória / IO) via cgroups,
escolhe o que será executado, acompanha o output e gerencia o ciclo de vida.

## Arquitetura

| Componente            | Tecnologia                         |
|-----------------------|------------------------------------|
| Virtualização         | Vagrant + VirtualBox (Ubuntu 22.04)|
| Web server            | Apache 2 + PHP                     |
| Armazenamento         | MySQL 8                            |
| Versionamento         | GitHub (este repositório)          |

```
host ──(vagrant up)──► VM proj2-cloud (1 core / 4096 MB)
                         ├─ provision: git clone deste repositório em /opt/proj2_cloud
                         ├─ Apache  → /opt/proj2_cloud/web   (www-data)
                         ├─ MySQL   → banco proj2 (db/schema.sql)
                         └─ sudo runner/envctl.sh  → cria/para/remove ambientes
                                └─ programa do usuário (proj2run), output em
                                   /var/lib/proj2/envs/<id>/output.log
```

## Como subir o ambiente

Pré-requisitos no host: [Vagrant](https://www.vagrantup.com/) e VirtualBox.

```bash
git clone https://github.com/Lu-CLopes/proj2_cloud.git
cd proj2_cloud
vagrant up
```

Acesse **http://localhost:8080** (ou http://192.168.56.10).

Todo o provisionamento — inclusive o `git clone` da aplicação — acontece no
`provision` do `Vagrantfile`. Para reaplicar após novos commits:

```bash
vagrant provision
```

## Estrutura

```
Vagrantfile            VM + provision (git clone + bootstrap)
provision/bootstrap.sh instalação de Apache, PHP e MySQL
db/schema.sql          esquema do banco
runner/envctl.sh       controlador dos ambientes (executado como root via sudo)
app/                   código PHP compartilhado (fora do DocumentRoot)
web/                   páginas públicas (DocumentRoot do Apache)
```

## Funcionalidades

- [x] Solicitar novo ambiente: nome e recursos (CPU / Memória / IO)
- [x] Perguntas de acompanhamento sobre o ambiente:
  - o que será executado: um comando, vários programas (sequência ou paralelo) ou um script
  - scripts enviados via formulário (upload ou colar) ficam armazenados no MySQL para reuso
  - tarefa que termina ou serviço contínuo
  - isolamento de rede (namespace de rede)
- [x] Biblioteca de scripts (`web/scripts.php`)
- [x] Output direcionado a arquivo e visualizado quando desejado (`env.php`, `log.php`)
- [x] Listar ambientes e verificar status: em execução / terminado / erro
- [x] Vários ambientes gerenciados simultaneamente
- [x] Terminar a execução, executar novamente e remover ambientes

## Contrato com o controlador (`runner/envctl.sh`)

A aplicação web nunca cria processos diretamente: ela gera o job em
`/var/lib/proj2/jobs/<id>/run.sh` e chama o controlador via `sudo`:

| Comando                                               | Saída                                   |
|-------------------------------------------------------|-----------------------------------------|
| `envctl.sh start <id> <cpu%> <mem_mb> <io_r> <io_w> <net>` | `started <pid>`                    |
| `envctl.sh status <id>`                               | `running <pid>` · `exited <rc> [oom]` · `stopped <rc>` · `lost` · `unknown` |
| `envctl.sh stop <id>`                                 | `stopped`                               |
| `envctl.sh remove <id>`                               | `removed`                               |
| `envctl.sh info <id>`                                 | linhas `chave=valor`                    |

> Na branch `main`, o `envctl.sh` é um **stub**: executa o programa como o
> usuário `proj2run`, sem cgroups e sem namespaces. A implementação real de
> isolamento fica na branch `feature/cgroups-namespaces`.
