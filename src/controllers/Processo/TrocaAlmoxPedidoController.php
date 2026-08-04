<?php

namespace src\controllers\Processo;

use core\Controller;
use src\handlers\Processo\TrocaAlmoxPedidoHandler;

class TrocaAlmoxPedidoController extends Controller
{
    private function emprIdSessao(): int
    {
        $id = (int) ($_SESSION['empresa']['id'] ?? 0);
        if ($id <= 0) throw new \Exception('Nenhuma empresa selecionada na sessão.', 400);
        return $id;
    }

    public function index(): void
    {
        $empresas = \src\models\Processo\TrocaAlmoxPedido::listarEmpresas();
        $this->render('processo/troca-almox-pedido', compact('empresas'));
    }

    public function buscarItensPedido(): void
    {
        try {
            $dados  = self::getBody() ?? [];
            $dados['empr_id'] = $dados['empr_id'] ?? $this->emprIdSessao();
            $result = TrocaAlmoxPedidoHandler::buscarItensPedido($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function buscarAlmoxarifado(): void
    {
        try {
            $dados  = self::getBody() ?? [];
            $dados['empr_id'] = $dados['empr_id'] ?? $this->emprIdSessao();
            $result = TrocaAlmoxPedidoHandler::buscarAlmoxarifado($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function executar(): void
    {
        try {
            $dados  = self::getBody() ?? [];
            $dados['empr_id'] = $dados['empr_id'] ?? $this->emprIdSessao();
            $result = TrocaAlmoxPedidoHandler::executar($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
