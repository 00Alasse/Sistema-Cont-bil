<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Core\Database;
use App\Core\UsuarioLogado;
use App\Exceptions\AcessoNegadoException;
use App\Exceptions\ConflitoException;
use App\Exceptions\NaoEncontradoException;
use App\Models\Fiscal\Obrigacao;
use App\Models\Nucleo\LogAtividade;
use App\Services\Nucleo\AuditoriaService;
use App\Support\Relogio;
use App\Support\RelogioSistema;
use InvalidArgumentException;

/**
 * Fluxo de status das obrigações (API §4.2). A matriz de transições é uma constante e as
 * regras sobre ela são métodos estáticos puros, testáveis sem banco. A mudança e a auditoria
 * acontecem na mesma transação: o log nunca diverge do estado (ARCHITECTURE §11.7).
 */
final class ObrigacaoStatusService
{
    public const MENSAGEM_PROIBIDA = 'Esta mudança de status não é permitida.';

    public const MENSAGEM_JUSTIFICATIVA = 'Justificativa é obrigatória para esta mudança de status.';

    public const MENSAGEM_PROTOCOLO_FORA_DA_ENTREGA = 'Protocolo só é informado na entrega.';

    public const MAX_JUSTIFICATIVA = 500;

    public const MAX_PROTOCOLO = 100;

    private const LIVRE = ['admin' => false, 'justificativa' => false];

    private const COM_JUSTIFICATIVA = ['admin' => false, 'justificativa' => true];

    private const REABERTURA = ['admin' => true, 'justificativa' => true];

    /**
     * De → para. Combinação ausente = proibida (inclusive de um status para ele mesmo).
     */
    public const TRANSICOES = [
        'PENDENTE' => [
            'EM_ANDAMENTO' => self::LIVRE,
            'AGUARDANDO_CLIENTE' => self::LIVRE,
            'ENTREGUE' => self::LIVRE,
            'DISPENSADA' => self::COM_JUSTIFICATIVA,
        ],
        'EM_ANDAMENTO' => [
            'PENDENTE' => self::LIVRE,
            'AGUARDANDO_CLIENTE' => self::LIVRE,
            'ENTREGUE' => self::LIVRE,
            'DISPENSADA' => self::COM_JUSTIFICATIVA,
        ],
        'AGUARDANDO_CLIENTE' => [
            'EM_ANDAMENTO' => self::LIVRE,
            'ENTREGUE' => self::LIVRE,
            'DISPENSADA' => self::COM_JUSTIFICATIVA,
        ],
        'ENTREGUE' => [
            'EM_ANDAMENTO' => self::REABERTURA,
        ],
        'DISPENSADA' => [
            'PENDENTE' => self::REABERTURA,
        ],
    ];

    public function __construct(
        private readonly Obrigacao $obrigacoes,
        private readonly AuditoriaService $auditoria,
        private readonly Relogio $relogio
    ) {
    }

    public static function padrao(): self
    {
        return new self(new Obrigacao(), new AuditoriaService(new LogAtividade()), new RelogioSistema());
    }

    /**
     * @return array{admin: bool, justificativa: bool}|null null quando a transição é proibida
     */
    public static function regra(string $de, string $para): ?array
    {
        return self::TRANSICOES[$de][$para] ?? null;
    }

    /**
     * Destinos que o perfil pode escolher a partir do status atual (usado no formulário).
     *
     * @return list<string>
     */
    public static function destinosPermitidos(string $de, bool $ehAdmin): array
    {
        $destinos = [];
        foreach (self::TRANSICOES[$de] ?? [] as $para => $regra) {
            if ($ehAdmin || !$regra['admin']) {
                $destinos[] = $para;
            }
        }

        return $destinos;
    }

    /**
     * Fora da matriz → 409; reabertura por quem não é ADMIN → 403.
     */
    public static function verificarTransicao(string $de, string $para, bool $ehAdmin): void
    {
        $regra = self::regra($de, $para);
        if ($regra === null) {
            throw new ConflitoException(self::MENSAGEM_PROIBIDA);
        }
        if ($regra['admin'] && !$ehAdmin) {
            throw new AcessoNegadoException();
        }
    }

