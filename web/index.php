<?php
// Página inicial: ambientes solicitados.
require __DIR__ . '/../app/bootstrap.php';

$envs = db()->query('SELECT * FROM environments ORDER BY id DESC')->fetchAll();

page_header('Ambientes de execução');
?>
<p><a class="btn" href="new.php">+ Solicitar novo ambiente</a></p>

<?php if (!$envs): ?>
  <p class="hint">Nenhum ambiente solicitado ainda.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Nome</th><th>CPU</th><th>Memória</th><th>IO (leitura / escrita)</th><th>Status</th><th>Criado em</th></tr>
  <?php foreach ($envs as $e): ?>
  <tr>
    <td><?= (int)$e['id'] ?></td>
    <td><?= h($e['name']) ?><?php if ($e['description']): ?><br><small><?= h($e['description']) ?></small><?php endif; ?></td>
    <td><?= (int)$e['cpu_percent'] ?>%</td>
    <td><?= (int)$e['memory_mb'] ?> MB</td>
    <td><?= $e['io_read_mbps'] ? (int)$e['io_read_mbps'] . ' MB/s' : '—' ?> / <?= $e['io_write_mbps'] ? (int)$e['io_write_mbps'] . ' MB/s' : '—' ?></td>
    <td><?= status_label($e['status']) ?></td>
    <td><?= h($e['created_at']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php
page_footer();
