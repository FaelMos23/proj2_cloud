<?php
// Ponte entre a aplicação web e o controlador de ambientes (runner/envctl.sh).
//
// A aplicação (usuário www-data) gera o job em /var/lib/proj2/jobs/<id>/ e
// chama envctl.sh via sudo (regra em /etc/sudoers.d/proj2). Quem cria cgroups e
// namespaces é o envctl.sh — esta camada só conversa com ele pelo contrato
// documentado no topo daquele arquivo.

define('ENVCTL',   getenv('PROJ2_ENVCTL') ?: '/opt/proj2_cloud/runner/envctl.sh');
define('JOBS_DIR', '/var/lib/proj2/jobs');
define('ENVS_DIR', '/var/lib/proj2/envs');

/** Executa o envctl.sh. Retorna [código de saída, linhas de output]. */
function envctl(string ...$args): array
{
    $cmd = 'sudo -n ' . escapeshellarg(ENVCTL);
    foreach ($args as $a) {
        $cmd .= ' ' . escapeshellarg($a);
    }
    $out = [];
    exec($cmd . ' 2>&1', $out, $rc);
    return [$rc, $out];
}

/** Gera /var/lib/proj2/jobs/<id>/run.sh (e payload, para scripts). */
function build_job(array $env): void
{
    $dir = JOBS_DIR . '/' . (int)$env['id'];
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException("Não foi possível criar $dir");
    }
    @unlink("$dir/payload");

    $sh = "#!/bin/bash\n# Gerado pela aplicação web — ambiente #{$env['id']} ({$env['name']})\n";

    switch ($env['exec_mode']) {
        case 'comando':
            $sh .= $env['command'] . "\n";
            break;

        case 'programas':
            $lines = explode("\n", (string)$env['programs']);
            if ($env['programs_parallel']) {
                $sh .= "pids=()\n";
                foreach ($lines as $l) {
                    $sh .= 'echo ' . escapeshellarg("[proj2] iniciando: $l") . "\n";
                    $sh .= "( $l ) &\npids+=(\$!)\n";
                }
                $sh .= "rc=0\nfor p in \"\${pids[@]}\"; do wait \"\$p\" || rc=\$?; done\nexit \$rc\n";
            } else {
                $sh .= "set -e\n";
                foreach ($lines as $l) {
                    $sh .= 'echo ' . escapeshellarg("[proj2] executando: $l") . "\n$l\n";
                }
            }
            break;

        case 'script':
            $s = find_script((int)$env['script_id']);
            if (!$s) {
                throw new RuntimeException('Script do ambiente não encontrado.');
            }
            file_put_contents("$dir/payload", $s['content']);
            chmod("$dir/payload", 0644);
            $sh .= 'exec ' . escapeshellarg($s['interpreter']) . " \"\$JOB_DIR/payload\"\n";
            break;

        default:
            throw new RuntimeException('Ambiente sem configuração de execução.');
    }

    file_put_contents("$dir/run.sh", $sh);
    chmod("$dir/run.sh", 0755);
}

