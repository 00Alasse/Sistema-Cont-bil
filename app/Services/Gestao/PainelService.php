<?php

declare(strict_types=1);

namespace App\Services\Gestao;

use App\Core\CartaoPainel;
use App\Core\Container;
use App\Core\Database;
use App\Core\UsuarioLogado;
use App\Models\Cadastro\Empresa;
use App\Models\Fiscal\Obrigacao;
use App\Support\Relogio;
use App\Support\RelogioSistema;
use InvalidArgumentException;
use PDO;

/**
 * Indicadores do painel do escritório (API §8). As contagens de obrigações reaproveitam as
 * condições da listagem (Obrigacao::contar), então cada número é igual ao total da listagem
 * aberta pelo link. São 5 consultas fixas, qualquer que seja o tamanho da carteira ou da equipe.
 * Documentos ficam de fora enquanto o módulo DO não existir (decisão P3).
 */
final class PainelService
{
    public const MEUS = 'meus';

    public const TODOS = 'todos';

    public const SEMAFOROS = ['verde', 'amarelo', 'vermelho', 'sem_historico'];

    private const LIMITE_PIORES = 5;

    private const ROTULOS = [
        'vence_hoje' => 'Vencem hoje',
        'vence_7d' => 'Vencem em até 7 dias',
        'atrasadas' => 'Atrasadas',
        'entregues_mes' => 'Entregues no mês',
        'entregues_no_prazo' => 'Entregues no prazo no mês',
    ];

    private readonly Relogio $relogio;

    private readonly PDO $db;

    private readonly Obrigacao $obrigacoes;

    private readonly Empresa $empresas;

    private readonly IndiceRegularidadeService $indice;

    public function __construct(
        ?Relogio $relogio = null,
        ?PDO $db = null,
        ?Obrigacao $obrigacoes = null,
        ?Empresa $empresas = null,
        ?IndiceRegularidadeService $indice = null
    ) {
        $this->relogio = $relogio ?? new RelogioSistema();
        $this->db = $db ?? Database::conexao();
        $this->obrigacoes = $obrigacoes ?? new Obrigacao($this->relogio, $this->db);
        $this->empresas = $empresas ?? new Empresa($this->db);
        $this->indice = $indice ?? new IndiceRegularidadeService($this->relogio, $this->db);
    }

    /**
     * Padrão por perfil (P4): ADMIN vê "todos"; COLABORADOR, "meus". Valor inválido volta ao padrão.
     */
    public static function escopoEfetivo(UsuarioLogado $usuario, mixed $pedido): string
    {
        if ($pedido === self::MEUS || $pedido === self::TODOS) {
            return $pedido;
        }

        return $usuario->ehAdmin() ? self::TODOS : self::MEUS;
    }

    /**
     * @return array{
     *     escopo: string,
     *     obrigacoes: array<string, array{rotulo: string, valor: int, caminho: string, query: array<string, string>}>,
     *     pontualidade: int|null,
     *     semaforo: array<string, int>,
     *     piores: list<array{empresa_id: int, nome: string, score: int|null, semaforo: string, motivos: list<string>, recomendacao: string, caminho: string}>,
     *     carga: list<array{responsavel_id: int|null, nome: string, inativo: bool, atrasadas: array{valor: int, query: array<string, string>|null}, vence_7d: array{valor: int, query: array<string, string>|null}}>
     * }
     */
    public function montar(UsuarioLogado $usuario, string $escopo): array
    {
        if ($escopo !== self::MEUS && $escopo !== self::TODOS) {
            throw new InvalidArgumentException('Escopo do painel inválido.');
        }

        $obrigacoes = $this->indicadoresDeObrigacoes($usuario, $escopo);
        [$semaforo, $piores] = $this->regularidade($usuario, $escopo);

        return [
            'escopo' => $escopo,
            'obrigacoes' => $obrigacoes,
            'pontualidade' => self::percentual($obrigacoes['entregues_no_prazo']['valor'], $obrigacoes['entregues_mes']['valor']),
            'semaforo' => $semaforo,
            'piores' => $piores,
            'carga' => $this->carga($usuario),
        ];
    }

