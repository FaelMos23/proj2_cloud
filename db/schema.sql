-- Aplicado no provisionamento: mysql proj2 < db/schema.sql
-- Ambientes de execução solicitados pelo usuário.
-- Passo 1 do pedido: identificação e recursos (CPU / Memória / IO).
CREATE TABLE IF NOT EXISTS environments (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name           VARCHAR(32)   NOT NULL,
    description    VARCHAR(255)  NULL,

    -- Recursos alocados
    cpu_percent    TINYINT UNSIGNED NOT NULL,             
    memory_mb      INT UNSIGNED  NOT NULL,                
    io_read_mbps   INT UNSIGNED  NOT NULL DEFAULT 0,      
    io_write_mbps  INT UNSIGNED  NOT NULL DEFAULT 0,      


    status         VARCHAR(20)   NOT NULL DEFAULT 'rascunho',

    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
