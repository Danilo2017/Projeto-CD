<?php

namespace src\handlers\Custo;

use src\models\Custo\CustoEntrada as M;

class CustoEntradaHandler
{
    private const MAX_MESES = 6;

    private static function meses(string $ini, string $fim): array
    {
        foreach ([$ini, $fim] as $m) {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) throw new \InvalidArgumentException('Ano/mês inválido', 400);
        }
        if ($ini > $fim) throw new \InvalidArgumentException('Ano/mês inicial maior que o final', 400);
        $meses = [];
        for ($d = new \DateTime("$ini-01"); $d->format('Y-m') <= $fim; $d->modify('+1 month')) {
            $meses[] = $d->format('Y-m');
            if (count($meses) > self::MAX_MESES) throw new \InvalidArgumentException('Selecione no máximo ' . self::MAX_MESES . ' meses', 400);
        }
        return $meses;
    }

    public static function empresas(): array
    {
        return array_map(fn($e) => ['id' => (int) $e['ID'], 'codigo' => (string) $e['CODIGO'], 'nome' => (string) $e['RAZAO_SOCIAL']], M::empresas());
    }

    /**
     * Uma linha por item de nota de compra, com:
     *  custo_frete = custo unitário da nota + (frete ligado + outros custos ligados) / quantidade
     *  var_pct     = custo_frete / custo médio do mês anterior − 1 (null sem custo médio)
     *  impacto     = (custo_frete − custo médio do mês anterior) × quantidade
     *  falta_frete = frete FOB (por nossa conta) sem nota de frete ligada e sem frete na própria nota
     */
    public static function listar(array $p): array
    {
        $emprId = (int) ($p['empresa'] ?? 0);
        $meses  = self::meses((string) ($p['ini'] ?? ''), (string) ($p['fim'] ?? ''));

        $f = fn($v) => $v === null || $v === '' ? null : (float) $v;
        $linhas = [];
        foreach ($meses as $m) {
            foreach (M::itensMes($emprId, $m) as $r) {
                // custo da nota é por unidade de estoque: frete por unidade, média e impacto usam a quantidade convertida
                $qtde   = (float) ($r['QTDE_ESTQ'] ?? $r['QTDE']);
                $custo  = (float) $r['CUSTO_UNIT'];
                $frete  = (float) ($r['FRETE_CUSTO'] ?? 0);
                $outros = (float) ($r['OUTROS_CUSTO'] ?? 0);
                $freteNf = (float) $r['VLR_FRETE_NF'];
                $custoFrete = $qtde > 0 ? $custo + ($frete + $outros) / $qtde : $custo;
                $cmAnt = $f($r['CM_ANT']);
                $notasFrete = $r['NOTAS_FRETE'] ? array_values(array_unique(explode(',', (string) $r['NOTAS_FRETE']))) : [];

                $linhas[] = [
                    'nfe_id'      => (int) $r['NFE_ID'],
                    'itestq_id'   => (int) $r['ITESTQ_ID'],
                    'masc_id'     => (int) $r['MASC_ID'],
                    'nf'          => (string) $r['NUM_NF'],
                    'serie'       => (string) $r['SERIE'],
                    'dt'          => (string) $r['DT_ENT'],
                    'forn_id'     => (int) $r['FORN_ID'],
                    'fornecedor'  => trim((string) $r['FORNECEDOR']),
                    'tp_frete'    => (string) $r['TP_FRETE'],
                    'tipo'        => (string) $r['TIPO_NF'],
                    'atu_cm'      => (int) $r['ATU_CUS_MED'] === 1,
                    'item'        => (string) $r['COD_ITEM'],
                    'desc'        => (string) $r['DESC_ITEM'],
                    'mascara'     => $r['MASCARA'] !== null ? (string) $r['MASCARA'] : '',
                    'grupo'       => trim(($r['COD_GRUPO'] ?? '') . ' ' . ($r['GRUPO'] ?? '')),
                    'qtde'        => $qtde,
                    'qtde_nf'     => (float) $r['QTDE'],
                    'preco'       => (float) $r['PRECO_UNIT'],
                    'custo'       => $custo,
                    'vlr_total'   => (float) $r['VLR_TOTAL'],
                    'frete_nf'    => $freteNf,
                    'frete'       => $frete,
                    'outros'      => $outros,
                    'notas_frete' => $notasFrete,
                    'transp'      => $r['TRANSPORTADORA'] !== null ? trim((string) $r['TRANSPORTADORA']) : '',
                    'custo_frete' => $custoFrete,
                    'cm_ant'      => $cmAnt,
                    'cm_mes'      => $f($r['CM_MES']),
                    'var_pct'     => $cmAnt ? ($custoFrete / $cmAnt - 1) * 100 : null,
                    'impacto'     => $cmAnt ? ($custoFrete - $cmAnt) * $qtde : null,
                    'falta_frete' => $r['TP_FRETE'] === 'F' && $frete == 0 && $freteNf == 0,
                ];
            }
        }
        return ['meses' => $meses, 'empresa' => $emprId, 'linhas' => $linhas];
    }

    private static function n(float $v, int $d = 2): string
    {
        return number_format($v, $d, ',', '.');
    }

    private static function unit(float $v): string
    {
        return 'R$ ' . self::n($v, abs($v) < 10 ? 4 : 2);
    }

    /**
     * Razão de custo do item: do 1º dia do mês anterior ao período até o fim do período.
     * Para cada movimentação: custo médio antes/depois, efeito e o motivo (texto montado a partir do tipo e dos números).
     */
    public static function razao(array $p): array
    {
        $meses = self::meses((string) ($p['ini'] ?? ''), (string) ($p['fim'] ?? ''));
        $dtIni = (new \DateTime($meses[0] . '-01'))->modify('-1 month')->format('Y-m-d');
        $dtFim = (new \DateTime(end($meses) . '-01'))->modify('last day of this month')->format('Y-m-d');

        $rows = M::razao((int) ($p['empresa'] ?? 0), (int) ($p['itestq_id'] ?? 0), (int) ($p['masc_id'] ?? 0), $dtIni, $dtFim);

        $out = [];
        foreach ($rows as $r) {
            $qtde   = (float) $r['QTDE'];
            $valor  = (float) $r['VALOR_MOVTO'];
            $cm     = (float) $r['CUSTO_MED'];
            $cmAnt  = $r['CM_ANTES'] !== null ? (float) $r['CM_ANTES'] : null;
            $sAnt   = $r['SALDO_ANTES'] !== null ? (float) $r['SALDO_ANTES'] : null;
            $ent    = $r['ENT_SAI'] === 'E';
            $unit   = $qtde != 0 ? $valor / $qtde : null;
            $efeito = $cmAnt !== null ? $cm - $cmAnt : null;
            $efPct  = $cmAnt ? ($cm / $cmAnt - 1) * 100 : null;

            // documento de origem
            $doc = '';
            if ($r['NUM_NF'] !== null) {
                $doc = 'NF ' . $r['NUM_NF'] . ' — ' . trim((string) $r['EMITENTE']);
                if ($r['NF_ORIGEM'] !== null) $doc .= ' (ref. NF ' . $r['NF_ORIGEM'] . ' — ' . trim((string) $r['FORN_ORIGEM']) . ')';
            } elseif ($r['NRO_DOC'] !== null) {
                $doc = 'Doc. ' . $r['NRO_DOC'];
            }

            // motivo
            $saldoTxt = $sAnt !== null ? self::n($sAnt, 3) . ' un' : 'saldo anterior desconhecido';
            if ($ent && $qtde == 0 && $valor != 0) {
                $oque = (int) ($r['TP_NF_ENT'] ?? 0) === 7 ? 'Frete' : 'Valor sem quantidade (complemento/despesa)';
                $qOrig = $r['QTDE_ORIGEM'] !== null ? (float) $r['QTDE_ORIGEM'] : null;
                $motivo = $oque . ' de R$ ' . self::n($valor) . ' lançado' . ($r['NF_ORIGEM'] !== null ? ' para a NF ' . $r['NF_ORIGEM'] . ' de ' . self::dBr((string) $r['DT_ORIGEM'])
                        . ($qOrig !== null ? ' (que trouxe ' . self::n($qOrig, 3) . ' un)' : '') : '');
                if ($sAnt !== null && $sAnt <= 0) {
                    $motivo .= ' com o estoque zerado';
                } elseif ($sAnt !== null && $qOrig && $sAnt < $qOrig * 0.5) {
                    $motivo .= ', mas só restavam ' . $saldoTxt . ' em estoque: o valor inteiro foi dividido só por elas';
                } else {
                    $motivo .= ', rateado sobre o saldo de ' . $saldoTxt;
                }
                if ($sAnt !== null && $sAnt > 0) $motivo .= ' (+' . self::unit($valor / $sAnt) . ' por unidade)';
            } elseif ($ent && $sAnt !== null && $sAnt <= 0 && $qtde > 0) {
                $motivo = 'Estoque estava zerado' . ($sAnt < 0 ? ' (negativo: ' . self::n($sAnt, 3) . ')' : '') . ': o custo médio passa a ser o desta entrada, ' . self::unit((float) $unit);
            } elseif ($ent && $qtde > 0 && $cmAnt) {
                $dif = ($unit / $cmAnt - 1) * 100;
                $motivo = 'Entrada de ' . self::n($qtde, 3) . ' un a ' . self::unit((float) $unit) . ' (' . ($dif >= 0 ? '+' : '') . self::n($dif, 1) . '% contra o médio de '
                        . self::unit($cmAnt) . ') sobre saldo de ' . $saldoTxt;
            } elseif (!$ent && $efeito !== null && abs($efeito) > 0.00005) {
                $motivo = 'Saída de ' . self::n($qtde, 3) . ' un a ' . self::unit((float) $unit) . ' com valor diferente do médio (o Focco recalculou o médio nesta saída)';
            } else {
                $motivo = '';
            }

            $out[] = [
                'dt' => (string) $r['DT'], 'es' => (string) $r['ENT_SAI'], 'sigla' => (string) $r['SIGLA'], 'tipo' => (string) $r['TIPO_MOV'],
                'qtde' => $qtde, 'valor' => $valor, 'unit' => $unit,
                'saldo_antes' => $sAnt, 'saldo' => (float) $r['SALDO_QTDE'], 'saldo_vlr' => (float) $r['SALDO_VLR'],
                'cm_antes' => $cmAnt, 'cm' => $cm, 'efeito' => $efeito, 'efeito_pct' => $efPct,
                'doc' => $doc, 'tipo_nf' => $r['TIPO_NF'] !== null ? (string) $r['TIPO_NF'] : '', 'usuario' => (string) ($r['USUARIO'] ?? ''),
                'motivo' => $motivo,
            ];
        }
        return ['de' => $dtIni, 'ate' => $dtFim, 'movs' => $out];
    }

    private static function dBr(string $d): string
    {
        return $d ? implode('/', array_reverse(explode('-', $d))) : '';
    }
}
