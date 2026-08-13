<?php

namespace src\handlers\Qualidade;

use src\models\Qualidade\RastreabilidadeTampoBordado;
use src\models\Qualidade\RastreabilidadeLinhaMontagem;
use src\models\Qualidade\RastreabilidadeMolas;
use src\handlers\PCP\RelatorioProdHandler;
use src\models\PCP\RelatorioProd;

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
        return RelatorioProdHandler::buscarPcpMolas($emprId, $numLote);
    }

    public static function buscarCordaoMolas(int $emprId, int $numLote): array
    {
        return RelatorioProdHandler::buscarPcpCordao($emprId, $numLote);
    }

    public static function buscarBordaMolas(int $emprId, int $numLote): array
    {
        return RelatorioProdHandler::buscarPcpBordaAco($emprId, $numLote);
    }

    public static function buscarCaixoteMola(int $emprId, int $numLote): array
    {
        $rows     = RelatorioProd::buscar($emprId, $numLote);
        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);

        $filtrado = array_values(array_filter($rows, function ($row) {
            return strtoupper(trim($row['ORD'] ?? '')) !== 'TRAVE_PEZE';
        }));

        return [
            'success'   => true,
            'rows'      => $filtrado,
            'data_lote' => $dataLote,
        ];
    }
}
