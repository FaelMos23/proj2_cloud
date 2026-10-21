<?php
// Descrição legível da configuração de execução de um ambiente.

function describe_execution(array $env): string
{
    switch ($env['exec_mode']) {
        case 'comando':
            return 'Comando: ' . $env['command'];
        case 'programas':
            $n = count(explode("\n", (string)$env['programs']));
            return $n . ' programa(s) em ' . ($env['programs_parallel'] ? 'paralelo' : 'sequência');
        case 'script':
            $s = $env['script_id'] ? find_script((int)$env['script_id']) : null;
            return 'Script: ' . ($s ? $s['name'] . ' (' . $s['interpreter'] . ')' : '?');
        default:
            return '— não configurado —';
    }
}

function run_kind_label(?string $kind): string
{
    return ['tarefa' => 'Tarefa', 'servico' => 'Serviço contínuo'][$kind] ?? '—';
}
