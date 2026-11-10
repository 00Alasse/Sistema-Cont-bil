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
