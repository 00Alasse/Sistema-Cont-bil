CREATE TABLE tipos_obrigacao (
  id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  escritorio_id           INT UNSIGNED NOT NULL,
  nome                    VARCHAR(100) NOT NULL,
  descricao               VARCHAR(255) NULL,
  esfera                  ENUM('FEDERAL','ESTADUAL','MUNICIPAL','TRABALHISTA','OUTRA') NOT NULL,
  periodicidade           ENUM('MENSAL','TRIMESTRAL','ANUAL','EVENTUAL') NOT NULL,
  dia_vencimento          TINYINT UNSIGNED NULL,
  meses_apos_competencia  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ativo                   TINYINT(1) NOT NULL DEFAULT 1,
  criado_em               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_tipos_tenant UNIQUE (id, escritorio_id),
  CONSTRAINT uq_tipos_nome   UNIQUE (escritorio_id, nome),
  CONSTRAINT fk_tipos_escritorio FOREIGN KEY (escritorio_id) REFERENCES escritorios(id),
  CONSTRAINT ck_tipos_dia    CHECK (dia_vencimento IS NULL OR dia_vencimento BETWEEN 1 AND 31),
  CONSTRAINT ck_tipos_meses  CHECK (meses_apos_competencia BETWEEN 0 AND 12),
  CONSTRAINT ck_tipos_dia_obrigatorio CHECK (
    periodicidade IN ('ANUAL','EVENTUAL') OR dia_vencimento IS NOT NULL
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quais tipos se aplicam a cada empresa (decisão do contador, não do sistema)
CREATE TABLE empresa_tipos_obrigacao (
  empresa_id             INT UNSIGNED NOT NULL,
  tipo_obrigacao_id      INT UNSIGNED NOT NULL,
  escritorio_id          INT UNSIGNED NOT NULL,
  responsavel_padrao_id  INT UNSIGNED NULL,
  criado_em              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (empresa_id, tipo_obrigacao_id),
  CONSTRAINT fk_eto_empresa FOREIGN KEY (empresa_id, escritorio_id)
      REFERENCES empresas(id, escritorio_id) ON DELETE CASCADE,
  CONSTRAINT fk_eto_tipo FOREIGN KEY (tipo_obrigacao_id, escritorio_id)
      REFERENCES tipos_obrigacao(id, escritorio_id) ON DELETE CASCADE,
  CONSTRAINT fk_eto_responsavel FOREIGN KEY (responsavel_padrao_id, escritorio_id)
      REFERENCES usuarios(id, escritorio_id),
  INDEX idx_eto_tipo (escritorio_id, tipo_obrigacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE obrigacoes (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  escritorio_id      INT UNSIGNED NOT NULL,
  empresa_id         INT UNSIGNED NOT NULL,
  tipo_obrigacao_id  INT UNSIGNED NOT NULL,
  competencia        DATE NOT NULL,                 -- sempre dia 01
  vencimento         DATE NOT NULL,
  status             ENUM('PENDENTE','EM_ANDAMENTO','AGUARDANDO_CLIENTE','ENTREGUE','DISPENSADA')
                     NOT NULL DEFAULT 'PENDENTE',
  responsavel_id     INT UNSIGNED NULL,
  entregue_em        DATETIME NULL,
  entregue_por       INT UNSIGNED NULL,
  protocolo          VARCHAR(100) NULL,             -- número do recibo, informado manualmente
  observacao         TEXT NULL,                     -- interna; nunca exibida ao cliente
  versao             INT UNSIGNED NOT NULL DEFAULT 1,
  criado_por         INT UNSIGNED NOT NULL,
  criado_em          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_obr_tenant      UNIQUE (id, escritorio_id),
  CONSTRAINT uq_obr_competencia UNIQUE (empresa_id, tipo_obrigacao_id, competencia),
  CONSTRAINT fk_obr_escritorio  FOREIGN KEY (escritorio_id) REFERENCES escritorios(id),
  CONSTRAINT fk_obr_empresa     FOREIGN KEY (empresa_id, escritorio_id)
      REFERENCES empresas(id, escritorio_id),
  CONSTRAINT fk_obr_tipo        FOREIGN KEY (tipo_obrigacao_id, escritorio_id)
      REFERENCES tipos_obrigacao(id, escritorio_id),
  CONSTRAINT fk_obr_responsavel FOREIGN KEY (responsavel_id, escritorio_id)
      REFERENCES usuarios(id, escritorio_id),
  CONSTRAINT fk_obr_entregue_por FOREIGN KEY (entregue_por, escritorio_id)
      REFERENCES usuarios(id, escritorio_id),
  CONSTRAINT fk_obr_criado_por  FOREIGN KEY (criado_por, escritorio_id)
      REFERENCES usuarios(id, escritorio_id),
  CONSTRAINT ck_obr_competencia CHECK (DAYOFMONTH(competencia) = 1),
  CONSTRAINT ck_obr_vencimento  CHECK (vencimento >= competencia),
  CONSTRAINT ck_obr_entrega CHECK (
    (status = 'ENTREGUE' AND entregue_em IS NOT NULL AND entregue_por IS NOT NULL) OR
    (status <> 'ENTREGUE' AND entregue_em IS NULL AND entregue_por IS NULL)
  ),
  INDEX idx_obr_painel      (escritorio_id, status, vencimento),
  INDEX idx_obr_empresa     (escritorio_id, empresa_id, vencimento),
  INDEX idx_obr_responsavel (escritorio_id, responsavel_id, status, vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
