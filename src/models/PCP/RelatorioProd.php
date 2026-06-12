<?php

namespace src\models\PCP;

use core\Database;

class RelatorioProd
{
    public static function buscar(int $emprId, int $numLote): array
    {
        $result = Database::switchParams('focco', [
            'empr_id'  => $emprId,
            'num_lote' => $numLote,
        ], 'pcp.relatorio.producao', true);
        return $result['retorno'] ?? [];
    }

    public static function listarEmpresas(): array
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }
}
