<?php

namespace src\models\Faturamento;

use core\Database;

/**
 * Model de Pedidos
 * Busca dados de pedidos em carteira e planejados
 */
class Pedidos
{
    /**
     * Buscar pedidos em carteira (liberados, em carga, sem carga)
     * @return array
     */
    public static function getPedidosCarteira(): array
    {
        $result = Database::switchParams('focco', [], 'faturamento.pedidos.carteira', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Buscar pedidos planejados
     * @return array
     */
    public static function getPedidosPlanejado(): array
    {
        $result = Database::switchParams('focco', [], 'faturamento.pedidos.planejado', true);
        return $result['retorno'] ?? [];
    }
}
