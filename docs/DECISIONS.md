# Registro de decisões arquiteturais (ADR)

> Cada decisão tem contexto, decisão e consequências. Para mudar uma decisão, **não edite a antiga**:
> crie um novo ADR no final do arquivo que a substitua e marque a antiga como "Substituída por ADR-NNN".
> Situação "Proposta" = aguardando aprovação do planejamento (Fase 1).

| ADR | Título | Situação |
|-----|--------|----------|
| 001 | MVC próprio, sem framework | Proposta |
| 002 | Monólito modular com contrato de extensão por módulo | Proposta |
| 003 | Multi-tenant em banco compartilhado com FKs compostas | Proposta |
| 004 | 404 para recursos de outro escritório | Proposta |
| 005 | Módulos contratáveis por escritório e serviços por empresa | Proposta |
| 006 | Composer só para autoload e ferramentas de desenvolvimento | Proposta |
| 007 | Controle de concorrência otimista | Proposta |
| 008 | Exclusão restrita e inativação | Proposta |
| 009 | Uploads fora do webroot | Proposta |
| 010 | Indicadores calculados sob demanda, com relógio injetável | Proposta |
| 011 | Dinheiro em centavos inteiros | Proposta |
| 012 | Schema em arquivos por módulo e SQL consolidado gerado | Proposta |
| 013 | Configuração por ambiente e `.htaccess` na raiz | Proposta |
| 014 | Compatibilidade MariaDB 10.4 e MySQL 8 | Proposta |
| 015 | Controle administrativo, não tributário | Proposta |
| 016 | Idioma dos identificadores | Proposta |
| 017 | Desenvolvimento com várias IAs: posse de arquivos e regras centralizadas | Proposta |
| 018 | Renderização no servidor e JS progressivo, sem bibliotecas de gráficos | Proposta |
| 019 | Estratégia de testes e análise estática | Proposta; meta de cobertura substituída pela ADR-021 |
| 020 | Publicação em hospedagem condicional; apresentação local como plano principal | Proposta (rev. 2) |
| 021 | Testes de segurança obrigatórios; cobertura de 80% como objetivo secundário | Proposta (rev. 2) |
| 022 | Cronograma por capacidade de revisão, núcleo reduzido e ordem de inclusão | Proposta (rev. 2) |
| 023 | Perfil do participante do teste com usuário | Proposta (rev. 2) |

---

### ADR-001 — MVC próprio, sem framework
- **Contexto:** a disciplina exige MVC com PHP puro, PDO e ponto único de entrada; quem desenvolve precisa explicar o código.
- **Decisão:** núcleo próprio e pequeno (Router, Kernel, Container, View, Validator, Session, Csrf), com contratos documentados em ARCHITECTURE §5.
- **Consequências:** (+) domínio total do código, nenhuma "mágica" de framework; (−) segurança e infraestrutura dependem de testes próprios (mitigado com as tarefas 🔒 e a Q01).

### ADR-002 — Monólito modular com contrato de extensão por módulo
- **Contexto:** a visão do produto tem seis áreas, e as tarefas serão executadas em paralelo por IAs diferentes.
- **Decisão:** cada módulo tem pastas próprias dentro das camadas MVC (`Controllers/Fiscal`, `Models/Fiscal`...) e um único ponto de integração: `config/modules/<modulo>.php` (rotas, menu, abas da empresa, cartões do painel).
- **Consequências:** (+) módulos não editam arquivos uns dos outros; a estrutura reflete o modelo comercial; (−) o carregador de módulos é um componente a mais (F05) e precisa de testes.

### ADR-003 — Multi-tenant em banco compartilhado com FKs compostas
- **Contexto:** cada escritório deve ver só os próprios dados; o projeto precisa ser simples de hospedar.
- **Decisão:** um banco para todos; `escritorio_id` em toda tabela de negócio; `UNIQUE (id, escritorio_id)` e FKs compostas; o filtro por escritório é parâmetro obrigatório em todo método de model; o escritório vem só da sessão.
- **Consequências:** (+) isolamento garantido também pelo banco: o DDL especificado foi verificado manualmente no MariaDB 10.4.32 durante a T00, sem gerar arquivo ou teste no repositório (ver DATABASE §8.2); os testes automatizados virão na F03, FI01 e FN01; (−) as FKs ficam mais verbosas; não se usa `ON DELETE SET NULL`.

