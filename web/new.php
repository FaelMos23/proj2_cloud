<?php
// Passo 1 — Solicitar novo ambiente de execução: nome e recursos (CPU / Memória / IO).
require __DIR__ . '/../app/bootstrap.php';

$errors = [];
$form = [
    'name' => '', 'description' => '',
    'cpu_percent' => 50, 'memory_mb' => 256,
    'io_read_mbps' => 0, 'io_write_mbps' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name']        = trim($_POST['name'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');
    foreach (['cpu_percent', 'memory_mb', 'io_read_mbps', 'io_write_mbps'] as $k) {
        $form[$k] = filter_var($_POST[$k] ?? '', FILTER_VALIDATE_INT);
    }

    if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $form['name'])) {
        $errors[] = 'Nome inválido: use 1 a 32 caracteres (letras, números, "-" ou "_").';
    }
    if (mb_strlen($form['description']) > 255) {
        $errors[] = 'Descrição deve ter no máximo 255 caracteres.';
    }
    if ($form['cpu_percent'] === false || $form['cpu_percent'] < LIMIT_CPU_MIN || $form['cpu_percent'] > LIMIT_CPU_MAX) {
        $errors[] = 'CPU deve estar entre ' . LIMIT_CPU_MIN . '% e ' . LIMIT_CPU_MAX . '%.';
    }
    if ($form['memory_mb'] === false || $form['memory_mb'] < LIMIT_MEM_MIN || $form['memory_mb'] > LIMIT_MEM_MAX) {
        $errors[] = 'Memória deve estar entre ' . LIMIT_MEM_MIN . ' e ' . LIMIT_MEM_MAX . ' MB.';
    }
    foreach (['io_read_mbps' => 'leitura', 'io_write_mbps' => 'escrita'] as $k => $label) {
        if ($form[$k] === false || $form[$k] < 0 || $form[$k] > LIMIT_IO_MAX) {
            $errors[] = "Limite de IO ($label) deve estar entre 0 e " . LIMIT_IO_MAX . ' MB/s.';
        }
    }

    if (!$errors) {
        $st = db()->prepare(
            'INSERT INTO environments (name, description, cpu_percent, memory_mb, io_read_mbps, io_write_mbps, status)
             VALUES (?, ?, ?, ?, ?, ?, \'rascunho\')'
        );
        $st->execute([
            $form['name'], $form['description'] ?: null,
            $form['cpu_percent'], $form['memory_mb'],
            $form['io_read_mbps'], $form['io_write_mbps'],
        ]);
        $id = (int)db()->lastInsertId();
        flash("Ambiente #$id solicitado. Agora defina o que será executado.");
        redirect('configure.php?id=' . $id);
    }
}

page_header('Novo ambiente — 1/2: recursos');
foreach ($errors as $e) {
    echo '<p class="flash err">' . h($e) . '</p>';
}
?>
<form method="post" class="card">
  <fieldset>
    <legend>Identificação</legend>
    <label for="name">Nome do ambiente</label>
    <input type="text" id="name" name="name" required maxlength="32" pattern="[A-Za-z0-9_\-]{1,32}" value="<?= h($form['name']) ?>">
    <small>Será usado como identificação do namespace (letras, números, "-" e "_").</small>

    <label for="description">Descrição (opcional)</label>
    <input type="text" id="description" name="description" maxlength="255" value="<?= h($form['description']) ?>">
  </fieldset>

  <fieldset>
    <legend>Recursos alocados</legend>
    <label for="cpu_percent">CPU (% de 1 core)</label>
    <input type="number" id="cpu_percent" name="cpu_percent" min="<?= LIMIT_CPU_MIN ?>" max="<?= LIMIT_CPU_MAX ?>" required value="<?= h($form['cpu_percent']) ?>">

    <label for="memory_mb">Memória máxima (MB)</label>
    <input type="number" id="memory_mb" name="memory_mb" min="<?= LIMIT_MEM_MIN ?>" max="<?= LIMIT_MEM_MAX ?>" required value="<?= h($form['memory_mb']) ?>">

    <label for="io_read_mbps">IO — leitura em disco (MB/s, 0 = sem limite)</label>
    <input type="number" id="io_read_mbps" name="io_read_mbps" min="0" max="<?= LIMIT_IO_MAX ?>" required value="<?= h($form['io_read_mbps']) ?>">

    <label for="io_write_mbps">IO — escrita em disco (MB/s, 0 = sem limite)</label>
    <input type="number" id="io_write_mbps" name="io_write_mbps" min="0" max="<?= LIMIT_IO_MAX ?>" required value="<?= h($form['io_write_mbps']) ?>">
  </fieldset>

  <button type="submit">Continuar</button>
  <a class="btn secondary" href="index.php">Cancelar</a>
</form>
<?php
page_footer();
