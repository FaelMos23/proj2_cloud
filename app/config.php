<?php
// Configuração central da aplicação.
// Valores podem ser sobrescritos por variáveis de ambiente do Apache (SetEnv).

define('APP_NAME', 'Proj2 Cloud');

define('DB_DSN',  getenv('PROJ2_DB_DSN')  ?: 'mysql:host=localhost;dbname=proj2;charset=utf8mb4');
define('DB_USER', getenv('PROJ2_DB_USER') ?: 'proj2');
define('DB_PASS', getenv('PROJ2_DB_PASS') ?: 'proj2pass');

// Limites aceitos no formulário (a VM tem 1 core e 4096 MB).
const LIMIT_CPU_MIN = 5;      // % de 1 core
const LIMIT_CPU_MAX = 100;
const LIMIT_MEM_MIN = 16;     // MB
const LIMIT_MEM_MAX = 2048;
const LIMIT_IO_MAX  = 500;    // MB/s (0 = sem limite)
