# Rotas do Tributo

> **Contrato de rotas.** Cada módulo declara suas rotas em `config/modules/<modulo>.php`
> (ver [ARCHITECTURE.md §4](ARCHITECTURE.md#4-módulos-e-o-contrato-de-extensão)).
> Situação: **planejado**. A coluna "Tarefa" indica quem implementa; o andamento real está no [ROADMAP.md](ROADMAP.md).

## 1. Convenções

- **Aplicação web renderizada no servidor:** as respostas são HTML. Não há API JSON pública na R1.
- **Métodos:** `GET` para páginas, filtros e paginação (refletidos na URL); `POST` para toda mutação. HTML não envia PUT nem DELETE, então:
  - criar: `POST /recurso`
  - atualizar: `POST /recurso/{id}`
  - excluir: `POST /recurso/{id}/excluir`
  - ações de estado: `POST /recurso/{id}/<acao>`
- **CSRF:** todo `POST` envia `_csrf` (gerado por `csrf_campo()`).
- **Concorrência:** formulários de edição de entidades com `versao` enviam o campo oculto `versao`.
- **Paginação:** `?pagina=N` (padrão 1; 20 itens por página). Valores inválidos viram 1; acima do total, vira a última página.
- **Busca:** `?q=` faz uma busca parcial (`LIKE`, com `%`, `_` e `\` escapados).
- **Ordenação:** `?ordem=campo` ou `?ordem=-campo` (decrescente), somente entre os campos listados em cada rota; outros valores são ignorados.
- **Datas** na URL e nos formulários: `AAAA-MM-DD`; **competência**: `AAAA-MM`; **valores**: `1.234,56` (pt-BR) ou `1234.56`.
- Perfis: **A** = ADMIN · **C** = COLABORADOR · **CL** = CLIENTE · **V** = visitante (sem sessão).
- Middlewares: `auth` (exige sessão) · `guest` (somente visitante) · `perfil:X,Y` · `modulo:CODIGO` (acrescentado automaticamente nos módulos contratáveis).

### 1.1 Respostas padronizadas

| Status | Quando | Comportamento |
|--------|--------|---------------|
| 200 | GET bem-sucedido | Página HTML |
| 302 | Mutação bem-sucedida (PRG), login ou logout, sessão ausente | Redireciona; flash de sucesso quando aplicável |
| 403 | Perfil sem permissão | Página `erros/403` |
| 403 | Módulo não contratado | Página `erros/modulo-inativo` |
| 404 | Rota inexistente, ID inexistente ou **de outro escritório/empresa**, empresa sem o serviço (`bpo_ativo`/`consultivo_ativo`) | Página `erros/404` |
| 405 | Método não permitido | Página de erro |
| 409 | Versão desatualizada, transição de estado inválida, exclusão bloqueada por vínculos | Formulário com mensagem ou flash de erro |
| 419 | CSRF ausente ou inválido | Página `erros/419` ("Sua sessão expirou. Recarregue a página.") |
| 422 | Validação falhou | Formulário re-renderizado com `erros[campo]` e valores digitados |
| 429 | Excesso de tentativas de login | Formulário com a mensagem de bloqueio |
| 500 | Erro inesperado | Página genérica; detalhes só no log |

---

## 2. Núcleo: autenticação e conta (`config/modules/nucleo.php`)

| Método | Rota | Finalidade | Perfil | Payload | Respostas | Tarefa |
|--------|------|------------|--------|---------|-----------|--------|
| GET | `/` | Redireciona conforme o perfil | V, A, C, CL | — | 302 → `/login`, `/painel` ou `/portal` | F07 |
| GET | `/login` | Formulário de login | V | — | 200; 302 se já autenticado | F07 |
| POST | `/login` | Autenticar | V | `email`*, `senha`* | 302; 422 (campos ou credencial: mensagem genérica); 429; 419 | F07 |
| POST | `/logout` | Encerrar a sessão | A, C, CL | — | 302 → `/login` | F07 |
| GET | `/perfil` | Dados do próprio usuário | A, C, CL | — | 200 | C02 |
| POST | `/perfil/senha` | Trocar a própria senha | A, C, CL | `senha_atual`*, `nova_senha`* (mín. 8), `nova_senha_confirmacao`* | 302; 422 ("senha atual incorreta", "confirmação não confere") | C02 |
| GET | `/plano` | Módulos contratados pelo escritório (somente leitura) | A | — | 200 | C03 |

## 3. Cadastros (`config/modules/cadastro.php`)

### 3.1 Empresas

| Método | Rota | Finalidade | Perfil | Payload / parâmetros | Respostas | Tarefa |
|--------|------|------------|--------|----------------------|-----------|--------|
| GET | `/empresas` | Listar | A, C | `q` (razão social, fantasia, CNPJ), `status`, `regime`, `responsavel_id`, `servico` (`bpo`\|`consultivo`), `ordem` (`razao_social`, `criado_em`), `pagina` | 200 | C01 |
| GET | `/empresas/nova` | Formulário | A, C | — | 200 | C01 |
| POST | `/empresas` | Criar | A, C | `razao_social`* (máx. 150), `nome_fantasia`, `cnpj`* (com ou sem máscara; numérico ou alfanumérico), `regime_tributario`*, `segmento`, `contato_nome`, `email`, `telefone`, `responsavel_id` (usuário A/C ativo do escritório), `bpo_ativo`, `consultivo_ativo`, `observacoes` | 302 → `/empresas/{id}`; 422 (CNPJ inválido, CNPJ já cadastrado **neste escritório**, responsável inválido) | C01 |
| GET | `/empresas/{id}` | Página da empresa: dados + abas declaradas pelos módulos (`abas_empresa`) | A, C | — | 200; 404 | C01 |
| GET | `/empresas/{id}/editar` | Formulário | A, C | — | 200; 404 | C01 |
| POST | `/empresas/{id}` | Atualizar | A, C | campos acima + `versao`* | 302; 422; 404; 409 | C01 |
| POST | `/empresas/{id}/excluir` | Excluir | A | — | 302; 403; 404; 409 ("possui registros vinculados; inative a empresa") | C01 |

As flags `bpo_ativo` e `consultivo_ativo` são apenas gravadas pelo cadastro. A conta "Caixa" padrão é criada **sob demanda** pelo módulo financeiro, na primeira vez que o financeiro da empresa é aberto (FN02, `Services\Financeiro\ContaService::garantirCaixaPadrao`).

### 3.2 Usuários

| Método | Rota | Finalidade | Perfil | Payload | Respostas | Tarefa |
|--------|------|------------|--------|---------|-----------|--------|
| GET | `/usuarios` | Listar | A | `q`, `perfil`, `ativo`, `pagina` | 200; 403 | C02 |
| GET | `/usuarios/novo` | Formulário | A | — | 200 | C02 |
| POST | `/usuarios` | Criar | A | `nome`*, `email`* (único no sistema), `perfil`*, `empresa_id` (obrigatório se CLIENTE; empresa do escritório), `senha_inicial`* (mín. 8) | 302; 422 | C02 |
| GET | `/usuarios/{id}/editar` | Formulário | A | — | 200; 404 | C02 |
| POST | `/usuarios/{id}` | Atualizar | A | `nome`*, `email`*, `perfil`*, `empresa_id` | 302; 422; 404; 409 (rebaixar o último ADMIN) | C02 |
| POST | `/usuarios/{id}/ativo` | Ativar ou desativar | A | `ativo` (0\|1) | 302; 409 ("último administrador", "não é possível desativar a si mesmo") | C02 |

## 4. Fiscal e obrigações (`config/modules/fiscal.php`)

### 4.1 Tipos de obrigação

| Método | Rota | Perfil | Payload / parâmetros | Respostas | Tarefa |
|--------|------|--------|----------------------|-----------|--------|
| GET | `/tipos-obrigacao` | A, C | `q`, `esfera`, `periodicidade`, `ativo`, `pagina` | 200 | FI02 |
| GET | `/tipos-obrigacao/novo` | A | — | 200; 403 | FI02 |
| POST | `/tipos-obrigacao` | A | `nome`* (único no escritório), `descricao`, `esfera`*, `periodicidade`*, `dia_vencimento` (1–31; obrigatório se MENSAL ou TRIMESTRAL), `meses_apos_competencia`* (0–12), `ativo` | 302; 422 | FI02 |
| GET | `/tipos-obrigacao/{id}/editar` | A | — | 200; 404 | FI02 |
| POST | `/tipos-obrigacao/{id}` | A | idem | 302; 422; 404 | FI02 |
| POST | `/tipos-obrigacao/{id}/excluir` | A | — | 302; 409 (existem obrigações: "inative o tipo") | FI02 |
| GET | `/empresas/{id}/tipos-obrigacao` | A, C | — | 200 (lista de tipos com marcação e responsável padrão) | FI02 |
| POST | `/empresas/{id}/tipos-obrigacao` | A, C | `tipos[]` (IDs do escritório), `responsavel_padrao[<tipo_id>]` | 302; 422 (tipo ou responsável inválido); 404 | FI02 |

### 4.2 Obrigações

| Método | Rota | Finalidade | Perfil | Payload / parâmetros | Respostas | Tarefa |
|--------|------|------------|--------|----------------------|-----------|--------|
| GET | `/obrigacoes` | Listar | A, C | `q` (empresa ou tipo), `empresa_id`, `tipo_id`, `status`, `situacao` (`atrasada`\|`vence_hoje`\|`vence_7d`\|`entregue_atraso`), `responsavel` (ID ou `meus`), `competencia` (AAAA-MM), `de`, `ate` (vencimento), `ordem` (`vencimento`, `competencia`, `empresa`), `pagina` | 200 | FI03 |
| GET | `/obrigacoes/nova` | Formulário avulso | A, C | `empresa_id` (pré-seleção) | 200 | FI03 |
| POST | `/obrigacoes` | Criar | A, C | `empresa_id`*, `tipo_obrigacao_id`*, `competencia`* (AAAA-MM), `vencimento`* (≥ início da competência), `responsavel_id`, `observacao` | 302; 422 (incluindo "já existe obrigação deste tipo nesta competência") | FI03 |
| GET | `/obrigacoes/{id}` | Detalhe + histórico de status | A, C | — | 200; 404 | FI03, FI04 |
| GET | `/obrigacoes/{id}/editar` | Formulário | A, C | — | 200; 404 | FI03 |
| POST | `/obrigacoes/{id}` | Atualizar vencimento, responsável e observação | A, C | `vencimento`*, `responsavel_id`, `observacao`, `versao`* | 302; 422; 404; 409 | FI03 |
| POST | `/obrigacoes/{id}/excluir` | Excluir | A | — | 302; 403; 404; 409 (solicitações vinculadas) | FI03 |
| POST | `/obrigacoes/{id}/status` | Mudar status | A, C (reabrir: só A) | `status`*, `versao`*, `protocolo` (se ENTREGUE), `justificativa` (obrigatória para DISPENSADA e reaberturas) | 302; 422; 403; 404; 409 | FI04 |
| GET | `/obrigacoes/gerar` | Tela do gerador | A, C | — | 200 | FI05 |
| POST | `/obrigacoes/gerar` | Gerar competência | A, C | `competencia`* (AAAA-MM), `empresa_ids[]` (opcional; padrão: todas as ativas) | 302 + flash "X criadas, Y já existiam"; 422 | FI05 |
| GET | `/calendario` | Vencimentos do mês | A, C | `mes` (AAAA-MM), `responsavel`, `empresa_id` | 200 | FI06 |

**Transições de status permitidas** (FI04):

| De \ Para | PENDENTE | EM_ANDAMENTO | AGUARDANDO_CLIENTE | ENTREGUE | DISPENSADA |
|-----------|:--------:|:------------:|:------------------:|:--------:|:----------:|
| PENDENTE | — | ✔ | ✔ | ✔ | ✔ (justificativa) |
| EM_ANDAMENTO | ✔ | — | ✔ | ✔ | ✔ (justificativa) |
| AGUARDANDO_CLIENTE | ✘ | ✔ | — | ✔ | ✔ (justificativa) |
| ENTREGUE | ✘ | ✔ **A + justificativa** | ✘ | — | ✘ |
| DISPENSADA | ✔ **A + justificativa** | ✘ | ✘ | ✘ | — |

Ao entrar em ENTREGUE: grava `entregue_em = agora` e `entregue_por = usuário`. Ao sair de ENTREGUE: limpa os dois campos. Toda transição gera um registro em `logs_atividade` (`acao = 'status:<NOVO>'`, `detalhes = {de, para, justificativa, protocolo}`).

## 5. Documentos e pendências (`config/modules/documentos.php`)

| Método | Rota | Finalidade | Perfil | Payload / parâmetros | Respostas | Tarefa |
|--------|------|------------|--------|----------------------|-----------|--------|
| GET | `/solicitacoes` | Listar | A, C | `q`, `empresa_id`, `status`, `situacao` (`vencida`\|`aguardando_analise`), `pagina` | 200 | DO01 |
| GET | `/solicitacoes/nova` | Formulário | A, C | `empresa_id`, `obrigacao_id` (pré-seleção) | 200 | DO01 |
| POST | `/solicitacoes` | Criar | A, C | `empresa_id`*, `titulo`* (máx. 150), `descricao`, `prazo`* (≥ hoje), `obrigacao_id` (da mesma empresa) | 302; 422 | DO01 |
| GET | `/solicitacoes/{id}` | Detalhe + arquivos | A, C | — | 200; 404 | DO01 |
| POST | `/solicitacoes/{id}` | Editar (somente ABERTA ou RECUSADA) | A, C | `titulo`*, `descricao`, `prazo`*, `versao`* | 302; 422; 409 | DO01 |
| POST | `/solicitacoes/{id}/cancelar` | Cancelar (ABERTA ou RECUSADA) | A, C | `versao`* | 302; 409 | DO01 |
| POST | `/solicitacoes/{id}/avaliar` | Aceitar ou recusar o envio (ENVIADA) | A, C | `decisao`* (`ACEITA`\|`RECUSADA`), `motivo_recusa` (obrigatório se RECUSADA), `versao`* | 302; 422; 409 | DO01 |
| POST | `/solicitacoes/{id}/documentos` | Enviar arquivo (`multipart/form-data`) | A, C, CL (da própria empresa) | `arquivo`* (pdf, jpg, jpeg, png, xml, csv, xlsx; até 10 MB) | 302; 422 (tipo, tamanho ou MIME); 404; 409 (ACEITA ou CANCELADA) | DO02 |
| GET | `/documentos/{id}/download` | Baixar | A, C; CL (da própria empresa) | — | 200 (`attachment`); 404 | DO02 |
| POST | `/documentos/{id}/excluir` | Remover arquivo enviado por engano | A; CL (próprio envio, solicitação ainda ENVIADA) | — | 302; 403; 404; 409 | DO02 |

**Estados da solicitação:** `ABERTA → ENVIADA` (primeiro upload) `→ ACEITA | RECUSADA`; `RECUSADA → ENVIADA` (novo upload); `ABERTA | RECUSADA → CANCELADA`.

## 6. Portal do cliente (`config/modules/portal.php`)

Todas as rotas: perfil **CL**; filtro automático por `escritorio_id` e `empresa_id` da sessão.

| Método | Rota | Finalidade | Respostas | Tarefa |
|--------|------|------------|-----------|--------|
| GET | `/portal` | Início: pendências em aberto, próximos vencimentos, ações sob responsabilidade do cliente | 200 | DO03 |
| GET | `/portal/solicitacoes` | Lista (`status`, `pagina`) | 200 | DO03 |
| GET | `/portal/solicitacoes/{id}` | Detalhe + formulário de envio | 200; 404 | DO03 |
| GET | `/portal/obrigacoes` | Obrigações da empresa, **somente leitura**, sem `observacao` interna (`status`, `competencia`, `pagina`) | 200 | DO03 |
| GET | `/portal/documentos` | Arquivos enviados pela empresa (`pagina`) | 200 | DO03 |
| GET | `/portal/financeiro` | Resumo financeiro (somente leitura) se a empresa tiver BPO ativo | 200; 404 | FN07 |
| GET | `/portal/acompanhamento` | Acompanhamentos e ações marcados como visíveis ao cliente | 200; 404 | CO02 |

## 7. BPO financeiro (`config/modules/financeiro.php`, `contratavel: BPO_FINANCEIRO`)

Todas as rotas `/empresas/{empresa}/financeiro/*` exigem `bpo_ativo = 1` na empresa (senão, 404).

### 7.1 Configuração

| Método | Rota | Perfil | Payload / parâmetros | Respostas | Tarefa |
|--------|------|--------|----------------------|-----------|--------|
| GET | `/financeiro/categorias` | A, C | `tipo`, `grupo`, `ativo`, `q`, `pagina` | 200 | FN02 |
| GET | `/financeiro/categorias/nova` | A | — | 200 | FN02 |
| POST | `/financeiro/categorias` | A | `nome`*, `tipo`*, `grupo`* (compatível com o tipo), `ativo` | 302; 422 | FN02 |
| GET | `/financeiro/categorias/{id}/editar` | A | — | 200; 404 | FN02 |
| POST | `/financeiro/categorias/{id}` | A | `nome`*, `grupo`*, `ativo` (o tipo não muda se houver lançamentos) | 302; 422; 409 | FN02 |
| POST | `/financeiro/categorias/{id}/excluir` | A | — | 302; 409 (com lançamentos: "inative") | FN02 |
| GET | `/empresas/{empresa}/financeiro/contas` | A, C | — | 200 | FN02 |
| POST | `/empresas/{empresa}/financeiro/contas` | A, C | `nome`*, `tipo`*, `saldo_inicial`* (pode ser negativo), `data_saldo_inicial`* | 302; 422 | FN02 |
| POST | `/empresas/{empresa}/financeiro/contas/{id}` | A, C | idem + `ativo` | 302; 422; 404 | FN02 |

### 7.2 Lançamentos

| Método | Rota | Finalidade | Perfil | Payload / parâmetros | Respostas | Tarefa |
|--------|------|------------|--------|----------------------|-----------|--------|
| GET | `/empresas/{empresa}/financeiro/lancamentos` | Listar | A, C | `tipo`, `status`, `situacao` (`vencido`\|`vence_7d`), `categoria_id`, `conta_id`, `de`, `ate` (vencimento), `q` (descrição, contraparte, documento), `ordem` (`vencimento`, `valor`, `pago_em`), `pagina` + totais filtrados | 200 | FN03 |
| GET | `.../lancamentos/novo` | Formulário | A, C | `tipo` (RECEITA\|DESPESA) | 200 | FN03 |
| POST | `.../lancamentos` | Criar | A, C | `tipo`*, `categoria_id`* (do mesmo tipo), `descricao`*, `contraparte`, `documento_ref`, `valor`* (> 0), `competencia`* (AAAA-MM), `vencimento`*, `conta_id` (da empresa), `observacao`, `ja_pago` + `pago_em` + `valor_pago` (atalho para criar já baixado) | 302; 422 | FN03 |
| GET | `.../lancamentos/{id}` | Detalhe + histórico | A, C | — | 200; 404 | FN03 |
| GET | `.../lancamentos/{id}/editar` | Formulário (somente ABERTO) | A, C | — | 200; 404; 409 | FN03 |
| POST | `.../lancamentos/{id}` | Atualizar (somente ABERTO) | A, C | campos de criação (exceto `tipo`) + `versao`* | 302; 422; 404; 409 | FN03 |
| POST | `.../lancamentos/{id}/excluir` | Excluir (ABERTO ou CANCELADO) | A | — | 302; 403; 409 (PAGO: "estorne antes") | FN03 |
| POST | `.../lancamentos/{id}/baixar` | Registrar pagamento ou recebimento | A, C | `pago_em`* (≤ hoje), `valor_pago`* (> 0), `conta_id`*, `versao`* | 302; 422; 409 (não ABERTO) | FN04 |
| POST | `.../lancamentos/{id}/estornar` | Desfazer a baixa (PAGO → ABERTO) | A, C | `justificativa`*, `versao`* | 302; 422; 409 | FN04 |
| POST | `.../lancamentos/{id}/cancelar` | Cancelar (ABERTO → CANCELADO) | A, C | `justificativa`*, `versao`* | 302; 422; 409 | FN04 |

### 7.3 Indicadores e visões

| Método | Rota | Finalidade | Perfil | Parâmetros | Respostas | Tarefa |
|--------|------|------------|--------|------------|-----------|--------|
| GET | `/empresas/{empresa}/financeiro` | Painel financeiro da empresa (indicadores da [ARCHITECTURE.md §10.5](ARCHITECTURE.md#105-indicadores-financeiros-módulo-bpo-para-o-mês-m-e-uma-empresa)) | A, C | `mes` (AAAA-MM; padrão: mês atual) | 200 | FN05 |
| GET | `/empresas/{empresa}/financeiro/fluxo-caixa` | Fluxo de caixa diário | A, C | `mes`, `visao` (`realizado`\|`projetado`) | 200 | FN05 |
| GET | `/financeiro/carteira` | Lançamentos abertos de todas as empresas com BPO (vencidos e próximos 7 dias) | A, C | `tipo`, `situacao`, `empresa_id`, `pagina` | 200 | FN06 |

## 8. Indicadores e relatórios (`config/modules/gestao.php`)

| Método | Rota | Finalidade | Perfil | Parâmetros | Respostas | Tarefa |
|--------|------|------------|--------|------------|-----------|--------|
| GET | `/painel` | Painel do escritório: obrigações (hoje, 7 dias, atrasadas, entregues no mês, % no prazo), documentos aguardando análise e vencidos, empresas por semáforo, 5 piores Índices com motivos, carga por responsável + cartões dos módulos contratados | A, C | `escopo` (`meus`\|`todos`) | 200; 403 (CL) | GE02 |
| GET | `/empresas/{id}/visao-geral` | Painel 360º: Índice + motivos, próximas obrigações, pendências, resumo financeiro (com BPO), alertas e ações (com Consultivo) | A, C | — | 200; 404 | GE03 |
| GET | `/empresas/{id}/relatorio` | Relatório mensal imprimível (layout `impressao`) | A, C | `mes` (AAAA-MM; padrão: mês anterior) | 200; 404; 422 (mês inválido) | GE04 |

Cada número do painel é um link para a listagem já filtrada (ex.: "Atrasadas: 4" → `/obrigacoes?situacao=atrasada`).

## 9. Gestão consultiva (`config/modules/consultivo.php`, `contratavel: CONSULTIVO`)

Rotas `/empresas/{empresa}/consultivo*` e de ações exigem `consultivo_ativo = 1` na empresa (senão, 404).

| Método | Rota | Finalidade | Perfil | Payload / parâmetros | Respostas | Tarefa |
|--------|------|------------|--------|----------------------|-----------|--------|
| GET | `/empresas/{empresa}/consultivo` | Radar + linha do tempo + plano de ação | A, C | — | 200; 404 | CO01–CO03 |
| POST | `/empresas/{empresa}/acompanhamentos` | Registrar acompanhamento | A, C | `data_registro`* (≤ hoje), `tipo`*, `titulo`*, `resumo`*, `visivel_cliente` | 302; 422 | CO01 |
| GET | `/empresas/{empresa}/acompanhamentos/{id}/editar` | Formulário | A, C | — | 200; 404 | CO01 |
| POST | `/empresas/{empresa}/acompanhamentos/{id}` | Atualizar | A, C | idem + `versao`* | 302; 422; 409 | CO01 |
| POST | `/empresas/{empresa}/acompanhamentos/{id}/excluir` | Excluir | A | — | 302; 409 (possui ações) | CO01 |
| POST | `/empresas/{empresa}/acoes` | Criar ação | A, C | `titulo`*, `descricao`, `responsavel_tipo`*, `responsavel_id` (se ESCRITORIO), `prazo`, `acompanhamento_id`, `origem_alerta` (código válido do radar), `visivel_cliente` | 302; 422 | CO02 |
| POST | `/empresas/{empresa}/acoes/{id}` | Atualizar | A, C | idem + `versao`* | 302; 422; 409 | CO02 |
| POST | `/empresas/{empresa}/acoes/{id}/status` | Mudar status | A, C | `status`*, `versao`* | 302; 409 (transição inválida) | CO02 |
| POST | `/empresas/{empresa}/acoes/{id}/excluir` | Excluir | A | — | 302 | CO02 |
| GET | `/consultivo` | Radar da carteira: empresas com alertas CRITICO/ATENCAO e ações atrasadas | A, C | `severidade`, `responsavel`, `pagina` | 200 | CO04 |

**Transições de ação:** `ABERTA → EM_ANDAMENTO | CONCLUIDA | CANCELADA`; `EM_ANDAMENTO → ABERTA | CONCLUIDA | CANCELADA`; `CONCLUIDA → EM_ANDAMENTO` (reabrir); `CANCELADA → ABERTA`.

## 10. Situação de implementação

Nenhuma rota de negócio está implementada. Atualize esta seção quando cada tarefa for concluída, indicando a tarefa e a data.

| Rota | Situação |
|------|----------|
| `GET /` (página provisória da T00) | Provisória: confirma apenas a instalação das dependências |
