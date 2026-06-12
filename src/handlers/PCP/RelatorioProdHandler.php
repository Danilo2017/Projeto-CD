<?php

namespace src\handlers\PCP;

use src\models\PCP\RelatorioProd;

class RelatorioProdHandler
{
    public static function buscar(int $emprId, int $numLote): array
    {
        $rows = RelatorioProd::buscar($emprId, $numLote);

        // Split by ORD field:
        // CON_BAS_AU → conjugado | MESA → mesa | MOLA+ALT=0 → sem_pillow | MOLA+ALT>0 → com_pillow
        $secoes = [
            'conjugado'  => [],
            'mesa'       => [],
            'sem_pillow' => [],
            'com_pillow' => [],
        ];

        foreach ($rows as $row) {
            $ord = strtoupper(trim($row['ORD'] ?? ''));
            $alt = (float) ($row['ALT'] ?? 0);

            if ($ord === 'CON_BAS_AU') {
                $secoes['conjugado'][] = $row;
            } elseif ($ord === 'MESA') {
                $secoes['mesa'][] = $row;
            } elseif ($alt > 0) {
                $secoes['com_pillow'][] = $row;
            } else {
                $secoes['sem_pillow'][] = $row;
            }
        }

        return ['success' => true, 'secoes' => $secoes];
    }

    public static function listarEmpresas(): array
    {
        return RelatorioProd::listarEmpresas();
    }
}
