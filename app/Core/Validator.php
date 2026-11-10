<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Cnpj;
use DateTimeImmutable;
use InvalidArgumentException;

final class Validator
{
    /**
     * @param array<string, mixed> $dados
     * @param array<string, mixed> $regras
     * @return array<string, list<string>>
     */
    public static function validar(array $dados, array $regras): array
    {
        $erros = [];
        $regrasValidas = [];

        foreach ($regras as $campo => $definicao) {
            $regrasValidas[$campo] = self::validarDefinicao($campo, $definicao);
            self::validarConfiguracaoRegras($regrasValidas[$campo]['regras']);
        }

        foreach ($regrasValidas as $campo => $definicao) {
            $valor = $dados[$campo] ?? null;
            $campoVazio = self::estaVazio($valor);
            $mensagens = self::validarCampo(
                $campo,
                $valor,
                $definicao,
                $dados,
                $regras,
                $campoVazio
            );

            if ($mensagens !== []) {
                $erros[$campo] = $mensagens;
            }
        }

        return $erros;
    }

    /**
     * @param mixed $definicao
     * @return array{rotulo: string, regras: string}
     */
    private static function validarDefinicao(string $campo, mixed $definicao): array
    {
        if (
            !is_array($definicao)
            || !isset($definicao['rotulo'], $definicao['regras'])
            || !is_string($definicao['rotulo'])
            || trim($definicao['rotulo']) === ''
            || !is_string($definicao['regras'])
        ) {
            throw new InvalidArgumentException('Definição de validação inválida para o campo ' . $campo . '.');
        }

        return [
            'rotulo' => $definicao['rotulo'],
            'regras' => $definicao['regras'],
        ];
    }

    /**
     * @param array<string, mixed> $dados
     * @param array<string, array{rotulo: string, regras: string}> $regras
     * @return list<string>
     */
    private static function validarCampo(
        string $campo,
        mixed $valor,
        array $definicao,
        array $dados,
        array $regras,
        bool $campoVazio
    ): array {
        $mensagens = [];
        $regrasCampo = self::separarRegras($definicao['regras']);

        if ($campoVazio && !in_array('obrigatorio', $regrasCampo, true)) {
            return [];
        }

        foreach ($regrasCampo as $regra) {
            if ($campoVazio && $regra !== 'obrigatorio') {
                continue;
            }

            $mensagem = self::validarRegra(
                $campo,
                $valor,
                $regra,
                $definicao['rotulo'],
                $dados,
                $regras
            );
            if ($mensagem !== null) {
                $mensagens[] = $mensagem;
            }
        }

        return $mensagens;
    }

    /**
     * @param array<string, mixed> $dados
     * @param array<string, array{rotulo: string, regras: string}> $regras
     */
    private static function validarRegra(
        string $campo,
        mixed $valor,
        string $regra,
        string $rotulo,
        array $dados,
        array $regras
    ): ?string {
        [$nome, $parametro] = self::lerRegra($regra);
        $texto = self::comoTexto($valor);

        if ($valor !== null && $texto === null) {
            return $rotulo . ' tem um formato inválido.';
        }

        return match ($nome) {
            'obrigatorio' => self::validarObrigatorio($valor, $rotulo),
            'min' => self::validarTamanhoMinimo($texto, $rotulo, $parametro),
            'max' => self::validarTamanhoMaximo($texto, $rotulo, $parametro),
            'email' => self::validarEmail($texto, $rotulo),
            'cnpj' => self::validarCnpj($texto, $rotulo),
            'data' => self::validarData($texto, $rotulo),
            'competencia' => self::validarCompetencia($texto, $rotulo),
            'inteiro' => self::validarInteiro($texto, $rotulo),
            'decimal' => self::validarDecimal($texto, $rotulo),
            'em' => self::validarOpcao($texto, $rotulo, $parametro),
            'minimo' => self::validarLimiteNumerico($texto, $rotulo, $parametro, true),
            'maximo' => self::validarLimiteNumerico($texto, $rotulo, $parametro, false),
            'depois_ou_igual' => self::validarOrdemDatas(
                $texto,
                $rotulo,
                $parametro,
                $dados,
                $regras
            ),
            'confirmado' => self::validarConfirmacao($campo, $texto, $rotulo, $dados),
            default => throw new InvalidArgumentException('Regra de validação desconhecida: ' . $nome),
        };
    }

