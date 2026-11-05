<?php

namespace src\handlers\Industrial;

use src\models\Industrial\ConsumoMolinha as M;

class ConsumoMolinhaHandler
{
    private const MAX_MESES = 12;

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

    /**
     * Por filial e tipo de molinha, em cada mês: molinhas produzidas, consumidas nos molejos e diferença.
     * Detalhe por item produzido (todos os itens com "MOLA", sem filtro de classificação).
     */
    public static function listar(array $p): array
    {
        $meses = self::meses((string) ($p['ini'] ?? ''), (string) ($p['fim'] ?? ''));
        $zero  = fn() => array_fill_keys($meses, ['produzidas' => 0.0, 'consumo' => 0.0]);

        $nomes = [];
        foreach (M::empresas() as $e) {
            $cidade = preg_replace_callback('/ (De|Da|Do|Das|Dos) /', fn($m) => ' ' . strtolower($m[1]) . ' ', (string) $e['CIDADE']);
            $nomes[(int) $e['ID']] = $e['COD_EMP'] . ' - ' . $cidade . ' (Molas)';
        }

        $filiais = [];
        foreach (M::FILIAIS as $e) {
            $filiais[$e] = ['empresa' => $e, 'nome' => $nomes[$e] ?? (string) $e, 'tipos' => [], 'itens' => [],
                            'total' => $zero(), 'molejos' => array_fill_keys($meses, 0.0), 'semEstrutura' => 0];
        }

        foreach ($meses as $m) {
            $contados = [];   // molejo com 2 tipos de molinha vem em 2 linhas: conta a produção uma vez
            foreach (M::mes($m) as $r) {
                $f = &$filiais[$r['empresa']];
                if ($r['tipo'] === 'MOLEJO') {
                    $km = $r['empresa'] . '|' . $r['cod_item'] . '|' . ($r['mascara'] ?? '');
                    if (!isset($contados[$km])) { $contados[$km] = true; $f['molejos'][$m] += $r['produzido']; }
                }
                if ($r['tipo'] === 'MOLEJO' && $r['molinha'] === null) $f['semEstrutura']++;
                if ($r['molinha'] !== null) {
                    $k = $r['molinha_cod'];
                    $f['tipos'][$k] ??= ['cod' => $k, 'molinha' => $r['molinha'], 'meses' => $zero()];
                    $f['tipos'][$k]['meses'][$m]['produzidas'] += $r['produzidas'];
                    $f['tipos'][$k]['meses'][$m]['consumo']    += $r['consumo'];
                    $f['total'][$m]['produzidas'] += $r['produzidas'];
                    $f['total'][$m]['consumo']    += $r['consumo'];
                }
                // detalhe: item + máscara + molinha, somando os meses do período
                $ki = $r['cod_item'] . '|' . ($r['mascara'] ?? '') . '|' . ($r['molinha_cod'] ?? '');
                $f['itens'][$ki] ??= ['tipo' => $r['tipo'], 'cod_item' => $r['cod_item'], 'desc' => $r['desc'], 'mascara' => $r['mascara'],
                                      'molinha' => $r['molinha'], 'por_unid' => $r['por_unid'],
                                      'produzido' => 0.0, 'produzidas' => 0.0, 'consumo' => 0.0];
                $f['itens'][$ki]['produzido']  += $r['produzido'];
                $f['itens'][$ki]['produzidas'] += $r['produzidas'];
                $f['itens'][$ki]['consumo']    += $r['consumo'];
                unset($f);
            }
        }

        $ordemTipo = ['MOLINHA' => 0, 'MOLEJO' => 1, 'OUTRO' => 2];
        foreach ($filiais as &$f) {
            $f['tipos'] = array_values($f['tipos']);
            usort($f['tipos'], fn($a, $b) => array_sum(array_column($b['meses'], 'produzidas')) + array_sum(array_column($b['meses'], 'consumo'))
                                         <=> array_sum(array_column($a['meses'], 'produzidas')) + array_sum(array_column($a['meses'], 'consumo')));
            $f['itens'] = array_values($f['itens']);
            usort($f['itens'], fn($a, $b) => [$ordemTipo[$a['tipo']], -($a['produzidas'] + $a['consumo']), -$a['produzido']]
                                         <=> [$ordemTipo[$b['tipo']], -($b['produzidas'] + $b['consumo']), -$b['produzido']]);
        }
        unset($f);

        return ['meses' => $meses, 'filiais' => array_values($filiais)];
    }
}
