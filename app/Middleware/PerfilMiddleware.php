<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AcessoNegadoException;
use InvalidArgumentException;

/**
 * Autoriza por perfil: `perfil:ADMIN,COLABORADOR`. Deve rodar depois do `auth`, que é quem
 * identifica o usuário; sem usuário na requisição, Request::usuario() falha (500) e nada é liberado.
 */
final class PerfilMiddleware
{
    private const PERFIS_VALIDOS = ['ADMIN', 'COLABORADOR', 'CLIENTE'];

    /** @var list<string> */
    private readonly array $permitidos;

    public function __construct(string $argumentos)
    {
        $perfis = explode(',', $argumentos);
        foreach ($perfis as $perfil) {
            // Configuração errada nunca vira "libera tudo": a rota falha ao ser executada.
            if (!in_array($perfil, self::PERFIS_VALIDOS, true)) {
                throw new InvalidArgumentException('Configuração de perfil inválida na rota.');
            }
        }
        $this->permitidos = array_values(array_unique($perfis));
    }

    /**
     * @param callable(Request): Response $proximo
     */
    public function tratar(Request $request, callable $proximo): Response
    {
        if (!in_array($request->usuario()->perfil, $this->permitidos, true)) {
            throw new AcessoNegadoException();
        }

        return $proximo($request);
    }
}
