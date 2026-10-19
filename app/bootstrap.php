<?php
// Incluído no topo de todas as páginas em web/.
declare(strict_types=1);

session_start();

require __DIR__ . '/config.php';
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';
