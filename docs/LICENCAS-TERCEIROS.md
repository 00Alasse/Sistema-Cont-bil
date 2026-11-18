# Licenças de terceiros

O -Tributo não usa bibliotecas de terceiros no servidor. No navegador há apenas os recursos
visuais abaixo, todos **hospedados no próprio projeto** (nada é carregado de CDN)
e aprovados na [ADR-024](DECISIONS.md). Qualquer outro item exige um novo ADR e entra nesta lista.

| Recurso | Onde está | Origem | Licença |
|---------|-----------|--------|---------|
| Fonte **Inter** (variável, subconjunto latino, pesos 400 a 700) | `public/assets/fonts/inter-latin-variable.woff2` | https://github.com/rsms/inter, versão 4.1 | SIL Open Font License 1.1 |
| **Bootstrap** 5.3.8 (CSS e JS, sem source maps) | `public/assets/vendor/bootstrap/` | https://getbootstrap.com, pacote npm `bootstrap@5.3.8` | MIT |
| Ícones **Lucide** (24 desenhos) | `app/Views/partials/icone.php` | https://lucide.dev, versão 1.73.0 | ISC; 12 dos 24 desenhos derivam do Feather e seguem a MIT (tabela na seção Lucide) |

## Bootstrap

Copyright (c) 2011-2025 The Bootstrap Authors. Licença MIT (texto integral em
`public/assets/vendor/bootstrap/LICENSE-Bootstrap.txt`). Usado sem modificação: `bootstrap.min.css` e
`bootstrap.bundle.min.js` (inclui o Popper). Só foi removida a linha de comentário `sourceMappingURL`, que
apontava para arquivos de mapa não incluídos. Autorizado na revisão da [ADR-024](DECISIONS.md).

## Inter

