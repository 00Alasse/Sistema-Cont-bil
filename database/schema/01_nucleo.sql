CREATE TABLE escritorios (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome           VARCHAR(150) NOT NULL,
  cnpj           CHAR(14)     NULL,
  email          VARCHAR(150) NOT NULL,
  ativo          TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_escritorios_cnpj UNIQUE (cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Módulos contratáveis. O Plano Base não é registrado aqui (sempre ativo).
CREATE TABLE escritorio_modulos (
  escritorio_id  INT UNSIGNED NOT NULL,
  modulo         ENUM('BPO_FINANCEIRO','CONSULTIVO') NOT NULL,
  ativo          TINYINT(1) NOT NULL DEFAULT 1,
  ativado_em     DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (escritorio_id, modulo),
  CONSTRAINT fk_escmod_escritorio FOREIGN KEY (escritorio_id)
      REFERENCES escritorios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A FK usuarios.empresa_id é criada depois de `empresas` (dependência circular).
CREATE TABLE usuarios (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  escritorio_id    INT UNSIGNED NOT NULL,
  empresa_id       INT UNSIGNED NULL,
  nome             VARCHAR(120) NOT NULL,
  email            VARCHAR(150) NOT NULL,
  senha_hash       VARCHAR(255) NOT NULL,
  perfil           ENUM('ADMIN','COLABORADOR','CLIENTE') NOT NULL,
  ativo            TINYINT(1)   NOT NULL DEFAULT 1,
  ultimo_login_em  DATETIME     NULL,
  criado_em        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_usuarios_email  UNIQUE (email),
  CONSTRAINT uq_usuarios_tenant UNIQUE (id, escritorio_id),
  CONSTRAINT fk_usuarios_escritorio FOREIGN KEY (escritorio_id) REFERENCES escritorios(id),
  CONSTRAINT ck_usuarios_cliente_empresa CHECK (
    (perfil = 'CLIENTE' AND empresa_id IS NOT NULL) OR
    (perfil <> 'CLIENTE' AND empresa_id IS NULL)
  ),
  INDEX idx_usuarios_lista (escritorio_id, perfil, ativo, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE empresas (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  escritorio_id      INT UNSIGNED NOT NULL,
  razao_social       VARCHAR(150) NOT NULL,
  nome_fantasia      VARCHAR(150) NULL,
  cnpj               CHAR(14)     NOT NULL,   -- sem máscara, maiúsculas; aceita formato alfanumérico
  regime_tributario  ENUM('MEI','SIMPLES_NACIONAL','LUCRO_PRESUMIDO','LUCRO_REAL') NOT NULL, -- informativo
  segmento           VARCHAR(80)  NULL,       -- texto livre (comércio, serviços...), informativo
  contato_nome       VARCHAR(120) NULL,
  email              VARCHAR(150) NULL,
  telefone           VARCHAR(20)  NULL,
  responsavel_id     INT UNSIGNED NULL,
  bpo_ativo          TINYINT(1)   NOT NULL DEFAULT 0,  -- escritório vende BPO a esta empresa
  consultivo_ativo   TINYINT(1)   NOT NULL DEFAULT 0,  -- escritório vende consultoria a esta empresa
  status             ENUM('ATIVA','INATIVA') NOT NULL DEFAULT 'ATIVA',
  observacoes        TEXT NULL,
  versao             INT UNSIGNED NOT NULL DEFAULT 1,
  criado_em          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_empresas_tenant UNIQUE (id, escritorio_id),
  CONSTRAINT uq_empresas_cnpj_por_escritorio UNIQUE (escritorio_id, cnpj),
  CONSTRAINT fk_empresas_escritorio  FOREIGN KEY (escritorio_id) REFERENCES escritorios(id),
  CONSTRAINT fk_empresas_responsavel FOREIGN KEY (responsavel_id, escritorio_id)
      REFERENCES usuarios(id, escritorio_id),
  INDEX idx_empresas_busca (escritorio_id, status, razao_social),
  INDEX idx_empresas_responsavel (escritorio_id, responsavel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE usuarios
  ADD CONSTRAINT fk_usuarios_empresa FOREIGN KEY (empresa_id, escritorio_id)
      REFERENCES empresas(id, escritorio_id);

-- Auditoria polimórfica (sem FK para a entidade auditada)
CREATE TABLE logs_atividade (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  escritorio_id  INT UNSIGNED NOT NULL,
  usuario_id     INT UNSIGNED NULL,
  entidade       VARCHAR(50)  NOT NULL,   -- ex.: 'obrigacao', 'lancamento'
  entidade_id    INT UNSIGNED NOT NULL,
  acao           VARCHAR(50)  NOT NULL,   -- ex.: 'criar', 'status:ENTREGUE', 'baixar', 'estornar'
  detalhes       JSON NULL,
  ip             VARCHAR(45)  NULL,
  criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_escritorio FOREIGN KEY (escritorio_id) REFERENCES escritorios(id),
  CONSTRAINT fk_log_usuario    FOREIGN KEY (usuario_id, escritorio_id)
      REFERENCES usuarios(id, escritorio_id),
  INDEX idx_log_entidade (escritorio_id, entidade, entidade_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tentativas_login (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(150) NOT NULL,
  ip         VARCHAR(45)  NOT NULL,
  sucesso    TINYINT(1)   NOT NULL,
  criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tl_email (email, criado_em),
  INDEX idx_tl_ip    (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
