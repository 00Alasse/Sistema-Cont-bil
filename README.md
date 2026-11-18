# -Tributo

Plataforma web para **escritórios de contabilidade e seus clientes**. Reúne em um só lugar o cadastro da carteira de empresas, o controle de obrigações e prazos, a cobrança e conferência de documentos e um painel gerencial com o Índice de Regularidade de cada empresa.

Projeto acadêmico da disciplina **Programação Web II**, desenvolvido com a visão de um produto SaaS comercializável.

> **Situação atual (MVP):** funcional no ambiente local para a equipe do escritório e para o cliente (empresário).
> - **Testes automatizados:** na última execução registrada (17/11/2026), **841 testes, 5.917 asserções e 0 falhas**; PHPStan sem erros; lint sem erros em 212 arquivos.
> - **Teste com usuário real: ainda não realizado.** Existe apenas o roteiro em [docs/TESTE-USUARIO.md](docs/TESTE-USUARIO.md); nenhum resultado foi coletado.
> - Andamento das tarefas e próximos módulos: [docs/ROADMAP.md](docs/ROADMAP.md).

---

## Sumário

1. [Problema e proposta](#1-problema-e-proposta)
2. [Público-alvo](#2-público-alvo)
3. [Funcionalidades implementadas](#3-funcionalidades-implementadas)
4. [Diferencial: fluxo de documentos entre escritório e cliente](#4-diferencial-fluxo-de-documentos-entre-escritório-e-cliente)
5. [Tecnologias e arquitetura](#5-tecnologias-e-arquitetura)
6. [Requisitos](#6-requisitos)
7. [Instalação e configuração local](#7-instalação-e-configuração-local)
8. [Usuários de demonstração](#8-usuários-de-demonstração)
9. [Testes e qualidade](#9-testes-e-qualidade)
10. [Comandos disponíveis](#10-comandos-disponíveis)
11. [Estrutura do projeto](#11-estrutura-do-projeto)
12. [Roteiro de apresentação](#12-roteiro-de-apresentação)
13. [Documentação complementar](#13-documentação-complementar)
14. [Limitações conhecidas e fora do escopo](#14-limitações-conhecidas-e-fora-do-escopo)

---

## 1. Problema e proposta

Escritórios contábeis de pequeno e médio porte costumam controlar obrigações e prazos em planilhas e cobrar documentos dos clientes por WhatsApp e e-mail, sem prazo, status nem histórico. Isso aumenta o risco de atrasos, retrabalho e multas.

O **-Tributo** organiza essa rotina:
- **Painel do escritório:** obrigações atrasadas, que vencem hoje e nos próximos 7 dias, entregas do mês e carga de trabalho por responsável.
- **Destaque de atraso crítico (> 30 dias):** obrigações em aberto vencidas há mais de 30 dias aparecem com sinalização própria.
- **Índice de Regularidade (0 a 100):** indicador gerencial com semáforo (verde, amarelo, vermelho, sem histórico) e recomendação de ação para as empresas com pior resultado.
- **Documentos e portal do cliente:** o escritório solicita documentos com prazo; o cliente envia pelo portal; o escritório aceita ou recusa com motivo.
- **Isolamento entre escritórios:** cada escritório só enxerga os próprios dados, no mesmo banco.

> **Aviso de conformidade (ADR-015):** o -Tributo é uma ferramenta de **controle administrativo e gerencial**. Ele não calcula tributos, não determina prazos legais e não atesta regularidade fiscal perante órgãos públicos. Os índices são calculados apenas com os registros do próprio escritório.

---

## 2. Público-alvo

| Quem | Como usa o sistema |
|---|---|
| Escritórios de contabilidade pequenos e médios (titular e equipe) | Cadastram empresas e obrigações, acompanham prazos, solicitam e conferem documentos, consultam o painel |
| Clientes do escritório (MEI, ME, EPP) | Acessam o portal da própria empresa para ver pendências e guias e enviar documentos |

---

## 3. Funcionalidades implementadas

### Perfis de acesso

| Perfil | Quem é | Página inicial após o login |
|---|---|---|
| `ADMIN` | Titular/administrador do escritório (o "contador" da demonstração) | `/painel` |
| `COLABORADOR` | Membro da equipe do escritório | `/painel` |
| `CLIENTE` | Empresário vinculado a uma empresa da carteira | `/portal` |

Cada rota exige login e o perfil correto; acessar dados de outro escritório responde **404**.

### Equipe do escritório (`ADMIN` e `COLABORADOR`)

- **Painel (`/painel`):**
  - indicadores de obrigações: atrasadas, vencem hoje, vencem em 7 dias, entregues no mês e pontualidade;
  - cartão **Documentos dos clientes** (arquivos aguardando conferência e solicitações vencidas);
  - semáforo do Índice de Regularidade da carteira, com filtro da tabela ao clicar em cada cor;
  - tabela dos **5 piores índices**, com motivos, recomendação de ação e link para as pendências da empresa;
  - carga de obrigações por responsável;
  - alternância de escopo entre "Todo o escritório" e "Minhas obrigações e empresas".
- **Empresas (`/empresas`):** listagem com busca, filtros (inclusive por situação ativa/inativa) e paginação; cadastro e edição com CNPJ validado e regime tributário (MEI, Simples Nacional, Lucro Presumido, Lucro Real, apenas informativo). A exclusão é restrita ao `ADMIN`.
- **Tipos de obrigação (`/tipos-obrigacao`):** catálogo do escritório com esfera, periodicidade e regra de vencimento. Todos consultam; só o `ADMIN` cria, edita e exclui.
- **Obrigações por empresa:** escolha dos tipos de obrigação habilitados para cada empresa.
- **Obrigações (`/obrigacoes`):** cadastro por competência (AAAA-MM) e vencimento, com filtros e paginação; mudança de status (`PENDENTE`, `EM_ANDAMENTO`, `AGUARDANDO_CLIENTE`, `ENTREGUE`, `DISPENSADA`) com histórico de quem alterou e quando; destaque de **atraso crítico (> 30 dias)** na listagem e no detalhe. A exclusão é restrita ao `ADMIN`.
- **Documentos (`/solicitacoes`):** criação de solicitações com prazo e orientações; filtros por empresa e situação; download do arquivo enviado; **aceite** ou **recusa com motivo**.
- **Avaliações (`/avaliacoes`):** total de avaliações recebidas, médias de nota geral e de facilidade de uso, e comentários.
- **Usuários (`/usuarios`, só `ADMIN`):** listagem dos usuários do escritório.

### Cliente (`CLIENTE`)

- **Portal (`/portal`):** resumo das pendências da própria empresa, lista de documentos solicitados (com prazo, situação e motivo de recusa, quando houver) e lista de guias/obrigações da empresa.
- **Envio de documentos:** upload pelo portal de PDF, XML, JPG ou PNG de até 5 MB, com reenvio quando o documento é recusado.
- **Avaliar o sistema (`/avaliacao`):** formulário com nota geral, facilidade de uso (1 a 5) e comentário. A mesma tela também está disponível para a equipe do escritório.

### Interface

- HTML gerado no servidor, com Bootstrap 5.3.8, fonte Inter e ícones Lucide hospedados no próprio projeto (ADR-024).
- Menu lateral fixo a partir de 992 px, que pode ser recolhido (a escolha fica salva no navegador); abaixo disso, o menu abre como painel lateral (offcanvas).
- As funções essenciais continuam utilizáveis sem JavaScript; a recusa de documentos, por exemplo, usa um modal com JavaScript e um formulário simples sem ele.

> As melhorias **atraso crítico (> 30 dias)** e **recomendação de ação no painel** estão implementadas, mas **ainda não foram validadas com usuários reais**: o teste com usuário não foi realizado.

---

## 4. Diferencial: fluxo de documentos entre escritório e cliente

O principal diferencial do -Tributo é substituir a cobrança informal de documentos por um fluxo com prazo, situação e histórico:

1. O escritório cria uma **solicitação** para uma empresa (título, orientações e prazo).
2. O empresário vê a pendência no **portal** e **envia o arquivo**.
3. O escritório **baixa e confere** o documento.
4. O escritório **aceita** o documento ou o **recusa informando o motivo**.
5. Em caso de recusa, o empresário vê o motivo no portal e **reenvia** o arquivo corrigido.

Solicitações vencidas e arquivos aguardando conferência aparecem no painel do escritório.

---

## 5. Tecnologias e arquitetura

| Camada | Tecnologia / padrão |
|---|---|
| **Back-end** | PHP 8.2+ puro, sem frameworks; MVC próprio (`Router`, `Kernel`, `Container`, `View`); ponto único de entrada em `public/index.php` |
| **Banco de dados** | MariaDB 10.4 (XAMPP) ou MySQL 8, via PDO com prepared statements; chaves compostas com `escritorio_id` para o isolamento entre escritórios |
| **Front-end** | HTML semântico renderizado no servidor, Bootstrap 5.3.8, fonte Inter e ícones Lucide, todos locais (sem CDN); JavaScript apenas como melhoria progressiva |
| **Segurança** | Sessão com cookie `HttpOnly` e `SameSite=Lax` e regeneração de ID no login; token CSRF em todos os formulários POST; senhas com `password_hash`/`password_verify`; bloqueio após tentativas de login inválidas; escape de saída com `e()`; cabeçalhos `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options` e `Referrer-Policy`; uploads fora da pasta pública, com nome aleatório e verificação do tipo real (`finfo`) |
| **Qualidade** | PHPUnit 11 (suítes Unit, Integration e E2E), PHPStan 2 (nível 5) e verificação de sintaxe (`php -l`) |
| **Dependências** | Composer apenas para autoload (PSR-4) e ferramentas de desenvolvimento; não há dependências PHP em produção |

Organização do código:
- **Controllers** leem a requisição, validam e chamam services ou models (sem SQL).
- **Services** concentram regras, transições de status, transações e auditoria.
- **Models** fazem o SQL, sempre filtrando pelo escritório do usuário logado.
- **Módulos** declaram rotas, menu e permissões em `config/modules/*.php`.

Detalhes em [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) e decisões em [docs/DECISIONS.md](docs/DECISIONS.md).

---

## 6. Requisitos

- **PHP 8.2 ou superior** (o XAMPP para Windows já inclui), com as extensões `pdo_mysql`, `mbstring` e `fileinfo` habilitadas no `php.ini`. A extensão `zip` é recomendada para o Composer.
- **MariaDB 10.4+** ou **MySQL 8** em execução (o MariaDB do XAMPP serve).
- **Composer 2** no PATH.
- **Git**.

---

## 7. Instalação e configuração local

### 7.1 Obter o código e as dependências

```bash
cd C:\xampp\htdocs
git clone https://github.com/00Alasse/Sistema-Cont-bil.git tributo
cd tributo
composer install
```

### 7.2 Configurar o ambiente

```bash
copy .env.example .env
```

O modelo já vem pronto para o **Apache do XAMPP em `htdocs/tributo`** (`APP_URL=http://localhost/tributo` e `APP_BASE_PATH=/tributo`) e para o banco padrão do XAMPP (`DB_USERNAME=root`, `DB_PASSWORD` vazio). Ajuste se o seu MariaDB/MySQL tiver senha.

`APP_URL` e `APP_BASE_PATH` precisam corresponder à forma de acesso (ver 7.4): todos os links e redirecionamentos usam `APP_BASE_PATH` como prefixo. Nunca versione o `.env`.

### 7.3 Criar o banco de dados com dados de demonstração

Com o MariaDB/MySQL ligado, rode:

```bash
php bin/db.php rebuild          # banco da aplicação (tributo)
php bin/db.php rebuild --test   # banco dos testes (tributo_test)
```

- Cada comando **apaga e recria** o respectivo banco (`DROP DATABASE` e `CREATE DATABASE`), aplica o schema de `database/schema/` e carrega os dados fictícios de `database/seeds/`.
- Use `--sem-seed` para criar só as tabelas.
- O `rebuild` é recusado quando `APP_ENV=production`.
- Rode-o de novo depois de atualizar o código, se o schema tiver mudado.

**Alternativa: importar `database/tributo.sql` (por exemplo, pelo phpMyAdmin).** Esse arquivo é gerado por `php bin/db.php build` a partir de `database/schema/` e `database/seeds/`; não o edite à mão.

Ele **não cria o banco**: contém apenas a remoção e a criação das tabelas e os dados de demonstração. Antes de importar:
1. Crie o banco `tributo` com `utf8mb4` / `utf8mb4_unicode_ci`.
2. Selecione-o e só então importe o arquivo.

A importação apaga as tabelas do -Tributo que já existirem nesse banco.

### 7.4 Iniciar e acessar

Use **uma** das duas formas:

- **Apache do XAMPP** (com o `.env` do modelo): acesse <http://localhost/tributo/>.
  - O `.htaccess` da raiz encaminha as requisições para `public/` e exige o `mod_rewrite` ativo.
  - Não use `/tributo/public/`: com `APP_BASE_PATH=/tributo`, esse endereço não corresponde às rotas.
- **Servidor embutido do PHP** (sem Apache): no `.env`, defina `APP_URL=http://localhost:8000` e `APP_BASE_PATH=` (vazio). Depois rode:
  ```bash
  php -S localhost:8000 -t public
  ```
  e acesse <http://localhost:8000/>.

A página inicial tem o botão **Entrar**, que leva a `/login`.

> O fluxo local foi verificado com o servidor embutido do PHP e o MariaDB do XAMPP. O acesso pelo Apache em `htdocs/tributo` segue a configuração do modelo, mas não foi reverificado na última atualização desta documentação.

---

## 8. Usuários de demonstração

Todos os usuários abaixo são **fictícios**, criados pelo seed, e usam a mesma senha: **`Senha@123`**.

A senha é comparada exatamente como digitada (maiúsculas, minúsculas e espaços contam). Depois de 5 tentativas inválidas, o acesso fica bloqueado por 15 minutos; os dois valores são configuráveis no `.env`.

| E-mail | Perfil | Escritório / empresa | Uso sugerido |
|---|:---:|---|---|
| **`contador@teste.com`** | `ADMIN` | Exemplo Contábil | **Contador da demonstração:** painel, empresas, obrigações, documentos, avaliações e usuários |
| **`empresario@teste.com`** | `CLIENTE` | Padaria Pão da Praça Ltda. (Exemplo Contábil) | **Empresário da demonstração:** portal, envio de documentos e avaliação |
| `admin@exemplo.test` | `ADMIN` | Exemplo Contábil | Administrador secundário |
| `ana@exemplo.test` | `COLABORADOR` | Exemplo Contábil | Colaboradora (escopo "Minhas obrigações e empresas") |
| `bruno@exemplo.test` | `COLABORADOR` | Exemplo Contábil | Colaborador secundário (carga por responsável) |
| `cliente.padaria@exemplo.test` | `CLIENTE` | Padaria Pão da Praça Ltda. | Segundo acesso da mesma empresa do `empresario@teste.com` |
| `cliente.oficina@exemplo.test` | `CLIENTE` | Oficina Motor Amigo Ltda. | Cliente de outra empresa do mesmo escritório |
| `admin@modelo.test` | `ADMIN` | Modelo Assessoria | **Isolamento:** não vê nenhuma empresa, obrigação ou documento do Exemplo Contábil |
| `cliente@modelo.test` | `CLIENTE` | Floricultura Jardim Feliz Ltda. (Modelo Assessoria) | Cliente do segundo escritório |

Os dados de demonstração (escritórios, empresas, CNPJs, obrigações, documentos e avaliações) são fictícios. Eles **não** representam clientes nem opiniões reais.

---

## 9. Testes e qualidade

| Suíte | O que verifica | Banco |
|---|---|---|
| **Unit** (`tests/Unit`) | Regras isoladas: validação, CNPJ, cálculo do Índice de Regularidade, middlewares, estrutura de CSS/JS/views | Não usa |
| **Integration** (`tests/Integration`) | Models e services com banco real: isolamento entre escritórios, transições de status, auditoria, segurança | `tributo_test` |
| **E2E** (`tests/E2E`) | Requisições HTTP reais a um servidor PHP embutido: login, permissões, CRUDs, painel, fluxo de documentos e portal, interface | `tributo_test` |

Entre os pontos cobertos estão:
- isolamento entre escritórios (404 para ID de outro escritório);
- CSRF (419 sem token);
- escape de saída contra XSS;
- prepared statements contra injeção de SQL;
- controle de perfis;
- autenticação: só a senha cadastrada é aceita.

**Última execução registrada (17/11/2026), commit `f8f5b27`:**

| Verificação | Resultado |
|---|---|
| `composer test` (Unit + Integration + E2E) | **841 testes, 5.917 asserções, 0 falhas** |
| `composer analyse` (PHPStan) | Sem erros |
| `composer lint` (`php -l`) | Sem erros em 212 arquivos |

Esses números valem para aquela execução. Rode `composer check` para conferir no seu ambiente.

Antes dos testes:
- Integration e E2E exigem o MariaDB/MySQL ligado e o banco `tributo_test` criado com `php bin/db.php rebuild --test`.
- Os testes gravam dados nesse banco.

---

## 10. Comandos disponíveis

| Comando | Descrição |
|---|---|
| `composer check` | Executa `lint`, `analyse` e `test`, nesta ordem (obrigatório antes de cada commit) |
| `composer lint` | Verifica a sintaxe de todos os arquivos PHP (`php -l`) |
| `composer analyse` | Análise estática com PHPStan |
| `composer test` | Todas as suítes (Unit, Integration e E2E) |
| `composer test:unit` | Só os testes unitários |
| `composer test:integration` | Só os testes de integração (banco `tributo_test`) |
| `composer test:e2e` | Só os testes E2E (banco `tributo_test`) |
| `php bin/db.php rebuild` | Apaga e recria o banco `tributo` com schema e dados de demonstração |
| `php bin/db.php rebuild --test` | Apaga e recria o banco `tributo_test` |
| `php bin/db.php rebuild --sem-seed` | Recria o banco sem os dados de demonstração |
| `php bin/db.php build` | Regenera `database/tributo.sql` a partir de `database/schema/` e `database/seeds/` |

---

## 11. Estrutura do projeto

```
tributo/
├── app/
│   ├── Controllers/      # Controladores por módulo (Cadastro, Documento, Fiscal, Gestao, Nucleo) e avaliação
│   ├── Core/             # Infraestrutura: Router, Kernel, Container, View, Request/Response, Session, Database, cabeçalhos de segurança
│   ├── Exceptions/       # Exceções mapeadas para respostas HTTP (acesso negado, não encontrado, método não permitido, conflito, CSRF, módulo inativo)
│   ├── Middleware/       # Autenticação, visitante, sessão, perfil, CSRF e módulo contratável
│   ├── Models/           # SQL com PDO, sempre filtrado por escritorio_id
│   ├── Services/         # Regras de negócio, Índice de Regularidade, painel, upload e auditoria
│   ├── Support/          # Utilitários de domínio (CNPJ, Competência e Relógio injetável)
│   └── Views/            # Templates PHP, layouts e partials
├── bin/                  # db.php (build/rebuild do banco) e lint.php
├── config/               # app.php, regras e declaração dos módulos (modules/)
├── database/             # schema/ e seeds/ por módulo; tributo.sql gerado
├── docs/                 # Documentação técnica, de produto e acadêmica
├── public/               # Raiz pública: index.php, CSS, JS, fontes e bibliotecas locais (vendor/)
├── storage/              # Logs, cache e uploads (fora da raiz pública; não versionados)
└── tests/                # Unit, Integration, E2E e utilitários de teste
```

---

## 12. Roteiro de apresentação

Roteiro sugerido de 15 minutos, com o ambiente local preparado (`php bin/db.php rebuild`, banco ligado e sistema acessível). A versão detalhada está em [docs/ROTEIRO_DEMO.md](docs/ROTEIRO_DEMO.md).

| Bloco | Tempo | Conteúdo |
|---|:---:|---|
| 1. Introdução e problema | 2 min | Perda de prazos, planilhas e cobrança de documentos pelo WhatsApp |
| 2. Visão do contador | 5 min | Painel, Índice de Regularidade, obrigações, status e criação de solicitação de documento |
| 3. Visão do empresário | 3 min | Portal, pendências, guias e envio de arquivo |
| 4. Fluxo de documentos | 2 min | Contador confere e aceita (ou recusa com motivo) o documento; tela de avaliação |
| 5. Arquitetura, segurança e qualidade | 2 min | MVC próprio, isolamento entre escritórios, testes e verificações |
| 6. Teste com usuário e próximos passos | 1 min | Declarar que o teste com usuário real está pendente e apresentar o roteiro; mostrar resultados somente depois de realizado |

Durante a apresentação:
- **Contas:** `contador@teste.com` (contador), `empresario@teste.com` (empresário) e `admin@modelo.test` (isolamento), todas com `Senha@123`.
- **Testes:** informe o resultado da execução feita no momento.
- **Validação com usuários:** não apresente tempos, notas, depoimentos ou melhorias como resultado de teste com usuários. Esse teste ainda não aconteceu.

---

## 13. Documentação complementar

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): contratos do núcleo, convenções, regras e fórmulas dos índices.
- [docs/DATABASE.md](docs/DATABASE.md): modelo relacional, DDL e isolamento entre escritórios.
- [docs/API.md](docs/API.md): rotas, parâmetros e respostas.
- [docs/DECISIONS.md](docs/DECISIONS.md): registro de decisões arquiteturais (ADRs).
- [docs/PRODUCT.md](docs/PRODUCT.md): visão de produto e visão comercial. Descreve também módulos futuros.
- [docs/ROADMAP.md](docs/ROADMAP.md): situação das tarefas e dependências.
- [docs/ROTEIRO_DEMO.md](docs/ROTEIRO_DEMO.md): roteiro detalhado da apresentação.
- [docs/RELATORIO.md](docs/RELATORIO.md): relatório acadêmico. Versão parcial: depende do teste com usuário.
- [docs/TESTE-USUARIO.md](docs/TESTE-USUARIO.md): roteiro e ficha do teste com usuário real (ainda não executado).
- [docs/LICENCAS-TERCEIROS.md](docs/LICENCAS-TERCEIROS.md): licenças do Bootstrap, da fonte Inter e dos ícones Lucide/Feather.
- [AGENTS.md](AGENTS.md): regras de desenvolvimento e convenções do projeto.

---

## 14. Limitações conhecidas e fora do escopo

**Limitações atuais**
- O **teste com usuário real ainda não foi realizado**. Nenhuma funcionalidade foi validada formalmente com contadores ou empresários.
- A gestão de usuários se limita à listagem; não há cadastro nem edição de usuários pela interface. Os usuários vêm do seed.
- Não há geração automática de obrigações por competência nem calendário de vencimentos; as obrigações são cadastradas uma a uma.
- O upload aceita até 5 MB por arquivo (limite fixo no código). O `.env` tem uma variável `UPLOAD_MAX_BYTES`, mas ela não altera esse limite atualmente.
- O sistema não foi publicado em hospedagem; o funcionamento foi verificado apenas no ambiente local.

**Fora do escopo do MVP (planejado, não implementado)**
- BPO financeiro (contas a pagar e a receber, fluxo de caixa).
- Gestão consultiva (acompanhamentos, plano de ação, radar de alertas).
- Visão 360º da empresa e relatório mensal imprimível.
- Notificações automáticas por e-mail ou WhatsApp e integrações com órgãos públicos ou bancos.
- Cálculo de tributos: fora do propósito do produto (ADR-015).