    /**
     * Cartões dos módulos (App\Core\CartaoPainel), só dos módulos ativos para o escritório
     * (mesma regra do menu), ordenados por ordem().
     *
     * @param array<string, array<string, mixed>> $modulos configuração devolvida por Modulos::carregar()
     * @return list<array{titulo: string, template: string, dados: array<string, mixed>}>
     */
    public function cartoes(UsuarioLogado $usuario, array $modulos): array
    {
        $container = new Container();
        $cartoes = [];
        foreach ($modulos as $configuracao) {
            $contratavel = $configuracao['contratavel'] ?? null;
            if (is_string($contratavel) && !$usuario->temModulo($contratavel)) {
                continue;
            }
            $classes = $configuracao['cartoes_painel'] ?? [];
            foreach (is_array($classes) ? $classes : [] as $classe) {
                $cartao = is_string($classe) ? $container->get($classe) : null;
                if ($cartao instanceof CartaoPainel) {
                    $cartoes[] = $cartao;
                }
            }
        }
        usort($cartoes, static fn (CartaoPainel $a, CartaoPainel $b): int => $a->ordem() <=> $b->ordem());

        return array_map(static fn (CartaoPainel $cartao): array => [
            'titulo' => $cartao->titulo(),
            'template' => $cartao->template(),
            'dados' => $cartao->dados($usuario),
        ], $cartoes);
    }

    /**
     * Os mesmos filtros geram a contagem e o link. No escopo "meus", o link usa responsavel=meus
     * (a listagem troca pelo ID de quem está logado) e a contagem usa o ID diretamente.
     *
     * @return array<string, array{rotulo: string, valor: int, caminho: string, query: array<string, string>}>
     */
    private function indicadoresDeObrigacoes(UsuarioLogado $usuario, string $escopo): array
    {
        $hoje = $this->relogio->hoje();
        $mes = [
            'entregue_de' => $hoje->modify('first day of this month')->format('Y-m-d'),
            'entregue_ate' => $hoje->modify('last day of this month')->format('Y-m-d'),
        ];
        $links = [
            'vence_hoje' => ['situacao' => 'vence_hoje'],
            'vence_7d' => ['situacao' => 'vence_7d'],
            'atrasadas' => ['situacao' => 'atrasada'],
            'entregues_mes' => $mes,
            'entregues_no_prazo' => $mes + ['situacao' => 'entregue_no_prazo'],
        ];

        $conjuntos = $links;
        if ($escopo === self::MEUS) {
            foreach (array_keys($links) as $chave) {
                $links[$chave]['responsavel'] = 'meus';
                $conjuntos[$chave]['responsavel'] = (string) $usuario->id;
            }
        }
        $contagens = $this->obrigacoes->contar($usuario->escritorioId, $conjuntos);

        $indicadores = [];
        foreach ($links as $chave => $query) {
            $indicadores[$chave] = [
                'rotulo' => self::ROTULOS[$chave],
                'valor' => $contagens[$chave],
                'caminho' => '/obrigacoes',
                'query' => $query,
            ];
        }

        return $indicadores;
    }

    /**
     * Semáforo e piores índices da carteira ativa (ou das empresas do usuário, no "meus"),
     * com uma consulta de empresas e uma chamada em lote ao Índice de Regularidade.
     *
     * @return array{array<string, int>, list<array{empresa_id: int, nome: string, score: int|null, semaforo: string, motivos: list<string>, recomendacao: string, caminho: string}>}
     */
    private function regularidade(UsuarioLogado $usuario, string $escopo): array
    {
        $nomes = $this->empresasAtivas($usuario, $escopo);
        $carteira = $this->indice->calcularCarteira($usuario->escritorioId, array_keys($nomes));

        $semaforo = array_fill_keys(self::SEMAFOROS, 0);
        $candidatas = [];
        foreach ($carteira as $empresaId => $resultado) {
            $semaforo[$resultado['semaforo']]++;
            if ($resultado['semaforo'] !== 'sem_historico') {
                $candidatas[] = [
                    'empresa_id' => $empresaId,
                    'nome' => $nomes[$empresaId],
                    'score' => $resultado['score'],
                    'semaforo' => $resultado['semaforo'],
                    'motivos' => $resultado['motivos'],
                    'recomendacao' => $this->gerarRecomendacao($resultado),
                    'caminho' => '/empresas/' . $empresaId,
                ];
            }
        }

        // P7: vermelhas sem score (atraso crítico) primeiro; depois score crescente; nome e ID desempatam.
        usort($candidatas, static fn (array $a, array $b): int =>
            [$a['score'] !== null, $a['score'] ?? 0] <=> [$b['score'] !== null, $b['score'] ?? 0]
            ?: strcmp($a['nome'], $b['nome'])
            ?: $a['empresa_id'] <=> $b['empresa_id']);

        return [$semaforo, array_slice($candidatas, 0, self::LIMITE_PIORES)];
    }

