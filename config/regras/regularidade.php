<?php

declare(strict_types=1);

/*
 * Pesos e limites do Índice de Regularidade (ARCHITECTURE §10.4). Os valores seguem
 * exatamente a documentação; um teste unitário garante isso.
 * O índice é gerencial e administrativo: não atesta regularidade fiscal (ADR-015).
 */
return [
    // Janela: vencimentos entre hoje − 12 meses e hoje − 1 dia.
    'janela_meses' => 12,
    // Pontos descontados por obrigação atrasada e por documento vencido.
    'peso_atrasada' => 10,
    'peso_documento_vencido' => 5,
    // Abaixo deste score: vermelho.
    'limite_vermelho' => 60,
    // A partir deste score, sem atrasadas: verde.
    'limite_verde' => 85,
    // Atrasada com vencimento há mais destes dias: vermelho em qualquer score.
    'dias_atraso_critico' => 30,
];
