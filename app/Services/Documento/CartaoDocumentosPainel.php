<?php

declare(strict_types=1);

namespace App\Services\Documento;

use App\Core\CartaoPainel;
use App\Core\UsuarioLogado;
use App\Models\Documento\SolicitacaoDocumento;

final class CartaoDocumentosPainel implements CartaoPainel
{
    private readonly SolicitacaoDocumento $solicitacoes;

    public function __construct()
    {
        $this->solicitacoes = new SolicitacaoDocumento();
    }

    public function titulo(): string
    {
        return 'Documentos dos Clientes';
    }

    public function ordem(): int
    {
        return 15;
    }

    public function template(): string
    {
        return 'documentos/painel/cartao';
    }

    /**
     * @return array{
     *   aguardando_conferencia: int,
     *   atrasadas: int
     * }
     */
    public function dados(UsuarioLogado $usuario): array
    {
        return [
            'aguardando_conferencia' => $this->solicitacoes->contarAguardandoConferencia($usuario->escritorioId),
            'atrasadas' => $this->solicitacoes->contarAtrasadas($usuario->escritorioId),
        ];
    }
}
