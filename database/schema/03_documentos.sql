CREATE TABLE solicitacoes_documentos (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  escritorio_id         INT UNSIGNED NOT NULL,
  empresa_id            INT UNSIGNED NOT NULL,
  titulo                VARCHAR(150) NOT NULL,
  descricao             TEXT         NULL,
  prazo                 DATE         NOT NULL,
  status                ENUM('aberta','enviada','aceita','recusada') NOT NULL DEFAULT 'aberta',
  motivo_recusa         TEXT         NULL,
  arquivo_path          VARCHAR(255) NULL,
  arquivo_nome_original VARCHAR(255) NULL,
  enviado_em            DATETIME     NULL,
  criado_por            INT UNSIGNED NOT NULL,
  criado_em             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_solic_tenant UNIQUE (id, escritorio_id),
  CONSTRAINT fk_solic_escritorio FOREIGN KEY (escritorio_id) REFERENCES escritorios(id),
  CONSTRAINT fk_solic_empresa FOREIGN KEY (empresa_id, escritorio_id) REFERENCES empresas(id, escritorio_id),
  CONSTRAINT fk_solic_criador FOREIGN KEY (criado_por, escritorio_id) REFERENCES usuarios(id, escritorio_id),
  INDEX idx_solic_empresa (escritorio_id, empresa_id, status, prazo),
  INDEX idx_solic_status  (escritorio_id, status, prazo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE avaliacoes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  escritorio_id  INT UNSIGNED NOT NULL,
  usuario_id     INT UNSIGNED NOT NULL,
  nota           TINYINT UNSIGNED NOT NULL,
  facilidade     TINYINT UNSIGNED NULL,
  comentario     TEXT NULL,
  criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_aval_tenant UNIQUE (id, escritorio_id),
  CONSTRAINT fk_aval_escritorio FOREIGN KEY (escritorio_id) REFERENCES escritorios(id),
  CONSTRAINT fk_aval_usuario FOREIGN KEY (usuario_id, escritorio_id) REFERENCES usuarios(id, escritorio_id),
  CONSTRAINT ck_aval_nota CHECK (nota BETWEEN 1 AND 5),
  CONSTRAINT ck_aval_facilidade CHECK (facilidade IS NULL OR facilidade BETWEEN 1 AND 5),
  INDEX idx_aval_lista (escritorio_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
