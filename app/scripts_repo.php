<?php
// Repositório de scripts: armazenamento permanente (MySQL) para reuso.

const SCRIPT_INTERPRETERS = ['bash' => 'Bash', 'sh' => 'sh (POSIX)', 'python3' => 'Python 3'];
const SCRIPT_MAX_BYTES    = 1048576; // 1 MB

function list_scripts(): array
{
    return db()->query('SELECT id, name, interpreter, original_filename, LENGTH(content) AS size, created_at
                        FROM scripts ORDER BY name')->fetchAll();
}

function find_script(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM scripts WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/**
 * Lê o conteúdo de um script vindo de upload (campo $fileField) ou de um
 * textarea (campo $textField). Retorna [conteudo, nome_original] ou lança
 * InvalidArgumentException com a mensagem para o usuário.
 */
function read_script_input(string $fileField, string $textField): array
{
    $file = $_FILES[$fileField] ?? null;
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Falha no upload do arquivo (código ' . $file['error'] . ').');
        }
        if ($file['size'] > SCRIPT_MAX_BYTES) {
            throw new InvalidArgumentException('Script maior que 1 MB.');
        }
        $content = file_get_contents($file['tmp_name']);
        $orig = basename($file['name']);
    } else {
        $content = (string)($_POST[$textField] ?? '');
        $orig = null;
    }

    $content = str_replace("\r\n", "\n", $content);
    if (trim($content) === '') {
        throw new InvalidArgumentException('Envie um arquivo ou cole o conteúdo do script.');
    }
    if (strlen($content) > SCRIPT_MAX_BYTES) {
        throw new InvalidArgumentException('Script maior que 1 MB.');
    }
    if (str_contains($content, "\0")) {
        throw new InvalidArgumentException('O script deve ser um arquivo de texto.');
    }
    return [$content, $orig];
}

/** Valida e grava um novo script. Retorna o id. */
function save_script(string $name, string $interpreter, string $content, ?string $orig): int
{
    if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $name)) {
        throw new InvalidArgumentException('Nome do script inválido (1 a 64 caracteres: letras, números, ".", "-", "_").');
    }
    if (!isset(SCRIPT_INTERPRETERS[$interpreter])) {
        throw new InvalidArgumentException('Interpretador inválido.');
    }
    try {
        $st = db()->prepare('INSERT INTO scripts (name, interpreter, content, original_filename) VALUES (?, ?, ?, ?)');
        $st->execute([$name, $interpreter, $content, $orig]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            throw new InvalidArgumentException("Já existe um script chamado \"$name\".");
        }
        throw $e;
    }
    return (int)db()->lastInsertId();
}
