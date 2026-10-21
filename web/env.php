<?php
// Detalhes de um ambiente.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/scripts_repo.php';
require __DIR__ . '/../app/env_view.php';

$id  = (int)($_GET['id'] ?? 0);
$env = find_env($id);
if (!$env) {
    http_response_code(404);
    page_header('Ambiente não encontrado');
    page_footer();
    exit;
}

page_header('Ambiente #' . $env['id'] . ' — ' . $env['name']);
?>
<div class="card">
  <dl class="kv">
    <dt>Status</dt>        <dd><?= status_label($env['status']) ?></dd>
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

<?php if ($env['exec_mode'] === 'programas'): ?>
  <h2>Programas</h2>
  <pre class="log"><?= h($env['programs']) ?></pre>
<?php endif; ?>

<p>
  <?php if (in_array($env['status'], ['rascunho', 'pendente'], true)): ?>
    <a class="btn" href="configure.php?id=<?= (int)$env['id'] ?>"><?= $env['status'] === 'rascunho' ? 'Configurar execução' : 'Editar configuração' ?></a>
  <?php endif; ?>
  <a class="btn secondary" href="index.php">Voltar</a>
</p>

<?php if ($env['status'] === 'pendente'): ?>
  <p class="hint">Ambiente configurado e aguardando execução.</p>
<?php endif; ?>
<?php
page_footer();
