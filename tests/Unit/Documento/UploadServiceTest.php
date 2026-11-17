<?php

declare(strict_types=1);

namespace Tests\Unit\Documento;

use App\Services\Documento\UploadService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UploadServiceTest extends TestCase
{
    private string $pastaTemporaria;
    private UploadService $servico;

    protected function setUp(): void
    {
        $this->pastaTemporaria = sys_get_temp_dir() . '/tributo_upload_test_' . bin2hex(random_bytes(6));
        mkdir($this->pastaTemporaria, 0755, true);
        $this->servico = new UploadService($this->pastaTemporaria);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->pastaTemporaria)) {
            $arquivos = glob($this->pastaTemporaria . '/*');
            if ($arquivos !== false) {
                foreach ($arquivos as $arq) {
                    @unlink($arq);
                }
            }
            @rmdir($this->pastaTemporaria);
        }
    }

    public function testRejeitaArquivoNulo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nenhum arquivo foi enviado.');

        $this->servico->salvarUpload(null);
    }

    public function testRejeitaArquivoComErroDeUpload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nenhum arquivo foi enviado.');

        $this->servico->salvarUpload([
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ]);
    }

    public function testRejeitaArquivoAcimaDoLimiteDe5MB(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O arquivo ultrapassa o limite máximo permitido de 5 MB.');

        $this->servico->salvarUpload([
            'error' => UPLOAD_ERR_OK,
            'size' => 6 * 1024 * 1024,
            'name' => 'grande.pdf',
        ]);
    }

    public function testRejeitaExtensaoNaoPermitida(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Formato não permitido');

        $this->servico->salvarUpload([
            'error' => UPLOAD_ERR_OK,
            'size' => 20,
            'name' => 'script.php',
            'tmp_name' => 'qualquer',
        ]);
    }

    public function testUploadXmlValidoComSucesso(): void
    {
        $tmpArquivo = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpArquivo, '<?xml version="1.0"?><nfeProc></nfeProc>');

        try {
            $resultado = $this->servico->salvarUpload([
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpArquivo),
                'name' => 'Nota_Fiscal.xml',
                'tmp_name' => $tmpArquivo,
            ]);

            $this->assertSame('Nota_Fiscal.xml', $resultado['nome_original']);
            $this->assertStringEndsWith('.xml', $resultado['caminho_relativo']);
            $this->assertFileExists($this->pastaTemporaria . '/' . $resultado['caminho_relativo']);
        } finally {
            @unlink($tmpArquivo);
        }
    }

    public function testConstantesDeUploadCorretas(): void
    {
        $this->assertSame(5 * 1024 * 1024, UploadService::LIMITE_BYTES);
        $this->assertArrayHasKey('pdf', UploadService::EXTENSOES_PERMITIDAS);
        $this->assertArrayHasKey('jpg', UploadService::EXTENSOES_PERMITIDAS);
        $this->assertArrayHasKey('png', UploadService::EXTENSOES_PERMITIDAS);
        $this->assertArrayHasKey('xml', UploadService::EXTENSOES_PERMITIDAS);
    }
}
