#!/usr/bin/env bash
#
# envctl.sh — controlador dos ambientes de execução (executado como root via sudo).
#
# ============================================================================
#  VERSÃO STUB — executa o programa SEM cgroups e SEM namespaces.
#  Serve para a interface web funcionar de ponta a ponta enquanto a parte de
#  isolamento é desenvolvida. A implementação real (cgroups v2 + namespaces)
#  fica na branch feature/cgroups-namespaces e deve respeitar o mesmo contrato.
# ============================================================================
#
# Contrato usado pela aplicação web (app/runner.php):
#
#   envctl.sh start  <id> <cpu%> <mem_mb> <io_r_mbps> <io_w_mbps> <net 0|1>
#         -> stdout: "started <pid>"           rc=0 em sucesso
#   envctl.sh status <id>
#         -> stdout: uma das linhas
#              running <pid>
#              exited <rc> [oom]
#              stopped <rc>
#              lost                            (sem processo e sem código de saída)
#              unknown                         (ambiente nunca iniciado)
#   envctl.sh stop   <id>      -> "stopped"
#   envctl.sh remove <id>      -> "removed"   (para, apaga cgroup e arquivos)
#   envctl.sh info   <id>      -> linhas "chave=valor" com métricas do ambiente
#
# Arquivos:
#   /var/lib/proj2/jobs/<id>/run.sh    gerado pela aplicação web (www-data)
#   /var/lib/proj2/envs/<id>/          diretório do ambiente (root)
#        job/        cópia somente-leitura do job
#        work/       diretório de trabalho do programa (usuário proj2run)
#        output.log  stdout + stderr do programa
#        exit_code, supervisor.pid, main.pid, stopped, params
#
set -uo pipefail

BASE=/var/lib/proj2
JOBS=$BASE/jobs
ENVS=$BASE/envs
RUN_USER=proj2run
SAFE_PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
SELF=$(readlink -f "$0")

umask 022

die()  { echo "erro: $*" >&2; exit 1; }
uint() { [[ "${1:-}" =~ ^[0-9]+$ ]] || die "valor numérico inválido: '${1:-}'"; }

envdir() { echo "$ENVS/$1"; }

pid_alive() { [ -n "${1:-}" ] && kill -0 "$1" 2>/dev/null; }

supervisor_alive() {
    local d; d=$(envdir "$1")
    [ -f "$d/supervisor.pid" ] || return 1
    local p; p=$(cat "$d/supervisor.pid")
    pid_alive "$p" && grep -qa "_supervise" "/proc/$p/cmdline" 2>/dev/null
}

# ---------------------------------------------------------------------------
# start
# ---------------------------------------------------------------------------
cmd_start() {
    local id=$1 cpu=$2 mem=$3 rbps=$4 wbps=$5 net=$6
    for v in "$id" "$cpu" "$mem" "$rbps" "$wbps" "$net"; do uint "$v"; done
    [ -f "$JOBS/$id/run.sh" ] || die "job $id não encontrado em $JOBS/$id"
    supervisor_alive "$id" && die "ambiente $id já está em execução"

    local d; d=$(envdir "$id")
    rm -rf "$d"
    mkdir -p "$d/job" "$d/work"
    cp -a "$JOBS/$id/." "$d/job/"
    chown -R root:root "$d/job" && chmod -R a+rX,go-w "$d/job"
    chown "$RUN_USER:$RUN_USER" "$d/work"
    : > "$d/output.log"
    printf 'cpu=%s\nmem=%s\nrbps=%s\nwbps=%s\nnet=%s\n' "$cpu" "$mem" "$rbps" "$wbps" "$net" > "$d/params"

    # Processo supervisor desacoplado (nova sessão) para não prender o PHP.
    setsid -f "$SELF" _supervise "$id" </dev/null >/dev/null 2>&1

    for _ in $(seq 50); do
        [ -s "$d/main.pid" ] && break
        sleep 0.1
    done
    [ -s "$d/main.pid" ] || die "o supervisor não iniciou"
    echo "started $(cat "$d/main.pid")"
}

# Supervisor: inicia o programa, espera terminar e grava o código de saída.
cmd_supervise() {
    local id=$1 d; d=$(envdir "$id")
    echo $$ > "$d/supervisor.pid"
    echo "[envctl] $(date '+%F %T') ambiente $id iniciado (STUB: sem cgroups/namespaces)" >> "$d/output.log"

    (
        cd "$d/work" || exit 126
        # setsid: o programa vira líder do próprio grupo, para o stop matar o grupo todo
        exec setsid setpriv --reuid="$RUN_USER" --regid="$RUN_USER" --init-groups --no-new-privs \
            env -i PATH="$SAFE_PATH" HOME="$d/work" JOB_DIR="$d/job" ENV_ID="$id" \
            /bin/bash "$d/job/run.sh"
    ) >> "$d/output.log" 2>&1 &
    local child=$!
    echo "$child" > "$d/main.pid"

    wait "$child"
    local rc=$?
    echo "$rc" > "$d/exit_code"
    echo "[envctl] $(date '+%F %T') processo terminou com código $rc" >> "$d/output.log"
}

# ---------------------------------------------------------------------------
# status / info
# ---------------------------------------------------------------------------
cmd_status() {
    local id=$1 d; d=$(envdir "$id")
    [ -d "$d" ] || { echo unknown; return; }
    if [ -f "$d/exit_code" ]; then
        local rc; rc=$(cat "$d/exit_code")
        if [ -f "$d/stopped" ]; then echo "stopped $rc"; else echo "exited $rc"; fi
    elif supervisor_alive "$id"; then
        echo "running $(cat "$d/main.pid" 2>/dev/null)"
    else
        echo lost
    fi
}

cmd_info() {
    local id=$1
    echo "modo=stub (sem cgroups/namespaces)"
    echo "status=$(cmd_status "$id")"
}

# ---------------------------------------------------------------------------
# stop / remove
# ---------------------------------------------------------------------------
cmd_stop() {
    local id=$1 d; d=$(envdir "$id")
    if supervisor_alive "$id"; then
        touch "$d/stopped"
        local pg; pg=$(cat "$d/main.pid")
        kill -TERM -- "-$pg" 2>/dev/null
        for _ in $(seq 30); do pid_alive "$pg" || break; sleep 0.1; done
        kill -KILL -- "-$pg" 2>/dev/null
        for _ in $(seq 50); do [ -f "$d/exit_code" ] && break; sleep 0.1; done
    fi
    echo stopped
}

cmd_remove() {
    local id=$1
    cmd_stop "$id" >/dev/null
    rm -rf "$(envdir "$id")" "${JOBS:?}/$id"
    echo removed
}

# ---------------------------------------------------------------------------
[ "$(id -u)" -eq 0 ] || die "execute como root (sudo)"
action=${1:-}; shift || true
case "$action" in
    start)      [ $# -eq 6 ] || die "uso: $0 start <id> <cpu%> <mem_mb> <io_r> <io_w> <net>"; cmd_start "$@" ;;
    _supervise) uint "${1:-}"; cmd_supervise "$1" ;;
    status|info|stop|remove)
                [ $# -eq 1 ] || die "uso: $0 $action <id>"; uint "$1"; "cmd_$action" "$1" ;;
    *)          die "uso: $0 {start|status|stop|remove|info} ..." ;;
esac
