<?php
// Passo 2 — Perguntas de acompanhamento sobre o ambiente:
//   * O que será executado? (comando / vários programas / script)
//   * Scripts: escolher um já armazenado ou enviar um novo (fica salvo para reuso)
//   * É uma tarefa que termina ou um serviço contínuo?
//   * O ambiente deve ter a rede isolada?
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/scripts_repo.php';
require __DIR__ . '/../app/runner.php';

$id  = (int)($_GET['id'] ?? 0);
$env = find_env($id);
if (!$env) {
    http_response_code(404);
    page_header('Ambiente não encontrado');
    page_footer();
    exit;
}
$env = sync_env($env);
if ($env['status'] === 'em_execucao') {
    flash('Termine a execução antes de alterar a configuração.', 'err');
    redirect('env.php?id=' . $id);
}

$errors = [];
$form = [
    'exec_mode'         => $env['exec_mode'] ?? 'comando',
    'command'           => $env['command'] ?? '',
    'programs'          => $env['programs'] ?? '',
    'programs_parallel' => (int)$env['programs_parallel'],
    'script_source'     => 'existente',
    'script_id'         => (int)($env['script_id'] ?? 0),
    'new_script_name'   => '',
    'new_script_interp' => 'bash',
    'script_text'       => '',
    'run_kind'          => $env['run_kind'] ?? 'tarefa',
    'isolate_net'       => (int)$env['isolate_net'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['exec_mode']         = $_POST['exec_mode'] ?? '';
    $form['command']           = trim($_POST['command'] ?? '');
    $form['programs']          = trim(str_replace("\r\n", "\n", $_POST['programs'] ?? ''));
    $form['programs_parallel'] = ($_POST['programs_parallel'] ?? '0') === '1' ? 1 : 0;
    $form['script_source']     = ($_POST['script_source'] ?? '') === 'novo' ? 'novo' : 'existente';
    $form['script_id']         = (int)($_POST['script_id'] ?? 0);
    $form['new_script_name']   = trim($_POST['new_script_name'] ?? '');
    $form['new_script_interp'] = $_POST['new_script_interp'] ?? 'bash';
    $form['script_text']       = $_POST['script_text'] ?? '';
    $form['run_kind']          = ($_POST['run_kind'] ?? '') === 'servico' ? 'servico' : 'tarefa';
    $form['isolate_net']       = isset($_POST['isolate_net']) ? 1 : 0;

    $command = $programs = $scriptId = null;

    switch ($form['exec_mode']) {
        case 'comando':
            if ($form['command'] === '' || strlen($form['command']) > 4000) {
                $errors[] = 'Informe o comando/programa a executar (até 4000 caracteres).';
            }
            $command = $form['command'];
            break;

        case 'programas':
            $lines = array_values(array_filter(array_map('trim', explode("\n", $form['programs'])), 'strlen'));
            if (count($lines) < 1 || count($lines) > 20) {
                $errors[] = 'Informe de 1 a 20 programas, um por linha.';
            }
            $programs = implode("\n", $lines);
            break;

        case 'script':
            if ($form['script_source'] === 'existente') {
                if (!find_script($form['script_id'])) {
                    $errors[] = 'Selecione um script armazenado.';
                }
                $scriptId = $form['script_id'];
            } else {
                try {
                    [$content, $orig] = read_script_input('script_file', 'script_text');
                    $scriptId = save_script($form['new_script_name'], $form['new_script_interp'], $content, $orig);
                    $form['script_source'] = 'existente';   // se outro campo falhar, o script já está salvo
                    $form['script_id'] = $scriptId;
                    flash('Script "' . $form['new_script_name'] . '" salvo na biblioteca para reuso.');
                } catch (InvalidArgumentException $e) {
                    $errors[] = $e->getMessage();
                }
            }
            break;

        default:
            $errors[] = 'Escolha o que será executado.';
    }

    if (!$errors) {
        $st = db()->prepare(
            'UPDATE environments
                SET exec_mode = ?, command = ?, programs = ?, programs_parallel = ?, script_id = ?,
                    run_kind = ?, isolate_net = ?, status = \'pendente\'
              WHERE id = ?'
        );
        $st->execute([
            $form['exec_mode'], $command, $programs, $form['programs_parallel'], $scriptId,
            $form['run_kind'], $form['isolate_net'], $id,
        ]);
        flash("Ambiente #$id configurado.");

        if (($_POST['then'] ?? 'run') === 'run') {
            [$ok, $msg] = start_env(find_env($id));
            flash($msg, $ok ? 'ok' : 'err');
        }
        redirect('env.php?id=' . $id);
    }
}

$scripts = list_scripts();
$checked = fn(bool $c) => $c ? 'checked' : '';

page_header('Novo ambiente — 2/2: execução');
foreach ($errors as $e) {
    echo '<p class="flash err">' . h($e) . '</p>';
}
?>
<p class="hint">
  Ambiente <strong>#<?= (int)$env['id'] ?> <?= h($env['name']) ?></strong> —
  CPU <?= (int)$env['cpu_percent'] ?>% · <?= (int)$env['memory_mb'] ?> MB ·
  IO <?= $env['io_read_mbps'] ?: '∞' ?>/<?= $env['io_write_mbps'] ?: '∞' ?> MB/s
</p>

<form method="post" enctype="multipart/form-data" class="card">
  <fieldset>
    <legend>1. O que será executado?</legend>
    <label class="inline"><input type="radio" name="exec_mode" value="comando" <?= $checked($form['exec_mode'] === 'comando') ?>> Um programa / comando</label>
    <label class="inline"><input type="radio" name="exec_mode" value="programas" <?= $checked($form['exec_mode'] === 'programas') ?>> Vários programas</label>
    <label class="inline"><input type="radio" name="exec_mode" value="script" <?= $checked($form['exec_mode'] === 'script') ?>> Um script</label>

    <div data-mode="comando">
      <label for="command">Comando</label>
      <input type="text" id="command" name="command" value="<?= h($form['command']) ?>" placeholder="ex.: python3 -m http.server 8000">
    </div>

    <div data-mode="programas">
      <label for="programs">Programas (um por linha)</label>
      <textarea id="programs" name="programs" placeholder="ex.:&#10;uname -a&#10;sleep 5&#10;echo fim"><?= h($form['programs']) ?></textarea>
      <label>Como executar?</label>
      <label class="inline"><input type="radio" name="programs_parallel" value="0" <?= $checked(!$form['programs_parallel']) ?>> Em sequência</label>
      <label class="inline"><input type="radio" name="programs_parallel" value="1" <?= $checked((bool)$form['programs_parallel']) ?>> Em paralelo</label>
    </div>

    <div data-mode="script">
      <label>Origem do script</label>
      <label class="inline"><input type="radio" name="script_source" value="existente" <?= $checked($form['script_source'] === 'existente') ?>> Já armazenado</label>
      <label class="inline"><input type="radio" name="script_source" value="novo" <?= $checked($form['script_source'] === 'novo') ?>> Enviar novo</label>

      <div data-source="existente">
        <label for="script_id">Script</label>
        <?php if (!$scripts): ?>
          <p class="hint">Nenhum script armazenado ainda — escolha “Enviar novo”.</p>
        <?php else: ?>
        <select id="script_id" name="script_id">
          <?php foreach ($scripts as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $form['script_id'] === (int)$s['id'] ? 'selected' : '' ?>>
              <?= h($s['name']) ?> (<?= h($s['interpreter']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
      </div>

      <div data-source="novo">
        <label for="new_script_name">Nome do script</label>
        <input type="text" id="new_script_name" name="new_script_name" maxlength="64" value="<?= h($form['new_script_name']) ?>" placeholder="ex.: meu_servidor.py">
        <label for="new_script_interp">Interpretador</label>
        <select id="new_script_interp" name="new_script_interp">
          <?php foreach (SCRIPT_INTERPRETERS as $k => $label): ?>
            <option value="<?= h($k) ?>" <?= $form['new_script_interp'] === $k ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <label for="script_file">Arquivo</label>
        <input type="file" id="script_file" name="script_file">
        <label for="script_text">…ou cole o conteúdo</label>
        <textarea id="script_text" name="script_text"><?= h($form['script_text']) ?></textarea>
        <small>O script fica armazenado e pode ser reutilizado em outros ambientes.</small>
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>2. O programa termina ou é um serviço?</legend>
    <label class="inline"><input type="radio" name="run_kind" value="tarefa" <?= $checked($form['run_kind'] === 'tarefa') ?>> Tarefa (termina sozinha)</label>
    <label class="inline"><input type="radio" name="run_kind" value="servico" <?= $checked($form['run_kind'] === 'servico') ?>> Serviço (executa continuamente)</label>
    <br><small>Em ambos os casos o output é gravado em arquivo e pode ser consultado a qualquer momento.</small>
  </fieldset>

  <fieldset>
    <legend>3. Rede</legend>
    <label class="inline"><input type="checkbox" name="isolate_net" value="1" <?= $checked((bool)$form['isolate_net']) ?>> Isolar rede (namespace de rede próprio, apenas loopback)</label>
  </fieldset>

  <button type="submit" name="then" value="run">Salvar e executar</button>
  <button type="submit" name="then" value="save" class="secondary">Apenas salvar</button>
  <a class="btn secondary" href="index.php">Voltar</a>
</form>

<script>
// Mostra apenas os campos da opção escolhida (sem JS, todos ficam visíveis).
function sync() {
  const mode = document.querySelector('input[name=exec_mode]:checked')?.value;
  const src  = document.querySelector('input[name=script_source]:checked')?.value;
  document.querySelectorAll('[data-mode]').forEach(el => el.hidden = el.dataset.mode !== mode);
  document.querySelectorAll('[data-source]').forEach(el => el.hidden = el.dataset.source !== src);
}
document.querySelectorAll('input[name=exec_mode], input[name=script_source]').forEach(el => el.addEventListener('change', sync));
sync();
</script>
<?php
page_footer();