/** Inicia (ou reinicia) o ambiente. Retorna [ok, mensagem]. */
function start_env(array $env): array
{
    try {
        build_job($env);
    } catch (RuntimeException $e) {
        set_env_state((int)$env['id'], 'erro', $e->getMessage());
        return [false, $e->getMessage()];
    }

    [$rc, $out] = envctl(
        'start', (string)$env['id'],
        (string)$env['cpu_percent'], (string)$env['memory_mb'],
        (string)$env['io_read_mbps'], (string)$env['io_write_mbps'],
        $env['isolate_net'] ? '1' : '0'
    );
    $line = trim(implode(' ', $out));

    if ($rc === 0 && preg_match('/^started (\d+)/', $line, $m)) {
        $st = db()->prepare("UPDATE environments
            SET status = 'em_execucao', status_msg = NULL, pid = ?, exit_code = NULL,
                started_at = NOW(), finished_at = NULL
            WHERE id = ?");
        $st->execute([(int)$m[1], $env['id']]);
        return [true, 'Ambiente iniciado (PID ' . $m[1] . ').'];
    }

    $msg = 'Falha ao iniciar: ' . ($line ?: "envctl retornou $rc");
    set_env_state((int)$env['id'], 'erro', $msg);
    return [false, $msg];
}

function set_env_state(int $id, string $status, ?string $msg): void
{
    $st = db()->prepare('UPDATE environments SET status = ?, status_msg = ? WHERE id = ?');
    $st->execute([$status, mb_substr((string)$msg, 0, 255) ?: null, $id]);
}

/**
 * Consulta o envctl.sh e atualiza o status do ambiente no banco.
 * Só ambientes "em_execucao" precisam de sincronização.
 */
function sync_env(array $env): array
{
    if ($env['status'] !== 'em_execucao') {
        return $env;
    }
    [$rc, $out] = envctl('status', (string)$env['id']);
    $parts = preg_split('/\s+/', trim($out[0] ?? ''));
    $state = $parts[0] ?? '';
    $code  = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;

    $upd = null;
    switch ($state) {
        case 'running':
            if ($code !== null && $code !== (int)$env['pid']) {
                db()->prepare('UPDATE environments SET pid = ? WHERE id = ?')->execute([$code, $env['id']]);
            }
            break;
        case 'exited':
            $oom = in_array('oom', $parts, true);
            $upd = [
                $code === 0 ? 'terminado' : 'erro',
                $oom ? 'Encerrado: limite de memória excedido (OOM)'
                     : ($code === 0 ? 'Finalizado com sucesso' : "Finalizado com código $code"),
                $code,
            ];
            break;
        case 'stopped':
            $upd = ['terminado', 'Parado pelo usuário', $code];
            break;
        case 'lost':
            $upd = ['erro', 'Processo não encontrado (VM reiniciada?)', null];
            break;
        case 'unknown':
            $upd = ['erro', 'Ambiente não encontrado pelo controlador', null];
            break;
        default:
            // Falha ao consultar (ex.: sudo não configurado) — mantém o status.
            return $env;
    }

    if ($upd) {
        $st = db()->prepare('UPDATE environments
            SET status = ?, status_msg = ?, exit_code = ?, finished_at = COALESCE(finished_at, NOW())
            WHERE id = ?');
        $st->execute([$upd[0], $upd[1], $upd[2], $env['id']]);
    }
    return find_env((int)$env['id']);
}

function stop_env(array $env): array
{
    [$rc, $out] = envctl('stop', (string)$env['id']);
    sync_env($env);
    return [$rc === 0, $rc === 0 ? 'Execução terminada.' : 'Falha ao parar: ' . implode(' ', $out)];
}

function remove_env(array $env): array
{
    [$rc, $out] = envctl('remove', (string)$env['id']);
    if ($rc !== 0) {
        return [false, 'Falha ao remover: ' . implode(' ', $out)];
    }
    db()->prepare('DELETE FROM environments WHERE id = ?')->execute([$env['id']]);
    return [true, 'Ambiente #' . $env['id'] . ' removido.'];
}

function env_info(array $env): array
{
    [$rc, $out] = envctl('info', (string)$env['id']);
    return $rc === 0 ? $out : [];
}

function log_path(int $id): string
{
    return ENVS_DIR . "/$id/output.log";
}

/** Últimos $maxBytes do output do ambiente (ou null se não houver arquivo). */
function read_log_tail(int $id, int $maxBytes = 65536): ?string
{
    $f = log_path($id);
    if (!is_readable($f)) {
        return null;
    }
    $size = filesize($f);
    $fh = fopen($f, 'rb');
    if ($size > $maxBytes) {
        fseek($fh, -$maxBytes, SEEK_END);
    }
    $data = stream_get_contents($fh);
    fclose($fh);
    return ($size > $maxBytes ? "… (exibindo os últimos " . intdiv($maxBytes, 1024) . " KB)\n" : '') . $data;
}
