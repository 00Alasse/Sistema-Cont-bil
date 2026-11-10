# Instruções para o GitHub Copilot: projeto -Tributo!

As regras completas estão em `AGENTS.md` na raiz do repositório. Resumo obrigatório:

- **Stack:** PHP 8.2+ puro, MVC próprio (sem framework), PDO, MariaDB 10.4/MySQL 8, HTML/CSS/JS sem bibliotecas. Não sugira frameworks nem pacotes.
- Todo arquivo PHP começa com `declare(strict_types=1);` e tem tipos em parâmetros e retornos.
- **Multi-tenant:** todo método de model recebe `int $escritorioId` como primeiro parâmetro e filtra `WHERE escritorio_id = :escritorio_id`. O `escritorio_id` vem de `UsuarioLogado`, nunca do formulário. Recurso de outro escritório → `NaoEncontradoException`.
- **SQL:** somente prepared statements com parâmetros nomeados; `ORDER BY` apenas via `QueryFiltros::ordenacao()` (whitelist); `LIKE` com `QueryFiltros::like()`.
- **Views:** toda saída com `<?= e($valor) ?>`; todo `<form method="post">` contém `<?= csrf_campo() ?>`; sem JavaScript inline.
- **Controllers** sem SQL; **Services** com as regras e transações (`Database::transacao`); **Models** com o SQL.
- Atualizações usam a coluna `versao` (concorrência otimista) e lançam `ConflitoException` quando 0 linhas são afetadas.
- Dinheiro em centavos via `App\Support\Dinheiro` (nunca `float`); "hoje" via `App\Support\Relogio`.
- Validação com `Validator::validar($dados, $regras)`; erros por campo → `voltarComErros()` (422).
- Nomes de domínio em português (`Empresa`, `Obrigacao`, `listar`, `baixar`); infraestrutura com nomes técnicos (`Router`, `Kernel`).
- **Não invente regras fiscais ou tributárias**, nem prazos legais.
- Commits: Conventional Commits em português, no imperativo (`feat: adicionar ...`).
- Ao explicar código, use linguagem simples: quem desenvolve é estudante.
