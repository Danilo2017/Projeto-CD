<?php

namespace src\handlers\Industrial;

use src\models\Industrial\ConsumoMolejoFiliais as M;

class ConsumoMolejoFiliaisHandler
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
            if (count($meses) > self::MAX_MESES) throw new \InvalidArgumentException('Selecione no máximo ' . self::MAX_MESES . ' meses (comparação diária)', 400);
        }
        return $meses;
    }

    /**
     * Por dia: produção de cada fábrica (10, 16) e consumo de cada filial, em molejos e molinhas.
     * Resumo do período por empresa (com detalhe por molejo) e por tipo de molinha.
     */
    public static function listar(array $p): array
    {
        $meses = self::meses((string) ($p['ini'] ?? ''), (string) ($p['fim'] ?? ''));

        $nomes = [];
        foreach (M::empresas() as $e) {
            $cidade = preg_replace_callback('/ (De|Da|Do|Das|Dos) /', fn($m) => ' ' . strtolower($m[1]) . ' ', (string) $e['CIDADE']);
            $nomes[(int) $e['ID']] = $e['COD_EMP'] . ' - ' . $cidade . (in_array((int) $e['ID'], M::FABRICAS, true) ? ' (Molas)' : '');
        }

        // todos os dias do período (dia sem movimento aparece zerado)
        $dias = [];
        $ini = new \DateTime($meses[0] . '-01');
        $fim = (new \DateTime(end($meses) . '-01'))->modify('last day of this month');
        for ($d = clone $ini; $d <= $fim; $d->modify('+1 day')) $dias[$d->format('Y-m-d')] = [];

        $empresas = ['P' => [], 'C' => []];   // resumo do período por lado e empresa
        $tipos = [];
        foreach ($meses as $m) {
            foreach (M::mes($m) as $r) {
                $l = $r['lado']; $e = $r['empresa']; $dia = $r['dia'];
                if (!isset($dias[$dia])) continue;
                $dias[$dia][$l][$e]['molejos']  = ($dias[$dia][$l][$e]['molejos'] ?? 0) + $r['molejos'];
                $dias[$dia][$l][$e]['molinhas'] = ($dias[$dia][$l][$e]['molinhas'] ?? 0) + $r['molinhas'];

                $empresas[$l][$e] ??= ['empresa' => $e, 'nome' => $nomes[$e] ?? (string) $e, 'molejos' => 0.0, 'molinhas' => 0.0, 'itens' => [], 'semEstrutura' => 0];
                $f = &$empresas[$l][$e];
                $f['molejos']  += $r['molejos'];
                $f['molinhas'] += $r['molinhas'];
                $ki = $r['cod_item'] . '|' . ($r['mascara'] ?? '') . '|' . ($r['molinha_cod'] ?? '');
                if (!isset($f['itens'][$ki])) {
                    $f['itens'][$ki] = ['cod_item' => $r['cod_item'], 'desc' => $r['desc'], 'mascara' => $r['mascara'],
                                        'molinha' => $r['molinha'], 'por_unid' => $r['por_unid'], 'molejos' => 0.0, 'molinhas' => 0.0];
                    if ($r['molinha'] === null) $f['semEstrutura']++;
                }
                $f['itens'][$ki]['molejos']  += $r['molejos'];
                $f['itens'][$ki]['molinhas'] += $r['molinhas'];
                unset($f);

                if ($r['molinha'] !== null) {
                    $tipos[$r['molinha_cod']] ??= ['cod' => $r['molinha_cod'], 'molinha' => $r['molinha'], 'P' => 0.0, 'C' => 0.0];
                    $tipos[$r['molinha_cod']][$l] += $r['molinhas'];
                }
            }
        }

        foreach ($empresas as &$lado) {
            ksort($lado);
            foreach ($lado as &$f) {
                $f['itens'] = array_values($f['itens']);
                usort($f['itens'], fn($a, $b) => [$b['molinhas'], $b['molejos']] <=> [$a['molinhas'], $a['molejos']]);
            }
            unset($f);
            $lado = array_values($lado);
        }
        unset($lado);
        $tipos = array_values($tipos);
        usort($tipos, fn($a, $b) => ($b['P'] + $b['C']) <=> ($a['P'] + $a['C']));

        $listaDias = [];
        foreach ($dias as $dia => $v) {
            $listaDias[] = ['dia' => $dia, 'P' => $v['P'] ?? (object) [], 'C' => $v['C'] ?? (object) []];
        }

        return ['meses' => $meses, 'empresaEstrutura' => M::EMPRESA_ESTRUTURA, 'fabricas' => M::FABRICAS,
                'dias' => $listaDias, 'producao' => $empresas['P'], 'consumo' => $empresas['C'], 'tipos' => $tipos];
    }
}
