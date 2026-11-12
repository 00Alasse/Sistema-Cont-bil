-- -Tributo! - SQL consolidado gerado; não editar à mão.

-- Gerado em: 2026-11-12

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `obrigacoes`;

DROP TABLE IF EXISTS `empresa_tipos_obrigacao`;

DROP TABLE IF EXISTS `tipos_obrigacao`;

DROP TABLE IF EXISTS `tentativas_login`;

DROP TABLE IF EXISTS `logs_atividade`;

DROP TABLE IF EXISTS `empresas`;

DROP TABLE IF EXISTS `usuarios`;

DROP TABLE IF EXISTS `escritorio_modulos`;

DROP TABLE IF EXISTS `escritorios`;

-- Fonte: 01_nucleo.sql

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

-- Fonte: 02_fiscal.sql

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

-- Fonte: 01_nucleo.sql

-- Dados fictícios para demonstração; as senhas são armazenadas somente como hash.
INSERT INTO escritorios (id, nome, email) VALUES
  (1, 'Exemplo Contábil', 'contato@exemplo.test'),
  (2, 'Modelo Assessoria', 'contato@modelo.test');

INSERT INTO escritorio_modulos (escritorio_id, modulo) VALUES
  (1, 'BPO_FINANCEIRO'),
  (1, 'CONSULTIVO');

INSERT INTO usuarios (id, escritorio_id, nome, email, senha_hash, perfil) VALUES
  (1, 1, 'Administradora Exemplo', 'admin@exemplo.test', '$2y$10$DiuwXQ6pyLZPjwrlj1mIBuERNI9fo6Ckr183lpMoalfdWAvcy0yXu', 'ADMIN'),
  (2, 1, 'Ana Colaboradora', 'ana@exemplo.test', '$2y$10$DiuwXQ6pyLZPjwrlj1mIBuERNI9fo6Ckr183lpMoalfdWAvcy0yXu', 'COLABORADOR'),
  (3, 1, 'Bruno Colaborador', 'bruno@exemplo.test', '$2y$10$DiuwXQ6pyLZPjwrlj1mIBuERNI9fo6Ckr183lpMoalfdWAvcy0yXu', 'COLABORADOR'),
  (4, 2, 'Administradora Modelo', 'admin@modelo.test', '$2y$10$DiuwXQ6pyLZPjwrlj1mIBuERNI9fo6Ckr183lpMoalfdWAvcy0yXu', 'ADMIN');

INSERT INTO empresas (
  id, escritorio_id, razao_social, nome_fantasia, cnpj, regime_tributario,
  segmento, responsavel_id, bpo_ativo, consultivo_ativo
) VALUES
  (1, 1, 'Padaria Pão da Praça Ltda.', 'Pão da Praça', '70000001000111', 'SIMPLES_NACIONAL', 'Alimentação', 2, 1, 1),
  (2, 1, 'Oficina Motor Amigo Ltda.', 'Motor Amigo', '70000002000166', 'SIMPLES_NACIONAL', 'Serviços automotivos', 3, 0, 0),
  (3, 1, 'Mercado Bom Vizinho Ltda.', 'Bom Vizinho', '70000003000100', 'LUCRO_PRESUMIDO', 'Comércio', 2, 1, 0),
  (4, 1, 'Studio Linha Criativa Ltda.', 'Linha Criativa', '70000004000155', 'MEI', 'Serviços', 3, 0, 1),
  (5, 1, 'Restaurante Sabor Caseiro Ltda.', 'Sabor Caseiro', '70000005000108', 'SIMPLES_NACIONAL', 'Alimentação', 2, 1, 1),
  (6, 1, 'Clínica Bem Cuidar Ltda.', 'Bem Cuidar', '70000006000144', 'LUCRO_PRESUMIDO', 'Saúde', 3, 0, 0),
  (7, 1, 'Livraria Ponto e Vírgula Ltda.', 'Ponto e Vírgula', '70000007000199', 'SIMPLES_NACIONAL', 'Comércio', 2, 1, 0),
  (8, 1, 'Ateliê Flor de Papel Ltda.', 'Flor de Papel', '70000008000133', 'MEI', 'Artesanato', 3, 0, 1),
  (9, 1, 'Construtora Horizonte Claro Ltda.', 'Horizonte Claro', '70000009000188', 'LUCRO_PRESUMIDO', 'Construção', 2, 1, 1),
  (10, 1, 'Pet Shop Amigo Fiel Ltda.', 'Amigo Fiel', '70000010000102', 'SIMPLES_NACIONAL', 'Comércio e serviços', 3, 0, 0),
  (11, 1, 'Escola Pequenos Passos Ltda.', 'Pequenos Passos', '70000011000157', 'SIMPLES_NACIONAL', 'Educação', 2, 1, 0),
  (12, 1, 'Café Estação Central Ltda.', 'Estação Central', '70000012000100', 'LUCRO_PRESUMIDO', 'Alimentação', 3, 0, 1),
  (13, 2, 'Floricultura Jardim Feliz Ltda.', 'Jardim Feliz', '70000013000146', 'SIMPLES_NACIONAL', 'Comércio', 4, 0, 0),
  (14, 2, 'Costura Ponto Certo Ltda.', 'Ponto Certo', '70000014000190', 'MEI', 'Serviços', 4, 0, 0),
  (15, 2, 'Tecnologia Nuvem Azul Ltda.', 'Nuvem Azul', '70000015000135', 'LUCRO_PRESUMIDO', 'Tecnologia', 4, 0, 0);

