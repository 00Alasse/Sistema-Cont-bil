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