    /**
     * @return list<string>
     */
    private static function separarRegras(string $regras): array
    {
        $separadas = array_map('trim', explode('|', $regras));
        if (in_array('', $separadas, true)) {
            throw new InvalidArgumentException('A lista de regras contém uma regra vazia.');
        }

        return $separadas;
    }

    private static function validarConfiguracaoRegras(string $especificacao): void
    {
        $nomesConhecidos = [
            'obrigatorio', 'min', 'max', 'email', 'cnpj', 'data', 'competencia',
            'inteiro', 'decimal', 'em', 'minimo', 'maximo', 'depois_ou_igual',
            'confirmado',
        ];

        foreach (self::separarRegras($especificacao) as $regra) {
            [$nome, $parametro] = self::lerRegra($regra);
            if (!in_array($nome, $nomesConhecidos, true)) {
                throw new InvalidArgumentException('Regra de validação desconhecida: ' . $nome);
            }

            if (in_array($nome, ['min', 'max'], true)) {
                self::limiteInteiro($parametro);
            } elseif (in_array($nome, ['minimo', 'maximo'], true)) {
                if (self::decimalCanonico((string) $parametro) === null) {
                    throw new InvalidArgumentException('Limite numérico inválido na regra.');
                }
            } elseif ($nome === 'em') {
                $opcoes = array_map('trim', explode(',', (string) $parametro));
                if (in_array('', $opcoes, true)) {
                    throw new InvalidArgumentException('A regra em exige opções válidas.');
                }
            } elseif ($nome === 'depois_ou_igual' && trim((string) $parametro) === '') {
                throw new InvalidArgumentException('A regra depois_ou_igual exige o nome de outro campo.');
            }
        }
    }

    private static function estaVazio(mixed $valor): bool
    {
        if ($valor === null || $valor === false || $valor === []) {
            return true;
        }

        return is_string($valor) && trim($valor) === '';
    }

    private static function comoTexto(mixed $valor): ?string
    {
        return is_scalar($valor) ? (string) $valor : null;
    }

    private static function validarObrigatorio(mixed $valor, string $rotulo): ?string
    {
        return self::estaVazio($valor) ? $rotulo . ' é obrigatório.' : null;
    }

    /**
     * @return array{string, ?string}
     */
    private static function lerRegra(string $regra): array
    {
        $partes = explode(':', $regra, 2);
        $nome = $partes[0];
        $parametro = $partes[1] ?? null;

        if ($nome === '' || in_array($nome, ['min', 'max', 'em', 'minimo', 'maximo', 'depois_ou_igual'], true) && $parametro === null) {
            throw new InvalidArgumentException('Parâmetro ausente na regra ' . $regra . '.');
        }

        if (in_array($nome, ['obrigatorio', 'email', 'cnpj', 'data', 'competencia', 'inteiro', 'decimal', 'confirmado'], true) && $parametro !== null) {
            throw new InvalidArgumentException('A regra ' . $nome . ' não aceita parâmetro.');
        }

        return [$nome, $parametro];
    }

    private static function validarTamanhoMinimo(?string $valor, string $rotulo, ?string $limite): ?string
    {
        $quantidade = self::limiteInteiro($limite);

        return $valor !== null && mb_strlen($valor) < $quantidade
            ? $rotulo . ' deve ter pelo menos ' . $quantidade . ' caracteres.'
            : null;
    }