INSERT INTO usuarios (id, escritorio_id, empresa_id, nome, email, senha_hash, perfil) VALUES
  (5, 1, 1, 'Cliente Padaria', 'cliente.padaria@exemplo.test', '$2y$10$DiuwXQ6pyLZPjwrlj1mIBuERNI9fo6Ckr183lpMoalfdWAvcy0yXu', 'CLIENTE'),
  (6, 1, 2, 'Cliente Oficina', 'cliente.oficina@exemplo.test', '$2y$10$DiuwXQ6pyLZPjwrlj1mIBuERNI9fo6Ckr183lpMoalfdWAvcy0yXu', 'CLIENTE'),
  (7, 2, 13, 'Cliente Modelo', 'cliente@modelo.test', '$2y$10$DiuwXQ6pyLZPjwrlj1mIBuERNI9fo6Ckr183lpMoalfdWAvcy0yXu', 'CLIENTE');

-- Fonte: 02_fiscal.sql

-- Dados fictícios do módulo fiscal. Os tipos abaixo são EXEMPLOS de demonstração:
-- os dias de vencimento não representam prazos legais; confira sempre a fonte oficial.
INSERT INTO tipos_obrigacao
  (id, escritorio_id, nome, descricao, esfera, periodicidade, dia_vencimento, meses_apos_competencia)
VALUES
  (1, 1, 'Guia mensal do Simples Nacional', 'Obrigação fictícia de demonstração. Exemplo — confira o prazo vigente na fonte oficial', 'FEDERAL', 'MENSAL', 20, 1),
  (2, 1, 'Declaração mensal de serviços', 'Obrigação fictícia de demonstração. Exemplo — confira o prazo vigente na fonte oficial', 'MUNICIPAL', 'MENSAL', 10, 1),
  (3, 1, 'Escrituração fiscal estadual', 'Obrigação fictícia de demonstração. Exemplo — confira o prazo vigente na fonte oficial', 'ESTADUAL', 'MENSAL', 15, 1),
  (4, 1, 'Folha de pagamento e encargos', 'Obrigação fictícia de demonstração. Exemplo — confira o prazo vigente na fonte oficial', 'TRABALHISTA', 'MENSAL', 7, 1),
  (5, 1, 'Apuração mensal do lucro presumido', 'Obrigação fictícia de demonstração. Exemplo — confira o prazo vigente na fonte oficial', 'FEDERAL', 'MENSAL', 25, 1),
  (6, 1, 'Apuração trimestral', 'Obrigação fictícia de demonstração. Exemplo — confira o prazo vigente na fonte oficial', 'FEDERAL', 'TRIMESTRAL', 30, 1),
  (7, 1, 'Declaração anual', 'Obrigação fictícia de demonstração. Exemplo — confira o prazo vigente na fonte oficial', 'FEDERAL', 'ANUAL', NULL, 5),
  (8, 1, 'Protocolo eventual', 'Obrigação fictícia de demonstração. Exemplo — confira o prazo vigente na fonte oficial', 'OUTRA', 'EVENTUAL', NULL, 0);

-- Vínculos: o responsável padrão é o responsável da empresa (2 = Ana, 3 = Bruno).
INSERT INTO empresa_tipos_obrigacao
  (empresa_id, tipo_obrigacao_id, escritorio_id, responsavel_padrao_id)
