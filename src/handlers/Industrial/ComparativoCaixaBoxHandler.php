<?php

namespace src\handlers\Industrial;

use src\models\Industrial\ComparativoCaixaBox as M;

class ComparativoCaixaBoxHandler
{
    private static function div(float $a, float $b): ?float
    {
        return $b > 0 ? $a / $b : null;
    }

    /** Indicadores (mesmas fórmulas da planilha). */
    private static function indicadores(array $r, ?float $meta): array
    {
        return $r + [
            'ggf_fat'   => self::div($r['ggf'], $r['faturamento']),     // GGF / Faturamento
            'meta'      => $meta,                                        // Meta A3
            'ggf_peca'  => self::div($r['ggf'], $r['pecas']),           // GGF / Peças
            'pecas_col' => self::div($r['pecas'], $r['colab']),         // Peças / funcionários
            'fat_col'   => self::div($r['faturamento'], $r['colab']),   // Faturamento / colaborador
        ];
    }

    /** Um mês de um segmento: indicadores por filial + total. */
    public static function mes(array $p): array
    {
        $seg   = (string) ($p['segmento'] ?? 'CAIXA_BOX');
        $mes   = (string) ($p['mes'] ?? '');
        $dados = M::mes($seg, $mes);

        $nomes = [];
        foreach (M::empresas() as $e) {
            $cidade = preg_replace_callback('/ (De|Da|Do|Das|Dos) /', fn($m) => ' ' . strtolower($m[1]) . ' ', (string) $e['CIDADE']);
            $nomes[(int) $e['ID']] = $e['COD_EMP'] . ' - ' . $cidade;
        }

        $filiais = [];
        $tot = ['faturamento' => 0.0, 'ggf' => 0.0, 'colab' => 0, 'pecas' => 0.0];
        foreach ($dados as $e => $r) {
            $filiais[] = ['empresa' => $e, 'nome' => $nomes[$e] ?? (string) $e] + self::indicadores($r, M::meta($seg, $e));
            foreach ($tot as $k => $_) $tot[$k] += $r[$k];
        }
        // Meta do total: metas das filiais ponderadas pelo faturamento
        $metaTot = null;
        if ($seg === 'CAIXA_BOX' && $tot['faturamento'] > 0) {
            $metaTot = array_sum(array_map(fn($f) => $f['meta'] * $f['faturamento'], $filiais)) / $tot['faturamento'];
        }

        return ['segmento' => $seg, 'mes' => $mes, 'filiais' => $filiais, 'total' => self::indicadores($tot, $metaTot)];
    }
}
