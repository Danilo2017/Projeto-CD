<?php

namespace src\models\Industrial;

use core\Database;
use src\utils\DashboardCache;

/**
 * Peças Produzidas por Colaborador (porte do painel Qlik) — somente leitura.
 *
 * Peças: mesma regra da FOCCO3I.VW_PROD_IND_SINTETICO (movimentos EPP - PPE x 1/fator de conversão),
 *        filtrando a data antes de agrupar (a view inteira demora minutos).
 * Colaboradores: FOCCO3I.VW_GAZIN_FUNCIONARIOS, ativos, centro de custo de produção (TCC.TIPO_CC = 'PRO').
 * Segmento: pelo AGRUPADOR comercial (peças) e pelo prefixo do centro de custo (colaboradores).
 */
class PecasColaborador
{
    /** Segmentos do relatório: AGRUPADOR das peças e prefixo do centro de custo dos colaboradores. */
    public const SEGMENTOS = [
        'COLCHOES'  => ['nome' => 'Colchões',  'agrupador' => "AG.AGRUPADOR LIKE 'COLCHAO%'",   'cc' => '110'],
        'ESTOFADOS' => ['nome' => 'Estofados', 'agrupador' => "AG.AGRUPADOR LIKE 'ESTOFADO%'",  'cc' => '120'],
        // Conjugado entra junto com a Caixa Box (mesmos colaboradores, centro de custo 140)
        'CAIXA_BOX' => ['nome' => 'Caixa Box', 'agrupador' => "AG.AGRUPADOR IN ('BASE', 'CONJUGADO')", 'cc' => '140'],
        'ESPUMA'    => ['nome' => 'Espuma',    'agrupador' => "AG.AGRUPADOR = 'ESPUMA INDUSTRIAL'", 'cc' => '130'],
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
        $chave = "industrial_pecas_colab_{$chave}_{$anoMes}";
        $val = DashboardCache::get($chave);
        if ($val !== null) return $val;
        $val = $fn();
        DashboardCache::set($chave, $val, $anoMes >= date('Y-m') ? 1800 : 43200);
        return $val;
    }

    private static function validaMes(string $anoMes): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $anoMes, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
            throw new \InvalidArgumentException("Ano/mês inválido: $anoMes", 400);
        }
        return $anoMes;
    }

    /** Empresas industriais (código - cidade). */
    public static function empresas(): array
    {
        return self::cache('empresas', '0000-00', fn() => self::sql('industrial.pecasColaborador.listarEmpresas'));
    }

    /** Peças produzidas no mês por empresa e segmento (agrupadores fixos no SQL — iguais a SEGMENTOS). */
    public static function pecas(string $anoMes): array
    {
        self::validaMes($anoMes);
        return self::cache('pecas_seg', $anoMes, function () use ($anoMes) {
            $rows = self::sql('industrial.pecasColaborador.listarPecas', ['ano_mes' => "'$anoMes'"]);
            $out = [];
            foreach ($rows as $r) $out[$r['SEGMENTO']][(int) $r['EMPRESA']] = round((float) $r['QTD'], 2);
            return $out;
        });
    }

    /** Colaboradores ativos no mês por empresa e segmento (segmento pelo prefixo do centro de custo). */
    public static function colaboradores(string $anoMes): array
    {
        self::validaMes($anoMes);
        return self::cache('colab_seg', $anoMes, function () use ($anoMes) {
            $rows = self::sql('industrial.pecasColaborador.listarColaboradores', ['anomes' => (int) str_replace('-', '', $anoMes)]);
            $out = [];
            foreach ($rows as $r) $out[$r['SEGMENTO']][(int) $r['EMPR_ID']] = (int) $r['QTD'];
            return $out;
        });
    }
}
