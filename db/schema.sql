-- Esquema do banco do Projeto 2 (MySQL 8)
-- Aplicado no provisionamento: mysql proj2 < db/schema.sql

-- Biblioteca de scripts enviados via formulário (mantidos para reuso).
CREATE TABLE IF NOT EXISTS scripts (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name              VARCHAR(64)   NOT NULL,
    interpreter       VARCHAR(16)   NOT NULL,             -- bash | sh | python3
    content           MEDIUMTEXT    NOT NULL,
    original_filename VARCHAR(255)  NULL,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_script_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ambientes de execução solicitados pelo usuário.
CREATE TABLE IF NOT EXISTS environments (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name              VARCHAR(32)   NOT NULL,
    description       VARCHAR(255)  NULL,

    -- Passo 1: recursos alocados
    cpu_percent       TINYINT UNSIGNED NOT NULL,          -- % de 1 core (5–100)
    memory_mb         INT UNSIGNED  NOT NULL,             -- limite de memória (MB)
    io_read_mbps      INT UNSIGNED  NOT NULL DEFAULT 0,   -- leitura em disco MB/s (0 = sem limite)
    io_write_mbps     INT UNSIGNED  NOT NULL DEFAULT 0,   -- escrita em disco MB/s (0 = sem limite)

    -- Passo 2: o que será executado (perguntas de acompanhamento)
    exec_mode         VARCHAR(16)   NULL,                 -- comando | programas | script
    command           TEXT          NULL,                 -- exec_mode = comando
    programs          TEXT          NULL,                 -- exec_mode = programas (um por linha)
    programs_parallel TINYINT(1)    NOT NULL DEFAULT 0,   -- 0 = sequencial, 1 = paralelo
    script_id         INT UNSIGNED  NULL,                 -- exec_mode = script
    run_kind          VARCHAR(16)   NULL,                 -- tarefa (termina) | servico (contínuo)
    isolate_net       TINYINT(1)    NOT NULL DEFAULT 0,   -- namespace de rede isolado

    -- Ciclo de vida
    --   rascunho    = só recursos definidos
    --   pendente    = configurado, aguardando execução
    --   em_execucao = processo rodando no ambiente
    --   terminado   = processo terminou com sucesso (ou foi parado pelo usuário)
    --   erro        = processo terminou com código != 0 ou falhou ao iniciar
    status            VARCHAR(20)   NOT NULL DEFAULT 'rascunho',
    status_msg        VARCHAR(255)  NULL,
    pid               INT UNSIGNED  NULL,                 -- PID (no host) do processo principal
    exit_code         INT           NULL,
    started_at        DATETIME      NULL,
    finished_at       DATETIME      NULL,

    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_status (status),
    CONSTRAINT fk_env_script FOREIGN KEY (script_id) REFERENCES scripts (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
