<?php
// Detalhes de um ambiente: configuração, status, output e ações.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/scripts_repo.php';
require __DIR__ . '/../app/env_view.php';
require __DIR__ . '/../app/runner.php';

$id  = (int)($_GET['id'] ?? 0);
$env = find_env($id);
if (!$env) {
    http_response_code(404);
    page_header('Ambiente não encontrado');
    page_footer();
    exit;
}
$env     = sync_env($env);
$running = $env['status'] === 'em_execucao';
$info    = $running ? env_info($env) : [];
$log     = isset($_GET['output']) || $env['status'] !== 'pendente' ? read_log_tail($id) : null;

function action_button(int $id, string $action, string $label, string $class = '', string $confirm = ''): string
{
    $onsubmit = $confirm ? ' onsubmit="return confirm(\'' . h($confirm) . '\')"' : '';
    return '<form method="post" action="actions.php" class="inline"' . $onsubmit . '>'
         . '<input type="hidden" name="id" value="' . $id . '">'
         . '<input type="hidden" name="action" value="' . h($action) . '">'
         . '<button type="submit" class="' . h($class) . '">' . h($label) . '</button></form> ';
}

page_header('Ambiente #' . $env['id'] . ' — ' . $env['name']);
?>
<p>
  <?php if ($running): ?>
    <?= action_button($id, 'stop', 'Terminar execução', 'danger', 'Terminar a execução deste ambiente?') ?>
  <?php elseif ($env['status'] !== 'rascunho'): ?>
    <?= action_button($id, 'start', $env['status'] === 'pendente' ? 'Iniciar' : 'Executar novamente') ?>
  <?php endif; ?>
  <?php if (!$running): ?>
    <a class="btn secondary" href="configure.php?id=<?= $id ?>"><?= $env['status'] === 'rascunho' ? 'Configurar execução' : 'Editar configuração' ?></a>
  <?php endif; ?>
  <?= action_button($id, 'remove', 'Remover ambiente', 'secondary', 'Remover o ambiente? A execução será terminada e o output apagado.') ?>
  <a class="btn secondary" href="env.php?id=<?= $id ?>">Atualizar</a>
</p>

<div class="card">
  <dl class="kv">
    <dt>Status</dt>        <dd><?= status_label($env['status']) ?><?php if ($env['status_msg']): ?> <small><?= h($env['status_msg']) ?></small><?php endif; ?></dd>
    <dt>PID (host)</dt>    <dd><?= $env['pid'] ? (int)$env['pid'] : '—' ?></dd>
    <dt>Código de saída</dt><dd><?= $env['exit_code'] !== null ? (int)$env['exit_code'] : '—' ?></dd>
    <dt>Iniciado em</dt>   <dd><?= h($env['started_at'] ?? '—') ?></dd>
    <dt>Finalizado em</dt> <dd><?= h($env['finished_at'] ?? '—') ?></dd>
    <dt>Descrição</dt>     <dd><?= h($env['description'] ?: '—') ?></dd>
    <dt>CPU</dt>           <dd><?= (int)$env['cpu_percent'] ?>% de 1 core</dd>
    <dt>Memória</dt>       <dd><?= (int)$env['memory_mb'] ?> MB</dd>
    <dt>IO leitura</dt>    <dd><?= $env['io_read_mbps'] ? (int)$env['io_read_mbps'] . ' MB/s' : 'sem limite' ?></dd>
    <dt>IO escrita</dt>    <dd><?= $env['io_write_mbps'] ? (int)$env['io_write_mbps'] . ' MB/s' : 'sem limite' ?></dd>
    <dt>Execução</dt>      <dd><?= h(describe_execution($env)) ?></dd>
    <dt>Tipo</dt>          <dd><?= h(run_kind_label($env['run_kind'])) ?></dd>
    <dt>Rede isolada</dt>  <dd><?= $env['isolate_net'] ? 'sim' : 'não' ?></dd>
    <dt>Criado em</dt>     <dd><?= h($env['created_at']) ?></dd>
  </dl>
</div>

<?php if ($info): ?>
  <h2>Uso de recursos</h2>
  <pre class="log"><?= h(implode("\n", $info)) ?></pre>
<?php endif; ?>

<?php if ($env['exec_mode'] === 'programas'): ?>
  <h2>Programas</h2>
  <pre class="log"><?= h($env['programs']) ?></pre>
<?php endif; ?>

<h2>Output</h2>
<?php if ($log === null): ?>
  <p class="hint">Ainda não há output — o ambiente não foi executado.</p>
<?php else: ?>
  <p class="hint">
    stdout e stderr são gravados em <code><?= h(log_path($id)) ?></code>.
    <a href="log.php?id=<?= $id ?>" target="_blank">Ver completo</a> ·
    <a href="log.php?id=<?= $id ?>&amp;download=1">Baixar</a>
  </p>
  <pre class="log"><?= h($log !== '' ? $log : '(vazio)') ?></pre>
<?php endif; ?>

<p><a class="btn secondary" href="index.php">Voltar</a></p>
<?php
page_footer();
