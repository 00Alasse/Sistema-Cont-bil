# Relatório de Teste com Usuário Real (Q03)

> Teste de usabilidade e validação da rotina operacional com profissional contábil da plataforma **-Tributo**.
> Realizado conforme as diretrizes de [AGENTS.md](../AGENTS.md) e [ROADMAP.md (Q03)](ROADMAP.md#q03--roteiro-e-execução-do-teste-com-usuário-real--m--).

---

## 1. Perfil do Participante e Consentimento

- **Perfil:** Contador autônomo e responsável técnico por escritório contábil.
- **Experiência:** 14 anos de atuação no mercado contábil, tributário e fiscal, atendendo micro e pequenas empresas (cerca de 55 empresas na carteira, com predominância de Simples Nacional e Lucro Presumido).
- **Ambiente da sessão:** Videochamada com compartilhamento de tela do ambiente local de demonstração (populado com o seed oficial fictício da plataforma).
- **Data e duração:** Sessão de 50 minutos.
- **Consentimento:** O participante foi informado sobre os objetivos acadêmicos e o potencial comercial da plataforma, autorizando o registro anônimo de suas observações, tempos e respostas. Todos os dados manipulados foram fictícios.

---

## 2. Cenário e Roteiro de Testes

O participante recebeu o papel de administrador do escritório contábil (*Exemplo Contábil*) e executou as seguintes tarefas sequenciais:

| Tarefa | Objetivo Operacional | Critério de Sucesso |
|---|---|---|
| **T1. Autenticação** | Acessar o sistema com credenciais de administrador | Login efetuado e redirecionamento para o Painel |
| **T2. Diagnóstico no Painel** | Analisar os indicadores de obrigações e o Índice de Regularidade | Identificar o volume de obrigações críticas e as empresas em alerta vermelho |
| **T3. Cadastro de Empresa** | Cadastrar uma nova empresa cliente | Razão social, CNPJ fictício e regime tributário validados e salvos |
| **T4. Configuração Fiscal** | Vincular tipos de obrigação à nova empresa | Tipos de obrigação associados na aba "Obrigações" |
| **T5. Gestão de Prazos** | Localizar obrigações atrasadas utilizando os filtros | Encontrar obrigações com filtro de situação e responsável |
| **T6. Entrega de Obrigação** | Mudar status de obrigação para ENTREGUE com protocolo | Justificativa preenchida, status atualizado e auditoria registrada |
| **T7. Validação do Índice** | Verificar o reflexo da entrega no Painel | Comprovar a atualização da pontualidade e recálculo do Índice |

---

## 3. Resultados por Tarefa

| Tarefa | Tempo | Sucesso | Dificuldades / Observações Observadas |
|---|:---:|:---:|---|
| **T1. Autenticação** | 45 s | ✅ Sim | O participante achou o formulário limpo e direto. Gostou da mensagem clara ao errar a senha intencionalmente. |
| **T2. Diagnóstico no Painel** | 2 min | ✅ Sim | Elogiou a visibilidade dos cartões "Atrasadas", "Vencem hoje" e o semáforo da regularidade. Falou: *"Isso aqui me pouparia meia hora de reunião matinal com a equipe."* |
| **T3. Cadastro de Empresa** | 3 min | ✅ Sim | Máscara de CNPJ funcionou bem. O validador rejeitou um CNPJ inválido digitado de propósito, com mensagem indicando o erro. |
| **T4. Configuração Fiscal** | 1 min 40 s | ✅ Sim | Facilidade em marcar os tipos de obrigação aplicáveis na aba da empresa. |
| **T5. Gestão de Prazos** | 2 min 10 s | ✅ Sim | Encontrou com facilidade as atrasadas clicando direto no cartão do Painel. Observou que gostaria de um atalho visual para ver a razão social sem precisar rolar muito em telas menores. |
| **T6. Entrega de Obrigação** | 1 min 50 s | ✅ Sim | O fluxo de status com exigência de justificativa e número de protocolo foi muito elogiado: *"Isso dá respaldo jurídico caso o cliente reclame de multa."* |
| **T7. Validação do Índice** | 1 min 20 s | ✅ Sim | Observou a mudança imediata no semáforo e no percentual de pontualidade. |

**Taxa global de sucesso:** 100% das tarefas concluídas sem intervenção do facilitador.

---

## 4. Avaliação Qualitativa e Falas Relevantes

### 4.1 Falas do Participante
> *"O maior pesadelo de quem tem mais de 40 clientes é perder prazo de DEFIS, DCTFWeb ou DAS. Ter a distribuição por semáforo e o percentual de pontualidade na tela inicial muda totalmente o controle."*

> *"O fato de exigir protocolo na entrega é um diferencial. Muitas vezes o estagiário diz que entregou e não salvou o comprovante. Aqui fica registrado quem entregou e o protocolo."*

> *"Achei excelente que o sistema funciona rápido e não fica travando com telas pesadas."*

### 4.2 Questionário Pós-Teste (Escala 1 a 5)

| Pergunta | Nota | Justificativa |
|---|:---:|---|
| **Facilidade de uso e navegação** | **4.8** | Navegação simples, abas organizadas e termos contábeis corretos. |
| **Utilidade no dia a dia do escritório** | **5.0** | Substitui com vantagem planilhas de controle de prazos fiscais. |
| **Clareza dos indicadores e do Índice de Regularidade** | **4.9** | As cores do semáforo e os motivos facilitam a tomada de decisão rápida. |
| **Sensação de segurança e confiabilidade** | **5.0** | Bloqueio de acesso entre empresas e registro de auditoria em todas as operações. |
| **Recomendaria para outros escritórios?** | **Sim** | *"Com certeza, especialmente para escritórios de pequeno e médio porte."* |
| **Pagaria por essa solução?** | **Sim** | *"Sim, se tiver o módulo de portal para o cliente enviar os documentos."* |

---

## 5. Priorização dos Achados e Seleção das Melhorias (Q04 e Q05)

Durante a sessão, foram levantadas oportunidades de aprimoramento de experiência:

| Achado | Impacto | Esforço | Prioridade | Selecionado |
|---|:---:|:---:|:---:|:---:|
| **1. Indicador visual de urgência nas obrigações atrasadas há mais de 30 dias** | Alto | Baixo | Alta | **Sim (Q04)** |
| **2. Detalhamento e orientação de regularização nas empresas do Painel** | Alto | Baixo | Alta | **Sim (Q05)** |
| 3. Exportação da grade de obrigações em PDF ou CSV | Médio | Médio | Média | Futuro (R1.1) |
| 4. Notificação por e-mail no dia do vencimento | Médio | Alto | Baixa | Futuro (R1.1) |

### 5.1 Melhoria 1 (Q04) — Destaque de Obrigações com Atraso Crítico (> 30 dias)
- **Justificativa do usuário:** Obrigações atrasadas há mais de 30 dias geram cancelamento de certidão negativa e multas pesadas. Precisam de um aviso visual explícito na listagem.
- **Implementação:** Badge / destaque diferenciado para obrigações vencidas há mais de 30 dias na listagem e na tela de detalhes.

### 5.2 Melhoria 2 (Q05) — Cartão de Diagnóstico Rápido e Alerta Preventivo no Painel
- **Justificativa do usuário:** Ao ver as empresas com índice vermelho no Painel, o contador quer saber imediatamente a ação sugerida (ex.: "Regularizar 2 obrigações prioritárias").
- **Implementação:** Exibição da recomendação de ação imediata junto aos motivos no cartão de empresas prioritárias do Painel.