    private static function validarTamanhoMaximo(?string $valor, string $rotulo, ?string $limite): ?string
    {
        $quantidade = self::limiteInteiro($limite);

        return $valor !== null && mb_strlen($valor) > $quantidade
            ? $rotulo . ' deve ter no máximo ' . $quantidade . ' caracteres.'
            : null;
    }

    private static function validarEmail(?string $valor, string $rotulo): ?string
    {
        return $valor !== null && filter_var($valor, FILTER_VALIDATE_EMAIL) === false
            ? $rotulo . ' deve ser um endereço de e-mail válido.'
            : null;
    }

    private static function validarCnpj(?string $valor, string $rotulo): ?string
    {
        return $valor !== null && !Cnpj::validar($valor)
            ? $rotulo . ' deve ser um CNPJ válido.'
            : null;
    }

    private static function validarData(?string $valor, string $rotulo): ?string
    {
        return $valor !== null && !self::dataValida($valor)
            ? $rotulo . ' deve ser uma data válida no formato AAAA-MM-DD.'
            : null;
    }

    private static function dataValida(string $valor): bool
    {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return $data !== false
            && $data->format('Y-m-d') === $valor
            && DateTimeImmutable::getLastErrors() === false;
    }

    private static function validarCompetencia(?string $valor, string $rotulo): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (preg_match('/\A([0-9]{4})-([0-9]{2})\z/', $valor, $partes) !== 1) {
            return $rotulo . ' deve ser uma competência válida no formato AAAA-MM.';
        }

        $ano = (int) $partes[1];
        $mes = (int) $partes[2];

