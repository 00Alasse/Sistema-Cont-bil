<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Cnpj;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CnpjTest extends TestCase
{
    #[DataProvider('valoresParaNormalizar')]
    public function testNormalizaFormatoEspacosEMinusculas(string $entrada, string $esperado): void
    {
        self::assertSame($esperado, Cnpj::normalizar($entrada));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function valoresParaNormalizar(): array
    {
        return [
            'máscara numérica' => ['04.252.011/0001-10', '04252011000110'],
            'minúsculas alfanuméricas' => ['12.abc.345/01de-35', '12ABC34501DE35'],
            'espaços externos' => [" \t04.252.011/0001-10 \n", '04252011000110'],
            'espaços internos' => ['12 ABC 345/01DE-35', '12ABC34501DE35'],
            'espaço Unicode' => ["12\u{00A0}ABC.345/01DE-35", '12ABC34501DE35'],
            'sem máscara' => ['04252011000110', '04252011000110'],
            'preserva caractere inválido para validação' => ['04#252.011/0001-10', '04#252011000110'],
        ];
    }

    #[DataProvider('cnpjsValidos')]
    public function testValidaCnpjsNumericosEAlfanumericos(string $cnpj): void
    {
        self::assertTrue(Cnpj::validar($cnpj));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cnpjsValidos(): array
    {
        return [
            'numérico formatado' => ['04.252.011/0001-10'],
            'numérico sem máscara' => ['04252011000110'],
            'exemplo oficial alfanumérico' => ['12.ABC.345/01DE-35'],
            'exemplo oficial sem máscara' => ['12ABC34501DE35'],
            'minúsculas normalizadas' => ['12.abc.345/01de-35'],
        ];
    }

    #[DataProvider('cnpjsInvalidos')]
    public function testRejeitaCnpjsInvalidos(string $cnpj): void
    {
        self::assertFalse(Cnpj::validar($cnpj));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cnpjsInvalidos(): array
    {
        return [
            'vazio' => [''],
            'curto' => ['1234567890123'],
            'longo' => ['123456789012345'],
            'todos zeros' => ['00000000000000'],
            'todos uns' => ['11111111111111'],
            'letra na base numérica' => ['04252011A00110'],
            'letra no primeiro dígito verificador' => ['042520110001A0'],
            'base com caractere inválido' => ['12#BC34501DE35'],
            'primeiro DV incorreto numérico' => ['04252011000120'],
            'segundo DV incorreto numérico' => ['04252011000111'],
            'DV alfanumérico incorreto' => ['12ABC34501DE36'],
            'menos de doze caracteres de base' => ['ABC34501DE35'],
            'espaços sem conteúdo' => ['               '],
        ];
    }

    #[DataProvider('valoresParaFormatar')]
    public function testFormataCnpj(string $entrada, string $esperado): void
    {
        self::assertSame($esperado, Cnpj::formatar($entrada));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function valoresParaFormatar(): array
    {
        return [
            'formata número' => ['04252011000110', '04.252.011/0001-10'],
            'formata alfanumérico oficial' => ['12ABC34501DE35', '12.ABC.345/01DE-35'],
            'normaliza antes de formatar' => ['12.abc.345/01de-35', '12.ABC.345/01DE-35'],
            'retorna normalizado se tamanho inválido' => ['12345', '12345'],
        ];
    }

    public function testGeraCnpjsNumericosValidosParaVariasSementes(): void
    {
        foreach ([-42, 0, 1, 2026, PHP_INT_MAX, PHP_INT_MIN] as $semente) {
            $cnpj = Cnpj::gerarFicticio($semente);

            self::assertMatchesRegularExpression('/\A[0-9]{14}\z/', $cnpj);
            self::assertTrue(Cnpj::validar($cnpj));
        }
    }

    public function testGeracaoEhDeterministica(): void
    {
        self::assertSame(Cnpj::gerarFicticio(123), Cnpj::gerarFicticio(123));
        self::assertNotSame(Cnpj::gerarFicticio(123), Cnpj::gerarFicticio(124));
    }
}
