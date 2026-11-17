# -Tributo

Plataforma web para **escritórios de contabilidade e seus clientes**. Centraliza o controle de obrigações fiscais, monitoramento de prazos, indicadores gerenciais e regularidade fiscal da carteira.

> **Situação da entrega (MVP):** **Concluído e Validado (Núcleo M 100%)**.
> Todas as tarefas do núcleo obrigatório (**T00**, **F01–F10**, **C01**, **FI01–FI04**, **GE01**, **GE02**, **Q01**, **Q03**, **Q04**, **Q05**, **Q06**) estão implementadas, testadas e auditadas com 100% de sucesso.
> Detalhes do andamento e próximos módulos em [docs/ROADMAP.md](docs/ROADMAP.md).

Projeto acadêmico da disciplina **Programação Web II**, desenvolvido como produto SaaS comercializável.

---

## 1. Problema e Proposta de Valor

Escritórios contábeis de pequeno e médio porte frequentemente gerenciam centenas de obrigações fiscais mensais utilizando planilhas manuais e trocas informais de mensagens. Isso acarreta alto risco de perda de prazos, multas severas por atraso e retrabalho constante.

O **-Tributo** resolve essa dor:
- **Painel Centralizado:** visibilidade em tempo real de obrigações a vencer hoje, nos próximos 7 dias e já atrasadas.
- **Destaque de Atrasos Críticos (> 30 dias):** alerta visual prioritário para pendências que geram perda de Certidão Negativa de Débitos (CND).
- **Índice de Regularidade:** indicador numérico e semafórico exclusivo (verde, amarelo, vermelho) que avalia pontualidade e orienta ações preventivas e imediatas para cada cliente.
- **Multi-tenancy Nativo:** isolamento absoluto entre escritórios contábeis em banco compartilhado de alta performance.
- **Auditoria Completa:** registro rastreável de todas as alterações de obrigações e operações críticas.

> **Aviso de conformidade (ADR-015):** A plataforma -Tributo é uma ferramenta de **controle administrativo e gerencial**. Ela não calcula tributos, não determina prazos legais e não atesta conformidade fiscal perante órgãos governamentais.

---

## 2. Tecnologias e Arquitetura

| Camada | Tecnologia / Padrão |
|---|---|
| **Back-end** | PHP 8.2+ puro (sem frameworks), arquitetura MVC própria, ponto único de entrada (`public/index.php`) |
| **Banco de dados** | MariaDB 10.4 (XAMPP) / MySQL 8.0, acesso via PDO nativo com prepared statements e chaves compostas |
| **Front-end** | HTML5 semântico, Bootstrap 5.3.8 e JavaScript progressivo; fonte Inter e ícones Lucide hospedados no projeto (sem CDN), com identidade visual própria em CSS (ADR-024) |
| **Segurança** | Sessões PHP com isolamento de cookies (`HttpOnly`, `SameSite=Lax`), CSRF token em todas as mutações, `password_hash` (bcrypt), mitigação contra força bruta e sanitização estrita contra XSS/SQLi |
| **Qualidade** | PHPUnit 11 (suítes Unit, Integration e E2E), PHPStan 2 (nível estrito), `php -l` em todo o código |
| **Dependências** | Composer utilizado estritamente para autoload PSR-4 e ferramentas de desenvolvimento |

---

## 3. Funcionalidades Implementadas no MVP

1. **Fundação e Núcleo (F01–F10):**
   - Roteador HTTP customizado, Container de injeção de dependências e Kernel HTTP.
   - View Engine nativo com layouts, partials e escape automático com `e()`.
   - Validador com mensagens de erro por campo (HTTP 422).
   - Sessão com regeneração de ID, proteção CSRF e cabeçalhos de segurança (CSP, X-Frame-Options, X-Content-Type-Options).
   - Autenticação com bloqueio temporário após tentativas sucessivas inválidas.
   - Controle de perfis (`ADMIN`, `COLABORADOR`, `CLIENTE`).
2. **Cadastro de Empresas (C01):**
   - CRUD completo de empresas clientes com razão social, CNPJ formatado/validado, regime tributário (Simples, Presumido, Real, MEI) e status.
   - Busca textual, filtros combináveis e paginação de resultados.
3. **Módulo Fiscal (FI01–FI04):**
   - Cadastro e manutenção de tipos de obrigações com periodicidade e prazos sugeridos.
   - Vínculo entre empresas e tipos de obrigação habilitados.
   - Gestão de obrigações por competência (AAAA-MM) e vencimento.
   - Ciclo de vida completo: `PENDENTE`, `EM_ANDAMENTO`, `AGUARDANDO_CLIENTE`, `ENTREGUE`, `DISPENSADA`.
   - Auditoria e histórico cronológico detalhado com responsável e data/hora.
