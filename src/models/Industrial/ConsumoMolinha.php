<?php

namespace src\models\Industrial;

use core\Database;
use src\utils\DashboardCache;

/**
 * Consumo de Molinha por Filial — somente leitura.
 *
 * Produção: SQL de produção informado pelo usuário — mesmos joins da VW_PROD_IND_SINTETICO, quantidade
 *           EPP − PPE SEM fator de conversão, SEM filtro de classificação e de descrição, pela DT_SIST,
 *           empresas de molas (10, 16). Conferido item a item com o SQL original (set/26: 237 itens, 0 diferenças).
 * Molinhas produzidas: itens "MOLA ENSACADA INDIVIDUALMENTE..." (a própria molinha).
 * Consumidas nos molejos: itens "MOLEJO..." x molinhas por unidade da FOCCO3I.TGAZIN_ADMIN_TANQUE_MOLA
 *           (linhas "MOLA ENSACADA INDIVIDUALMENTE", QTDE_TOT já multiplicado pelos níveis).
 *           Usa a estrutura da máscara produzida quando existe; senão, a estrutura sem máscara.
 * Semi-acabados, cordões e demais itens aparecem no detalhe, mas não somam no consumo
 * (a molinha deles seria contada de novo no molejo).
 */
class ConsumoMolinha
{
    /** Fábricas de molas — a mesma lista está fixa nos SQLs industrial.consumoMolinha.* da GAZIN_SQLS. */
    public const FILIAIS = [10, 16];

    /** Executa um SQL da FOCCO3I.GAZIN_SQLS (registro em database/gazin-sqls/2026-11-05-industrial.php). */
    private static function sql(string $idsql, array $params = []): array
    {
        $r = Database::switchParams('focco', $params, $idsql, true, false);
        if (!empty($r['error'])) throw new \RuntimeException("Falha no SQL $idsql: " . $r['error']);
        return is_array($r['retorno']) ? $r['retorno'] : [];
    }

    private static function validaMes(string $anoMes): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $anoMes)) {
            throw new \InvalidArgumentException("Ano/mês inválido: $anoMes", 400);
        }
    }

    /** Mês atual 30 min; mês anterior 12h; meses mais antigos 7 dias. */
    private static function ttl(string $anoMes): int
    {
        return $anoMes >= date('Y-m') ? 1800 : ($anoMes >= date('Y-m', strtotime('first day of last month')) ? 43200 : 604800);
    }

    public static function empresas(): array
    {
        $val = DashboardCache::get('industrial_molinha_empresas');
        if ($val !== null) return $val;
        $val = self::sql('industrial.consumoMolinha.listarEmpresas');
        DashboardCache::set('industrial_molinha_empresas', $val, 43200);
        return $val;
    }

    /** Tipo do item pela descrição técnica. */
    public static function tipo(string $desc): string
    {
        $d = ltrim(strtoupper($desc));
        if (str_starts_with($d, 'MOLA ENSACADA INDIVIDUALMENTE')) return 'MOLINHA';
        if (str_starts_with($d, 'MOLEJO')) return 'MOLEJO';
        return 'OUTRO';
    }

    /**
     * Produção do mês (DT_SIST) por empresa, item e máscara, quantidade sem fator de conversão.
     * O mês vai como texto já validado (não como bind): com bind o Oracle não usa o índice de data do movimento.
     */
    private static function producao(string $anoMes): array
    {
        return self::sql('industrial.consumoMolinha.listarProducao', ['ano_mes' => "'$anoMes'"]);
    }

    /** Molinhas por unidade de cada item pai: [empr|cod_item|masc] => [[cod, desc, qtde_tot], ...]. */
    private static function estrutura(): array
    {
        $val = DashboardCache::get('industrial_molinha_estrutura');
        if ($val !== null) return $val;
        $rows = self::sql('industrial.consumoMolinha.listarEstrutura');
        $val = [];
        foreach ($rows as $r) {
            $val[$r['EMPR_ID'] . '|' . $r['PAI_COD_ITEM'] . '|' . ($r['PAI_TMASC_ITEM_ID'] ?? '')][] =
                ['cod' => $r['COD_ITEM'], 'desc' => trim(preg_replace('/\s+/', ' ', $r['DESC_TECNICA'])), 'qtde' => (float) $r['QTDE_TOT']];
        }
        DashboardCache::set('industrial_molinha_estrutura', $val, 43200);
        return $val;
    }

    /**
     * Mês: linhas por empresa x item produzido (x tipo de molinha para os molejos).
     *  tipo MOLINHA -> produzidas (a própria molinha); tipo MOLEJO -> consumo pela estrutura; OUTRO -> só detalhe.
     */
    public static function mes(string $anoMes): array
    {
        self::validaMes($anoMes);
        $chave = "industrial_molinha_mes3_$anoMes";
        $val = DashboardCache::get($chave);
        if ($val !== null) return $val;

        $bom = self::estrutura();
        $out = [];
        foreach (self::producao($anoMes) as $p) {
            $qtd = (float) $p['QTD'];
            if (abs($qtd) < 0.0001) continue;
            $tipo = self::tipo((string) $p['DESC_TECNICA']);
            $item = ['empresa' => (int) $p['EMPRESA'], 'tipo' => $tipo, 'cod_item' => $p['COD_ITEM'], 'desc' => trim($p['DESC_TECNICA']),
                     'mascara' => $p['MASCARA'], 'produzido' => $qtd, 'produzidas' => 0.0, 'consumo' => 0.0];

            if ($tipo === 'MOLINHA') {
                $out[] = array_merge($item, ['molinha_cod' => $p['COD_ITEM'], 'molinha' => trim(preg_replace('/\s+/', ' ', $p['DESC_TECNICA'])),
                                             'por_unid' => 1.0, 'produzidas' => $qtd]);
                continue;
            }
            $base = $p['EMPRESA'] . '|' . $p['COD_ITEM'] . '|';
            $mol  = $tipo === 'MOLEJO' ? ($bom[$base . ($p['ID_MASC'] ?? '')] ?? $bom[$base] ?? null) : null;
            if (!$mol) { $out[] = array_merge($item, ['molinha_cod' => null, 'molinha' => null, 'por_unid' => null]); continue; }
            foreach ($mol as $m) {
                $out[] = array_merge($item, ['molinha_cod' => $m['cod'], 'molinha' => $m['desc'], 'por_unid' => $m['qtde'],
                                             'consumo' => $qtd * $m['qtde']]);
            }
        }
        DashboardCache::set($chave, $out, self::ttl($anoMes));
        return $out;
    }
}