### ADR-004 — 404 para recursos de outro escritório
- **Decisão:** acesso a um ID de outro escritório (ou de outra empresa, para o cliente) responde 404, nunca 403.
- **Consequências:** não revela a existência de registros alheios (evita enumeração).

### ADR-005 — Módulos contratáveis por escritório e serviços por empresa
- **Contexto:** a proposta comercial prevê um Plano Base e módulos pagos (BPO Financeiro e Consultivo).
- **Decisão:** a tabela `escritorio_modulos` liga os módulos por escritório (`ModuloMiddleware`); as flags `bpo_ativo` e `consultivo_ativo` em `empresas` indicam a quais clientes o escritório vende cada serviço.
- **Consequências:** (+) o sistema demonstra o modelo de negócio (página "Módulo não contratado"); (−) os módulos são ativados pelo seed (sem super-administrador na R1).

### ADR-006 — Composer só para autoload e ferramentas de desenvolvimento
- **Decisão:** sem dependências de produção; PHPUnit e PHPStan só em `require-dev`; `config.platform.php = 8.2.0`; a pasta `vendor/` é gerada no deploy com `--no-dev` e enviada à hospedagem (que pode não ter SSH).
- **Consequências:** (+) superfície de ataque mínima; (−) utilitários (validação, `.env`) são escritos e testados no projeto.

### ADR-007 — Controle de concorrência otimista
- **Decisão:** coluna `versao` nas entidades editáveis; `UPDATE ... WHERE id=? AND escritorio_id=? AND versao=?`; 0 linhas → 409 com os dados digitados preservados.
- **Consequências:** sem locks longos; o usuário é avisado em vez de sobrescrever o trabalho de outro.

### ADR-008 — Exclusão restrita e inativação
- **Decisão:** FKs com RESTRICT; registros com dependentes são inativados; usuários nunca são excluídos; CASCADE apenas em tabelas de vínculo.
- **Consequências:** histórico e auditoria preservados; a interface precisa oferecer "inativar".

### ADR-009 — Uploads fora do webroot
- **Decisão:** arquivos em `storage/uploads/{escritorio_id}/` com nome aleatório; download só pelo controller após a autorização; MIME validado com `finfo`.
- **Consequências:** impede a execução de arquivos enviados e o acesso por URL direta.

### ADR-010 — Indicadores calculados sob demanda, com relógio injetável
- **Decisão:** Índice, indicadores financeiros e radar são calculados a cada acesso com consultas agregadas (sem N+1); "hoje" vem da interface `Relogio`.
- **Consequências:** (+) sempre consistentes e testáveis com datas fixas; (−) reavaliar cache ou snapshots acima de cerca de 500 empresas por escritório.

### ADR-011 — Dinheiro em centavos inteiros
- **Decisão:** `DECIMAL(15,2)` no banco; inteiro em centavos no PHP (`Support\Dinheiro`); `float` proibido para valores.
- **Consequências:** evita erros de arredondamento (`0,1 + 0,2`).

### ADR-012 — Schema em arquivos por módulo e SQL consolidado gerado
- **Contexto:** faixas paralelas criam tabelas; a hospedagem exige um único arquivo para o phpMyAdmin.
- **Decisão:** `database/schema/NN_<modulo>.sql` e `database/seeds/NN_<modulo>.sql`; `bin/db.php build` gera `database/tributo.sql` (sem `CREATE DATABASE`); um teste garante que o arquivo gerado está atualizado.
- **Consequências:** (+) sem conflitos de merge no schema; um comando reconstrói o banco local; (−) é preciso lembrar de rodar `build` (o teste avisa).

### ADR-013 — Configuração por ambiente e `.htaccess` na raiz
- **Decisão:** `.env` por ambiente (`local`, `testing`, `production`), com modelos `.env.example` e `.env.production.example`; `APP_BASE_PATH` explícito (ex.: `/tributo` no XAMPP); o `.htaccess` da raiz redireciona tudo para `public/` e, sem mod_rewrite, nega o acesso.
- **Consequências:** funciona em `htdocs/tributo`, em VirtualHost e em hospedagem sem DocumentRoot configurável, sem expor `app/`, `vendor/` ou `.env`.

