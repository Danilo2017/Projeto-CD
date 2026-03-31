<?php

namespace src\handlers\Faturamento;

use src\models\Faturamento\FaturamentoMensal;
use src\models\Faturamento\PainelVendas;
use src\models\Faturamento\Pedidos;

/**
 * Handler do Dashboard de Faturamento Indústrias
 * Orquestra chamadas aos models e formata dados
 */
class FaturamentoDashboardHandler
{
    /**
     * Buscar resumo mensal de faturamento
     */
    public static function getResumoMensal(): array
    {
        $dados = FaturamentoMensal::getResumoMensal();
        
        return [
            'success' => true,
            'data' => $dados,
            'total' => count($dados),
            'ultima_atualizacao' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Buscar painel de vendas por empresa
     */
    public static function getPainelVendas(): array
    {
        $dados = PainelVendas::getPainelVendas();
        
        return [
            'sucesso' => true,
            'total_registros' => count($dados),
            'ultima_atualizacao' => date('Y-m-d H:i:s'),
            'dados' => $dados
        ];
    }

    /**
     * Buscar status de pedidos em carteira
     */
    public static function getPedidos(): array
    {
        $dados = Pedidos::getPedidosCarteira();
        
        return [
            'success' => true,
            'data' => $dados,
            'ultima_atualizacao' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Buscar pedidos planejados
     */
    public static function getPedidosPlanejado(): array
    {
        $dados = Pedidos::getPedidosPlanejado();
        
        return [
            'success' => true,
            'data' => $dados,
            'ultima_atualizacao' => date('Y-m-d H:i:s')
        ];
    }
}
