# Roteiro e Registro do Teste com Usuário Real (Q03)

> **STATUS: TESTE AINDA NÃO REALIZADO.**
> Este documento é o **roteiro** e a **ficha de registro** do teste de usabilidade do **-Tributo** com um profissional contábil. Nenhuma sessão aconteceu até agora: **não existem tempos, notas, depoimentos nem resultados.** Todos os campos marcados com `______` ou `[ ]` devem ser preenchidos **somente depois da sessão real**, com o que for efetivamente observado.
> Diretrizes: [AGENTS.md](../AGENTS.md) e [ROADMAP.md (Q03)](ROADMAP.md#q03--roteiro-e-execução-do-teste-com-usuário-real--m--).

**Regras de honestidade deste documento:**
- Não preencher tempos, notas ou frases por estimativa ou suposição.
- Não citar o participante sem a autorização registrada na seção 1.
- Se uma tarefa não for feita, registrar "não realizada" e o motivo; não omitir.
- Enquanto a seção 1 estiver em branco, o README e a apresentação devem dizer que o teste está **pendente**.

---

## 0. Preparação (antes da sessão)

- [ ] Participante escolhido e sessão agendada (data: ______).
- [ ] Banco de demonstração recriado com `php bin/db.php rebuild` e sistema acessível (ver [README](../README.md), seção 4).
- [ ] Usuários de teste conferidos (senha única: `Senha@123`): `contador@teste.com` (perfil do escritório) e `empresario@teste.com` (perfil do cliente).
- [ ] Apenas dados **fictícios** na tela. Nada de dados reais de clientes do participante.
- [ ] Termo de consentimento lido e aceito (seção 1).
- [ ] Alguém designado para **facilitar** (explica o cenário, não ajuda a executar) e alguém para **anotar** (pode ser a mesma pessoa).
- [ ] Cronômetro e local para anotar (esta ficha, impressa ou aberta).
- [ ] Combinar com o participante: pensar em voz alta, e dificuldades são do sistema, não dele.

---

## 1. Perfil do Participante e Consentimento

*Preencher no início da sessão. Não registrar nome nem dados que identifiquem o participante, a menos que ele autorize por escrito.*

| Campo | Registro |
|---|---|
| **Data da sessão** | ______ |
| **Duração real** | ______ minutos |
| **Identificação anônima** (ex.: P1) | ______ |
| **Perfil profissional** (ex.: contador autônomo, funcionário de escritório, empresário cliente) | ______ |
| **Tempo de experiência na área** | ______ anos |
| **Porte da carteira** (aprox. nº de clientes e regimes predominantes) | ______ |
| **Ferramentas usadas hoje** (planilha, sistema, WhatsApp...) | ______ |
| **Modalidade** (presencial / videochamada com tela compartilhada) | ______ |
| **Dispositivo e navegador usados** | ______ |
| **Perfil testado** (contador / empresário / ambos) | ______ |

**Consentimento** (marcar apenas se de fato ocorreu):
- [ ] O participante foi informado do objetivo acadêmico e do potencial comercial do projeto.
- [ ] Autorizou o registro anônimo de observações, tempos e respostas.
- [ ] Autorizou citar frases suas, de forma anônima (se não autorizar, registrar só o sentido geral).
- [ ] Foi informado de que todos os dados da sessão são fictícios e de que pode interromper a qualquer momento.

---

## 2. Cenário e Roteiro de Tarefas

O participante recebe o papel de **administrador do escritório contábil** (*Exemplo Contábil*) e executa as tarefas abaixo na ordem, sem ajuda do facilitador. Para cada tarefa, o facilitador lê apenas o objetivo; não diz onde clicar.

### 2.1 Visão do escritório (contador)

| Tarefa | Objetivo operacional | Critério de sucesso |
|---|---|---|
| **T1. Autenticação** | Acessar o sistema com credenciais de administrador | Login efetuado e redirecionamento para o Painel |
| **T2. Diagnóstico no Painel** | Analisar os indicadores de obrigações e o Índice de Regularidade | Identificar o volume de obrigações críticas e as empresas em alerta vermelho |
| **T3. Cadastro de Empresa** | Cadastrar uma nova empresa cliente | Razão social, CNPJ fictício e regime tributário validados e salvos |
| **T4. Configuração Fiscal** | Vincular tipos de obrigação à nova empresa | Tipos de obrigação associados na aba "Obrigações" |
| **T5. Gestão de Prazos** | Localizar obrigações atrasadas utilizando os filtros | Encontrar obrigações com filtro de situação e responsável |
| **T6. Entrega de Obrigação** | Mudar o status de uma obrigação para ENTREGUE com protocolo | Justificativa preenchida, status atualizado e auditoria registrada |
| **T7. Validação do Índice** | Verificar o reflexo da entrega no Painel | Comprovar a atualização da pontualidade e o recálculo do Índice |

### 2.2 Documentos e portal do cliente (inovação)

*Tarefas propostas para validar a funcionalidade de pendências e portal. Se a sessão for curta, priorizar T1–T7 e registrar quais destas ficaram de fora.*

| Tarefa | Objetivo operacional | Critério de sucesso |
|---|---|---|
| **T8. Pedir um documento** | Como contador, criar uma solicitação de documento para uma empresa, com prazo e orientação | Solicitação criada e visível na lista com status "aberta" |
| **T9. Enviar o documento** | Como empresário (`empresario@teste.com`), localizar a pendência no portal e enviar um arquivo | Arquivo enviado; status passa a "em conferência" |
| **T10. Conferir o documento** | Como contador, abrir o arquivo recebido e aceitá-lo, ou recusá-lo informando o motivo | Status "aceita" ou "recusada" com motivo visível ao empresário |
| **T11. Avaliar a plataforma** | Como empresário, preencher a tela de avaliação | Avaliação enviada e visível para a equipe do escritório |

---

## 3. Registro por Tarefa

*Preencher durante a sessão. Tempo = do fim da leitura do objetivo até o participante declarar que terminou ou desistir.*

| Tarefa | Tempo | Concluiu sozinho? (sim / com ajuda / não) | Erros e hesitações observados | Dificuldades e comentários do participante |
|---|:---:|:---:|---|---|
| T1. Autenticação | ______ | ______ | ______ | ______ |
| T2. Diagnóstico no Painel | ______ | ______ | ______ | ______ |
| T3. Cadastro de Empresa | ______ | ______ | ______ | ______ |
| T4. Configuração Fiscal | ______ | ______ | ______ | ______ |
| T5. Gestão de Prazos | ______ | ______ | ______ | ______ |
| T6. Entrega de Obrigação | ______ | ______ | ______ | ______ |
| T7. Validação do Índice | ______ | ______ | ______ | ______ |
| T8. Pedir um documento | ______ | ______ | ______ | ______ |
| T9. Enviar o documento | ______ | ______ | ______ | ______ |
| T10. Conferir o documento | ______ | ______ | ______ | ______ |
| T11. Avaliar a plataforma | ______ | ______ | ______ | ______ |

**Taxa de conclusão sem ajuda:** ______ de ______ tarefas realizadas (calcular só depois da sessão).

**Critérios de observação** (anotar sempre que ocorrer):
- Participante não encontrou um botão, menu ou filtro.
- Participante clicou em lugar errado antes de achar o caminho correto.
- Mensagem de erro não foi compreendida ou não ajudou a corrigir.
- Termo da tela que o participante não entendeu ou entendeu diferente do pretendido.
- Pediu ajuda ao facilitador (registrar em que ponto).
- Reação positiva espontânea (elogio, surpresa, "isso me ajudaria em...").
- Problema técnico (erro 500, página em branco, layout quebrado, tela que não carrega).
- Dificuldade específica em celular ou tela pequena, se usada.

---

## 4. Avaliação Qualitativa

### 4.1 Perguntas abertas (fazer após as tarefas)

Registrar as respostas **com as palavras do participante**, apenas se autorizado na seção 1.

| Pergunta | Resposta |
|---|---|
| Qual foi a parte mais fácil? E a mais confusa? | ______ |
| O que mais chamou sua atenção no Painel? Os indicadores fazem sentido para a sua rotina? | ______ |
| Os termos usados na tela (ex.: Índice de Regularidade, situação, competência) são os que você usa no escritório? | ______ |
| O semáforo e a recomendação de ação ajudam a decidir o que fazer primeiro? | ______ |
| Hoje, como você cobra documentos dos clientes? O fluxo de solicitação e envio pelo portal substituiria isso? | ______ |
| O que faltou para você usar isto no dia a dia? | ______ |
| O que você mudaria primeiro? | ______ |
| Você usaria ou recomendaria para outros escritórios? Por quê? | ______ |
| Pagaria por uma solução assim? Que função seria decisiva? | ______ |

### 4.2 Questionário pós-teste (escala 1 a 5; 1 = muito ruim, 5 = muito bom)

| Pergunta | Nota | Justificativa dada pelo participante |
|---|:---:|---|
| Facilidade de uso e navegação | ______ | ______ |
| Utilidade no dia a dia do escritório | ______ | ______ |
| Clareza dos indicadores e do Índice de Regularidade | ______ | ______ |
| Clareza do fluxo de documentos (pedir, enviar, conferir) | ______ | ______ |
| Sensação de segurança e confiabilidade | ______ | ______ |
| Recomendaria para outros escritórios? (sim / não / talvez) | ______ | ______ |
| Pagaria por esta solução? (sim / não / talvez) | ______ | ______ |

---

## 5. Priorização dos Achados

*Preencher após a sessão, com base na seção 3 e na seção 4. Listar **todos** os achados, não só os que se pretende implementar.*

| # | Achado (o que foi observado) | Evidência (tarefa / fala) | Impacto (A/M/B) | Esforço (A/M/B) | Prioridade | Selecionado para implementar? |
|:-:|---|---|:---:|:---:|:---:|:---:|
| 1 | ______ | ______ | ______ | ______ | ______ | ______ |
| 2 | ______ | ______ | ______ | ______ | ______ | ______ |
| 3 | ______ | ______ | ______ | ______ | ______ | ______ |
| 4 | ______ | ______ | ______ | ______ | ______ | ______ |
| 5 | ______ | ______ | ______ | ______ | ______ | ______ |

---

## 6. Melhorias Identificadas e Implementadas Após o Teste

O requisito da disciplina pede **pelo menos duas melhorias** originadas do teste. Registrar aqui somente melhorias que **de fato** vieram de um achado da seção 5, com o commit que as implementou.

### 6.1 Melhoria 1

| Campo | Registro |
|---|---|
| **Achado de origem** (nº na seção 5) | ______ |
| **Justificativa dada pelo participante** | ______ |
| **O que foi implementado** | ______ |
| **Arquivos / tarefa do roadmap** | ______ |
| **Commit** | ______ |
| **Como foi verificado** (teste automatizado e/ou conferência manual) | ______ |

### 6.2 Melhoria 2

| Campo | Registro |
|---|---|
| **Achado de origem** (nº na seção 5) | ______ |
| **Justificativa dada pelo participante** | ______ |
| **O que foi implementado** | ______ |
| **Arquivos / tarefa do roadmap** | ______ |
| **Commit** | ______ |
| **Como foi verificado** (teste automatizado e/ou conferência manual) | ______ |

### 6.3 Melhorias adicionais (opcional)

______

### 6.4 Achados não implementados (deixados para depois)

| Achado | Motivo de ficar fora desta entrega |
|---|---|
| ______ | ______ |

---

## 7. Sobre as melhorias Q04 e Q05 já presentes no código

O sistema já contém duas melhorias de usabilidade, criadas **antes** de qualquer teste com usuário real:

- **Q04:** destaque visual para obrigações com **atraso crítico (mais de 30 dias)**, na listagem e na tela de detalhes.
- **Q05:** **recomendação de ação** (imediata ou preventiva) junto às empresas prioritárias do Painel.

Elas partiram de hipóteses da equipe e **ainda não foram validadas com usuário real**. Durante o teste, observar e registrar:

- [ ] O participante percebeu o destaque de atraso crítico sem ser avisado? Entendeu o que significa?
- [ ] O participante leu a recomendação do Painel? Ela o ajudou a decidir o que fazer?
- [ ] Ele considera essas melhorias úteis, irrelevantes ou confusas? (resposta: ______)

Se o participante confirmar a utilidade ou sugerir ajustes, registrar o resultado na seção 6. **Não** apresentar Q04 e Q05 como "pedidas pelo usuário" sem esse registro.

---

## 8. Encerramento da Sessão

- [ ] Agradecimento ao participante e confirmação de que os dados são anônimos.
- [ ] Ficha revisada logo após a sessão (completar anotações enquanto estão frescas).
- [ ] Seções 1 a 6 preenchidas com o que de fato ocorreu.
- [ ] README e roteiro de apresentação atualizados com os resultados reais (hoje, ambos dizem que o teste está pendente).

**Resumo para a apresentação** (preencher só após a sessão): ______
