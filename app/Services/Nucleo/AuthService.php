<?php

declare(strict_types=1);

namespace App\Services\Nucleo;

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Core\UsuarioLogado;
use App\Models\Nucleo\TentativaLogin;
use App\Models\Nucleo\Usuario;
use App\Support\Relogio;
use App\Support\RelogioSistema;
use RuntimeException;

final class AuthService
{
    /** Limite de falhas por IP na janela de bloqueio (ARCHITECTURE §8). */
    private const CHAVE_USUARIO = 'auth_usuario_id';
    private const CHAVE_ESCRITORIO = 'auth_escritorio_id';

    private const MAX_FALHAS_POR_IP = 20;
    private const TEMPO_ESPERA_BLOQUEIO_SEGUNDOS = 5;

    /**
     * Hash fictício (de uma senha qualquer) usado quando o e-mail não existe, para que o tempo
     * de resposta não revele se a conta existe.
     */
    private const HASH_FICTICIO = '$2y$10$DiuwXQ6pyLZPjwrlj1mIBuERNI9fo6Ckr183lpMoalfdWAvcy0yXu';

    public function __construct(
        private readonly Usuario $usuarios,
        private readonly TentativaLogin $tentativas,
        private readonly Relogio $relogio
    ) {
    }

    /**
     * O Container não sabe montar models (PDO) nem o Relogio; por isso a montagem padrão fica aqui.
     */
    public static function padrao(): self
    {
        return new self(new Usuario(), new TentativaLogin(), new RelogioSistema());
    }

    public function bloqueado(string $email, string $ip): bool
    {
        $desde = $this->relogio->agora()
            ->modify('-' . $this->minutosDeBloqueio() . ' minutes')
            ->format('Y-m-d H:i:s');

        return $this->tentativas->contarFalhasPorEmail($this->normalizarEmail($email), $desde) >= $this->maxTentativas()
            || $this->tentativas->contarFalhasPorIp($ip, $desde) >= self::MAX_FALHAS_POR_IP;
    }

