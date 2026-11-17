# -Tributo

Plataforma web para **escritórios de contabilidade e seus clientes**. Centraliza o controle de obrigações fiscais, monitoramento de prazos, indicadores gerenciais e regularidade fiscal da carteira.

> **Situação da entrega (MVP):** funcional no ambiente local para os perfis de contador e empresário, **com pendências conhecidas**.
> - **Testes automatizados:** 774 na suíte completa, com **768 aprovados e 6 falhas conhecidas**, todas de identidade visual (ver [seção 3, item 9](#3-funcionalidades-implementadas-no-mvp)).
> - **Teste com usuário real (Q03): ainda não realizado.** Há apenas o roteiro em [docs/TESTE-USUARIO.md](docs/TESTE-USUARIO.md); nenhum resultado foi coletado.
> - Detalhes do andamento e próximos módulos em [docs/ROADMAP.md](docs/ROADMAP.md).

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
4. **Melhoria de usabilidade (Q04):**
   - *Ainda não validada com usuário real (o teste Q03 não foi realizado).*
   - Identificação e badge visual explícito de **Atraso Crítico (> 30 dias)** na listagem e na página de detalhes da obrigação, prevenindo multas e bloqueio de certidões.
5. **Índice de Regularidade (GE01):**
   - Fórmula ponderada determinística calculada sob demanda com relógio injetável.
   - Classificação em semáforos: **Verde** (em dia), **Amarelo** (atenção), **Vermelho** (crítico) e **Sem Histórico**.
6. **Painel do Escritório (GE02 e melhoria Q05, esta ainda não validada com usuário real):**
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
9. **Suíte Consolidada de Segurança e Testes Automatizados** (resultado da última execução, branch `fix/funcionalidade-local`):

   | Verificação | Resultado |
   |---|---|
   | Suíte completa (`composer test`) | **774 testes: 768 aprovados, 6 com falha** |
   | Unitários | 387 testes, 1 falha |
   | Integração | **282 testes, todos aprovados** |
   | E2E | 105 testes, 5 falhas |
   | PHPStan (`composer analyse`) | Sem erros |
   | Lint (`composer lint`) | Sem erros em 204 arquivos |

   - **Falhas conhecidas (6), todas de identidade visual, não de funcionalidade:** `KernelTest::testRotaInicialProvisoriaPassaPeloKernel`, quatro testes de `InterfaceTest` e `PainelTest::testAdminVePainelComEscopoTodosPorPadrao`. Eles esperam o padrão visual da ADR-024 (nome "-Tributo" sem exclamação, Bootstrap e o markup do menu lateral), e as telas ainda usam a versão anterior. A reconciliação visual está pendente.
   - Os testes cobrem isolamento multi-tenant (HTTP 404 em ID de outro escritório), CSRF (HTTP 419 em ausência de token), escape de saída contra XSS, prepared statements contra injeção de SQL, controle de perfis e autenticação (apenas a senha cadastrada é aceita, sem espaços removidos nem senhas alternativas).
   - Ainda **não há testes HTTP automatizados** para o fluxo de solicitações de documentos e o portal do cliente (há testes de model e de upload); esse fluxo foi verificado manualmente por HTTP no ambiente local.
10. **Validação com Usuário Real (Q03): pendente.**
   - O teste com usuário real **ainda não foi realizado**. Não existem tempos, notas nem depoimentos coletados.
   - O roteiro, os campos de registro e os critérios de observação estão em [docs/TESTE-USUARIO.md](docs/TESTE-USUARIO.md), para serem preenchidos após a sessão.

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
   O modelo vem configurado para o **Apache do XAMPP em `htdocs/tributo`**:
   `APP_URL=http://localhost/tributo` e `APP_BASE_PATH=/tributo`. O banco usa `DB_USERNAME=root` e `DB_PASSWORD` vazio (padrão do XAMPP); ajuste se o seu MariaDB tiver senha.
   `APP_URL` e `APP_BASE_PATH` precisam combinar com a forma como você acessa o sistema (ver o passo 6): todos os links e redirecionamentos usam `APP_BASE_PATH` como prefixo.

4. Crie e popule os bancos de dados local e de testes com dados de demonstração (o MariaDB do XAMPP precisa estar ligado):
   ```bash
   php bin/db.php rebuild
   php bin/db.php rebuild --test
   ```
   > O `rebuild` **apaga e recria** os bancos `tributo` e `tributo_test`. Ele serve para o ambiente local e não roda com `APP_ENV=production`. Rode-o também depois de atualizar o código, caso o schema tenha mudado (por exemplo, para criar as tabelas de documentos e avaliações).

5. Verifique a integridade do código e dos testes:
   ```bash
   composer check
   ```
   Na versão atual, o lint e o PHPStan passam sem erros, mas a suíte de testes termina com **6 falhas conhecidas de identidade visual** (ver [seção 3, item 9](#3-funcionalidades-implementadas-no-mvp)), portanto o `composer check` completo ainda não termina em verde. Os testes de integração (`composer test:integration`) passam por inteiro.

6. Acesse a plataforma no navegador, de **uma** das duas formas:
   - **Apache do XAMPP** (com `.env` como no modelo): <http://localhost/tributo/>. O `.htaccess` da raiz encaminha as requisições para `public/` e exige o `mod_rewrite` ativo. **Não use** `/tributo/public/`: com `APP_BASE_PATH=/tributo` esse endereço não casa com as rotas.
   - **Servidor embutido do PHP** (sem Apache): ajuste o `.env` para `APP_URL=http://localhost:8000` e `APP_BASE_PATH=` (vazio) e rode:
     ```bash
     php -S localhost:8000 -t public
     ```
     e acesse <http://localhost:8000/>. Com `APP_BASE_PATH=/tributo` neste modo, os links gerados apontam para um prefixo inexistente.

   > O fluxo local foi verificado com o servidor embutido do PHP e o MariaDB do XAMPP. O acesso pelo Apache em `htdocs/tributo` segue a configuração do modelo, mas não foi reverificado na última atualização desta documentação.

---

## 5. Usuários de Teste (Dados de Demonstração)

Todos os usuários abaixo pertencem aos dados de demonstração gerados pelo seed e possuem a mesma senha: **`Senha@123`**. Essa é a **única** senha aceita: o login compara a senha exatamente como foi digitada (maiúsculas, minúsculas e espaços contam) e não existem senhas alternativas. Depois de 5 tentativas inválidas, a conta fica bloqueada por 15 minutos.

| E-mail | Perfil | Escritório / Empresa | Finalidade de Teste |
|---|:---:|---|---|
| **`contador@teste.com`** | **CONTADOR** (`ADMIN`) | Exemplo Contábil (ID 1) | **Usuário principal do escritório:** acesso a empresas, obrigações, painel, criação e conferência de solicitações de documentos e visualização de avaliações |
| **`empresario@teste.com`** | **EMPRESÁRIO** (`CLIENTE`) | Padaria Alvorada Ltda | **Usuário principal do cliente:** acesso mobile/desktop ao portal da empresa, acompanhamento de guias fiscais, upload de documentos solicitados e tela de avaliação |
| `admin@exemplo.test` | **ADMIN** | Exemplo Contábil (ID 1) | Usuário administrativo secundário para testes |
| `ana@exemplo.test` | **COLABORADOR** | Exemplo Contábil (ID 1) | Operacional: visualização de suas obrigações ("meus"), baixa e filtros |
| `bruno@exemplo.test` | **COLABORADOR** | Exemplo Contábil (ID 1) | Operacional secundário para testes de carga por responsável |
| `cliente.padaria@exemplo.test` | **CLIENTE** | Padaria Alvorada Ltda | Segundo acesso da mesma empresa do `empresario@teste.com` |
| `cliente.oficina@exemplo.test` | **CLIENTE** | Auto Mecânica Central | Cliente secundário (outra empresa) |
| `cliente@modelo.test` | **CLIENTE** | Escritório Modelo (ID 2) | Cliente do segundo escritório, para o teste de isolamento |
| `admin@modelo.test` | **ADMIN** | Escritório Modelo (ID 2) | **Teste de isolamento multi-tenant:** não vê nem acessa nenhuma empresa, obrigação ou documento do Escritório 1 |

Os 9 usuários acima foram conferidos com login real (todos entram com `Senha@123`): perfis ADMIN e COLABORADOR vão para `/painel`, e perfis CLIENTE vão para `/portal`.

---

## 6. Comandos Disponíveis

| Comando | Descrição |
|---|---|
| `composer check` | **Comando mestre:** executa `lint`, `analyse` e `test` (obrigatório antes de commits) |
| `composer lint` | Validação de sintaxe PHP (`php -l`) em todos os diretórios do projeto |
| `composer analyse` | Análise estática com PHPStan (nível rigoroso com tipos declarados) |
| `composer test` | Execução completa de todos os testes (Unitários, Integração e E2E) |
| `composer test:unit` | Execução rápida exclusiva dos testes unitários |
| `composer test:integration` | Testes de integração com o banco `tributo_test` (282 testes, todos aprovados) |
| `php bin/db.php rebuild` | Recria o banco `tributo` com schema DDL e seeds de demonstração |
| `php bin/db.php rebuild --test` | Recria o banco `tributo_test` para a suíte de testes de integração e E2E |
| `php bin/db.php build` | Regenera o arquivo consolidado `database/tributo.sql` a partir dos módulos |

---

## 7. Roteiro de Apresentação (15 Minutos)

Para a banca, siga o roteiro de 15 minutos abaixo, com o ambiente local já preparado (`php bin/db.php rebuild`, MariaDB ligado e sistema acessível). A versão detalhada, passo a passo, está em [docs/ROTEIRO_DEMO.md](docs/ROTEIRO_DEMO.md).

| Bloco | Tempo | Conteúdo |
|---|:---:|---|
| 1. Introdução e problema | 2 min | Dor dos escritórios: perda de prazos, planilhas e cobrança de documentos pelo WhatsApp |
| 2. Visão do contador | 5 min | Painel, Índice de Regularidade, obrigações, status e criação de solicitação de documento |
| 3. Visão do empresário | 3 min | Portal, pendências, guias a vencer e envio de arquivo |
| 4. Inovação e conferência | 2 min | Contador confere e aceita (ou recusa com motivo) o documento; tela de avaliação |
| 5. Arquitetura, segurança e qualidade | 2 min | MVC próprio, isolamento entre escritórios, testes e verificações |
| 6. Teste com usuário e próximos passos | 1 min | Apresentar o que o teste com usuário real mostrou **somente se ele já tiver sido realizado**; caso contrário, declarar que está pendente e apresentar o roteiro |

Pontos de atenção para a demonstração:
- **Contador:** entrar com `contador@teste.com`. **Empresário:** entrar com `empresario@teste.com`. Isolamento: `admin@modelo.test`. Senha de todos: `Senha@123`.
- **Qualidade:** ao mostrar os testes, informar o resultado real (774 testes, 768 aprovados e 6 falhas conhecidas de identidade visual; 282 testes de integração aprovados; PHPStan e lint sem erros).
- **Teste com usuário real (Q03):** ainda não realizado. Não apresente tempos, notas ou depoimentos como resultados; use [docs/TESTE-USUARIO.md](docs/TESTE-USUARIO.md) como roteiro e registre os resultados depois da sessão.
- **Melhorias Q04 e Q05:** estão implementadas, mas só poderão ser apresentadas como "originadas do teste com usuário" depois que o teste ocorrer e as justificativas forem registradas.

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
- [docs/TESTE-USUARIO.md](docs/TESTE-USUARIO.md) — Roteiro e ficha de registro do teste com usuário real (**ainda não executado**; resultados a preencher).
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