    /**
     * Recomendação de ação imediata ou preventiva para empresas com pior índice.
     *
     * @param array{score: int|null, semaforo: string, motivos: list<string>, contadores: array<string, int|null>} $resultado
     */
    private function gerarRecomendacao(array $resultado): string
    {
        $contadores = $resultado['contadores'];
        $criticas = (int) ($contadores['atrasadas_criticas'] ?? 0);
        $atrasadas = (int) ($contadores['atrasadas'] ?? 0);
        $docs = (int) ($contadores['docs_vencidos'] ?? 0);

        if ($criticas > 0) {
            return 'Ação imediata: regularizar ' . $criticas . ' obrigação(ões) com atraso crítico (> 30 dias).';
        }

        if ($atrasadas > 0) {
            return 'Ação imediata: regularizar ' . $atrasadas . ' obrigação(ões) atrasada(s).';
        }

        if ($docs > 0) {
            return 'Ação preventiva: solicitar ' . $docs . ' documento(s) pendente(s).';
        }

        if ($resultado['semaforo'] === 'vermelho' || $resultado['semaforo'] === 'amarelo') {
            return 'Ação preventiva: acompanhar entregas da próxima competência.';
        }

        return 'Manter rotina regular de entregas.';
    }

    /**
     * @return array<int, string> razão social indexada pelo ID da empresa
     */
    private function empresasAtivas(UsuarioLogado $usuario, string $escopo): array
    {
        $sql = "SELECT id, razao_social FROM empresas WHERE escritorio_id = :escritorio_id AND status = 'ATIVA'";
        $parametros = ['escritorio_id' => $usuario->escritorioId];
        if ($escopo === self::MEUS) {
            $sql .= ' AND responsavel_id = :responsavel_id';
            $parametros['responsavel_id'] = $usuario->id;
        }

        $stmt = $this->db->prepare($sql . ' ORDER BY id');
        $stmt->execute($parametros);
        $nomes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $nomes[(int) $linha['id']] = (string) $linha['razao_social'];
        }

        return $nomes;
    }

    /**
     * Carga por responsável (P5, P6, C3): sempre a equipe inteira. Ativos aparecem mesmo zerados;
     * inativos e "Sem responsável" só com alguma obrigação nas categorias exibidas.
     *
     * @return list<array{responsavel_id: int|null, nome: string, inativo: bool, atrasadas: array{valor: int, query: array<string, string>|null}, vence_7d: array{valor: int, query: array<string, string>|null}}>
     */
    private function carga(UsuarioLogado $usuario): array
    {
        $situacoes = ['atrasadas' => 'atrasada', 'vence_7d' => 'vence_7d'];
        $conjuntos = array_map(static fn (string $situacao): array => ['situacao' => $situacao], $situacoes);
        $contagens = [];
        foreach ($this->obrigacoes->contarPorResponsavel($usuario->escritorioId, $conjuntos) as $linha) {
            $contagens[$linha['responsavel_id'] ?? 0] = $linha;
        }

        $carga = [];
        foreach ($this->empresas->listarResponsaveis($usuario->escritorioId) as $ativo) {
            $carga[] = self::linhaDaCarga($ativo['id'], $ativo['nome'], false, $contagens[$ativo['id']]['contagens'] ?? [], $situacoes);
            unset($contagens[$ativo['id']]);
        }
        // Sobram os inativos e a linha sem responsável (chave 0), que vem por último.
        $semResponsavel = $contagens[0] ?? null;
        unset($contagens[0]);
        foreach ($contagens as $id => $linha) {
            if (array_sum($linha['contagens']) > 0) {
                $inativo = $linha['ativo'] !== true;
                $nome = (string) $linha['nome'] . ($inativo ? ' (inativo)' : '');
                $carga[] = self::linhaDaCarga($id, $nome, $inativo, $linha['contagens'], $situacoes);
            }
        }
        if ($semResponsavel !== null && array_sum($semResponsavel['contagens']) > 0) {
            $carga[] = self::linhaDaCarga(null, 'Sem responsável', false, $semResponsavel['contagens'], $situacoes);
        }

        return $carga;
    }

    /**
     * Sem responsável não tem link: a listagem não filtra obrigações sem responsável.
     *
     * @param array<string, int> $contagens
     * @param array<string, string> $situacoes
     * @return array{responsavel_id: int|null, nome: string, inativo: bool, atrasadas: array{valor: int, query: array<string, string>|null}, vence_7d: array{valor: int, query: array<string, string>|null}}
     */
    private static function linhaDaCarga(?int $id, string $nome, bool $inativo, array $contagens, array $situacoes): array
    {
        $linha = ['responsavel_id' => $id, 'nome' => $nome, 'inativo' => $inativo];
        foreach ($situacoes as $coluna => $situacao) {
            $linha[$coluna] = [
                'valor' => $contagens[$coluna] ?? 0,
                'query' => $id === null ? null : ['responsavel' => (string) $id, 'situacao' => $situacao],
            ];
        }

        return $linha;
    }

    /**
     * round(parte / total × 100) só com inteiros (meio arredonda para cima); null sem total.
     */
    private static function percentual(int $parte, int $total): ?int
    {
        return $total === 0 ? null : intdiv(200 * $parte + $total, 2 * $total);
    }
}
