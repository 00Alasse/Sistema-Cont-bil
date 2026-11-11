<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Relogio;
use App\Support\RelogioSistema;
use LogicException;

/**
 * Sessão segura com flash. Usa a sessão nativa do PHP (web) ou um array (testes e CLI),
 * para que os testes não dependam de cookies nem de cabeçalhos HTTP.
 */
final class Session
{
    private const CHAVE_ATIVIDADE = '_ultima_atividade';
    private const CHAVE_FLASH = '_flash';
    private const CHAVE_CSRF = '_csrf';

    private static ?self $atual = null;

    /**
     * @var array<string, mixed>
     */
    private array $dados;

    private string $identificador;

    /**
     * @param array<string, mixed> $dados
     * @param array<string, mixed> $opcoesCookie
     */
    private function __construct(
        array &$dados,
        private readonly Relogio $relogio,
        private readonly int $inatividadeMin,
        private readonly bool $nativa,
        private readonly string $nome = 'tributo_sessao',
        private readonly array $opcoesCookie = []
    ) {
        $this->dados = &$dados;
        $this->identificador = bin2hex(random_bytes(16));
    }

    /**
     * Sessão em memória: serve aos testes e ao CLI, onde não há cookie nem cabeçalho.
     *
     * @param array<string, mixed> $dados
     */
    public static function emArray(array &$dados, Relogio $relogio, int $inatividadeMin): self
    {
        return new self($dados, $relogio, $inatividadeMin, false);
    }

    /**
     * Monta a sessão a partir de config/app.php. Fora do navegador (CLI) usa memória.
     */
    public static function daConfiguracao(): self
    {
        $inatividade = max(1, (int) Config::obter('sessao.inatividade_min', 30));
        $relogio = new RelogioSistema();

        if (PHP_SAPI === 'cli') {
            $dados = [];

            return new self($dados, $relogio, $inatividade, false);
        }

        $nome = Config::obter('sessao.nome', 'tributo_sessao');
        $seguro = filter_var(Config::obter('sessao.cookie_seguro', false), FILTER_VALIDATE_BOOLEAN);
        $dadosNativos = [];

        return new self($dadosNativos, $relogio, $inatividade, true, is_string($nome) ? $nome : 'tributo_sessao', [
            'lifetime' => 0,
            'path' => '/',
            'secure' => $seguro,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function atual(): self
    {
        return self::$atual ?? throw new LogicException('A sessão ainda não foi iniciada.');
    }

    public static function definirAtual(?self $sessao): void
    {
        self::$atual = $sessao;
    }

    public function iniciar(): void
    {
        if ($this->nativa && session_status() !== PHP_SESSION_ACTIVE) {
            $this->iniciarNativa();
        }

        $agora = $this->relogio->agora()->getTimestamp();
        $ultima = $this->dados[self::CHAVE_ATIVIDADE] ?? null;
        $expirou = is_int($ultima) && ($agora - $ultima) > $this->inatividadeMin * 60;

        if ($expirou) {
            // Descarta tudo (inclusive o token CSRF) e troca o ID para que o antigo não sirva mais.
            $this->dados = [];
            $this->regenerar();
            $this->flash('aviso', 'Sua sessão expirou');
        }

        $this->dados[self::CHAVE_ATIVIDADE] = $agora;
    }

    public function obter(string $chave, mixed $padrao = null): mixed
    {
        return $this->dados[$chave] ?? $padrao;
    }

    public function definir(string $chave, mixed $valor): void
    {
        $this->dados[$chave] = $valor;
    }

    public function remover(string $chave): void
    {
        unset($this->dados[$chave]);
    }

    public function id(): string
    {
        return $this->nativa ? session_id() ?: '' : $this->identificador;
    }

    /**
     * Troca o ID (contra fixação de sessão) e o token CSRF; os demais dados são mantidos.
     */
    public function regenerar(): void
    {
        if ($this->nativa) {
            session_regenerate_id(true);
        } else {
            $this->identificador = bin2hex(random_bytes(16));
        }

        unset($this->dados[self::CHAVE_CSRF]);
    }

    public function destruir(): void
    {
        $this->dados = [];

        if ($this->nativa && session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
            if (!headers_sent()) {
                // setcookie não aceita a chave "lifetime" usada em session_set_cookie_params.
                setcookie($this->nome, '', [
                    'expires' => 1,
                    'path' => '/',
                    'secure' => (bool) ($this->opcoesCookie['secure'] ?? false),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
    }

    public function flash(string $tipo, string $msg): void
    {
        $fila = $this->dados[self::CHAVE_FLASH] ?? [];
        $fila = is_array($fila) ? $fila : [];
        $fila[] = ['tipo' => $tipo, 'mensagem' => $msg];
        $this->dados[self::CHAVE_FLASH] = $fila;
    }

    /**
     * Devolve as mensagens pendentes e as apaga: cada flash é lido uma única vez.
     *
     * @return list<array{tipo: string, mensagem: string}>
     */
    public function consumirFlash(): array
    {
        $fila = $this->dados[self::CHAVE_FLASH] ?? [];
        unset($this->dados[self::CHAVE_FLASH]);

        $mensagens = [];
        foreach (is_array($fila) ? $fila : [] as $item) {
            if (is_array($item) && is_string($item['tipo'] ?? null) && is_string($item['mensagem'] ?? null)) {
                $mensagens[] = ['tipo' => $item['tipo'], 'mensagem' => $item['mensagem']];
            }
        }

        return $mensagens;
    }

    private function iniciarNativa(): void
    {
        // Modo estrito: o PHP recusa IDs de sessão que o servidor não criou.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name($this->nome);
        session_set_cookie_params($this->opcoesCookie);

        if (!session_start()) {
            throw new LogicException('Não foi possível iniciar a sessão.');
        }

        $this->dados = &$_SESSION;
    }
}
