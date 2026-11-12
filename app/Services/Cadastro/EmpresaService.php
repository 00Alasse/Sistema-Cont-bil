<?php

declare(strict_types=1);

namespace App\Services\Cadastro;

use App\Core\Database;
use App\Core\UsuarioLogado;
use App\Exceptions\ConflitoException;
use App\Exceptions\NaoEncontradoException;
use App\Models\Cadastro\Empresa;
use App\Models\Nucleo\LogAtividade;
use App\Services\Nucleo\AuditoriaService;
use PDOException;

final class EmpresaService
{
    public function __construct(
        private readonly Empresa $empresas,
        private readonly AuditoriaService $auditoria
    ) {
    }

    public static function padrao(): self
    {
        return new self(new Empresa(), new AuditoriaService(new LogAtividade()));
    }

    public function cnpjJaCadastrado(UsuarioLogado $usuario, string $cnpj, ?int $ignorarId = null): bool
    {
        return $this->empresas->cnpjJaCadastrado($usuario->escritorioId, $cnpj, $ignorarId);
    }

    public function responsavelValido(UsuarioLogado $usuario, int $responsavelId): bool
    {
        return $this->empresas->responsavelValido($usuario->escritorioId, $responsavelId);
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function criar(UsuarioLogado $usuario, array $dados): int
    {
        return Database::transacao(function () use ($usuario, $dados): int {
            $id = $this->empresas->criar($usuario->escritorioId, $dados);
            $this->auditoria->registrar($usuario, 'empresa', $id, 'criar');

            return $id;
        });
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function atualizar(UsuarioLogado $usuario, int $id, array $dados, int $versao): void
    {
        Database::transacao(function () use ($usuario, $id, $dados, $versao): void {
            if ($this->empresas->buscar($usuario->escritorioId, $id) === null) {
                throw new NaoEncontradoException();
            }

            $this->empresas->atualizar($usuario->escritorioId, $id, $dados, $versao);
            $this->auditoria->registrar($usuario, 'empresa', $id, 'editar');
        });
    }

    public function excluir(UsuarioLogado $usuario, int $id): void
    {
        Database::transacao(function () use ($usuario, $id): void {
            if ($this->empresas->buscar($usuario->escritorioId, $id) === null) {
                throw new NaoEncontradoException();
            }

            try {
                if ($this->empresas->excluir($usuario->escritorioId, $id) === 0) {
                    throw new NaoEncontradoException();
                }
            } catch (PDOException $erro) {
                if ($this->ehRestricaoDeDependencia($erro)) {
                    throw new ConflitoException(
                        'A empresa possui registros vinculados; inative a empresa.'
                    );
                }

                throw $erro;
            }

            $this->auditoria->registrar($usuario, 'empresa', $id, 'excluir');
        });
    }

    private function ehRestricaoDeDependencia(PDOException $erro): bool
    {
        $codigoSql = is_array($erro->errorInfo) ? ($erro->errorInfo[1] ?? null) : null;

        return is_numeric($codigoSql) && (int) $codigoSql === 1451;
    }
}
