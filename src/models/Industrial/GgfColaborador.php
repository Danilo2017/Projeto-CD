<?php

namespace src\models\Industrial;

use core\Database;
use src\utils\DashboardCache;

/**
 * GGF por Colaborador — somente leitura.
 *
 * GGF: FOCCO3I.VW_MLC_CUSTOS_PRODUTIVOS, SUM(VALOR) por empresa e centro de custo no mês (MES_ANO = 1º dia).
 * Colaboradores: FOCCO3I.VW_GAZIN_FUNCIONARIOS, ativos, centro de custo de produção (TCC.TIPO_CC = 'PRO').
 * Segmento: pelo prefixo do centro de custo, nos dois casos.
 */
class GgfColaborador
{
    public const SEGMENTOS = [
        'COLCHOES'  => ['nome' => 'Colchões',  'cc' => '110'],
        'ESTOFADOS' => ['nome' => 'Estofados', 'cc' => '120'],
        'CAIXA_BOX' => ['nome' => 'Caixa Box', 'cc' => '140'],
        'ESPUMA'    => ['nome' => 'Espuma',    'cc' => '130'],
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
        $chave = "industrial_ggf_colab_{$chave}_{$anoMes}";
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

    /** Empresas (código - cidade). */
    public static function empresas(): array
    {
        return self::cache('empresas', '0000-00', fn() => self::sql('industrial.ggfColaborador.listarEmpresas'));
    }

    /** GGF do mês por empresa e segmento (segmentos fixos no SQL: 110, 120, 130, 140 — iguais a SEGMENTOS). */
    public static function ggf(string $anoMes): array
    {
        self::validaMes($anoMes);
        return self::cache('ggf', $anoMes, function () use ($anoMes) {
            $rows = self::sql('industrial.ggfColaborador.listarGgf', ['ano_mes' => "'$anoMes'"]);
            $out = [];
            foreach ($rows as $r) $out[$r['SEGMENTO']][(int) $r['EMPR_ID']] = round((float) $r['VALOR'], 2);
            return $out;
        });
    }

    /** Colaboradores ativos no mês por empresa e segmento (segmento pelo prefixo do centro de custo). */
    public static function colaboradores(string $anoMes): array
    {
        self::validaMes($anoMes);
        return self::cache('colab', $anoMes, function () use ($anoMes) {
            $rows = self::sql('industrial.ggfColaborador.listarColaboradores', ['anomes' => (int) str_replace('-', '', $anoMes)]);
            $out = [];
            foreach ($rows as $r) $out[$r['SEGMENTO']][(int) $r['EMPR_ID']] = (int) $r['QTD'];
            return $out;
        });
    }
}
