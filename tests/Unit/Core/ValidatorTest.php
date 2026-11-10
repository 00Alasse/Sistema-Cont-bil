<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class ValidatorTest extends TestCase
{
    #[DataProvider('valoresValidos')]
    public function testRegrasAceitamValoresValidos(string $regra, mixed $valor): void
    {
        $dados = ['campo' => $valor];
        $regras = ['campo' => ['rotulo' => 'Campo', 'regras' => $regra]];
        if (str_starts_with($regra, 'depois_ou_igual:')) {
            $dados['inicio'] = '2024-02-28';
            $regras['inicio'] = ['rotulo' => 'Início', 'regras' => 'data'];
        }
        if ($regra === 'confirmado') {
            $dados['campo_confirmacao'] = $valor;
        }

        self::assertSame([], Validator::validar(
            $dados,
            $regras
        ));
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function valoresValidos(): array
    {
        return [
            'obrigatório com conteúdo' => ['obrigatorio', 'texto'],
            'mínimo de texto' => ['min:3', 'abc'],
            'máximo de texto' => ['max:3', 'abc'],
            'e-mail' => ['email', 'ana@exemplo.test'],
            'CNPJ numérico' => ['cnpj', '04.252.011/0001-10'],
            'CNPJ alfanumérico' => ['cnpj', '12.ABC.345/01DE-35'],
            'data válida' => ['data', '2024-02-29'],
            'competência válida' => ['competencia', '2024-02'],
            'inteiro zero' => ['inteiro', '0'],
            'inteiro negativo' => ['inteiro', '-12'],
            'inteiro com zeros à esquerda' => ['inteiro', '0012'],
            'decimal brasileiro' => ['decimal', '1.234,56'],
            'decimal com ponto' => ['decimal', '1234.56'],
            'valor permitido' => ['em:ATIVA,INATIVA', 'ATIVA'],
            'valor numérico mínimo' => ['minimo:10', '10'],
            'mínimo numérico decimal' => ['minimo:1,5', '1.50'],
            'valor numérico máximo' => ['maximo:10', '10'],
            'datas em ordem' => ['depois_ou_igual:inicio', '2024-02-29'],
            'confirmação' => ['confirmado', 'segredo'],
            'regras combinadas' => ['obrigatorio|min:3|max:10|em:OK,PRONTO', 'PRONTO'],
        ];
    }

    #[DataProvider('valoresInvalidos')]
    public function testRegrasRejeitamValoresInvalidos(string $regra, mixed $valor, string $mensagem): void
    {
        $dados = ['campo' => $valor];
        $regras = ['campo' => ['rotulo' => 'Meu campo', 'regras' => $regra]];
        if (str_starts_with($regra, 'depois_ou_igual:')) {
            $dados['inicio'] = '2024-03-01';
            $regras['inicio'] = ['rotulo' => 'Início', 'regras' => 'data'];
        }

        $erros = Validator::validar(
            $dados,
            $regras
        );

        self::assertSame(['campo' => [$mensagem]], $erros);
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function valoresInvalidos(): array
    {
        return [
            'obrigatório vazio' => ['obrigatorio', '', 'Meu campo é obrigatório.'],
            'mínimo de texto' => ['min:3', 'ab', 'Meu campo deve ter pelo menos 3 caracteres.'],
            'máximo de texto' => ['max:3', 'abcd', 'Meu campo deve ter no máximo 3 caracteres.'],
            'e-mail inválido' => ['email', 'inválido', 'Meu campo deve ser um endereço de e-mail válido.'],
            'CNPJ inválido' => ['cnpj', '04.252.011/0001-11', 'Meu campo deve ser um CNPJ válido.'],
            'data impossível' => ['data', '2023-02-29', 'Meu campo deve ser uma data válida no formato AAAA-MM-DD.'],
            'formato de data errado' => ['data', '29/02/2024', 'Meu campo deve ser uma data válida no formato AAAA-MM-DD.'],
            'competência sem mês válido' => ['competencia', '2024-13', 'Meu campo deve ser uma competência válida no formato AAAA-MM.'],
            'inteiro decimal' => ['inteiro', '1.2', 'Meu campo deve ser um número inteiro.'],
            'decimal inválido' => ['decimal', '12,34.56', 'Meu campo deve ser um número decimal válido.'],
            'fora das opções' => ['em:ATIVA,INATIVA', 'PENDENTE', 'Meu campo deve ser um dos valores permitidos.'],
            'abaixo do mínimo numérico' => ['minimo:10', '9,99', 'Meu campo deve ser no mínimo 10.'],
            'abaixo do mínimo decimal' => ['minimo:1,5', '1.49', 'Meu campo deve ser no mínimo 1,5.'],
            'acima do máximo numérico' => ['maximo:10', '10.01', 'Meu campo deve ser no máximo 10.'],
            'data anterior' => ['depois_ou_igual:inicio', '2024-02-28', 'Meu campo deve ser posterior ou igual a Início.'],
            'confirmação diferente' => ['confirmado', 'segredo', 'Meu campo e sua confirmação não coincidem.'],
            'array em regra textual' => ['email', ['email'], 'Meu campo tem um formato inválido.'],
        ];
    }

    public function testRetornaErrosDeMultiplosCamposComRotulos(): void
    {
        $erros = Validator::validar(
            [
                'razao_social' => 'AB',
                'email' => 'cliente-invalido',
                'nome_fantasia' => '   ',
            ],
            [
                'razao_social' => [
                    'rotulo' => 'Razão social',
                    'regras' => 'obrigatorio|min:3|max:150',
                ],
                'email' => [
                    'rotulo' => 'E-mail',
                    'regras' => 'email',
                ],
                'nome_fantasia' => [
                    'rotulo' => 'Nome fantasia',
                    'regras' => 'min:3|max:150',
                ],
            ]
        );

        self::assertSame([
            'razao_social' => ['Razão social deve ter pelo menos 3 caracteres.'],
            'email' => ['E-mail deve ser um endereço de e-mail válido.'],
        ], $erros);
    }

    public function testValorObrigatorioEmBrancoGeraSomenteErroDeObrigatoriedade(): void
    {
        self::assertSame(
            ['nome' => ['Nome é obrigatório.']],
            Validator::validar(
                ['nome' => " \t\n "],
                ['nome' => ['rotulo' => 'Nome', 'regras' => 'obrigatorio|min:3|email']]
            )
        );
    }

    public function testCampoOpcionalVazioIgnoraTodasAsRegras(): void
    {
        foreach (['', " \t\n ", null] as $valor) {
            self::assertSame(
                [],
                Validator::validar(
                    ['email' => $valor],
                    ['email' => ['rotulo' => 'E-mail', 'regras' => 'email|min:5|max:10']]
                )
            );
        }
    }

    public function testRegraConfirmadoUsaCampoDeConfirmacao(): void
    {
        self::assertSame(
            [],
            Validator::validar(
                ['senha' => 'segredo', 'senha_confirmacao' => 'segredo'],
                ['senha' => ['rotulo' => 'Senha', 'regras' => 'confirmado']]
            )
        );
    }

    public function testRegraDeDatasUsaRotuloDoCampoComparado(): void
    {
        self::assertSame(
            [
                'fim' => ['Fim deve ser posterior ou igual a Início.'],
            ],
            Validator::validar(
                ['inicio' => '2024-03-01', 'fim' => '2024-02-29'],
                [
                    'fim' => ['rotulo' => 'Fim', 'regras' => 'depois_ou_igual:inicio'],
                    'inicio' => ['rotulo' => 'Início', 'regras' => 'data'],
                ]
            )
        );
    }

    public function testRegraDeDatasRejeitaDataDeReferenciaInvalida(): void
    {
        self::assertSame(
            [
                'fim' => ['Fim deve ser uma data válida e posterior ou igual a Início.'],
                'inicio' => ['Início deve ser uma data válida no formato AAAA-MM-DD.'],
            ],
            Validator::validar(
                ['inicio' => '2024-02-30', 'fim' => '2024-03-01'],
                [
                    'fim' => ['rotulo' => 'Fim', 'regras' => 'depois_ou_igual:inicio'],
                    'inicio' => ['rotulo' => 'Início', 'regras' => 'data'],
                ]
            )
        );
    }

    public function testRegrasDesconhecidasGeramErroDeConfiguracao(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Validator::validar(
            ['campo' => 'valor'],
            ['campo' => ['rotulo' => 'Campo', 'regras' => 'regra_inexistente']]
        );
    }

    public function testRegrasDesconhecidasSaoRejeitadasMesmoComCampoOpcionalVazio(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Validator::validar(
            ['campo' => '   '],
            ['campo' => ['rotulo' => 'Campo', 'regras' => 'regra_inexistente']]
        );
    }

    public function testRetornaMaisDeUmErroParaOMesmoCampo(): void
    {
        self::assertSame(
            [
                'nome' => [
                    'Nome deve ter pelo menos 3 caracteres.',
                    'Nome deve ser um endereço de e-mail válido.',
                ],
            ],
            Validator::validar(
                ['nome' => 'AB'],
                ['nome' => ['rotulo' => 'Nome', 'regras' => 'min:3|email']]
            )
        );
    }
}