### ADR-014 — Compatibilidade MariaDB 10.4 e MySQL 8
- **Contexto:** o XAMPP local tem MariaDB 10.4.32; a hospedagem pode ter MySQL 8.
- **Decisão:** usar só recursos comuns: CHECK, FKs compostas, JSON simples, `DAYOFMONTH`. Charset e collation declarados por tabela. Sem procedures, triggers, `RETURNING` ou funções de janela.
- **Consequências:** o DDL **especificado** em DATABASE §4 foi aceito pelo MariaDB 10.4.32 numa verificação manual e temporária durante a T00 (DATABASE §8.2). Não existe ainda implementação nem teste versionado, e a compatibilidade com o MySQL 8 **não** foi verificada: isso fica para a F03 (localmente) e a Q02 (hospedagem, se houver).

### ADR-015 — Controle administrativo, não tributário
- **Decisão:** o sistema não calcula tributos, não determina obrigações por regime e não atesta regularidade fiscal. O catálogo de obrigações é do escritório; índices, alertas e relatórios exibem um aviso de caráter gerencial; os seeds marcam prazos como exemplo.
- **Consequências:** reduz o risco jurídico e a manutenção de regras legais; evolução futura só com fonte oficial citada.

### ADR-016 — Idioma dos identificadores
- **Decisão:** domínio em português (`Empresa`, `Obrigacao`, `baixar`); infraestrutura com nomes técnicos consagrados (`Router`, `Kernel`, `Request`); banco em português `snake_case` sem acento.
- **Consequências:** o código fica próximo da linguagem do contador e da disciplina; as IAs recebem a regra explícita para não misturar idiomas.

### ADR-017 — Desenvolvimento com várias IAs: posse de arquivos e regras centralizadas
- **Contexto:** Opus planeja; Sonnet, Copilot, Gemini e ChatGPT implementam e revisam; não pode haver dependência de uma única ferramenta.
- **Decisão:** regras em `AGENTS.md` (importado por `CLAUDE.md`, resumido em `.github/copilot-instructions.md`); cartões autocontidos no ROADMAP com lista exclusiva de arquivos; tarefas 🔒 com revisão obrigatória por uma segunda IA; arquivos compartilhados com regra própria (ROADMAP §5).
- **Consequências:** qualquer IA executa qualquer cartão; conflitos de merge ficam raros; mudanças de contrato passam por aprovação.

### ADR-018 — Renderização no servidor e JS progressivo, sem bibliotecas de gráficos
- **Decisão:** HTML gerado no servidor; formulários funcionam sem JS; gráficos em barras CSS ou SVG gerado em PHP; CSP sem scripts inline.
- **Consequências:** simples, seguro e sem dependências de front-end; visual mais modesto.

### ADR-019 — Estratégia de testes e análise estática
- **Decisão:** PHPUnit 11 (unitário, integração com MariaDB real `tributo_test`, E2E com `php -S` + cURL); PHPStan começando no nível 5 (meta: 6 após a R1); `composer check` obrigatório antes de cada commit; ~~cobertura ≥ 80% na lógica de negócio como critério~~ → **ver ADR-021**.
- **Consequências:** sem Node nem navegador automatizado; os testes de integração exigem o MySQL do XAMPP ligado (os unitários não).

---

## Revisão 2 do planejamento

### ADR-020 — Publicação em hospedagem condicional; apresentação local como plano principal
- **Contexto:** a revisão 1 tratava a publicação (Q02) como obrigatória e a agendava para o D3, no meio do núcleo. O trabalho prevê a apresentação local como alternativa válida, e a disponibilidade de hospedagem não depende de quem desenvolve.
- **Decisão:** a Q02 passa a ser **condicional**. Só é iniciada com o núcleo funcionando localmente (GE02), uma hospedagem disponível que atenda os requisitos, e o congelamento feito ou folga de ≥ 0,5 dia. Tem limite de 2 horas; se falhar, o problema é documentado e a apresentação é local. A arquitetura continua preparada para a publicação (ADR-013, `.env.production.example`, `tributo.sql` sem `CREATE DATABASE`).
- **Consequências:** (+) a publicação não consome o tempo do núcleo; (−) problemas específicos da hospedagem (versão do MySQL, `.htaccess`) podem só aparecer tarde, aceitável porque a apresentação local é garantida.

