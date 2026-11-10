# AGENTS.md: regras para quem implementa ou revisa o Tributo

Este arquivo vale para **qualquer IA** (Claude, Copilot, Gemini, ChatGPT...) e para qualquer pessoa.
Ele é a fonte única de regras de trabalho; `CLAUDE.md` e `.github/copilot-instructions.md` apontam para cá.

## 1. O projeto em poucas linhas

- **Tributo:** plataforma web para escritórios de contabilidade (obrigações, documentos, BPO financeiro, indicadores e consultivo). Projeto acadêmico (Programação Web II) com potencial comercial.
- **Stack fixa:** PHP 8.2+ puro, MVC próprio, PDO, MariaDB 10.4 / MySQL 8, HTML + CSS + JS sem framework, sessões PHP, PHPUnit 11, PHPStan, Composer (só autoload e ferramentas de desenvolvimento). **Não introduza frameworks nem bibliotecas.**
- **Ambiente local:** XAMPP (Windows). Produção: hospedagem compartilhada.
- Quem desenvolve é **estudante** e precisa entender e apresentar o código: prefira clareza a esperteza.

## 2. Antes de escrever qualquer código, leia

1. Este arquivo.
2. O **cartão da tarefa** em [docs/ROADMAP.md](docs/ROADMAP.md#7-cartões-das-tarefas).
3. As seções indicadas em "Ler" no cartão ([ARCHITECTURE](docs/ARCHITECTURE.md), [DATABASE](docs/DATABASE.md), [API](docs/API.md)).
4. O código já existente que a tarefa usa (contratos do núcleo em `app/Core`).

## 3. Regras invioláveis

1. **Altere somente os arquivos listados no cartão.** Se precisar mudar outro arquivo, **pare** e escreva um "PEDIDO DE MUDANÇA" explicando o arquivo, o motivo e a alternativa.
2. **Não mude contratos** (assinaturas em ARCHITECTURE §5, DDL em DATABASE §4, rotas em API) sem aprovação.
3. **Não invente regras fiscais ou tributárias**, prazos legais, cálculos de impostos ou promessas de conformidade (ver DECISIONS ADR-015). Fórmulas de índices e alertas estão em ARCHITECTURE §10: siga exatamente.
4. **Testes primeiro.** Entregue os testes do cartão antes do código; nunca deixe teste falhando; nunca apague ou enfraqueça um teste para "passar".
5. **Isolamento entre escritórios:** todo método de model recebe `int $escritorioId` como primeiro parâmetro e filtra por ele; `escritorio_id` vem só de `UsuarioLogado`; recurso de outro escritório → `NaoEncontradoException` (404).
6. **Segurança:** só prepared statements; `ORDER BY` apenas via whitelist; toda saída em view com `e()`; todo `<form method="post">` com `<?= csrf_campo() ?>`; senhas só com `password_hash`/`password_verify`; nada sensível em log.
7. **Dinheiro em centavos** (`Support\Dinheiro`), nunca `float`. **"Hoje"** vem de `Support\Relogio`, nunca `date()` direto em service.
8. **Nunca versione** `.env`, `vendor/`, logs, uploads ou `database/tributo.sql` editado à mão (ele é gerado por `php bin/db.php build`).
9. **Dados de exemplo sempre fictícios** (`@exemplo.test`, CNPJs gerados).
10. Não documente como pronta uma funcionalidade que não existe.

## 4. Convenções de código (resumo de ARCHITECTURE §6)

- `declare(strict_types=1);` e tipos em todos os parâmetros e retornos.
- Namespaces: `App\Controllers\<Modulo>`, `App\Models\<Modulo>`, `App\Services\<Modulo>`; views em `app/Views/<modulo>/<recurso>/`.
- **Controller:** lê a requisição → `Validator::validar()` → service ou model → `view()` / `redirecionar()` / `voltarComErros()`. Sem SQL.
- **Service:** regras, transições de status, transações (`Database::transacao`), auditoria. Sem HTML, sem superglobais.
- **Model:** SQL com parâmetros nomeados; métodos `listar`, `buscar`, `criar`, `atualizar` (com `versao`), `excluir`.
- Registros como arrays associativos com PHPDoc (`@return array<string, mixed>|null`).
- Mutação bem-sucedida → redirecionamento (PRG) com flash.
- Domínio em português, infraestrutura com nomes técnicos (`Router`, `Kernel`). Banco: português, `snake_case`, sem acento.
- Métodos curtos (≈ 30 linhas). Comentários explicam o **porquê**.
- Sem JS inline (CSP). Tudo funciona sem JS.

## 5. Checklist de segurança (obrigatório em tarefas 🔒; desejável em todas)

- [ ] Toda consulta filtra por `escritorio_id` (e por `empresa_id` quando o usuário é CLIENTE).
- [ ] ID de outro escritório → 404 (há teste provando).
- [ ] Rota protegida por `auth` e pelo `perfil` correto; módulo contratável protegido.
- [ ] POST com CSRF; mutações com PRG.
- [ ] Entrada validada no servidor com mensagens por campo (422).
- [ ] Nenhuma concatenação de entrada do usuário em SQL; `LIKE` escapado; ordenação por whitelist.
- [ ] Toda saída escapada com `e()`; nenhum `<?= $var ?>` sem `e()`.
- [ ] Concorrência: `versao` verificada nas atualizações.
- [ ] Transação quando há mais de uma escrita (incluindo a auditoria).
- [ ] Upload: tamanho, extensão, MIME (`finfo`), nome aleatório, fora do webroot.
- [ ] Nada de senha, token ou dado sensível em log ou mensagem de erro.

## 6. Comandos

```bash
composer install                 # dependências (uma vez)
php bin/db.php rebuild           # recria o banco local com dados de demonstração (a partir da F03)
php bin/db.php build             # regenera database/tributo.sql (rode depois de alterar schema/ ou seeds/)
composer lint                    # php -l em todo o projeto
composer analyse                 # PHPStan
composer test                    # todos os testes (integração e E2E exigem o MySQL do XAMPP ligado)
composer test:unit               # só os unitários
composer check                   # lint + analyse + test: OBRIGATÓRIO antes de cada commit
```

## 7. Git e commits

- Uma branch por cartão (nome no cartão). Nunca trabalhe direto na `main`.
- **Conventional Commits** com descrição em português, no imperativo, até 72 caracteres:
  `feat:`, `fix:`, `test:`, `refactor:`, `docs:`, `chore:`. Ex.: `feat: adicionar cadastro de empresas com busca e paginação`.
- Um commit por mudança lógica. Se o cartão gerar testes e código juntos, um commit `feat:` com ambos é aceitável; refatorações separadas vão em `refactor:`.
- Ordem: `git switch -c <branch>` → `git add <arquivos do cartão>` (nunca `git add .` às cegas) → `composer check` → `git commit -m "..."`.
- **Nunca** commitar com `composer check` falhando, nem incluir `.env`, logs ou arquivos de debug.

## 8. Prompt de execução (copie para a IA implementadora)

```text
Você vai implementar UMA tarefa do projeto Tributo.
1. Leia AGENTS.md (regras), o cartão <ID> em docs/ROADMAP.md e as seções que ele indica em "Ler".
2. Altere SOMENTE os arquivos listados no cartão. Se precisar de outro, pare e escreva "PEDIDO DE MUDANÇA".
3. Entregue nesta ordem:
   a) Testes (caminho + conteúdo completo de cada arquivo).
   b) Código (caminho + conteúdo completo de cada arquivo; para arquivo existente, só o trecho alterado com a indicação de onde fica).
   c) Explicação curta (até 10 linhas) do que cada arquivo faz, para uma estudante entender.
   d) Comandos: composer check (e php bin/db.php build, se mexeu em SQL).
   e) Comandos Git: git switch -c <branch>, git add <arquivos>, git commit -m "<mensagem do cartão>".
4. Não invente regras fiscais. Não mude contratos. Não adicione dependências.
Cartão:
<cole aqui o cartão>
```

## 9. Prompt de revisão (copie para a IA revisora: Gemini, ChatGPT, Opus...)

```text
Revise a implementação da tarefa <ID> do projeto Tributo (diff ou arquivos abaixo).
Contexto: AGENTS.md e o cartão <ID> de docs/ROADMAP.md (colados abaixo).
Verifique e classifique cada problema como CRÍTICO, ALTO, MÉDIO ou BAIXO:
1. Critérios de aceite do cartão: cada um atendido? Aponte os que faltam.
2. Testes: caminho feliz, erros, casos extremos; há teste de isolamento entre escritórios?
3. Checklist de segurança do AGENTS.md §5, item por item.
4. Integridade: transações, versao, FKs, mensagens 409/422 corretas.
5. Arquivos alterados fora do cartão? Contratos alterados?
6. Complexidade desnecessária ou código difícil de explicar para uma estudante.
Para cada problema: arquivo:linha, explicação, correção sugerida (só o trecho).
Não reescreva arquivos inteiros. Não proponha bibliotecas ou frameworks.
```

## 10. Quando parar e perguntar

- O cartão contradiz ARCHITECTURE, DATABASE ou API.
- A tarefa parece exigir uma regra fiscal ou tributária não documentada.
- É necessário alterar um arquivo de outra faixa ou um contrato do núcleo.
- Um teste existente falha por motivo alheio à tarefa.