    /**
     * Devolve o usuário autenticado, ou null por qualquer motivo (senha errada, conta inativa,
     * escritório inativo ou bloqueio). O motivo nunca é exposto a quem chama.
     */
    public function autenticar(string $email, string $senha, string $ip): ?UsuarioLogado
    {
        $email = $this->normalizarEmail($email);
        return $this->comBloqueiosLogin($email, $ip, function () use ($email, $senha, $ip): ?UsuarioLogado {
            if ($this->bloqueado($email, $ip)) {
                return null;
            }

            $registro = $this->usuarios->buscarPorEmailParaLogin($email);
            $hash = is_string($registro['senha_hash'] ?? null) ? $registro['senha_hash'] : self::HASH_FICTICIO;
            $senhaConfere = password_verify($senha, $hash);
            $agora = $this->relogio->agora()->format('Y-m-d H:i:s');
            // Uma única checagem para todos os motivos de falha: quem chama não distingue um do outro.
            if (
                $registro === null
                || !$senhaConfere
                || (int) $registro['usuario_ativo'] !== 1
                || (int) $registro['escritorio_ativo'] !== 1
            ) {
                $this->tentativas->registrar($email, $ip, false, $agora);

                return null;
            }

            $escritorioId = (int) $registro['escritorio_id'];
            $usuarioId = (int) $registro['id'];
            Database::transacao(function () use ($email, $ip, $senha, $hash, $escritorioId, $usuarioId, $agora): void {
                if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                    $this->usuarios->atualizarHash($escritorioId, $usuarioId, password_hash($senha, PASSWORD_DEFAULT));
                }
                $this->usuarios->registrarLogin($escritorioId, $usuarioId, $agora);
                $this->tentativas->registrar($email, $ip, true, $agora);
            });

            return $this->carregarUsuario($escritorioId, $usuarioId);
        });
    }

    /**
     * Serializa tentativas pelo e-mail e pelo IP para que requisições concorrentes não
     * ultrapassem os limites antes de gravarem suas falhas.
     *
     * @param callable(): ?UsuarioLogado $operacao
     */
    private function comBloqueiosLogin(string $email, string $ip, callable $operacao): ?UsuarioLogado
    {
        $conexao = Database::conexao();
        $nomes = [
            'auth:e:' . substr(hash('sha256', $email), 0, 57),
            'auth:i:' . substr(hash('sha256', $ip), 0, 57),
        ];
        sort($nomes, SORT_STRING);
        $adquiridos = [];

        try {
            foreach ($nomes as $nome) {
                $stmt = $conexao->prepare('SELECT GET_LOCK(:nome, :espera)');
                $stmt->execute(['nome' => $nome, 'espera' => self::TEMPO_ESPERA_BLOQUEIO_SEGUNDOS]);
                if ((int) $stmt->fetchColumn() !== 1) {
                    throw new RuntimeException('Não foi possível adquirir o bloqueio de autenticação.');
                }
                $adquiridos[] = $nome;
            }

            return $operacao();
        } finally {
            foreach (array_reverse($adquiridos) as $nome) {
                $stmt = $conexao->prepare('SELECT RELEASE_LOCK(:nome)');
                $stmt->execute(['nome' => $nome]);
                if ((int) $stmt->fetchColumn() !== 1) {
                    throw new RuntimeException('Não foi possível liberar o bloqueio de autenticação.');
                }
            }
        }
    }

    /**
     * Monta o UsuarioLogado a partir do estado atual do banco; null se o usuário ou o escritório
     * foram desativados.
     */
    public function carregarUsuario(int $escritorioId, int $usuarioId): ?UsuarioLogado
    {
        $registro = $this->usuarios->buscarAtivo($escritorioId, $usuarioId);
        if ($registro === null) {
            return null;
        }

        return new UsuarioLogado(
            (int) $registro['id'],
            (int) $registro['escritorio_id'],
            (string) $registro['perfil'],
            $registro['empresa_id'] === null ? null : (int) $registro['empresa_id'],
            (string) $registro['nome'],
            $this->usuarios->modulosAtivos($escritorioId)
        );
    }

    /**
     * Guarda na sessão só os identificadores (definidos pelo servidor). O ID da sessão é trocado
     * para impedir fixação de sessão; regenerar() também descarta o token CSRF antigo.
     */
    public function abrirSessao(Session $sessao, UsuarioLogado $usuario): void
    {
        $sessao->regenerar();
        $sessao->definir(self::CHAVE_USUARIO, $usuario->id);
        $sessao->definir(self::CHAVE_ESCRITORIO, $usuario->escritorioId);
    }

    /**
     * Relê o usuário no banco a cada requisição. Se ele (ou o escritório) foi desativado,
     * a sessão é esvaziada e o acesso termina na hora.
     */
    public function usuarioDaSessao(Session $sessao): ?UsuarioLogado
    {
        $usuarioId = $sessao->obter(self::CHAVE_USUARIO);
        $escritorioId = $sessao->obter(self::CHAVE_ESCRITORIO);
        if (!is_int($usuarioId) || !is_int($escritorioId)) {
            return null;
        }

        $usuario = $this->carregarUsuario($escritorioId, $usuarioId);
        if ($usuario === null) {
            $sessao->remover(self::CHAVE_USUARIO);
            $sessao->remover(self::CHAVE_ESCRITORIO);
        }

        return $usuario;
    }

    public function destinoInicial(UsuarioLogado $usuario): string
    {
        return $usuario->ehCliente() ? '/portal' : '/painel';
    }

    private function normalizarEmail(string $email): string
    {
        return mb_substr(mb_strtolower(trim($email)), 0, 150);
    }

    private function maxTentativas(): int
    {
        return max(1, (int) Config::obter('login.max_tentativas', 5));
    }

    private function minutosDeBloqueio(): int
    {
        return max(1, (int) Config::obter('login.bloqueio_min', 15));
    }
}
