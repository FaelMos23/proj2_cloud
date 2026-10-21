<?php
// Helpers de apresentação: escape, mensagens flash, cabeçalho/rodapé.

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function status_label(string $status): string
{
    $labels = [
        'rascunho' => 'Rascunho',
        'pendente' => 'Aguardando execução',
    ];
    return '<span class="badge b-' . h($status) . '">' . h($labels[$status] ?? $status) . '</span>';
}

function page_header(string $title): void
{
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <strong><?= h(APP_NAME) ?></strong>
  <nav>
    <a href="index.php">Ambientes</a>
    <a href="new.php">Novo ambiente</a>
    <a href="scripts.php">Scripts</a>
  </nav>
</header>
<main>
<h1><?= h($title) ?></h1>
<?php
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        echo '<p class="flash ' . h($type) . '">' . h($msg) . '</p>';
    }
    unset($_SESSION['flash']);
}

function page_footer(): void
{
    echo "</main>\n<footer>Projeto 2 — Computação em Nuvem · PUC Campinas</footer>\n</body>\n</html>\n";
}
