<?php

declare(strict_types=1);

namespace App\Services\Documento;

use finfo;
use InvalidArgumentException;
use RuntimeException;

final class UploadService
{
    public const LIMITE_BYTES = 5 * 1024 * 1024; // 5 MB

    public const EXTENSOES_PERMITIDAS = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'xml'  => ['application/xml', 'text/xml'],
    ];

    private readonly string $diretorioUploads;

    public function __construct(?string $diretorioUploads = null)
    {
        $this->diretorioUploads = $diretorioUploads ?? dirname(__DIR__, 3) . '/storage/uploads';
    }

    /**
     * @param array<string, mixed>|null $arquivo Entrada de $_FILES['...']
     * @return array{caminho_relativo: string, nome_original: string, mime: string}
     * @throws InvalidArgumentException Se o arquivo não atender aos critérios de validação.
     */
    public function salvarUpload(?array $arquivo): array
    {
        if ($arquivo === null || !isset($arquivo['error']) || $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Nenhum arquivo foi enviado.');
        }

        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Falha no envio do arquivo (código: ' . (int) $arquivo['error'] . ').');
        }

        $tamanho = (int) ($arquivo['size'] ?? 0);
        if ($tamanho <= 0 || $tamanho > self::LIMITE_BYTES) {
            throw new InvalidArgumentException('O arquivo ultrapassa o limite máximo permitido de 5 MB.');
        }

        $nomeOriginal = (string) ($arquivo['name'] ?? 'documento');
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        if (!array_key_exists($extensao, self::EXTENSOES_PERMITIDAS)) {
            throw new InvalidArgumentException('Formato não permitido. Formatos aceitos: PDF, JPG, PNG e XML.');
        }

        $tmpName = (string) ($arquivo['tmp_name'] ?? '');
        $ehArquivoValido = $tmpName !== '' && (is_uploaded_file($tmpName) || (defined('PHPUNIT_COMPOSER_INSTALL') && file_exists($tmpName)));
        if (!$ehArquivoValido) {
            throw new InvalidArgumentException('O arquivo enviado é inválido.');
        }

        // Validação real do tipo MIME usando finfo (não confia na extensão nem no cabeçalho enviado)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeDetectado = $finfo->file($tmpName);
        if (!is_string($mimeDetectado)) {
            throw new InvalidArgumentException('Não foi possível identificar o tipo de arquivo.');
        }

        $mimeEsperado = self::EXTENSOES_PERMITIDAS[$extensao];
        $mimeValido = is_array($mimeEsperado)
            ? in_array($mimeDetectado, $mimeEsperado, true)
            : ($mimeDetectado === $mimeEsperado);

        if (!$mimeValido) {
            throw new InvalidArgumentException('O conteúdo do arquivo não corresponde à sua extensão (' . $extensao . ').');
        }

        if (!is_dir($this->diretorioUploads) && !mkdir($this->diretorioUploads, 0755, true) && !is_dir($this->diretorioUploads)) {
            throw new RuntimeException('Não foi possível criar a pasta de uploads.');
        }

        // Nome aleatório imprevisível no servidor (nunca grava o nome original em disco)
        $nomeAleatorio = bin2hex(random_bytes(16)) . '.' . $extensao;
        $destinoAbsoluto = $this->diretorioUploads . '/' . $nomeAleatorio;

        $gravou = is_uploaded_file($tmpName)
            ? move_uploaded_file($tmpName, $destinoAbsoluto)
            : copy($tmpName, $destinoAbsoluto);

        if (!$gravou) {
            throw new RuntimeException('Falha ao gravar o arquivo enviado.');
        }

        // Sanitiza o nome original para exibição segura
        $nomeOriginalLimpo = basename((string) preg_replace('/[^\p{L}\p{N}._-]/u', '_', $nomeOriginal) ?: ('documento.' . $extensao));

        return [
            'caminho_relativo' => $nomeAleatorio,
            'nome_original' => $nomeOriginalLimpo,
            'mime' => $mimeDetectado,
        ];
    }

    /**
     * Apaga UM arquivo recém-gravado por salvarUpload, quando o registro no banco não foi confirmado.
     *
     * Só aceita exatamente o formato que salvarUpload gera (32 caracteres hexadecimais + extensão
     * permitida), sem pastas, dentro do diretório de uploads. Não faz buscas no diretório: quem chama
     * passa o nome devolvido pelo salvarUpload da mesma requisição. Retorna false se não apagou.
     */
    public function removerEnvioNaoConfirmado(string $caminhoRelativo): bool
    {
        $extensoes = implode('|', array_keys(self::EXTENSOES_PERMITIDAS));
        if (preg_match('/\A[0-9a-f]{32}\.(?:' . $extensoes . ')\z/', $caminhoRelativo) !== 1) {
            return false;
        }

        $base = realpath($this->diretorioUploads);
        $arquivo = $base === false ? false : realpath($base . DIRECTORY_SEPARATOR . $caminhoRelativo);
        if ($base === false || $arquivo === false || dirname($arquivo) !== $base || !is_file($arquivo)) {
            return false;
        }

        return unlink($arquivo);
    }

    public function caminhoAbsoluto(string $caminhoRelativo): string
    {
        // Evita directory traversal
        $base = realpath($this->diretorioUploads);
        $nomeArquivo = basename($caminhoRelativo);
        $destino = $this->diretorioUploads . '/' . $nomeArquivo;

        if ($base === false || !file_exists($destino)) {
            throw new RuntimeException('Arquivo não encontrado no servidor.');
        }

        return $destino;
    }
}