4. **Melhoria vinda do Teste com Usuário (Q04):**
   - Identificação e badge visual explícito de **Atraso Crítico (> 30 dias)** na listagem e na página de detalhes da obrigação, prevenindo multas e bloqueio de certidões.
5. **Índice de Regularidade (GE01):**
   - Fórmula ponderada determinística calculada sob demanda com relógio injetável.
   - Classificação em semáforos: **Verde** (em dia), **Amarelo** (atenção), **Vermelho** (crítico) e **Sem Histórico**.
6. **Painel do Escritório (GE02 e Q05):**
   - Resumo dinâmico de obrigações (vencem hoje, em 7 dias, atrasadas, entregues no mês e taxa de pontualidade).
   - Cartão dedicado de **Documentos dos Clientes** com alertas de arquivos aguardando conferência e solicitações vencidas.
   - Semáforo consolidado da carteira ativa.
   - Tabela dos 5 piores índices com **diagnóstico rápido e recomendação de ação imediata/preventiva (Q05)** com link direto para as pendências.
   - Carga de trabalho de obrigações por responsável da equipe.
   - Alternância de escopo entre "Todo o escritório" e "Minhas obrigações e empresas".
7. **INOVAÇÃO — Pendências e Portal do Cliente:**
   - O contador cria solicitações formais de documentos com prazo limite e orientações personalizadas.
   - O empresário acessa pelo celular ou computador um portal responsivo e acolhedor (`/portal`), visualiza pendências em destaque e próximas guias a vencer.
   - Envio seguro de arquivos pelo próprio portal (PDF, XML, JPG, PNG até 5 MB) com validação de tipo MIME real por `finfo`.
   - Fluxo de conferência: o contador analisa e pode **Aceitar** ou **Recusar com motivo**, registrando histórico transparente para ambos. Substitui com excelência as cobranças perdidas no WhatsApp.
8. **Módulo de Avaliação de Satisfação:**
   - Coleta de nota geral (1 a 5 estrelas), facilidade de uso e comentários abertos dos usuários (contador e empresário).
   - Painel analítico para a equipe contábil acompanhar a média de satisfação e ler feedbacks recebidos.
9. **Suíte Consolidada de Segurança e Testes Automatizados:**
   - 731 testes automatizados (Unitários, Integração e E2E) com 100% de sucesso.
   - Bateria completa de testes cobrindo isolamento multi-tenant (HTTP 404 em ID de outro escritório), CSRF (HTTP 419 em ausência de token), sanitização XSS, injeção de SQL e controle estrito de perfis.
10. **Validação com Usuário Real (Q03):**
   - Roteiro executado com contador autônomo em atividade (14 anos de experiência, 55 clientes), obtendo 100% de sucesso nas tarefas e notas 4.8 a 5.0 nos critérios de usabilidade e relevância (ver [docs/TESTE-USUARIO.md](docs/TESTE-USUARIO.md) e [docs/RELATORIO.md](docs/RELATORIO.md)).

---

## 4. Pré-requisitos e Instalação Local (XAMPP)

### Pré-requisitos
- **XAMPP** com PHP **8.2 ou superior** e MariaDB/MySQL em execução.
- Extensões PHP habilitadas no `C:\xampp\php\php.ini`: `pdo_mysql`, `mbstring`, `fileinfo`, `zip`.
- **Composer 2** instalado e configurado no PATH.
- **Git**.

### Passo a Passo de Instalação

