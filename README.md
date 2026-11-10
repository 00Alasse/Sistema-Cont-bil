# -Tributo!

Plataforma web para **escritórios de contabilidade e seus clientes**. Centraliza obrigações fiscais, pendências documentais, BPO financeiro, indicadores gerenciais e acompanhamento consultivo.

> **Situação:** em desenvolvimento. Concluída a **T00**: estrutura inicial, configuração e documentação.
> **Nenhuma funcionalidade de negócio está implementada ainda**, e o banco de dados também não: não há scripts SQL nem tabelas no projeto (a modelagem está especificada em [docs/DATABASE.md](docs/DATABASE.md)). Andamento em [docs/ROADMAP.md](docs/ROADMAP.md#6-quadro-de-situação).

Projeto acadêmico da disciplina **Programação Web II**, planejado como produto SaaS com potencial comercial.

---

## Problema

Escritórios contábeis pequenos e médios controlam prazos em planilhas, cobram documentos dos clientes pelo WhatsApp e quase não têm visibilidade financeira desses clientes. O resultado é risco de atraso, retrabalho e um serviço percebido como custo obrigatório, e não como apoio à gestão.

## Objetivo

Transformar o escritório contábil em **parceiro de gestão** dos clientes:

- organizar obrigações, vencimentos e entregas da carteira inteira;
- substituir a cobrança informal de documentos por solicitações com prazo, status e histórico, em um portal simples para o cliente;
- permitir que o escritório ofereça **BPO financeiro** (contas a pagar e a receber, fluxo de caixa);
- gerar **indicadores** (Índice de Regularidade, painel da carteira, relatório mensal) e apoiar o **serviço consultivo** com um radar de alertas e um plano de ação.

> A plataforma -Tributo! é uma ferramenta de **controle administrativo e gerencial**. Ela não calcula tributos, não determina obrigações legais e não atesta regularidade fiscal.

## Público-alvo

- Escritórios de contabilidade de pequeno e médio porte (administradores e colaboradores).
- Contadores autônomos.
- Empresas clientes, especialmente MEIs, micro e pequenas empresas (pelo portal do cliente).

Visão completa do produto e do modelo comercial: [docs/PRODUCT.md](docs/PRODUCT.md).

## Tecnologias

| Camada | Tecnologia |
|--------|------------|
| Back-end | PHP 8.2+ (sem framework), arquitetura MVC, ponto único de entrada |
| Banco de dados | MariaDB 10.4 (XAMPP) / MySQL 8, acesso via PDO com prepared statements |
| Front-end | HTML5, CSS3 e JavaScript, sem bibliotecas |
| Autenticação | Sessões PHP, `password_hash` |
| Qualidade | PHPUnit 11, PHPStan 2, `php -l` |
| Dependências | Composer (só autoload e ferramentas de desenvolvimento) |
| Versionamento | Git e GitHub |

## Pré-requisitos

- **XAMPP** com PHP **8.2 ou superior** e MariaDB/MySQL (ambiente de referência do desenvolvimento: PHP 8.2.12 e MariaDB 10.4.32).
- Extensões do PHP habilitadas no `php.ini`: `pdo_mysql`, `mbstring`, `fileinfo`. Recomendada: `zip` (acelera o Composer; no XAMPP, basta remover o `;` da linha `extension=zip` em `C:\xampp\php\php.ini`).
- **Composer 2**: instalador para Windows em <https://getcomposer.org/download/>; durante a instalação, aponte para `C:\xampp\php\php.exe`.
- **Git**.

## Instalação local (XAMPP)

> Recomenda-se manter o projeto **fora de pastas sincronizadas pelo OneDrive** e em um caminho sem acentos ou espaços.

1. Clone o repositório dentro da pasta do Apache:

   ```bash
   cd C:\xampp\htdocs
   git clone <url-do-repositorio> tributo
   cd tributo
   ```

2. Instale as dependências:

   ```bash
   composer install
   ```

3. Crie o arquivo de configuração local:

   ```bash
   copy .env.example .env
   ```

   Ajuste `APP_BASE_PATH` se a pasta tiver outro nome (ex.: `/tributo`).

4. Inicie o **Apache** e o **MySQL** no painel do XAMPP e acesse <http://localhost/tributo/>.
   Nesta etapa (T00), a página só confirma que a estrutura foi instalada.

5. Verifique a instalação:

   ```bash
   composer check
   ```

> **Banco de dados:** a criação do banco (`php bin/db.php rebuild`), os dados de demonstração e os usuários de teste serão adicionados na tarefa **F03** e documentados aqui.

## Publicação em hospedagem

**Planejada, mas condicional.** A forma principal de execução e apresentação é a **instalação local** acima. A publicação (tarefa **Q02**) só será feita se houver uma hospedagem compatível disponível e o sistema estiver estável localmente. O projeto já está preparado para ela: dependências geradas com `composer install --no-dev`, banco importado com `database/tributo.sql` pelo phpMyAdmin (arquivo gerado a partir da tarefa F03) e `.env` de produção a partir de `.env.production.example`. Detalhes em [docs/ARCHITECTURE.md §9](docs/ARCHITECTURE.md#9-ambientes-local-testes-e-produção) e [docs/DECISIONS.md, ADR-020](docs/DECISIONS.md).

## Comandos

| Comando | Função |
|---------|--------|
| `composer lint` | Verifica a sintaxe de todos os arquivos PHP |
| `composer analyse` | Análise estática com PHPStan |
| `composer test` | Executa todos os testes |
| `composer test:unit` | Executa só os testes unitários |
| `composer check` | Lint + análise + testes (obrigatório antes de cada commit) |

## Estrutura do projeto

```
tributo/
├── app/            # código da aplicação (MVC)
│   ├── Core/         # infraestrutura: roteador, kernel, sessão, validação, banco
│   ├── Controllers/  # controladores, organizados por módulo
│   ├── Models/       # acesso a dados (SQL), por módulo
│   ├── Services/     # regras de negócio, por módulo
│   ├── Middleware/   # autenticação, perfis, CSRF, módulos contratados
│   ├── Support/      # utilitários de domínio (CNPJ, dinheiro, datas)
│   ├── Exceptions/   # exceções mapeadas para respostas HTTP
│   └── Views/        # templates HTML
├── bin/            # scripts de linha de comando (lint, banco)
├── config/         # configuração; config/modules/ declara rotas e menus de cada módulo
├── database/       # schema/ e seeds/ por módulo; tributo.sql gerado para importação
├── docs/           # documentação técnica e de produto
├── public/         # única pasta acessível pelo navegador (index.php, assets)
├── storage/        # logs, uploads e cache (fora do Git)
└── tests/          # Unit, Integration e E2E
```

## Documentação

| Documento | Conteúdo |
|-----------|----------|
| [docs/PRODUCT.md](docs/PRODUCT.md) | Problema, módulos, viabilidade, releases e visão comercial |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Arquitetura, contratos do núcleo, segurança, regras com fórmula, fluxos e testes |
| [docs/DATABASE.md](docs/DATABASE.md) | Modelo de dados, DDL, isolamento entre escritórios e seeds |
| [docs/API.md](docs/API.md) | Rotas, perfis, payloads e respostas |
| [docs/ROADMAP.md](docs/ROADMAP.md) | Tarefas, dependências, critérios de aceite e situação |
| [docs/DECISIONS.md](docs/DECISIONS.md) | Decisões arquiteturais e justificativas |
| [AGENTS.md](AGENTS.md) | Regras de desenvolvimento para pessoas e ferramentas de IA |

## Usuários de teste

Serão criados pelos dados de demonstração na tarefa **F03** e listados aqui. Todos os dados de exemplo são fictícios.