Copyright (c) 2016 The Inter Project Authors (https://github.com/rsms/inter).
Licenciada sob a SIL Open Font License, Version 1.1. O texto integral da licença está em
`public/assets/fonts/LICENSE-Inter.txt`, junto do arquivo da fonte.

O arquivo do projeto é um **subconjunto** de `InterVariable.woff2` (release 4.1): o eixo óptico foi fixado
em 14, o eixo de peso foi limitado a 400 a 700 e só os caracteres latinos usados em português (e
pontuação comum) foram mantidos. O nome da família (Inter) foi mantido e o texto integral da licença acompanha o arquivo; a fonte não é
vendida separadamente.

## Lucide

Os desenhos dos ícones foram copiados do Lucide sem alteração, só reorganizados num mapa de nomes em
português dentro de `app/Views/partials/icone.php`.

### Conferência de procedência por ícone (17/11/2026)

- **Versão:** os 24 SVGs de `icone.php` são idênticos (conteúdo normalizado) aos arquivos `icons/<nome>.svg` da
  tag `1.73.0` de https://github.com/lucide-icons/lucide.
- **Quem deriva do Feather:** o `LICENSE` do Lucide nessa tag lista os ícones derivados do Feather (MIT,
  © 2013-present Cole Bemis). A lista usa nomes antigos; a ligação com o nome atual foi feita pelos *aliases* do
  `icons/<nome>.json` do Lucide e **confirmada pela geometria**: cada desenho foi renderizado e comparado com os 287
  ícones do Feather v4.29.2 (IoU, e cobertura mútua com tolerância de 0,5 unidade do grid de 24).
- **Critério:** o titular do Lucide declara no `LICENSE` quais ícones derivam do Feather; os que não constam da lista
  são tratados como desenho do Lucide (ISC). A autoria registrada nos metadados não foi usada como prova.

| Desenho (`icone.php`) | Nome no Lucide 1.73.0 | Nome na lista do Feather | Licença | Evidência | Confiança |
|---|---|---|---|---|---|
| `sair` | `log-out` | `log-out` | MIT (Feather) | geometria idêntica ao Feather (IoU 1,00) | alta |
| `fechar` | `x` | `x` | MIT (Feather) | idêntica (IoU 1,00) | alta |
| `adicionar` | `plus` | `plus` | MIT (Feather) | idêntica (IoU 1,00) | alta |
| `buscar` | `search` | `search` | MIT (Feather) | idêntica (IoU 1,00) | alta |
| `info` | `info` | `info` | MIT (Feather) | idêntica (IoU 1,00) | alta |
| `voltar` | `arrow-left` | `arrow-left` | MIT (Feather) | idêntica (IoU 1,00) | alta |
| `anterior` | `chevron-left` | `chevron-left` | MIT (Feather) | idêntica (IoU 1,00) | alta |
| `proximo` | `chevron-right` | `chevron-right` | MIT (Feather) | idêntica (IoU 1,00) | alta |
| `padrao` | `circle` | `circle` | MIT (Feather) | idêntica (IoU 1,00) | alta |
| `erro` | `circle-x` | `x-circle` (alias) | MIT (Feather) | idêntica ao `x-circle` do Feather (IoU 1,00) | alta |
| `excluir` | `trash` | `trash` e `trash-2` | MIT (Feather) | idêntica ao `trash-2` do Feather (IoU 1,00); o `trash` do Feather é outro desenho (IoU 0,83) | alta |
| `aviso` | `triangle-exclamation-point` | `alert-triangle` (alias) | MIT (Feather) | melhor correspondência entre os 287 (cobertura 1,00); coordenadas levemente diferentes (IoU 0,87), ou seja, redesenho sobre a forma do Feather | média |
| `painel` | `layout-dashboard` | — | ISC (Lucide) | fora da lista; sem correspondência no Feather (melhor IoU 0,59) | alta |
| `empresa` | `building` | — | ISC (Lucide) | fora da lista; sem pictograma equivalente no Feather (melhor IoU 0,74, ícone sem relação) | alta |
| `obrigacoes` | `calendar-check` | — | ISC (Lucide) | fora da lista; difere de `calendar` do Feather (IoU 0,52), mas reaproveita a moldura de calendário (caixa e linha de cabeçalho); a maior sobreposição entre os 287 é com `layout` (IoU 0,81, pictograma sem relação) | média |
| `tipos` | `list-checks` | — | ISC (Lucide) | fora da lista; difere de `list` do Feather (IoU 0,31) | alta |
| `sucesso` | `circle-check` | — | ISC (Lucide) | fora da lista; difere de `check-circle` do Feather (IoU 0,72); as maiores sobreposições com ícones da lista (`minus-circle`, IoU 0,85) vêm só do círculo base | alta |
| `menu` | `menu` | — | ISC (Lucide) | fora da lista; redesenhado em relação ao `menu` do Feather (IoU 0,46) | alta |
| `ver` | `eye` | — | ISC (Lucide) | fora da lista; difere do `eye` do Feather (IoU 0,59) | alta |
| `usuarios` | `users` | — | ISC (Lucide) | fora da lista; parecido com o `users` do Feather, mas distinto (IoU 0,74, cobertura 0,93) | média |
| `documentos` | `file-text` | — | ISC (Lucide), **origem inconclusiva** | fora da lista, mas geometria quase igual à do `file-text` do Feather (IoU 0,95; cobertura 0,99) | inconclusiva |
| `avaliacao` | `star` | — | ISC (Lucide), **origem inconclusiva** | fora da lista, mas contorno da estrela dentro da tolerância do `star` do Feather (cobertura 1,00; IoU 0,77, pontas arredondadas) | inconclusiva |
| `editar` | `pencil` | — | ISC (Lucide), **origem inconclusiva** | fora da lista, mas próxima do `edit-2` do Feather (IoU 0,90; cobertura 0,96) | inconclusiva |
| `portal` | `house` | — (`home` no Feather) | ISC (Lucide), **origem inconclusiva** | fora da lista; o Lucide registra `home` como alias, mas `home` não consta da lista de derivados; próxima do `home` do Feather (IoU 0,78; cobertura 0,95) | inconclusiva |

**Casos inconclusivos (`file-text`, `star`, `pencil`, `house`).** O `LICENSE` do Lucide não os declara derivados do
Feather, então a atribuição adotada aqui é a do titular (ISC). Porém o desenho é muito próximo do ícone de mesmo
pictograma do Feather. Semelhança geométrica, por si só, não prova cópia (dois desenhos do mesmo pictograma na mesma
grade de 24 px costumam coincidir), e os metadados de autoria do Lucide também não resolvem a questão. Com os dados
disponíveis não dá para afirmar nem descartar a ascendência no Feather. O texto da licença MIT do Feather já está
neste arquivo, e as duas licenças exigem apenas manter os avisos de copyright.

**Limites da conferência.** A comparação é visual/estrutural (renderização no Chromium, 96 a 240 px) e usa a lista e os
aliases do Lucide 1.73.0; não houve acesso ao histórico completo de commits do Lucide. Os valores de IoU e cobertura
indicam semelhança, não derivação jurídica.

```text
ISC License

Copyright (c) 2026 Lucide Icons and Contributors

Permission to use, copy, modify, and/or distribute this software for any
purpose with or without fee is hereby granted, provided that the above
copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
```

Os ícones herdados do Feather usados aqui são `log-out`, `x`, `plus`, `search`, `info`, `circle`, `arrow-left`,
`chevron-left`, `chevron-right`, `trash` (`trash-2` no Feather), `circle-x` (`x-circle` na lista do Lucide) e
`triangle-exclamation-point` (`alert-triangle` na lista do Lucide). Eles seguem esta licença:

```text
The MIT License (MIT) (for the icons listed above)

Copyright (c) 2013-present Cole Bemis

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