### ADR-021 — Testes de segurança obrigatórios; cobertura de 80% como objetivo secundário
- **Contexto:** medir cobertura exige instalar e configurar o Xdebug ou o PCOV no XAMPP (hoje ausentes), e uma meta numérica rígida pode levar a testes feitos só para "subir o número", tomando tempo do núcleo.
- **Decisão:**
  - **Obrigatórios (critério de entrega, não podem ser cortados):** testes de cada cartão (caminho feliz, validação, isolamento entre escritórios) e a suíte de segurança Q01 (IDOR por rota, CSRF em todo POST, XSS, SQLi, escalada de perfil, sessão, senhas, headers e módulo não contratado).
  - **Objetivo secundário (Q07, prioridade C):** medir a cobertura das pastas de lógica de negócio e buscar 80%. O número é informado no README; **não bloqueia commits nem a entrega**.
- **Consequências:** (+) o esforço vai para os testes que protegem dados e regras; (−) a cobertura pode ficar abaixo de 80% ou não ser medida, o que será declarado com transparência.

### ADR-022 — Cronograma por capacidade de revisão, núcleo reduzido e ordem de inclusão
- **Contexto:** a revisão 1 planejava cerca de 44 tarefas em 7 dias com duas faixas paralelas. O gargalo real é a estudante revisar, testar e **entender** cada entrega (que precisará apresentar), não a geração de código pelas IAs.
- **Decisão:**
  - estimativa por criticidade (🔒 ≈ 2h30, ◆ ≈ 1h45, ○ ≈ 50 min) + rotina diária, com capacidade de 2 a 4 tarefas por dia, a ser **medida** no D1 e no D2;
  - **núcleo M reduzido** ao que os requisitos acadêmicos exigem (C02 e FI05 passam para S), estimado em ≈ 6 dias;
  - a "ordem de corte" é substituída por uma **ordem de inclusão**: os módulos S (Documentos, BPO, Consultivo, Relatório) permanecem no plano do produto e entram um bloco por vez, conforme os pontos de decisão A, B e C. O que não entrar fica ⏭️ na continuação pós-entrega (R1.1), com o cartão preservado;
  - regras de ritmo: uma tarefa só termina quando cada arquivo pode ser explicado; uma tarefa 🔒 em revisão por vez; nenhuma S com uma M vermelha; tarefa acima de 2× a estimativa é dividida ou reduzida.
- **Consequências:** (+) entrega acadêmica protegida, com uma visão de produto completa e honesta; (−) é provável que a entrega de 7 dias mostre só parte dos módulos S. A demonstração e a documentação apresentam os demais como roadmap, nunca como prontos.

### ADR-023 — Perfil do participante do teste com usuário
- **Contexto:** o trabalho exige teste com um **usuário real**. A revisão 1 admitia um estudante de Ciências Contábeis como participante principal.
- **Decisão:** o participante principal (pelo menos 1) deve ser um **contador(a) em atividade**, um **profissional de escritório contábil** ou um **empresário(a) que utiliza serviços contábeis**. Estudantes são avaliadores complementares, com achados registrados à parte, e não substituem o participante principal. O agendamento acontece no D1; a sessão pode ser por videochamada com o sistema local.
- **Consequências:** (+) resultados representativos do público-alvo e cumprimento do requisito; (−) depende da agenda de terceiros; por isso o agendamento é antecipado e há um plano de reagendamento.

### Correção de registro (rev. 2)
As ADR-003 e ADR-014 diziam que o DDL foi "testado durante a T00", o que podia sugerir um banco implementado. O texto foi corrigido para deixar claro que houve apenas uma **verificação manual, temporária e não versionada** do DDL especificado (DATABASE §8.2). Nenhum arquivo `.sql`, script de banco ou teste de banco existe no repositório até a F03.