VALUES
  (1, 1, 1, 2), (1, 2, 1, 2), (1, 3, 1, 2), (1, 4, 1, 2),
  (2, 1, 1, 3), (2, 2, 1, 3), (2, 4, 1, 3),
  (3, 2, 1, 2), (3, 3, 1, 2), (3, 4, 1, 2), (3, 5, 1, 2), (3, 6, 1, 2),
  (4, 1, 1, 3),
  (5, 1, 1, 2), (5, 2, 1, 2), (5, 3, 1, 2), (5, 4, 1, 2),
  (6, 2, 1, 3), (6, 3, 1, 3), (6, 4, 1, 3), (6, 5, 1, 3), (6, 6, 1, 3),
  (7, 1, 1, 2), (7, 2, 1, 2), (7, 3, 1, 2), (7, 4, 1, 2),
  (8, 1, 1, 3),
  (9, 2, 1, 2), (9, 3, 1, 2), (9, 4, 1, 2), (9, 5, 1, 2), (9, 6, 1, 2), (9, 7, 1, 2),
  (10, 1, 1, 3), (10, 2, 1, 3), (10, 4, 1, 3),
  (11, 1, 1, 2), (11, 2, 1, 2), (11, 4, 1, 2),
  (12, 2, 1, 3), (12, 3, 1, 3), (12, 4, 1, 3), (12, 5, 1, 3), (12, 6, 1, 3);

-- Seis competências (mai/2026 a out/2026) para cada vínculo de tipo MENSAL.
-- A situação é decidida por uma conta fixa (k), então o resultado é sempre o mesmo:
--   k = 0 dispensada; competências antigas (n <= 3) em geral entregues, com algumas
--   entregas depois do vencimento e algumas ainda abertas (atrasadas);
--   competências recentes (n >= 4) com mistura de entregues e abertas.
-- Obrigações entregues usam entregue_em limitado a 10/11/2026, para não ficar no futuro.
INSERT INTO obrigacoes
  (escritorio_id, empresa_id, tipo_obrigacao_id, competencia, vencimento, status,
   responsavel_id, entregue_em, entregue_por, protocolo, criado_por)
SELECT
  x.escritorio_id, x.empresa_id, x.tipo_obrigacao_id, x.competencia, x.vencimento, x.status,
  x.responsavel_id,
  CASE WHEN x.status = 'ENTREGUE'
    THEN LEAST(TIMESTAMP(DATE_ADD(x.vencimento, INTERVAL x.dias_entrega DAY), '10:00:00'), TIMESTAMP('2026-11-10', '10:00:00'))
  END,
  CASE WHEN x.status = 'ENTREGUE' THEN x.responsavel_id END,
  CASE WHEN x.status = 'ENTREGUE'
    THEN CONCAT('EXEMPLO-', x.empresa_id, '-', x.tipo_obrigacao_id, '-', DATE_FORMAT(x.competencia, '%Y%m'))
  END,
  1
FROM (
  SELECT
    b.escritorio_id, b.empresa_id, b.tipo_obrigacao_id, b.competencia, b.vencimento,
    b.responsavel_id,
    CASE
      WHEN b.k = 0 THEN 'DISPENSADA'
      WHEN b.n >= 4 THEN
        CASE WHEN b.k <= 2 THEN 'ENTREGUE'
             WHEN b.k <= 5 THEN 'PENDENTE'
             WHEN b.k <= 7 THEN 'EM_ANDAMENTO'
             ELSE 'AGUARDANDO_CLIENTE' END
      ELSE
        CASE WHEN b.k <= 7 THEN 'ENTREGUE'
             WHEN b.k = 8 THEN 'PENDENTE'
             ELSE 'AGUARDANDO_CLIENTE' END
    END AS status,
    CASE WHEN b.n <= 3 AND b.k IN (6, 7) THEN 5 ELSE -2 END AS dias_entrega
  FROM (
    SELECT
      v.escritorio_id, v.empresa_id, v.tipo_obrigacao_id, v.responsavel_padrao_id AS responsavel_id,
      m.n,
      MOD(v.empresa_id * 3 + v.tipo_obrigacao_id * 5 + m.n * 7, 10) AS k,
      DATE_ADD('2026-05-01', INTERVAL m.n MONTH) AS competencia,
      DATE_ADD(
        DATE_ADD(DATE_ADD('2026-05-01', INTERVAL m.n MONTH), INTERVAL t.meses_apos_competencia MONTH),
        INTERVAL t.dia_vencimento - 1 DAY
      ) AS vencimento
    FROM empresa_tipos_obrigacao v
    JOIN tipos_obrigacao t
      ON t.id = v.tipo_obrigacao_id AND t.escritorio_id = v.escritorio_id
    CROSS JOIN (
      SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2
      UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
    ) m
    WHERE t.periodicidade = 'MENSAL'
  ) b
) x;

SET FOREIGN_KEY_CHECKS=1;
