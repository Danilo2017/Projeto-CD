<?php

namespace src\models\Industrial;

use src\utils\DashboardCache;

/**
 * Comparativo Filiais (dashboard) — porte da planilha "comparativo filiais gazin qlik".
 * Usa os dados dos relatórios já criados (só lê, não altera nenhum deles):
 *  - Faturamento:   FaturamentoColaborador::faturamento()  (faturamento líquido por agrupador)
 *  - Peças:         PecasColaborador::pecas()              (produção EPP − PPE por agrupador)
 *  - GGF:           GgfColaborador::ggf()                  (custos produtivos por centro de custo)
 *  - Colaboradores: GgfColaborador::colaboradores()        (ativos por centro de custo de produção)
 *
 * Premissas da planilha para a Caixa Box (bloco TOTAL CAIXA BOX):
 *  - GGF e colaboradores das fábricas de molas somam na filial que atendem: 10 → 1 Douradina, 11 → 3 Candelária.
 *  - Meta A3 (GGF/Faturamento): Douradina 7,8%, Elói Mendes 6,5%, demais 7,5%.
 */
class ComparativoCaixaBox
{
    public const SEGMENTOS = [
        'CAIXA_BOX' => 'Caixa Box',
        'COLCHOES'  => 'Colchões',
        'ESTOFADOS' => 'Estofados',
        'ESPUMA'    => 'Espuma',
    ];

    /** Fábrica de molas => filial onde o GGF e os colaboradores da Caixa Box são somados. */
    public const MOLAS_PARA_FILIAL = [10 => 1, 11 => 3];

    /** Meta A3 de GGF/Faturamento da Caixa Box por filial (premissa da planilha). */
    public const META_A3 = [1 => 0.078, 13 => 0.065];
    public const META_A3_PADRAO = 0.075;

    /** Meta só existe na planilha para a Caixa Box. */
    public static function meta(string $segmento, int $empr): ?float
    {
        return $segmento === 'CAIXA_BOX' ? (self::META_A3[$empr] ?? self::META_A3_PADRAO) : null;
    }

    /** Valores do mês por filial: faturamento, ggf, colab, pecas. */
    public static function mes(string $segmento, string $anoMes): array
    {
        if (!isset(self::SEGMENTOS[$segmento])) throw new \InvalidArgumentException('Segmento inválido', 400);
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $anoMes)) {
            throw new \InvalidArgumentException("Ano/mês inválido: $anoMes", 400);
        }
        $chave = "industrial_comparativo_{$segmento}_$anoMes";
        $val = DashboardCache::get($chave);
        if ($val !== null) return $val;

        $fat   = FaturamentoColaborador::faturamento($anoMes)[$segmento] ?? [];
        $pecas = PecasColaborador::pecas($anoMes)[$segmento] ?? [];
        $ggf   = GgfColaborador::ggf($anoMes)[$segmento] ?? [];
        $colab = GgfColaborador::colaboradores($anoMes)[$segmento] ?? [];

        $molas = $segmento === 'CAIXA_BOX' ? self::MOLAS_PARA_FILIAL : [];
        $out = [];
        $soma = function (int $e, string $campo, float $v) use (&$out, $molas) {
            $e = $molas[$e] ?? $e;
            $out[$e] ??= ['faturamento' => 0.0, 'ggf' => 0.0, 'colab' => 0, 'pecas' => 0.0];
            $out[$e][$campo] += $v;
        };
        foreach ($fat as $e => $v)   if (!isset($molas[$e])) $soma($e, 'faturamento', (float) $v);
        foreach ($pecas as $e => $v) if (!isset($molas[$e])) $soma($e, 'pecas', (float) $v);
        foreach ($ggf as $e => $v)   $soma($e, 'ggf', (float) $v);
        foreach ($colab as $e => $v) $soma($e, 'colab', (int) $v);

        // Filial só entra com faturamento ou peças do segmento (sem isso os indicadores não têm base)
        $out = array_filter($out, fn($r) => $r['faturamento'] > 0 || $r['pecas'] > 0);
        ksort($out);

        // Mês atual 30 min; mês anterior 12h; meses mais antigos mudam pouco: 7 dias
        $ttl = $anoMes >= date('Y-m') ? 1800 : ($anoMes >= date('Y-m', strtotime('first day of last month')) ? 43200 : 604800);
        DashboardCache::set($chave, $out, $ttl);
        return $out;
    }

    public static function empresas(): array
    {
        return GgfColaborador::empresas();
    }
}
