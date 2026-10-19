<?php
// Conexão PDO única (lazy) com o MySQL.

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function find_env(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM environments WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
