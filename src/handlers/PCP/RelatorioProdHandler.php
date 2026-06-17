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

        $ordsExcluidos = ['TRAVE_PEZE'];

        foreach ($rows as $row) {
            $ord     = strtoupper(trim($row['ORD']     ?? ''));
            $alt     = (float) ($row['ALT']     ?? 0);
            $altMola = (float) ($row['ALT_MOLA'] ?? 0);

            if (in_array($ord, $ordsExcluidos, true)) {
                continue;
            }

            if ($ord === 'CON_BAS_AU') {
                $secoes['conjugado'][] = $row;
            } elseif ($alt <= 0) {
                // sem pillow: qualquer ORD com AL = 0
                $secoes['sem_pillow'][] = $row;
            } elseif ($altMola >= 150) {
                // mola alta → seção MESA
                $secoes['mesa'][] = $row;
            } else {
                // mola baixa + pillow → COM PILLOW
                $secoes['com_pillow'][] = $row;
            }
        }

        // conjugado → LARGURA desc; demais → LARGURA asc, ALT_EPS asc (0 por último)
        foreach ($secoes as $chave => &$linhas) {
            $isConj = ($chave === 'conjugado');
            usort($linhas, function ($a, $b) use ($isConj) {
                $larA = (int)($a['LARGURA_COLCHAO'] ?? 0);
                $larB = (int)($b['LARGURA_COLCHAO'] ?? 0);
                $epsA = (int)($a['ALT_EPS']        ?? 0);
                $epsB = (int)($b['ALT_EPS']        ?? 0);

                if ($larA !== $larB) {
                    return $isConj ? ($larB <=> $larA) : ($larA <=> $larB);
                }

                // ALT_EPS 0 (sem EPS) vai para o final do grupo de largura
                if ($epsA === 0 && $epsB !== 0) return 1;
                if ($epsA !== 0 && $epsB === 0) return -1;
                return $epsA <=> $epsB;
            });
        }
        unset($linhas);

        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);

        return ['success' => true, 'secoes' => $secoes, 'data_lote' => $dataLote];
    }

    public static function buscarFpt(int $emprId, int $numLote): array
    {
        $rows       = RelatorioProd::buscarFpt($emprId, $numLote);
        $dataLote   = RelatorioProd::buscarDataLote($emprId, $numLote);

        // LINEAR do PILLOW = soma QTDE_OF do relatório Pillow × 7,5
        $pillowRows   = RelatorioProd::buscarPillow($emprId, $numLote);
        $pillowQtde   = array_sum(array_column($pillowRows, 'QTDE_OF'));
        $pillowLinear = round($pillowQtde * 7.5, 2);

        return [
            'success'       => true,
            'fpt_rows'      => $rows,
            'data_lote'     => $dataLote,
            'pillow_linear' => $pillowLinear,
        ];
    }

    public static function buscarPillow(int $emprId, int $numLote): array
    {
        $rows     = RelatorioProd::buscarPillow($emprId, $numLote);
        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
        return ['success' => true, 'pillow_rows' => $rows, 'data_lote' => $dataLote];
    }

    public static function buscarMesaFaixa(int $emprId, int $numLote): array
    {
        $rows     = RelatorioProd::buscarMesaFaixa($emprId, $numLote);
        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
        return ['success' => true, 'mesa_rows' => $rows, 'data_lote' => $dataLote];
    }

    public static function buscarOptron(int $emprId, int $numLote): array
    {
        $rows          = RelatorioProd::buscarOptron($emprId, $numLote);
        $agrupadoRows  = RelatorioProd::buscarOptronAgrupado($emprId, $numLote);
        $dataLote      = RelatorioProd::buscarDataLote($emprId, $numLote);
        return [
            'success'        => true,
            'optron_rows'    => $rows,
            'agrupado_rows'  => $agrupadoRows,
            'data_lote'      => $dataLote,
        ];
    }

    public static function buscarTampoLiso(int $emprId, int $numLote): array
    {
        $rows         = RelatorioProd::buscarTampoLiso($emprId, $numLote);
        $agrupadoRows = RelatorioProd::buscarTampoLisoAgrupado($emprId, $numLote);
        $dataLote     = RelatorioProd::buscarDataLote($emprId, $numLote);
        return [
            'success'       => true,
            'tampo_rows'    => $rows,
            'agrupado_rows' => $agrupadoRows,
            'data_lote'     => $dataLote,
        ];
    }

    public static function buscarTampoBordado(int $emprId, int $numLote): array
    {
        $rows           = RelatorioProd::buscarTampoBordado($emprId, $numLote);
        $mesaRows       = RelatorioProd::buscarTampoBordadoMesa($emprId, $numLote);
        $conjRows       = RelatorioProd::buscarTampoBordadoConj($emprId, $numLote);
        $bordadeiraRows = RelatorioProd::buscarBordadeira($emprId, $numLote);
        $dataLote       = RelatorioProd::buscarDataLote($emprId, $numLote);
        return [
            'success'         => true,
            'tampo_rows'      => $rows,
            'mesa_rows'       => $mesaRows,
            'conj_rows'       => $conjRows,
            'bordadeira_rows' => $bordadeiraRows,
            'data_lote'       => $dataLote,
        ];
    }

    public static function buscarTampoBordadoMesa(int $emprId, int $numLote): array
    {
        $rows     = RelatorioProd::buscarTampoBordadoMesa($emprId, $numLote);
        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
        return ['success' => true, 'tampo_rows' => $rows, 'data_lote' => $dataLote];
    }

    public static function buscarManta(int $emprId, int $numLote): array
    {
        $rows      = RelatorioProd::buscarManta($emprId, $numLote);
        $mesaRows  = RelatorioProd::buscarMantaMesa($emprId, $numLote);
        $dataLote  = RelatorioProd::buscarDataLote($emprId, $numLote);
        return [
            'success'         => true,
            'manta_rows'      => $rows,
            'manta_mesa_rows' => $mesaRows,
            'data_lote'       => $dataLote,
        ];
    }

    public static function buscarMantaMesa(int $emprId, int $numLote): array
    {
        $rows     = RelatorioProd::buscarMantaMesa($emprId, $numLote);
        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
        return ['success' => true, 'manta_rows' => $rows, 'data_lote' => $dataLote];
    }

    public static function buscarMesaDeCorte(int $emprId, int $numLote): array
    {
        $caixaBoxRows = RelatorioProd::buscarMesaCorteCaixaBox($emprId, $numLote);
        $caixoteRows  = RelatorioProd::buscarMesaCorteCaixote($emprId, $numLote);
        $dataLote     = RelatorioProd::buscarDataLote($emprId, $numLote);
        return [
            'success'       => true,
            'caixa_box_rows'=> $caixaBoxRows,
            'caixote_rows'  => $caixoteRows,
            'data_lote'     => $dataLote,
        ];
    }

    public static function buscarBordadeira(int $emprId, int $numLote): array
    {
        $rows     = RelatorioProd::buscarBordadeira($emprId, $numLote);
        $roloRows = RelatorioProd::buscarRoloBordado($emprId, $numLote);
        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
        return [
            'success'         => true,
            'bordadeira_rows' => $rows,
            'rolo_rows'       => $roloRows,
            'data_lote'       => $dataLote,
        ];
    }

    public static function buscarTapecaria(int $emprId, int $numLote): array
    {
        $rows     = RelatorioProd::buscarTapecaria($emprId, $numLote);
        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);

        $colchaoboxRows = array_values(array_filter($rows, fn($r) =>
            stripos($r['DESCICAO'] ?? '', 'COLCHAO BOX') === 0
        ));
        $cabeceiraRows  = array_values(array_filter($rows, fn($r) =>
            stripos($r['DESCICAO'] ?? '', 'CABECEIRA') === 0
        ));

        return [
            'success'          => true,
            'colchaobox_rows'  => $colchaoboxRows,
            'cabeceira_rows'   => $cabeceiraRows,
            'data_lote'        => $dataLote,
        ];
    }

    public static function buscarRobotec(int $emprId, int $numLote): array
    {
        $rows     = RelatorioProd::buscarRobotec($emprId, $numLote);
        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);

        $linhaRows = array_values(array_filter($rows, fn($r) => ($r['ORD'] ?? '') !== 'MESA_PL'));
        $mesaRows  = array_values(array_filter($rows, fn($r) => ($r['ORD'] ?? '') === 'MESA_PL'));

        return [
            'success'    => true,
            'linha_rows' => $linhaRows,
            'mesa_rows'  => $mesaRows,
            'data_lote'  => $dataLote,
        ];
    }

    public static function buscarRoloBordado(int $emprId, int $numLote): array
    {
        $rows        = RelatorioProd::buscarRoloBordado($emprId, $numLote);
        $detalheRows = RelatorioProd::buscarRoloBordadoDetalhe($emprId, $numLote);
        $dataLote    = RelatorioProd::buscarDataLote($emprId, $numLote);
        return [
            'success'       => true,
            'rolo_rows'     => $rows,
            'detalhe_rows'  => $detalheRows,
            'data_lote'     => $dataLote,
        ];
    }

    public static function buscarConjugado(int $emprId, int $numLote): array
    {
        $rows     = RelatorioProd::buscarConjugado($emprId, $numLote);
        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
        return ['success' => true, 'conjugado_rows' => $rows, 'data_lote' => $dataLote];
    }

    public static function buscarTravePeze(int $emprId, int $numLote): array
    {
        $rows     = RelatorioProd::buscarTravePeze($emprId, $numLote);
        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
        return ['success' => true, 'travepeze_rows' => $rows, 'data_lote' => $dataLote];
    }

    public static function buscarMolasBordas(int $emprId, int $numLote): array
    {
        $rows     = RelatorioProd::buscarMolasBordas($emprId, $numLote);
        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
        return ['success' => true, 'molas_rows' => $rows, 'data_lote' => $dataLote];
    }

    public static function buscarCaixaBox(int $emprId, int $numLote): array
    {
        $principalRows = RelatorioProd::buscarCaixaBoxPrincipal($emprId, $numLote);
        $auxRows       = RelatorioProd::buscarCaixaBoxAux($emprId, $numLote);
        $dataLote      = RelatorioProd::buscarDataLote($emprId, $numLote);

        return [
            'success'        => true,
            'principal_rows' => $principalRows,
            'aux_rows'       => $auxRows,
            'data_lote'      => $dataLote,
        ];
    }

    public static function buscarRobotecAbastecedor(int $emprId, int $numLote): array
    {
        $rows     = RelatorioProd::buscarRobotecAbastecedor($emprId, $numLote);
        $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
        return ['success' => true, 'rows' => $rows, 'data_lote' => $dataLote];
    }

    public static function listarEmpresas(): array
    {
        return RelatorioProd::listarEmpresas();
    }
}
