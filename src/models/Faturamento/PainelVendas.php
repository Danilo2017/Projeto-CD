<?php

namespace src\models\Faturamento;

use core\Database;

/**
 * Model de Painel de Vendas
 * Busca dados de vendas por empresa com metas e estoque
 */
class PainelVendas
{
    /**
     * Buscar painel de vendas por empresa
     * @return array
     */
    public static function getPainelVendas(): array
    {
        $result = Database::switchParams('focco', [], 'faturamento.painel.vendas', true);
        return $result['retorno'] ?? [];
    }
}
