<?php

namespace src\controllers\Faturamento;

use core\Controller;
use src\handlers\Faturamento\TransferenciaPedidoHandler;

class TransferenciaPedidoController extends Controller
{
    public function index(): void
    {
        $empresas = TransferenciaPedidoHandler::listarEmpresas();
        $this->render('faturamento/transferencia-pedido', compact('empresas'));
    }

    public function buscarPedidos(): void
    {
        try {
            $dados = self::getBody() ?? [];
            $result = TransferenciaPedidoHandler::buscarPedidos($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function executar(): void
    {
        try {
            $dados = self::getBody() ?? [];
            $result = TransferenciaPedidoHandler::executar($dados);
            self::response($result, 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
