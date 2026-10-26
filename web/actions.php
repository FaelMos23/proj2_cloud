<?php
// Ações sobre um ambiente (POST): iniciar/reexecutar, terminar, remover.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/scripts_repo.php';
require __DIR__ . '/../app/runner.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$id  = (int)($_POST['id'] ?? 0);
$env = find_env($id);
if (!$env) {
    flash("Ambiente #$id não encontrado.", 'err');
    redirect('index.php');
}
$env = sync_env($env);
$back = ($_POST['back'] ?? '') === 'index' ? 'index.php' : 'env.php?id=' . $id;

switch ($_POST['action'] ?? '') {
    case 'start':
        if ($env['status'] === 'em_execucao') {
            flash('O ambiente já está em execução.', 'err');
        } elseif ($env['status'] === 'rascunho') {
            flash('Configure a execução antes de iniciar.', 'err');
        } else {
            [$ok, $msg] = start_env($env);
            flash($msg, $ok ? 'ok' : 'err');
        }
        break;

    case 'stop':
        [$ok, $msg] = stop_env($env);
        flash($msg, $ok ? 'ok' : 'err');
        break;

    case 'remove':
        [$ok, $msg] = remove_env($env);
        flash($msg, $ok ? 'ok' : 'err');
        if ($ok) {
            $back = 'index.php';
        }
        break;

    default:
        flash('Ação inválida.', 'err');
}
redirect($back);
