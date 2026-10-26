<?php
// Output completo de um ambiente, em texto puro (?download=1 para baixar).
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/runner.php';

$id = (int)($_GET['id'] ?? 0);
$f  = log_path($id);
if ($id <= 0 || !is_readable($f)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Sem output para o ambiente #$id.\n";
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
if (isset($_GET['download'])) {
    header('Content-Disposition: attachment; filename="ambiente-' . $id . '-output.log"');
}
readfile($f);
