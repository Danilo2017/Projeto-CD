<?php

namespace src\controllers\Processo;

use core\Controller;
use src\handlers\Processo\DespesaCentroCustoHandler;

class DespesaCentroCustoController extends Controller
{
    public function index(): void
    {
        $empresas = \src\models\Processo\DespesaCentroCusto::listarEmpresas();
        $this->render('processo/relatorio-despesa-centro-custo', compact('empresas'));
    }

    public function listar(): void
    {
        try {
            $dados = self::getBody() ?? [];
            self::response(DespesaCentroCustoHandler::listar($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }

    public function detalhe(): void
    {
        try {
            $dados = self::getBody() ?? [];
            self::response(DespesaCentroCustoHandler::detalhe($dados), 200);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
            self::response(['error' => $e->getMessage()], $code ?: 500);
        }
    }
}
