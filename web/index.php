<?php
// Página inicial: lista os ambientes e o status de cada um (sincronizado com o controlador).
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/scripts_repo.php';
require __DIR__ . '/../app/env_view.php';
require __DIR__ . '/../app/runner.php';

$envs = array_map('sync_env', db()->query('SELECT * FROM environments ORDER BY id DESC')->fetchAll());

$counts = array_count_values(array_column($envs, 'status'));

page_header('Ambientes de execução');
?>
<p>
  <a class="btn" href="new.php">+ Solicitar novo ambiente</a>
  <a class="btn secondary" href="index.php">Atualizar</a>
  <span class="hint">
    <?= count($envs) ?> ambiente(s) ·
    <?= $counts['em_execucao'] ?? 0 ?> em execução ·
    <?= $counts['terminado'] ?? 0 ?> terminado(s) ·
    <?= $counts['erro'] ?? 0 ?> com erro
  </span>
</p>

<?php if (!$envs): ?>
  <p class="hint">Nenhum ambiente solicitado ainda.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Nome</th><th>Recursos</th><th>Execução</th><th>Status</th><th>Ações</th></tr>
  <?php foreach ($envs as $e): $id = (int)$e['id']; ?>
  <tr>
    <td><?= $id ?></td>
    <td><a href="env.php?id=<?= $id ?>"><?= h($e['name']) ?></a><?php if ($e['description']): ?><br><small><?= h($e['description']) ?></small><?php endif; ?></td>
    <td>
      CPU <?= (int)$e['cpu_percent'] ?>% · <?= (int)$e['memory_mb'] ?> MB<br>
      <small>IO <?= $e['io_read_mbps'] ?: '∞' ?> / <?= $e['io_write_mbps'] ?: '∞' ?> MB/s</small>
    </td>
    <td><?= h(describe_execution($e)) ?><br><small><?= h(run_kind_label($e['run_kind'])) ?><?= $e['isolate_net'] ? ' · rede isolada' : '' ?></small></td>
    <td><?= status_label($e['status']) ?><?php if ($e['status_msg']): ?><br><small><?= h($e['status_msg']) ?></small><?php endif; ?></td>
    <td>
      <a href="env.php?id=<?= $id ?>">Output</a>
      <?php if ($e['status'] === 'rascunho'): ?>
        · <a href="configure.php?id=<?= $id ?>">Configurar</a>
      <?php else: ?>
      <form method="post" action="actions.php" class="inline">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="back" value="index">
        <?php if ($e['status'] === 'em_execucao'): ?>
          <button type="submit" name="action" value="stop" class="secondary">Terminar</button>
        <?php else: ?>
          <button type="submit" name="action" value="start" class="secondary">Executar</button>
        <?php endif; ?>
      </form>
      <?php endif; ?>
      <form method="post" action="actions.php" class="inline" onsubmit="return confirm('Remover o ambiente #<?= $id ?>?')">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="back" value="index">
        <button type="submit" name="action" value="remove" class="secondary">Remover</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php
page_footer();
