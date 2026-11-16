# Licenças de terceiros

O -Tributo não usa bibliotecas de código de terceiros no navegador nem no servidor. Há apenas
dois recursos visuais de terceiros, ambos **hospedados no próprio projeto** (nada é carregado de CDN)
e aprovados na [ADR-024](DECISIONS.md). Qualquer outro item exige um novo ADR e entra nesta lista.

| Recurso | Onde está | Origem | Licença |
|---------|-----------|--------|---------|
| Fonte **Inter** (variável, subconjunto latino, pesos 400 a 700) | `public/assets/fonts/inter-latin-variable.woff2` | https://github.com/rsms/inter, versão 4.1 | SIL Open Font License 1.1 |
| Ícones **Lucide** (22 desenhos) | `app/Views/partials/icone.php` | https://lucide.dev, versão 1.73.0 | ISC (parte dos desenhos, herdada do Feather, é MIT) |

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

Os ícones herdados do Feather (entre os usados aqui: `log-out`, `x`, `plus`, `search`, `info`,
`circle`, `arrow-left`, `chevron-left` e `chevron-right`) seguem esta licença:

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
