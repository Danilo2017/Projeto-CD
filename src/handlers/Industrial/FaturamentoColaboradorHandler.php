<?php

namespace src\handlers\Industrial;

use src\models\Industrial\FaturamentoColaborador as M;

class FaturamentoColaboradorHandler
{
    private const MAX_MESES = 12;

    /** Meses do intervalo Ano/Mês inicial..final. */
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

    private static function porColab(float $valor, int $colab): ?float
    {
        return $colab > 0 ? round($valor / $colab, 2) : null;
    }

    public static function listar(array $p): array
    {
        $meses = self::meses((string) ($p['ini'] ?? ''), (string) ($p['fim'] ?? ''));

        $nomes = [];
        foreach (M::empresas() as $e) {
            $cidade = preg_replace_callback('/ (De|Da|Do|Das|Dos) /', fn($m) => ' ' . strtolower($m[1]) . ' ', (string) $e['CIDADE']);
            $nomes[(int) $e['ID']] = $e['COD_EMP'] . ' - ' . $cidade;
        }

        $fat = $colab = [];
        foreach ($meses as $m) {
            $fat[$m]   = M::faturamento($m);
            $colab[$m] = M::colaboradores($m);
        }

        $segmentos = [];
        foreach (M::SEGMENTOS as $k => $s) {
            // Linhas: empresas com faturamento do segmento em algum mês
            $emps = [];
            foreach ($meses as $m) foreach (array_keys($fat[$m][$k] ?? []) as $e) $emps[$e] = true;
            ksort($emps);

            $linhas = [];
            $tot = array_fill_keys($meses, ['faturamento' => 0.0, 'colab' => 0]);
            foreach (array_keys($emps) as $e) {
                $valores = [];
                foreach ($meses as $m) {
                    $vf = (float) ($fat[$m][$k][$e] ?? 0);
                    $nc = (int) ($colab[$m][$k][$e] ?? 0);
                    $valores[$m] = ['faturamento' => $vf, 'colab' => $nc, 'por' => self::porColab($vf, $nc)];
                    $tot[$m]['faturamento'] += $vf;
                    $tot[$m]['colab'] += $nc;
                }
                $linhas[] = ['empresa' => $e, 'nome' => $nomes[$e] ?? (string) $e, 'meses' => $valores];
            }
            foreach ($tot as $m => $t) $tot[$m]['por'] = self::porColab($t['faturamento'], $t['colab']);

            $segmentos[] = ['chave' => $k, 'nome' => $s['nome'], 'linhas' => $linhas, 'totais' => $tot];
        }

        return ['meses' => $meses, 'segmentos' => $segmentos];
    }
}
