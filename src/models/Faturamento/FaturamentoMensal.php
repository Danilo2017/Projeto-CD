<?php

namespace src\models\Faturamento;

use core\Database;

/**
 * Model de Faturamento Mensal
 * Busca dados de faturamento, devoluções e metas
 */
class FaturamentoMensal
{
    /**
     * Buscar resumo mensal de faturamento por filial
     * @return array
     */
    public static function getResumoMensal(): array
    {
        $result = Database::switchParams('focco', [], 'faturamento.resumo.mensal', true);
        return $result['retorno'] ?? [];
    }
}
