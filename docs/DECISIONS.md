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
| 019 | Estratégia de testes e análise estática | Proposta |

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
- **Consequências:** (+) isolamento garantido também pelo banco (testado no MariaDB 10.4 durante a T00); (−) as FKs ficam mais verbosas; não se usa `ON DELETE SET NULL`.

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
- **Consequências:** o DDL foi executado e testado no MariaDB 10.4.32 durante a T00.

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
- **Decisão:** PHPUnit 11 (unitário, integração com MariaDB real `tributo_test`, E2E com `php -S` + cURL); PHPStan começando no nível 5 (meta: 6 após a R1); `composer check` obrigatório antes de cada commit; cobertura ≥ 80% na lógica de negócio (Xdebug ou PCOV, configurados na Q01).
- **Consequências:** sem Node nem navegador automatizado; os testes de integração exigem o MySQL do XAMPP ligado (os unitários não).