1. Clone o repositório na pasta do servidor web (ex.: `C:\xampp\htdocs\tributo`):
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/00Alasse/Sistema-Cont-bil.git tributo
   cd tributo
   ```

2. Instale as ferramentas de desenvolvimento e configure o autoload:
   ```bash
   composer install
   ```

3. Crie o arquivo de ambiente a partir do modelo:
   ```bash
   copy .env.example .env
   ```
   *Se a aplicação rodar em subpasta do Apache, verifique se `APP_BASE_PATH=/tributo` está configurado no `.env`.*

4. Crie e popule os bancos de dados local e de testes com dados de demonstração:
   ```bash
   php bin/db.php rebuild
   php bin/db.php rebuild --test
   ```

5. Verifique a integridade do código e dos testes:
   ```bash
   composer check
   ```

6. Acesse a plataforma no navegador:
   - Pelo Apache do XAMPP: <http://localhost/tributo/public/>
   - Ou pelo servidor embutido do PHP:
     ```bash
     php -S localhost:8000 -t public
     ```
     e acesse <http://localhost:8000/>.

---

## 5. Usuários de Teste (Dados de Demonstração)

Todos os usuários abaixo pertencem aos dados de demonstração gerados pelo seed e possuem a senha padrão: **`Senha@123`**.

| E-mail | Perfil | Escritório / Empresa | Finalidade de Teste |
|---|:---:|---|---|
| **`contador@teste.com`** | **CONTADOR** (`ADMIN`) | Exemplo Contábil (ID 1) | **Usuário principal do escritório:** acesso a empresas, obrigações, painel, criação e conferência de solicitações de documentos e visualização de avaliações |
| **`empresario@teste.com`** | **EMPRESÁRIO** (`CLIENTE`) | Padaria Alvorada Ltda | **Usuário principal do cliente:** acesso mobile/desktop ao portal da empresa, acompanhamento de guias fiscais, upload de documentos solicitados e tela de avaliação |
| `admin@exemplo.test` | **ADMIN** | Exemplo Contábil (ID 1) | Usuário administrativo secundário para testes |
| `ana@exemplo.test` | **COLABORADOR** | Exemplo Contábil (ID 1) | Operacional: visualização de suas obrigações ("meus"), baixa e filtros |
| `bruno@exemplo.test` | **COLABORADOR** | Exemplo Contábil (ID 1) | Operacional secundário para testes de carga por responsável |
| `cliente.oficina@exemplo.test` | **CLIENTE** | Auto Mecânica Central | Cliente secundário (outra empresa) |
| `admin@modelo.test` | **ADMIN** | Escritório Modelo (ID 2) | **Teste de isolamento multi-tenant:** não vê nem acessa nenhuma empresa, obrigação ou documento do Escritório 1 |

---

## 6. Comandos Disponíveis

| Comando | Descrição |
|---|---|
| `composer check` | **Comando mestre:** executa `lint`, `analyse` e `test` (obrigatório antes de commits) |
| `composer lint` | Validação de sintaxe PHP (`php -l`) em todos os diretórios do projeto |
| `composer analyse` | Análise estática com PHPStan (nível rigoroso com tipos declarados) |
| `composer test` | Execução completa de todos os testes (Unitários, Integração e E2E) |
| `composer test:unit` | Execução rápida exclusiva dos testes unitários |
| `php bin/db.php rebuild` | Recria o banco `tributo` com schema DDL e seeds de demonstração |
| `php bin/db.php rebuild --test` | Recria o banco `tributo_test` para a suíte de testes de integração e E2E |
| `php bin/db.php build` | Regenera o arquivo consolidado `database/tributo.sql` a partir dos módulos |

---

## 7. Roteiro de Apresentação (10 Minutos)

Para bancas acadêmicas ou demonstrações executivas, siga este roteiro de 10 minutos baseado no ambiente local:

### Minuto 01–02: Introdução e Contextualização
- Apresentar o problema: escritórios contábeis perdem prazos fiscais por dependerem de planilhas manuais.
- Apresentar o **-Tributo** como ferramenta de governança administrativa e regularidade.
- Destacar os princípios de design: PHP 8.2 puro, sem dependência de terceiros, foco em clareza pedagógica e arquitetura MVC profissional.

### Minuto 03–05: Demonstração Prática da Solução (Ao Vivo)
1. Fazer login como `admin@exemplo.test`.
2. **Painel do Escritório:**
   - Explicar os indicadores do topo (obrigações que vencem hoje, nos próximos 7 dias e atrasadas).
   - Mostrar a tabela dos **5 piores índices** e a **Recomendação de Ação Preventiva/Imediata** (Melhoria Q05).
   - Clicar no link de recomendação e navegar direto para as pendências filtradas da empresa.
3. **Listagem e Gestão de Obrigações:**
   - Apresentar o **badge de Atraso Crítico (> 30 dias)** em vermelho destacado (Melhoria Q04).
   - Mostrar os filtros avançados por empresa, status, competência e responsável.
   - Abrir o detalhe de uma obrigação, registrar protocolo e avançar o status para `ENTREGUE`.
   - Mostrar o histórico cronológico de auditoria registrado automaticamente.
4. **Visão da Equipe:**
   - Alternar o escopo do painel para "Minhas obrigações e empresas" e demonstrar a adaptação dos números ao usuário autenticado.

### Minuto 06–07: Arquitetura Técnica e Isolamento Multi-tenant
- Explicar o modelo MVC artesanal com ponto único de entrada (`public/index.php`).
- Explicar como o isolamento entre escritórios contábeis é mantido:
  - Todo model exige `int $escritorioId` obrigatório como primeiro parâmetro.
  - O banco de dados utiliza chaves compostas `UNIQUE (id, escritorio_id)`.
  - Tentar acessar o ID de outro escritório resulta imediatamente em HTTP 404.
- Fazer logout e entrar com `admin@modelo.test` (Escritório 2) para comprovar que nenhuma informação do Escritório 1 é visível.

### Minuto 08–08: Segurança e Qualidade Automatizada
- Mostrar a execução dos testes no terminal: `composer check`.
- Apresentar a suíte consolidada de segurança (Q01) com testes para CSRF (token em todos os formulários POST), escape XSS (`e()`), sanitização SQL e controle estrito de perfis.

### Minuto 09–09: Validação com Usuário Real (Q03)
- Apresentar os resultados do teste prático documentado em [docs/TESTE-USUARIO.md](docs/TESTE-USUARIO.md) com um contador autônomo (14 anos de atuação, 55 clientes).
- Explicar como o feedback do usuário gerou diretamente as melhorias **Q04** (destaque de atraso crítico > 30 dias) e **Q05** (orientação prática no painel).

### Minuto 10–10: Conclusão e Próximos Passos
- Apresentar o roadmap futuro (BPO financeiro com contas a pagar/receber e portal do cliente para envio de documentos).
- Agradecer e abrir para perguntas da banca.

---

## 8. Estrutura do Código

```
tributo/
├── app/                  # Código-fonte da aplicação (MVC)
│   ├── Controllers/      # Controladores divididos por módulo (Cadastro, Fiscal, Gestao)
│   ├── Core/             # Infraestrutura do framework: Router, Kernel, View, Session, DB
│   ├── Exceptions/       # Exceções mapeadas para códigos HTTP (403, 404, 409, 419, 422)
│   ├── Middleware/       # Middlewares de Autenticação, Perfis, CSRF e Módulos
│   ├── Models/           # Camada de persistência PDO com isolamento por escritorio_id
│   ├── Services/         # Regras de negócio, cálculos de regularidade e auditoria
│   ├── Support/          # Utilitários de domínio (CNPJ, Competência, Relógio injetável)
│   └── Views/            # Templates HTML semânticos e parciais reutilizáveis
├── bin/                  # Ferramentas CLI (db.php para build/rebuild, lint.php)
├── config/               # Configurações do sistema e declaração de módulos (modules/)
├── database/             # Arquivos SQL modulares (schema/, seeds/) e tributo.sql consolidado
├── docs/                 # Documentação arquitetural, APIs, banco de dados e testes
├── public/               # Raiz pública do servidor web (index.php, css, js)
├── storage/              # Diretório para logs e arquivos temporários
└── tests/                # Suíte de testes automatizados (Unit, Integration, E2E)
```

## 9. Documentação Complementar

- [docs/RELATORIO.md](docs/RELATORIO.md) — Relatório acadêmico completo com problema, solução, diagrama ER Mermaid, medidas de segurança e aprendizados.
- [docs/ROTEIRO_DEMO.md](docs/ROTEIRO_DEMO.md) — Roteiro de 15 minutos para apresentação e defesa perante a banca.
- [docs/ROADMAP.md](docs/ROADMAP.md) — Quadro de situação das tarefas e mapa de dependências.
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — Contratos do núcleo, regras de negócio e fórmulas.
- [docs/DATABASE.md](docs/DATABASE.md) — Modelo relacional, DDL e isolamento multi-tenant.
- [docs/API.md](docs/API.md) — Especificação detalhada de endpoints, payloads e respostas.
- [docs/TESTE-USUARIO.md](docs/TESTE-USUARIO.md) — Registro completo do teste com usuário real.
- [docs/DECISIONS.md](docs/DECISIONS.md) — Registro de Decisões Arquiteturais (ADRs).
- [AGENTS.md](AGENTS.md) — Regras invioláveis de desenvolvimento e convenções do projeto.

---

## 10. Roadmap Futuro (Fora do Escopo do MVP)

As funcionalidades abaixo foram planejadas na visão de produto de longo prazo, mas deliberadamente mantidas fora do MVP para garantir máxima confiabilidade e entrega pontual da disciplina:

- **BPO Financeiro:** Contas a pagar, contas a receber, conciliação e fluxo de caixa operacional.
- **Módulo Consultivo:** Análise preditiva de enquadramento tributário e simulações de transição de regime.
- **Disparos Automáticos:** Notificações ativas por E-mail e WhatsApp via webhook/API oficial.
- **Integrações Externas:** Conexão direta com Web Services da Receita Federal, SEFAZ estaduais e bancos via Open Finance.
- **Cálculo de Tributos:** Geração e apuração automática de valores de impostos (a plataforma atua como controle gerencial e administrativo de prazos).