        return checkdate($mes, 1, $ano)
            ? null
            : $rotulo . ' deve ser uma competência válida no formato AAAA-MM.';
    }

    private static function validarInteiro(?string $valor, string $rotulo): ?string
    {
        return $valor !== null && preg_match('/\A[+-]?[0-9]+\z/', $valor) !== 1
            ? $rotulo . ' deve ser um número inteiro.'
            : null;
    }

    private static function validarDecimal(?string $valor, string $rotulo): ?string
    {
        return $valor !== null && self::decimalCanonico($valor) === null
            ? $rotulo . ' deve ser um número decimal válido.'
            : null;
    }

    private static function validarOpcao(?string $valor, string $rotulo, ?string $opcoes): ?string
    {
        $permitidos = array_map('trim', explode(',', (string) $opcoes));

        return $valor !== null && !in_array($valor, $permitidos, true)
            ? $rotulo . ' deve ser um dos valores permitidos.'
            : null;
    }

    private static function validarLimiteNumerico(
        ?string $valor,
        string $rotulo,
        ?string $limite,
        bool $minimo
    ): ?string {
        $valorCanonico = $valor === null ? null : self::decimalCanonico($valor);
        $limiteCanonico = self::decimalCanonico((string) $limite);
        if ($limiteCanonico === null) {
            throw new InvalidArgumentException('Limite numérico inválido na regra.');
        }

        if ($valorCanonico === null) {
            return $rotulo . ' deve ser um número válido.';
        }

        $comparacao = self::compararDecimais($valorCanonico, $limiteCanonico);
        $invalido = $minimo ? $comparacao < 0 : $comparacao > 0;
        if (!$invalido) {
            return null;
        }

        return $minimo
            ? $rotulo . ' deve ser no mínimo ' . $limite . '.'
            : $rotulo . ' deve ser no máximo ' . $limite . '.';
    }

    /**
     * @param array<string, mixed> $dados
     * @param array<string, array{rotulo: string, regras: string}> $regras
     */
    private static function validarOrdemDatas(
        ?string $valor,
        string $rotulo,
        ?string $campoReferencia,
        array $dados,
        array $regras
    ): ?string {
        $referencia = $dados[(string) $campoReferencia] ?? null;
        $dataReferencia = self::comoTexto($referencia);
        $rotuloReferencia = $regras[(string) $campoReferencia]['rotulo']
            ?? self::rotuloPadrao((string) $campoReferencia);

        if (
            $valor === null
            || $dataReferencia === null
            || !self::dataValida($valor)
            || !self::dataValida($dataReferencia)
        ) {
            return $rotulo . ' deve ser uma data válida e posterior ou igual a ' . $rotuloReferencia . '.';
        }

        if (strcmp($valor, $dataReferencia) >= 0) {
            return null;
        }

        return $rotulo . ' deve ser posterior ou igual a ' . $rotuloReferencia . '.';
    }

    /**
     * @param array<string, mixed> $dados
     */
    private static function validarConfirmacao(
        string $campo,
        ?string $valor,
        string $rotulo,
        array $dados
    ): ?string {
        $confirmacao = self::comoTexto($dados[$campo . '_confirmacao'] ?? null);

        return $valor !== null && $valor !== $confirmacao
            ? $rotulo . ' e sua confirmação não coincidem.'
            : null;
    }

    private static function limiteInteiro(?string $limite): int
    {
        if ($limite === null || preg_match('/\A[0-9]+\z/', $limite) !== 1) {
            throw new InvalidArgumentException('Limite de caracteres inválido na regra.');
        }

        return (int) $limite;
    }

    private static function decimalCanonico(string $valor): ?string
    {
        $valor = trim($valor);
        if (str_contains($valor, ',')) {
            if (preg_match('/\A([+-]?)([0-9]+|[0-9]{1,3}(?:\.[0-9]{3})+),([0-9]+)\z/', $valor, $partes) !== 1) {
                return null;
            }

            return $partes[1] . str_replace('.', '', $partes[2]) . '.' . $partes[3];
        }

        return preg_match('/\A[+-]?[0-9]+(?:\.[0-9]+)?\z/', $valor) === 1
            ? $valor
            : null;
    }

    private static function compararDecimais(string $primeiro, string $segundo): int
    {
        [$sinalA, $inteiroA, $fracaoA] = self::partesDecimal($primeiro);
        [$sinalB, $inteiroB, $fracaoB] = self::partesDecimal($segundo);

        if ($sinalA !== $sinalB) {
            return $sinalA <=> $sinalB;
        }

        $comparacao = self::compararMagnitude($inteiroA, $fracaoA, $inteiroB, $fracaoB);

        return $sinalA < 0 ? -$comparacao : $comparacao;
    }

    /**
     * @return array{int, string, string}
     */
    private static function partesDecimal(string $valor): array
    {
        $sinal = str_starts_with($valor, '-') ? -1 : 1;
        $semSinal = ltrim($valor, '+-');
        [$inteiro, $fracao] = array_pad(explode('.', $semSinal, 2), 2, '');
        $inteiro = ltrim($inteiro, '0') ?: '0';
        $fracao = rtrim($fracao, '0');

        if ($inteiro === '0' && $fracao === '') {
            $sinal = 1;
        }

        return [$sinal, $inteiro, $fracao];
    }

    private static function compararMagnitude(
        string $inteiroA,
        string $fracaoA,
        string $inteiroB,
        string $fracaoB
    ): int {
        if (strlen($inteiroA) !== strlen($inteiroB)) {
            return strlen($inteiroA) <=> strlen($inteiroB);
        }

        $comparacaoInteiros = strcmp($inteiroA, $inteiroB);
        if ($comparacaoInteiros !== 0) {
            return $comparacaoInteiros <=> 0;
        }

        $tamanhoFracao = max(strlen($fracaoA), strlen($fracaoB));

        return strcmp(
            str_pad($fracaoA, $tamanhoFracao, '0'),
            str_pad($fracaoB, $tamanhoFracao, '0')
        ) <=> 0;
    }

    private static function rotuloPadrao(string $campo): string
    {
        $nome = str_replace(['_', '-'], ' ', $campo);

        return mb_strtoupper(mb_substr($nome, 0, 1)) . mb_substr($nome, 1);
    }
}
