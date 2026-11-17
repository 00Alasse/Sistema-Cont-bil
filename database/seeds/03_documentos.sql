-- Dados fictícios para o módulo de Solicitações de Documentos e Avaliações
INSERT INTO solicitacoes_documentos (
  id, escritorio_id, empresa_id, titulo, descricao, prazo, status,
  motivo_recusa, arquivo_path, arquivo_nome_original, enviado_em, criado_por
) VALUES
  (1, 1, 1, 'Extrato Bancário - Caixa Econômica (Outubro)', 'Favor enviar o extrato completo em formato PDF ou OFX referente ao mês de Outubro.', DATE_SUB(CURRENT_DATE, INTERVAL 5 DAY), 'aberta', NULL, NULL, NULL, NULL, 1),
  (2, 1, 1, 'Notas Fiscais de Entrada (Outubro)', 'Arquivos XML das notas fiscais de compra emitidas no mês de Outubro.', DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY), 'aberta', NULL, NULL, NULL, NULL, 1),
  (3, 1, 1, 'Comprovante de Pagamento do Simples', 'Enviar o comprovante de pagamento da guia DAS do mês anterior.', DATE_SUB(CURRENT_DATE, INTERVAL 2 DAY), 'enviada', NULL, 'comprovante_das_out.pdf', 'Comprovante_DAS_Outubro.pdf', DATE_SUB(NOW(), INTERVAL 1 DAY), 1),
  (4, 1, 1, 'Folha de Ponto dos Colaboradores', 'Folhas de ponto assinadas de todos os colaboradores para fechamento da folha.', DATE_SUB(CURRENT_DATE, INTERVAL 10 DAY), 'aceita', NULL, 'folha_ponto_out.pdf', 'Folhas_Ponto_Outubro_Assinadas.pdf', DATE_SUB(NOW(), INTERVAL 8 DAY), 1),
  (5, 1, 1, 'Comprovante de Energia Elétrica', 'Conta de energia do estabelecimento para dedução de custos.', DATE_SUB(CURRENT_DATE, INTERVAL 4 DAY), 'recusada', 'O arquivo enviado está ilegível. Favor enviar foto ou digitalização nítida.', 'conta_luz_out.jpg', 'Conta_Luz_Ilegivel.jpg', DATE_SUB(NOW(), INTERVAL 3 DAY), 1),
  (6, 1, 2, 'Extrato Bancário - Itaú (Outubro)', 'Extrato de conta corrente da pessoa jurídica do mês de Outubro.', DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY), 'aberta', NULL, NULL, NULL, NULL, 1),
  (7, 1, 2, 'Relatório de Vendas com Cartão', 'Relatório de maquininha de cartão de crédito/débito.', DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY), 'enviada', NULL, 'vendas_cartao_out.pdf', 'Relatorio_Maquininhas_Outubro.pdf', NOW(), 1),
  (8, 1, 3, 'Contrato de Locação Renovado', 'Cópia digitalizada do termo aditivo de renovação do aluguel do imóvel.', DATE_ADD(CURRENT_DATE, INTERVAL 10 DAY), 'aberta', NULL, NULL, NULL, NULL, 1),
  (9, 1, 4, 'Relatório Mensal de Faturamento MEI', 'Relatório com o somatório das vendas brutas com e sem nota fiscal.', DATE_SUB(CURRENT_DATE, INTERVAL 8 DAY), 'aceita', NULL, 'relatorio_mei_out.pdf', 'Faturamento_MEI_Outubro.pdf', DATE_SUB(NOW(), INTERVAL 6 DAY), 1),
  (10, 1, 5, 'Inventário de Estoque Trimestral', 'Planilha ou relatório de inventário físico com valores de custo.', DATE_ADD(CURRENT_DATE, INTERVAL 15 DAY), 'aberta', NULL, NULL, NULL, NULL, 1),
  (11, 1, 6, 'Comprovante de Despesas Médicas', 'Recibos e notas de compras de insumos para compensação.', DATE_SUB(CURRENT_DATE, INTERVAL 3 DAY), 'recusada', 'Faltou o carimbo do profissional emitente no recibo.', 'recibo_insumos.pdf', 'Recibos_Incompletos.pdf', DATE_SUB(NOW(), INTERVAL 2 DAY), 1),
  (12, 1, 7, 'Extrato Bancário - Santander (Outubro)', 'Extrato mensal consolidado da Livraria.', DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY), 'aberta', NULL, NULL, NULL, NULL, 1);

-- Avaliações de satisfação e usabilidade
INSERT INTO avaliacoes (id, escritorio_id, usuario_id, nota, facilidade, comentario, criado_em) VALUES
  (1, 1, 5, 5, 5, 'Excelente plataforma! Ficou muito mais rápido enviar documentos e ver as guias sem precisar ficar caçando mensagem no WhatsApp.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  (2, 1, 6, 5, 4, 'O semáforo de regularidade ajudou muito a entender o que estava pendente na oficina.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
  (3, 1, 2, 4, 5, 'Como colaboradora, o painel centralizado me economiza pelo menos 1 hora por dia de cobrança de clientes.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (4, 1, 9, 5, 5, 'Facilidade impressionante no celular. Em 2 minutos anexei o comprovante do Simples.', NOW());