    /**
     * Erros de justificativa e protocolo (422) para uma transição já verificada.
     * Texto só com espaços conta como não informado.
     *
     * @return array<string, list<string>>
     */
    public static function errosDosCampos(string $de, string $para, ?string $justificativa, ?string $protocolo): array
    {
        $justificativa = self::texto($justificativa);
        $protocolo = self::texto($protocolo);
        $erros = [];

        if ($justificativa === null && (self::regra($de, $para)['justificativa'] ?? false)) {
            $erros['justificativa'] = [self::MENSAGEM_JUSTIFICATIVA];
        } elseif ($justificativa !== null && mb_strlen($justificativa) > self::MAX_JUSTIFICATIVA) {
            $erros['justificativa'] = ['Justificativa deve ter no máximo ' . self::MAX_JUSTIFICATIVA . ' caracteres.'];
        }

        // D8: protocolo pertence só à entrega; enviado em outra transição é recusado, não ignorado.
        if ($protocolo !== null && $para !== 'ENTREGUE') {
            $erros['protocolo'] = [self::MENSAGEM_PROTOCOLO_FORA_DA_ENTREGA];
        } elseif ($protocolo !== null && mb_strlen($protocolo) > self::MAX_PROTOCOLO) {
            $erros['protocolo'] = ['Protocolo deve ter no máximo ' . self::MAX_PROTOCOLO . ' caracteres.'];
        }

        return $erros;
    }

    /**
     * Validação completa antes de gravar, para o controller devolver 422 com os campos.
     * Ordem: versão desatualizada (409), transição (409/403) e, por fim, os campos (422).
     *
     * @param array<string, mixed> $obrigacao registro atual (de Obrigacao::buscar)
     * @param array{status: string, justificativa: string|null, protocolo: string|null} $mudanca
     * @return array<string, list<string>>
     */
    public function errosDaMudanca(UsuarioLogado $usuario, array $obrigacao, array $mudanca, int $versao): array
    {
        // A versão vem primeiro: se outra pessoa mudou o status, julgar a transição sobre o
        // status novo daria uma resposta enganosa (por exemplo, 403 de reabertura).
        if ((int) $obrigacao['versao'] !== $versao) {
            throw new ConflitoException();
        }

        $de = (string) $obrigacao['status'];
        self::verificarTransicao($de, $mudanca['status'], $usuario->ehAdmin());

        return self::errosDosCampos($de, $mudanca['status'], $mudanca['justificativa'], $mudanca['protocolo']);
    }

    /**
     * @param array{status: string, justificativa: string|null, protocolo: string|null} $mudanca
     */
    public function mudarStatus(UsuarioLogado $usuario, int $id, array $mudanca, int $versao): void
    {
        Database::transacao(function () use ($usuario, $id, $mudanca, $versao): void {
            $obrigacao = $this->obrigacoes->buscar($usuario->escritorioId, $id);
            if ($obrigacao === null) {
                throw new NaoEncontradoException();
            }
            if ($this->errosDaMudanca($usuario, $obrigacao, $mudanca, $versao) !== []) {
                // O controller valida antes; chegar aqui com erro é falha de programação.
                throw new InvalidArgumentException('Dados da mudança de status inválidos.');
            }

            $para = $mudanca['status'];
            $protocolo = self::texto($mudanca['protocolo']);
            $entrega = $para === 'ENTREGUE';
            $this->obrigacoes->mudarStatus($usuario->escritorioId, $id, [
                'status' => $para,
                // Entrar em ENTREGUE grava data e usuário; qualquer outro destino os limpa.
                'entregue_em' => $entrega ? $this->relogio->agora()->format('Y-m-d H:i:s') : null,
                'entregue_por' => $entrega ? $usuario->id : null,
                'protocolo' => $protocolo,
            ], $versao);

            // D9: o evento registra só o protocolo enviado nesta requisição (null se nenhum).
            $this->auditoria->registrar($usuario, 'obrigacao', $id, 'status:' . $para, [
                'de' => (string) $obrigacao['status'],
                'para' => $para,
                'justificativa' => self::texto($mudanca['justificativa']),
                'protocolo' => $protocolo,
            ]);
        });
    }

    private static function texto(?string $valor): ?string
    {
        $valor = $valor === null ? '' : trim($valor);

        return $valor === '' ? null : $valor;
    }
}
