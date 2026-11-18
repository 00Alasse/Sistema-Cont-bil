<?php

declare(strict_types=1);

/*
 * Ícone SVG embutido (ADR-024). Sem arquivo externo e sem fonte de ícones: nada novo para a CSP.
 *
 * Os desenhos vêm do Lucide 1.73.0 (https://lucide.dev), licença ISC. Os que o LICENSE do Lucide lista como
 * derivados do Feather seguem a licença MIT: log-out, x, plus, search, info, circle, arrow-left, chevron-left,
 * chevron-right, trash, circle-x e triangle-exclamation-point (12 dos 24). Os demais são desenhos do Lucide, só ISC;
 * em file-text, star, pencil e house a ascendência no Feather é inconclusiva. A conferência por ícone e os textos
 * das duas licenças estão em docs/LICENCAS-TERCEIROS.md.
 *
 * Variáveis do chamador:
 *   $icone        nome em português (veja a lista abaixo); nome desconhecido usa "padrao".
 *   $iconeTitulo  (opcional) texto alternativo. Sem ele o ícone é decorativo e some para leitores
 *                 de tela (aria-hidden); com ele o ícone ganha role="img" e rótulo.
 *   $iconeClasse  (opcional) classe extra, por exemplo "icone-grande".
 */

/** @var array<string, string> $desenhosIcone */
$desenhosIcone = [
    'painel' => '<rect width="7" height="9" x="3" y="3" rx="1" /><rect width="7" height="5" x="14" y="3" rx="1" /><rect width="7" height="9" x="14" y="12" rx="1" /><rect width="7" height="5" x="3" y="16" rx="1" />',  // lucide: layout-dashboard
    'empresa' => '<path d="M12 10h.01" /><path d="M12 14h.01" /><path d="M12 6h.01" /><path d="M16 10h.01" /><path d="M16 14h.01" /><path d="M16 6h.01" /><path d="M8 10h.01" /><path d="M8 14h.01" /><path d="M8 6h.01" /><path d="M9 22v-3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3" /><rect x="4" y="2" width="16" height="20" rx="2" />',  // lucide: building
    'obrigacoes' => '<path d="M8 2v3" /><path d="M16 2v3" /><rect x="3" y="3" width="18" height="18" rx="2" /><path d="M3 9h18" /><path d="m9 15 2 2 4-4" />',  // lucide: calendar-check
    'tipos' => '<path d="M13 5h8" /><path d="M13 12h8" /><path d="M13 19h8" /><path d="m3 17 2 2 4-4" /><path d="m3 7 2 2 4-4" />',  // lucide: list-checks
    'usuarios' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><path d="M16 3.128a4 4 0 0 1 0 7.744" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><circle cx="9" cy="7" r="4" />',  // lucide: users
    'portal' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8" /><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />',  // lucide: house
    'documentos' => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z" /><path d="M14 2v5a1 1 0 0 0 1 1h5" /><path d="M10 9H8" /><path d="M16 13H8" /><path d="M16 17H8" />',  // lucide: file-text
    'avaliacao' => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z" />',  // lucide: star
    'sair' => '<path d="m16 17 5-5-5-5" /><path d="M21 12H9" /><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />',  // lucide: log-out
    'menu' => '<path d="M4 5h16" /><path d="M4 12h16" /><path d="M4 19h16" />',  // lucide: menu
    'fechar' => '<path d="M18 6 6 18" /><path d="m6 6 12 12" />',  // lucide: x
    'adicionar' => '<path d="M5 12h14" /><path d="M12 5v14" />',  // lucide: plus
    'buscar' => '<path d="m21 21-4.34-4.34" /><circle cx="11" cy="11" r="8" />',  // lucide: search
    'aviso' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /><path d="M12 9v4" /><path d="M12 17h.01" />',  // lucide: triangle-exclamation-point
    'sucesso' => '<circle cx="12" cy="12" r="10" /><path d="m16 9-5.5 5.5L8 12" />',  // lucide: circle-check
    'info' => '<circle cx="12" cy="12" r="10" /><path d="M12 16v-4" /><path d="M12 8h.01" />',  // lucide: info
    'erro' => '<circle cx="12" cy="12" r="10" /><path d="m15 9-6 6" /><path d="m9 9 6 6" />',  // lucide: circle-x
    'voltar' => '<path d="m12 19-7-7 7-7" /><path d="M19 12H5" />',  // lucide: arrow-left
    'editar' => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" /><path d="m15 5 4 4" />',  // lucide: pencil
    'excluir' => '<path d="M10 11v6" /><path d="M14 11v6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" /><path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />',  // lucide: trash
    'ver' => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" /><circle cx="12" cy="12" r="3" />',  // lucide: eye
    'anterior' => '<path d="m15 18-6-6 6-6" />',  // lucide: chevron-left
    'proximo' => '<path d="m9 18 6-6-6-6" />',  // lucide: chevron-right
    'padrao' => '<circle cx="12" cy="12" r="10" />',  // lucide: circle
];

$nomeIcone = is_string($icone ?? null) && isset($desenhosIcone[$icone]) ? $icone : 'padrao';
$tituloIcone = is_string($iconeTitulo ?? null) && trim($iconeTitulo) !== '' ? trim($iconeTitulo) : null;
$classeIcone = is_string($iconeClasse ?? null) && preg_match('/\A[a-z][a-z0-9 -]*\z/', $iconeClasse) === 1
    ? ' ' . $iconeClasse
    : '';
unset($icone, $iconeTitulo, $iconeClasse);
?>
<svg class="icone<?= $classeIcone ?>" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"<?= $tituloIcone === null ? ' aria-hidden="true"' : ' role="img" aria-label="' . e($tituloIcone) . '"' ?>><?= $desenhosIcone[$nomeIcone] ?></svg>