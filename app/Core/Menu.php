<?php

declare(strict_types=1);

namespace App\Core;

use UnexpectedValueException;

final class Menu
{
    /**
     * @param array<string, array<string, mixed>> $modulos configurações devolvidas por Modulos::carregar()
     */
    public function __construct(private readonly array $modulos)
    {
    }

    /**
     * @return list<array{rotulo: string, url: string, icone: string, ordem: int}>
     */
    public function itens(UsuarioLogado $usuario): array
    {
        $itens = [];
        foreach ($this->modulos as $configuracao) {
            $contratavel = $configuracao['contratavel'] ?? null;
            if ($contratavel !== null && !is_string($contratavel)) {
                throw new UnexpectedValueException('O módulo possui um código contratável inválido.');
            }
            if (is_string($contratavel) && !$usuario->temModulo($contratavel)) {
                continue;
            }

            $itensDoModulo = $configuracao['menu'] ?? null;
            if (!is_array($itensDoModulo)) {
                throw new UnexpectedValueException('A configuração do menu do módulo é inválida.');
            }

            foreach ($itensDoModulo as $item) {
                if (!$this->itemValido($item)) {
                    throw new UnexpectedValueException('Um item de menu é inválido.');
                }
                if (!in_array($usuario->perfil, $item['perfis'], true)) {
                    continue;
                }

                $itens[] = [
                    'rotulo' => $item['rotulo'],
                    'url' => $item['url'],
                    'icone' => $item['icone'],
                    'ordem' => $item['ordem'],
                ];
            }
        }

        usort($itens, static fn (array $a, array $b): int => $a['ordem'] <=> $b['ordem']);

        return $itens;
    }

    private function itemValido(mixed $item): bool
    {
        if (
            !is_array($item)
            || !is_string($item['rotulo'] ?? null)
            || !is_string($item['url'] ?? null)
            || !is_string($item['icone'] ?? null)
            || !is_array($item['perfis'] ?? null)
            || !is_int($item['ordem'] ?? null)
        ) {
            return false;
        }

        foreach ($item['perfis'] as $perfil) {
            if (!is_string($perfil)) {
                return false;
            }
        }

        return true;
    }
}
