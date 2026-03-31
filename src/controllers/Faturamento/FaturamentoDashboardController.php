<?php

namespace src\controllers\Faturamento;

use \core\Controller as ctrl;
use src\handlers\Faturamento\FaturamentoDashboardHandler;

/**
 * Controller do Dashboard de Faturamento Indústrias
 * Gerencia visualização de faturamento, metas e pedidos
 */
class FaturamentoDashboardController extends ctrl
{
    /**
     * Página principal do Dashboard
     */
    public function index()
    {
        $dados = [
            'titulo' => 'Dashboard - Faturamento Indústrias',
            'pagina' => 'Faturamento'
        ];

        $this->render('faturamento/dashboard', $dados);
    }

    /**
     * API - Resumo mensal de faturamento
     * Retorna faturamento bruto, devoluções, líquido e metas por filial
     */
    public function getResumoMensal()
    {
        try {
            $resultado = FaturamentoDashboardHandler::getResumoMensal();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Painel de vendas por empresa
     * Retorna meta, faturamento, planejado, estoque por empresa
     */
    public function getPainelVendas()
    {
        try {
            $resultado = FaturamentoDashboardHandler::getPainelVendas();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Status de pedidos em carteira
     * Retorna pedidos liberados, em carga e sem carga
     */
    public function getPedidos()
    {
        try {
            $resultado = FaturamentoDashboardHandler::getPedidos();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Pedidos planejados
     * Retorna valor total de pedidos planejados
     */
    public function getPedidosPlanejado()
    {
        try {
            $resultado = FaturamentoDashboardHandler::getPedidosPlanejado();
            self::response($resultado, 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
