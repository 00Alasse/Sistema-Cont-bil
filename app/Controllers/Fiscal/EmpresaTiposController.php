<?php

declare(strict_types=1);

namespace App\Controllers\Fiscal;

use App\Core\Controller;
use App\Core\Menu;
use App\Core\Modulos;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Exceptions\NaoEncontradoException;
use App\Models\Cadastro\Empresa;
use App\Models\Fiscal\EmpresaTipoObrigacao;
use App\Services\Fiscal\TipoObrigacaoService;

/**
 * Aba "Obrigações" da empresa: quais tipos se aplicam a ela e quem é o responsável padrão.
 */
final class EmpresaTiposController extends Controller
{
    private readonly Empresa $empresas;

    private readonly EmpresaTipoObrigacao $vinculos;

    private readonly TipoObrigacaoService $servico;

    public function __construct(View $viewEngine)
    {
        parent::__construct($viewEngine);
        $this->empresas = new Empresa();
        $this->vinculos = new EmpresaTipoObrigacao();
        $this->servico = TipoObrigacaoService::padrao();
    }

    public function mostrar(Request $request, int $id): Response
    {
        $empresa = $this->buscarEmpresa($request, $id);
        $linhas = $this->vinculos->listarParaEmpresa($request->usuario()->escritorioId, $id);

        return $this->renderizar($request, $empresa, $linhas, [], 200);
    }

    public function salvar(Request $request, int $id): Response
    {
        $usuario = $request->usuario();
        $empresa = $this->buscarEmpresa($request, $id);
        [$vinculos, $erros] = $this->vinculosDaRequisicao($request);
        if ($erros === []) {
            $erros = $this->servico->errosDosVinculos($usuario, $id, $vinculos);
        }
        if ($erros !== []) {
            $linhas = $this->comEnvio($this->vinculos->listarParaEmpresa($usuario->escritorioId, $id), $vinculos);

            return $this->renderizar($request, $empresa, $linhas, $erros, 422);
        }

        $this->servico->substituirVinculos($usuario, $id, $vinculos);

        return $this->redirecionar(
            '/empresas/' . $id . '/tipos-obrigacao',
            'Tipos de obrigação da empresa atualizados.'
        );
    }

    /**
     * Lê `tipos[]` e `responsavel_padrao[<tipo_id>]`. O responsável só conta para tipos marcados;
     * os selects dos tipos desmarcados chegam junto e são ignorados.
     *
     * @return array{array<int, int|null>, array<string, list<string>>}
     */
    private function vinculosDaRequisicao(Request $request): array
    {
        $tipos = $request->input('tipos', []);
        $responsaveis = $request->input('responsavel_padrao', []);
        $tipos = is_array($tipos) ? $tipos : [$tipos];
        $responsaveis = is_array($responsaveis) ? $responsaveis : [];

        $vinculos = [];
        $erros = [];
        foreach ($tipos as $tipo) {
            if (!$this->ehIdPositivo($tipo)) {
                $erros['tipos'] = ['Selecione apenas tipos ativos deste escritório.'];
                continue;
            }
            $tipoId = (int) $tipo;
            $responsavel = $responsaveis[$tipoId] ?? '';
            if ($responsavel !== '' && !$this->ehIdPositivo($responsavel)) {
                $erros['responsavel_padrao'] = ['Selecione um responsável ativo deste escritório.'];
                continue;
            }
            $vinculos[$tipoId] = $responsavel === '' ? null : (int) $responsavel;
        }

        return [$vinculos, $erros];
    }

    private function ehIdPositivo(mixed $valor): bool
    {
        return is_string($valor) && preg_match('/\A[1-9][0-9]{0,9}\z/', $valor) === 1;
    }

    /**
     * Na resposta 422, a tela mostra o que foi enviado, não o que está gravado.
     *
     * @param list<array<string, mixed>> $linhas
     * @param array<int, int|null> $vinculos
     * @return list<array<string, mixed>>
     */
    private function comEnvio(array $linhas, array $vinculos): array
    {
        return array_map(static function (array $linha) use ($vinculos): array {
            $tipoId = (int) $linha['id'];
            $linha['vinculado'] = array_key_exists($tipoId, $vinculos) ? 1 : 0;
            $linha['responsavel_padrao_id'] = $vinculos[$tipoId] ?? null;

            return $linha;
        }, $linhas);
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarEmpresa(Request $request, int $id): array
    {
        $empresa = $this->empresas->buscar($request->usuario()->escritorioId, $id);
        if ($empresa === null) {
            throw new NaoEncontradoException();
        }

        return $empresa;
    }

    /**
     * @param array<string, mixed> $empresa
     * @param list<array<string, mixed>> $linhas
     * @param array<string, list<string>> $erros
     */
    private function renderizar(Request $request, array $empresa, array $linhas, array $erros, int $status): Response
    {
        $usuario = $request->usuario();
        $modulos = (new Modulos(new Router()))->carregar();

        return $this->view('fiscal/empresa-tipos/form', [
            'titulo' => 'Obrigações da empresa',
            'usuario' => $usuario,
            'itensMenu' => (new Menu($modulos))->itens($usuario),
            'empresa' => $empresa,
            'empresaId' => (int) $empresa['id'],
            'abas' => $this->abasDaEmpresa($modulos),
            'linhas' => $linhas,
            'responsaveis' => $this->empresas->listarResponsaveis($usuario->escritorioId),
            'erros' => $erros,
        ], $status);
    }

    /**
     * Mesma montagem do EmpresaController::mostrar (duplicação pequena e aprovada na FI02).
     *
     * @param array<string, array<string, mixed>> $modulos
     * @return list<array<string, mixed>>
     */
    private function abasDaEmpresa(array $modulos): array
    {
        $abas = [];
        foreach ($modulos as $modulo) {
            foreach ($modulo['abas_empresa'] as $aba) {
                $abas[] = $aba;
            }
        }
        usort($abas, static fn (array $a, array $b): int => $a['ordem'] <=> $b['ordem']);

        return $abas;
    }
}
