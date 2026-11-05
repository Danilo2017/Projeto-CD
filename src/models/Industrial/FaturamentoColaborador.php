<?php

namespace src\models\Industrial;

use core\Database;
use src\utils\DashboardCache;

/**
 * Faturamento por Colaborador — somente leitura.
 *
 * Faturamento: FOCCO3I.VGAZIN_FAT_COM_DEV_IMP_NW (mesmas regras do relatório de faturamento por agrupador):
 *   líquido = contábil saída − ST saída + contábil entrada − ST entrada,
 *   sem pedidos 28411905%, devoluções só do tipo de nota 5.
 * Colaboradores: FOCCO3I.VW_GAZIN_FUNCIONARIOS, ativos, centro de custo de produção (TCC.TIPO_CC = 'PRO').
 * Segmento: pelo AGRUPADOR (faturamento) e pelo prefixo do centro de custo (colaboradores).
 */
class FaturamentoColaborador
{
    public const SEGMENTOS = [
        'COLCHOES'  => ['nome' => 'Colchões',  'agrupador' => "V.AGRUPADOR LIKE 'COLCHAO%'",               'cc' => '110'],
        'ESTOFADOS' => ['nome' => 'Estofados', 'agrupador' => "V.AGRUPADOR LIKE 'ESTOFADO%'",              'cc' => '120'],
        // Conjugado entra junto com a Caixa Box (mesmos colaboradores, centro de custo 140)
        'CAIXA_BOX' => ['nome' => 'Caixa Box', 'agrupador' => "V.AGRUPADOR IN ('BASE', 'CONJUGADO')",      'cc' => '140'],
        'ESPUMA'    => ['nome' => 'Espuma',    'agrupador' => "V.AGRUPADOR = 'ESPUMA INDUSTRIAL'",         'cc' => '130'],
    ];

    /** Executa um SQL da FOCCO3I.GAZIN_SQLS (registro em database/gazin-sqls/2026-11-05-industrial.php). */
    private static function sql(string $idsql, array $params = []): array
    {
        $r = Database::switchParams('focco', $params, $idsql, true, false);
        if (!empty($r['error'])) throw new \RuntimeException("Falha no SQL $idsql: " . $r['error']);
        return is_array($r['retorno']) ? $r['retorno'] : [];
    }

    /** Mês fechado fica 12h em cache; o mês corrente, 30 min. */
    private static function cache(string $chave, string $anoMes, callable $fn): array
    {
        $chave = "industrial_fat_colab_{$chave}_{$anoMes}";
        $val = DashboardCache::get($chave);
        if ($val !== null) return $val;
        $val = $fn();
        DashboardCache::set($chave, $val, $anoMes >= date('Y-m') ? 1800 : 43200);
        return $val;
    }

    private static function validaMes(string $anoMes): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $anoMes)) {
            throw new \InvalidArgumentException("Ano/mês inválido: $anoMes", 400);
        }
    }

    /** Empresas industriais (código - cidade). */
    public static function empresas(): array
    {
        return self::cache('empresas', '0000-00', fn() => self::sql('industrial.faturamentoColaborador.listarEmpresas'));
    }

    /** Faturamento líquido do mês por empresa e segmento (agrupadores fixos no SQL — iguais a SEGMENTOS). */
    public static function faturamento(string $anoMes): array
    {
        self::validaMes($anoMes);
        return self::cache('fat', $anoMes, function () use ($anoMes) {
            $rows = self::sql('industrial.faturamentoColaborador.listarFaturamento', ['ano_mes' => "'$anoMes'"]);
            $out = [];
            foreach ($rows as $r) $out[$r['SEGMENTO']][(int) $r['EMPRESA']] = round((float) $r['VALOR'], 2);
            return $out;
        });
    }

    /** Colaboradores ativos no mês por empresa e segmento (segmento pelo prefixo do centro de custo). */
    public static function colaboradores(string $anoMes): array
    {
        self::validaMes($anoMes);
        return self::cache('colab', $anoMes, function () use ($anoMes) {
            $rows = self::sql('industrial.faturamentoColaborador.listarColaboradores', ['anomes' => (int) str_replace('-', '', $anoMes)]);
            $out = [];
            foreach ($rows as $r) $out[$r['SEGMENTO']][(int) $r['EMPR_ID']] = (int) $r['QTD'];
            return $out;
        });
    }
}
