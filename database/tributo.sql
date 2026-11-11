-- -Tributo! - SQL consolidado gerado; não editar à mão.

-- Gerado em: 2026-11-11

SET FOREIGN_KEY_CHECKS=0;

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

SET FOREIGN_KEY_CHECKS=1;
