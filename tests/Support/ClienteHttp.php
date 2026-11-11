<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Cliente HTTP mínimo para os testes E2E: cURL com cookies, sem seguir redirecionamentos
 * (os testes querem ver o 302 e o Location).
 */
final class ClienteHttp
{
    private readonly string $arquivoCookies;

    /** @var array<string, string> */
    private array $cookiesManuais = [];

    public function __construct(private readonly string $urlBase)
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'tributo-cookies-');
        if ($arquivo === false) {
            throw new RuntimeException('Não foi possível criar o arquivo de cookies do teste.');
        }
        $this->arquivoCookies = $arquivo;
    }

    public function __destruct()
    {
        if (is_file($this->arquivoCookies)) {
            unlink($this->arquivoCookies);
        }
    }

    /**
     * Envia também este cookie (por exemplo, um ID de sessão antigo) além dos que o cliente guardou.
     */
    public function comCookie(string $nome, string $valor): self
    {
        $this->cookiesManuais[$nome] = $valor;

        return $this;
    }

    public function get(string $caminho): RespostaHttp
    {
        return $this->enviar('GET', $caminho, null);
    }

    /**
     * @param array<string, string> $campos
     */
    public function post(string $caminho, array $campos): RespostaHttp
    {
        return $this->enviar('POST', $caminho, $campos);
    }

    /**
     * Valor atual de um cookie guardado pelo cliente, ou null.
     */
    public function cookie(string $nome): ?string
    {
        $conteudo = file_get_contents($this->arquivoCookies);
        foreach (explode("\n", $conteudo === false ? '' : $conteudo) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || (str_starts_with($linha, '#') && !str_starts_with($linha, '#HttpOnly_'))) {
                continue;
            }

            $partes = explode("\t", $linha);
            if (count($partes) === 7 && $partes[5] === $nome) {
                return $partes[6];
            }
        }

        return null;
    }

    public static function extrairCsrf(string $html): ?string
    {
        if (preg_match('/name="_csrf"\s+value="([0-9a-f]+)"/', $html, $partes) !== 1) {
            return null;
        }

        return $partes[1];
    }

    /**
     * @param array<string, string>|null $campos
     */
    private function enviar(string $metodo, string $caminho, ?array $campos): RespostaHttp
    {
        $curl = curl_init($this->urlBase . $caminho);
        if ($curl === false) {
            throw new RuntimeException('Não foi possível iniciar o cURL.');
        }

        $cabecalhos = [];
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_COOKIEFILE => $this->arquivoCookies,
            CURLOPT_COOKIEJAR => $this->arquivoCookies,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $linha) use (&$cabecalhos): int {
                if (str_contains($linha, ':')) {
                    [$nome, $valor] = explode(':', $linha, 2);
                    $cabecalhos[strtolower(trim($nome))][] = trim($valor);
                }

                return strlen($linha);
            },
        ]);
        if ($this->cookiesManuais !== []) {
            $pares = [];
            foreach ($this->cookiesManuais as $nome => $valor) {
                $pares[] = $nome . '=' . $valor;
            }
            curl_setopt($curl, CURLOPT_COOKIE, implode('; ', $pares));
        }
        if ($campos !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($campos));
        }

        $corpo = curl_exec($curl);
        if (!is_string($corpo)) {
            $erro = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('Falha na requisição de teste: ' . $erro);
        }

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return new RespostaHttp($status, $cabecalhos, $corpo);
    }
}

final class RespostaHttp
{
    /**
     * @param array<string, list<string>> $headers nomes em minúsculas
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $corpo
    ) {
    }

    public function header(string $nome): ?string
    {
        return $this->headers[strtolower($nome)][0] ?? null;
    }

    public function location(): ?string
    {
        return $this->header('Location');
    }
}
