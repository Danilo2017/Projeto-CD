<?php

namespace src\controllers\Faturamento;

use \core\Controller as ctrl;
use src\handlers\Faturamento\FaturamentoProgramacaoHandler;
use src\utils\DashboardCache;
use src\utils\GetSqlFocco;

class FaturamentoProgramacaoController extends ctrl
{
    public function index()
    {
        $this->render('faturamento/programacao', [
            'titulo' => 'Programação de Pedidos',
            'pagina' => 'Faturamento',
        ]);
    }

    public function listar()
    {
        try {
            $resultado = FaturamentoProgramacaoHandler::listar();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function resumoDashboard()
    {
        try {
            $resultado = FaturamentoProgramacaoHandler::resumoDashboard();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function ocupacao()
    {
        try {
            $resultado = FaturamentoProgramacaoHandler::ocupacao();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function flushCache()
    {
        GetSqlFocco::invalidar('faturamento.programacao.listar');
        GetSqlFocco::invalidar('faturamento.programacao.tanques');
        GetSqlFocco::invalidar('faturamento.programacao.dias-uteis');
        DashboardCache::forget('programacao.listar');
        DashboardCache::forget('programacao.ocupacao');
        $mes = (new \DateTime())->format('Y-m');
        DashboardCache::forget('programacao.resumo_dashboard_' . $mes);
        self::response(['success' => true, 'message' => 'Cache limpo com sucesso.'], 200);
    }
}
