<?php

namespace src\handlers\PCP;

use src\models\PCP\RelatorioProd;
use src\utils\DashboardCache;

class RelatorioProdHandler
{
    private static function cached(string $key, callable $fn): array
    {
        $val = DashboardCache::get($key);
        if ($val !== null) return $val;
        $result = $fn();
        DashboardCache::set($key, $result, 300);
        return $result;
    }

    public static function buscar(int $emprId, int $numLote): array
    {
        return self::cached("pcp.caixote.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows = RelatorioProd::buscar($emprId, $numLote);

            $secoes = ['conjugado' => [], 'mesa' => [], 'sem_pillow' => [], 'com_pillow' => []];
            $ordsExcluidos = ['TRAVE_PEZE'];

            foreach ($rows as $row) {
                $ord     = strtoupper(trim($row['ORD']     ?? ''));
                $alt     = (float) ($row['ALT']     ?? 0);
                $altMola = (float) ($row['ALT_MOLA'] ?? 0);

                if (in_array($ord, $ordsExcluidos, true)) continue;

                if ($ord === 'CON_BAS_AU') {
                    $secoes['conjugado'][] = $row;
                } elseif (in_array($ord, ['MESA', 'MESA_PL'], true)) {
                    $secoes['mesa'][] = $row;
                } elseif ($alt <= 0) {
                    $secoes['sem_pillow'][] = $row;
                } elseif ($altMola >= 150) {
                    $secoes['mesa'][] = $row;
                } else {
                    $secoes['com_pillow'][] = $row;
                }
            }

            foreach ($secoes as $chave => &$linhas) {
                $isConj = ($chave === 'conjugado');
                usort($linhas, function ($a, $b) use ($isConj) {
                    $larA = (int)($a['LARGURA_COLCHAO'] ?? 0);
                    $larB = (int)($b['LARGURA_COLCHAO'] ?? 0);
                    $epsA = (int)($a['ALT_EPS']        ?? 0);
                    $epsB = (int)($b['ALT_EPS']        ?? 0);
                    if ($larA !== $larB) return $isConj ? ($larB <=> $larA) : ($larA <=> $larB);
                    if ($epsA === 0 && $epsB !== 0) return 1;
                    if ($epsA !== 0 && $epsB === 0) return -1;
                    return $epsA <=> $epsB;
                });
            }
            unset($linhas);

            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return ['success' => true, 'secoes' => $secoes, 'data_lote' => $dataLote];
        });
    }

    public static function buscarFpt(int $emprId, int $numLote): array
    {
        return self::cached("pcp.fpt.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows         = RelatorioProd::buscarFpt($emprId, $numLote);
            $dataLote     = RelatorioProd::buscarDataLote($emprId, $numLote);
            $pillowRows   = RelatorioProd::buscarPillow($emprId, $numLote);
            $pillowQtde   = array_sum(array_column($pillowRows, 'QTDE_OF'));
            $pillowLinear = round($pillowQtde * 7.5, 2);
            return [
                'success'       => true,
                'fpt_rows'      => $rows,
                'data_lote'     => $dataLote,
                'pillow_linear' => $pillowLinear,
            ];
        });
    }

    public static function buscarPillow(int $emprId, int $numLote): array
    {
        return self::cached("pcp.pillow.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarPillow($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return ['success' => true, 'pillow_rows' => $rows, 'data_lote' => $dataLote];
        });
    }

    public static function buscarMesaFaixa(int $emprId, int $numLote): array
    {
        return self::cached("pcp.mesaFaixa.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarMesaFaixa($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return ['success' => true, 'mesa_rows' => $rows, 'data_lote' => $dataLote];
        });
    }

    public static function buscarOptron(int $emprId, int $numLote): array
    {
        return self::cached("pcp.optron.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows         = RelatorioProd::buscarOptron($emprId, $numLote);
            $agrupadoRows = RelatorioProd::buscarOptronAgrupado($emprId, $numLote);
            $dataLote     = RelatorioProd::buscarDataLote($emprId, $numLote);
            return [
                'success'       => true,
                'optron_rows'   => $rows,
                'agrupado_rows' => $agrupadoRows,
                'data_lote'     => $dataLote,
            ];
        });
    }

    public static function buscarTampoLiso(int $emprId, int $numLote): array
    {
        return self::cached("pcp.tampoLiso.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows         = RelatorioProd::buscarTampoLiso($emprId, $numLote);
            $agrupadoRows = RelatorioProd::buscarTampoLisoAgrupado($emprId, $numLote);
            $dataLote     = RelatorioProd::buscarDataLote($emprId, $numLote);
            return [
                'success'       => true,
                'tampo_rows'    => $rows,
                'agrupado_rows' => $agrupadoRows,
                'data_lote'     => $dataLote,
            ];
        });
    }

    public static function buscarTampoBordado(int $emprId, int $numLote): array
    {
        return self::cached("pcp.tampoBordado.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
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
        });
    }

    public static function buscarTampoBordadoMesa(int $emprId, int $numLote): array
    {
        return self::cached("pcp.tampoBordadoMesa.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarTampoBordadoMesa($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return ['success' => true, 'tampo_rows' => $rows, 'data_lote' => $dataLote];
        });
    }

    public static function buscarManta(int $emprId, int $numLote): array
    {
        return self::cached("pcp.manta.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarManta($emprId, $numLote);
            $mesaRows = RelatorioProd::buscarMantaMesa($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return [
                'success'         => true,
                'manta_rows'      => $rows,
                'manta_mesa_rows' => $mesaRows,
                'data_lote'       => $dataLote,
            ];
        });
    }

    public static function buscarMantaMesa(int $emprId, int $numLote): array
    {
        return self::cached("pcp.mantaMesa.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarMantaMesa($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return ['success' => true, 'manta_rows' => $rows, 'data_lote' => $dataLote];
        });
    }

    public static function buscarMesaDeCorte(int $emprId, int $numLote): array
    {
        return self::cached("pcp.mesaDeCorte.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $caixaBoxRows = RelatorioProd::buscarMesaCorteCaixaBox($emprId, $numLote);
            $caixoteRows  = RelatorioProd::buscarMesaCorteCaixote($emprId, $numLote);
            $dataLote     = RelatorioProd::buscarDataLote($emprId, $numLote);
            return [
                'success'        => true,
                'caixa_box_rows' => $caixaBoxRows,
                'caixote_rows'   => $caixoteRows,
                'data_lote'      => $dataLote,
            ];
        });
    }

    public static function buscarBordadeira(int $emprId, int $numLote): array
    {
        return self::cached("pcp.bordadeira.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarBordadeira($emprId, $numLote);
            $roloRows = RelatorioProd::buscarRoloBordado($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return [
                'success'         => true,
                'bordadeira_rows' => $rows,
                'rolo_rows'       => $roloRows,
                'data_lote'       => $dataLote,
            ];
        });
    }

    public static function buscarTapecaria(int $emprId, int $numLote): array
    {
        return self::cached("pcp.tapecaria.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarTapecaria($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);

            $colchaoboxRows = array_values(array_filter($rows, fn($r) =>
                stripos($r['DESCICAO'] ?? '', 'COLCHAO BOX') === 0
            ));
            $cabeceiraRows  = array_values(array_filter($rows, fn($r) =>
                stripos($r['DESCICAO'] ?? '', 'CABECEIRA') === 0
            ));

            return [
                'success'         => true,
                'colchaobox_rows' => $colchaoboxRows,
                'cabeceira_rows'  => $cabeceiraRows,
                'data_lote'       => $dataLote,
            ];
        });
    }

    public static function buscarRobotec(int $emprId, int $numLote): array
    {
        return self::cached("pcp.robotec.v2.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarRobotec($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);

            $mesaOrds  = ['MESA_PL', 'MESA'];
            $linhaRows = array_values(array_filter($rows, fn($r) => !in_array($r['ORD'] ?? '', $mesaOrds, true)));
            $mesaRows  = array_values(array_filter($rows, fn($r) =>  in_array($r['ORD'] ?? '', $mesaOrds, true)));

            return [
                'success'    => true,
                'linha_rows' => $linhaRows,
                'mesa_rows'  => $mesaRows,
                'data_lote'  => $dataLote,
            ];
        });
    }

    public static function buscarRoloBordado(int $emprId, int $numLote): array
    {
        return self::cached("pcp.roloBordado.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows        = RelatorioProd::buscarRoloBordado($emprId, $numLote);
            $detalheRows = RelatorioProd::buscarRoloBordadoDetalhe($emprId, $numLote);
            $dataLote    = RelatorioProd::buscarDataLote($emprId, $numLote);
            return [
                'success'      => true,
                'rolo_rows'    => $rows,
                'detalhe_rows' => $detalheRows,
                'data_lote'    => $dataLote,
            ];
        });
    }

    public static function buscarConjugado(int $emprId, int $numLote): array
    {
        return self::cached("pcp.conjugado.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarConjugado($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return ['success' => true, 'conjugado_rows' => $rows, 'data_lote' => $dataLote];
        });
    }

    public static function buscarTravePeze(int $emprId, int $numLote): array
    {
        return self::cached("pcp.travePeze.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarTravePeze($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return ['success' => true, 'travepeze_rows' => $rows, 'data_lote' => $dataLote];
        });
    }

    public static function buscarMolasBordas(int $emprId, int $numLote): array
    {
        return self::cached("pcp.molasBordas.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarMolasBordas($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return ['success' => true, 'molas_rows' => $rows, 'data_lote' => $dataLote];
        });
    }

    public static function buscarCaixaBox(int $emprId, int $numLote): array
    {
        return self::cached("pcp.caixaBox.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $principalRows = RelatorioProd::buscarCaixaBoxPrincipal($emprId, $numLote);
            $auxRows       = RelatorioProd::buscarCaixaBoxAux($emprId, $numLote);
            $dataLote      = RelatorioProd::buscarDataLote($emprId, $numLote);
            return [
                'success'        => true,
                'principal_rows' => $principalRows,
                'aux_rows'       => $auxRows,
                'data_lote'      => $dataLote,
            ];
        });
    }

    public static function buscarRobotecAbastecedor(int $emprId, int $numLote): array
    {
        return self::cached("pcp.robotecAbastecedor.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarRobotecAbastecedor($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return ['success' => true, 'rows' => $rows, 'data_lote' => $dataLote];
        });
    }

    public static function buscarVerticalEspuma(int $emprId, int $numLote): array
    {
        return self::cached("pcp.verticalEspuma.v6.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarVerticalEspuma($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return ['success' => true, 'rows' => $rows, 'data_lote' => $dataLote];
        });
    }

    public static function buscarHorizontalEspuma(int $emprId, int $numLote): array
    {
        return self::cached("pcp.horizontalEspuma.v2.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $rows     = RelatorioProd::buscarHorizontalEspuma($emprId, $numLote);
            $dataLote = RelatorioProd::buscarDataLote($emprId, $numLote);
            return ['success' => true, 'rows' => $rows, 'data_lote' => $dataLote];
        });
    }

    public static function buscarPcpMolas(int $numLote): array
    {
        return self::cached("pcp.pcpMolas.{$numLote}", function () use ($numLote) {
            $rows = RelatorioProd::buscarPcpMolas($numLote);
            return ['success' => true, 'rows' => $rows];
        });
    }

    public static function buscarPcpCordao(int $numLote): array
    {
        return self::cached("pcp.pcpCordao.{$numLote}", function () use ($numLote) {
            $rows = RelatorioProd::buscarPcpCordao($numLote);
            return ['success' => true, 'rows' => $rows];
        });
    }

    public static function buscarResumoDeLote(int $emprId, int $numLote): array
    {
        return self::cached("pcp.resumoDeLote.v1.{$emprId}.{$numLote}", function () use ($emprId, $numLote) {
            $robotecRows    = RelatorioProd::buscarRobotec($emprId, $numLote);
            $tapRows        = RelatorioProd::buscarTapecaria($emprId, $numLote);
            $conjRows       = RelatorioProd::buscarConjugado($emprId, $numLote);
            $prodRows       = RelatorioProd::buscar($emprId, $numLote);
            $pillowRows     = RelatorioProd::buscarPillow($emprId, $numLote);
            $fptRows        = RelatorioProd::buscarFpt($emprId, $numLote);
            $optronRows     = RelatorioProd::buscarOptron($emprId, $numLote);
            $travePezeRows  = RelatorioProd::buscarTravePeze($emprId, $numLote);
            $mcCaixaBoxRows = RelatorioProd::buscarMesaCorteCaixaBox($emprId, $numLote);
            $mcCaixoteRows  = RelatorioProd::buscarMesaCorteCaixote($emprId, $numLote);
            $brdRows        = RelatorioProd::buscarBordadeira($emprId, $numLote);
            $dataLote       = RelatorioProd::buscarDataLote($emprId, $numLote);

            // ── COLCHÕES (da robotec: ORD determina o tipo) ──────────────
            $colMesa = $colMola = $colEspuma = $colchonete = 0.0;
            foreach ($robotecRows as $r) {
                $qtde = (float) ($r['QTDE'] ?? 0);
                $ord  = strtoupper(trim($r['ORD'] ?? ''));
                $desc = strtoupper($r['DESCICAO'] ?? '');
                if ($ord === 'MESA_PL' || $ord === 'MESA') {
                    $colMesa += $qtde;
                } elseif (str_contains($desc, 'COLCHONETE')) {
                    $colchonete += $qtde;
                } elseif ($ord === 'MOLA' || str_contains($desc, 'MOLA')) {
                    $colMola += $qtde;
                } else {
                    $colEspuma += $qtde;
                }
            }

            // ── TAPEÇARIA ────────────────────────────────────────────────
            $tapBase = $tapAux = $tapCabec = 0.0;
            foreach ($tapRows as $r) {
                $qtde = (float) ($r['QTDE'] ?? 0);
                $desc = strtoupper($r['DESCICAO'] ?? '');
                if (str_starts_with($desc, 'COLCHAO BOX')) {
                    $tapBase += $qtde;
                } elseif (str_starts_with($desc, 'CABECEIRA')) {
                    $tapCabec += $qtde;
                } else {
                    $tapAux += $qtde;
                }
            }
            $tapConj = $tapCnjEsp = $tapCnjMol = 0.0;
            foreach ($conjRows as $r) {
                $qtde = (float) ($r['QTDE'] ?? 0);
                $desc = strtoupper($r['DESCICAO'] ?? '');
                if (str_contains($desc, 'ESPUMA')) {
                    $tapCnjEsp += $qtde;
                } elseif (str_contains($desc, 'MOLA')) {
                    $tapCnjMol += $qtde;
                } else {
                    $tapConj += $qtde;
                }
            }
            $tapConjTotal = $tapConj + $tapCnjEsp + $tapCnjMol;

            // ── CAIXOTE (classifica pelo mesmo critério do handler de caixote) ──
            $cxColchao = $cxConjugado = $cxPillow = $cxMesa = 0.0;
            foreach ($prodRows as $r) {
                $ord    = strtoupper(trim($r['ORD'] ?? ''));
                $alt    = (float) ($r['ALT']     ?? 0);
                $altMol = (float) ($r['ALT_MOLA'] ?? 0);
                $qtde   = (float) ($r['QTDE']    ?? 0);
                if ($ord === 'TRAVE_PEZE') continue;
                if ($ord === 'CON_BAS_AU') {
                    $cxConjugado += $qtde;
                } elseif ($altMol >= 150) {
                    $cxMesa += $qtde;
                } elseif ($alt > 0) {
                    $cxColchao += $qtde;
                    $cxPillow  += $qtde;
                } else {
                    $cxColchao += $qtde;
                }
            }

            // ── COSTURA (FAIXA) ──────────────────────────────────────────
            $cstPillow    = (float) array_sum(array_column($pillowRows,    'QTDE_OF'));
            $cstFptQtde   = (float) array_sum(array_column($fptRows,       'QTDE'));
            $cstFptLinear = (float) array_sum(array_column($fptRows,       'LINEAR'));
            $cstOptQtde   = (float) array_sum(array_column($optronRows,    'QTDE'));
            $cstOptLinear = (float) array_sum(array_column($optronRows,    'LINEAR'));
            $cstDiversos  = (float) array_sum(array_column($travePezeRows, 'QTDE'));

            // ── MESA DE CORTE ─────────────────────────────────────────────
            $mcCaixaBox = (float) array_sum(array_column($mcCaixaBoxRows, 'QTDE_OF'));
            $mcCaixote  = (float) array_sum(array_column($mcCaixoteRows,  'QTDE_OF'));

            // ── BORDADEIRA (P1 vs P2/P3 pelo campo TANQUE) ────────────────
            $brd01 = $brd02 = 0.0;
            foreach ($brdRows as $r) {
                $tanque = trim($r['TANQUE'] ?? '');
                $linear = (float) ($r['LINEAR'] ?? 0);
                if ($tanque === '' || preg_match('/P1/i', $tanque)) {
                    $brd01 += $linear;
                } else {
                    $brd02 += $linear;
                }
            }

            return [
                'success'    => true,
                'data_lote'  => $dataLote,
                'colchoes'   => [
                    'col_mesa'   => (int) $colMesa,
                    'col_mola'   => (int) $colMola,
                    'col_espuma' => (int) $colEspuma,
                    'colchonete' => (int) $colchonete,
                    'total'      => (int) ($colMesa + $colMola + $colEspuma + $colchonete),
                ],
                'tapecaria'  => [
                    'base'        => (int) $tapBase,
                    'conjugado'   => (int) $tapConjTotal,
                    'conj_espuma' => (int) $tapCnjEsp,
                    'conj_mola'   => (int) $tapCnjMol,
                    'aux_auxiliar'=> (int) $tapAux,
                    'cabeceira'   => (int) $tapCabec,
                    'total'       => (int) ($tapBase + $tapConjTotal + $tapAux + $tapCabec),
                ],
                'caixote'    => [
                    'c_colchao'   => (int) $cxColchao,
                    'c_conjugado' => (int) $cxConjugado,
                    'c_pillow'    => (int) $cxPillow,
                    'c_mesa'      => (int) $cxMesa,
                    'mola_bonnel' => 0,
                    'total'       => (int) ($cxColchao + $cxConjugado),
                ],
                'costura'    => [
                    'pillow'       => (int) $cstPillow,
                    'fpt_linear'   => round($cstFptLinear, 2),
                    'fpt_qtde'     => (int) $cstFptQtde,
                    'optron_linear'=> round($cstOptLinear, 2),
                    'optron_qtde'  => (int) $cstOptQtde,
                    'diversos'     => (int) $cstDiversos,
                    'total'        => (int) ($cstPillow + $cstFptQtde + $cstOptQtde + $cstDiversos),
                ],
                'mesa_corte' => [
                    'caixa_box' => (int) $mcCaixaBox,
                    'caixote'   => (int) $mcCaixote,
                    'total'     => (int) ($mcCaixaBox + $mcCaixote),
                ],
                'bordadeira' => [
                    'brd01' => round($brd01, 2),
                    'brd02' => round($brd02, 2),
                    'total' => round($brd01 + $brd02, 2),
                ],
            ];
        });
    }

    public static function listarEmpresas(): array
    {
        return RelatorioProd::listarEmpresas();
    }
}
