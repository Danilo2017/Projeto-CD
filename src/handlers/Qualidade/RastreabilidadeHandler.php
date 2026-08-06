<?php

namespace src\handlers\Qualidade;

use src\models\Qualidade\RastreabilidadeTampoBordado;
use src\models\Qualidade\RastreabilidadeLinhaMontagem;
use src\models\Qualidade\RastreabilidadeMolas;

class RastreabilidadeHandler
{
    public static function buscarTampoBordado(int $emprId, int $numLote): array
    {
        $rows     = RastreabilidadeTampoBordado::buscarTampo($emprId, $numLote);
        $dataLote = RastreabilidadeTampoBordado::buscarDataLote($emprId, $numLote);

        return [
            'success'    => true,
            'tampo_rows' => $rows,
            'data_lote'  => $dataLote,
        ];
    }

    public static function buscarLinhaMontagem(int $emprId, int $numLote): array
    {
        $rows     = RastreabilidadeLinhaMontagem::buscar($emprId, $numLote);
        $dataLote = RastreabilidadeLinhaMontagem::buscarDataLote($emprId, $numLote);

        return [
            'success'   => true,
            'rows'      => $rows,
            'data_lote' => $dataLote,
        ];
    }

    public static function buscarMolas(int $emprId, int $numLote): array
    {
        $rows     = RastreabilidadeMolas::buscar($emprId, $numLote);
        $dataLote = RastreabilidadeMolas::buscarDataLote($emprId, $numLote);

        return [
            'success'   => true,
            'rows'      => $rows,
            'data_lote' => $dataLote,
        ];
    }
}
