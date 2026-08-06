<?php

namespace src\models\Qualidade;

use core\Database;

class RastreabilidadeMolas
{
    public static function buscar(int $emprId, int $numLote): array
    {
        $result = Database::switchParams('focco', [
            'empr_id'  => $emprId,
            'num_lote' => $numLote,
        ], 'qualidade.rastreabilidade.moloEnsacado', true);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']);
        }

        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function buscarDataLote(int $emprId, int $numLote): string
    {
        $result = Database::switchParams('focco', [
            'empr_id'  => $emprId,
            'num_lote' => $numLote,
        ], 'pcp.relatorioProd.dataLote', true);

        if (!empty($result['error'])) return '';
        $rows = is_array($result['retorno']) ? $result['retorno'] : [];
        return $rows[0]['DT'] ?? '';
    }
}
