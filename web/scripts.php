<?php
// Biblioteca de scripts: enviar, visualizar e remover scripts armazenados.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/scripts_repo.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'upload') {
            [$content, $orig] = read_script_input('script_file', 'script_text');
            $name = trim($_POST['name'] ?? '');
            if ($name === '' && $orig) {
                $name = $orig;
            }
            save_script($name, $_POST['interpreter'] ?? '', $content, $orig);
            flash("Script \"$name\" armazenado.");
        } elseif ($action === 'delete') {
            $st = db()->prepare('DELETE FROM scripts WHERE id = ?');
            $st->execute([(int)($_POST['id'] ?? 0)]);
            flash('Script removido.');
        }
    } catch (InvalidArgumentException $e) {
        flash($e->getMessage(), 'err');
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }
        flash('Script em uso por algum ambiente — remova o ambiente antes.', 'err');
    }
    redirect('scripts.php');
}

// Visualizar um script
if (isset($_GET['view'])) {
    $s = find_script((int)$_GET['view']);
    page_header($s ? 'Script: ' . $s['name'] : 'Script não encontrado');
    if ($s) {
        echo '<p class="hint">Interpretador: ' . h($s['interpreter']) . ' · enviado em ' . h($s['created_at']) . '</p>';
        echo '<pre class="log">' . h($s['content']) . '</pre>';
    }
    echo '<p><a class="btn secondary" href="scripts.php">Voltar</a></p>';
    page_footer();
    exit;
}

$scripts = list_scripts();
page_header('Scripts armazenados');
?>
<?php if (!$scripts): ?>
  <p class="hint">Nenhum script armazenado.</p>
<?php else: ?>
<table>
  <tr><th>Nome</th><th>Interpretador</th><th>Tamanho</th><th>Enviado em</th><th></th></tr>
  <?php foreach ($scripts as $s): ?>
  <tr>
    <td><a href="scripts.php?view=<?= (int)$s['id'] ?>"><?= h($s['name']) ?></a></td>
    <td><?= h($s['interpreter']) ?></td>
    <td><?= number_format((int)$s['size'] / 1024, 1, ',', '.') ?> KB</td>
    <td><?= h($s['created_at']) ?></td>
    <td>
      <form method="post" class="inline" onsubmit="return confirm('Remover este script?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <button class="secondary" type="submit">Remover</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Enviar novo script</h2>
<form method="post" enctype="multipart/form-data" class="card">
  <input type="hidden" name="action" value="upload">
  <label for="name">Nome (vazio = nome do arquivo)</label>
  <input type="text" id="name" name="name" maxlength="64">
  <label for="interpreter">Interpretador</label>
  <select id="interpreter" name="interpreter">
    <?php foreach (SCRIPT_INTERPRETERS as $k => $label): ?>
      <option value="<?= h($k) ?>"><?= h($label) ?></option>
    <?php endforeach; ?>
  </select>
  <label for="script_file">Arquivo</label>
  <input type="file" id="script_file" name="script_file">
  <label for="script_text">…ou cole o conteúdo</label>
  <textarea id="script_text" name="script_text"></textarea>
  <p><button type="submit">Armazenar</button></p>
</form>
<?php
page_footer();
