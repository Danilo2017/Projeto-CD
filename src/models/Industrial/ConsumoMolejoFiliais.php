<?php

namespace src\models\Industrial;

use core\Database;
use src\utils\DashboardCache;

/**
 * Molejo: Produção (empresas 10 e 16) x Consumo nas Filiais, por dia, convertido em molinhas — somente leitura.
 *
 * Produção: molejos ("MOLEJO...") produzidos nas fábricas de molas (10, 16): EPP − PPE pela DT_SIST,
 *   quantidade direta (mesma regra do SQL de produção do relatório Consumo de Molinha).
 * Consumo nas filiais: movimentos REP (Requisição Planejada, saída para as ordens de produção) dos molejos,
 *   pela DT_SIST, quantidade direta.
 * Produção em molinhas: a molinha de fato produzida (itens "MOLA ENSACADA INDIVIDUALMENTE", EPP − PPE), não molejo x estrutura.
 * Consumo em molinhas: molejos x molinhas por unidade da estrutura da EMPRESA 10 (FOCCO3I.TGAZIN_ADMIN_TANQUE_MOLA,
 *   linhas "MOLA ENSACADA INDIVIDUALMENTE", QTDE_TOT), pelo item e máscara; sem a máscara, a estrutura sem máscara.
 *   A mesma estrutura vale para a produção e para o consumo, para os dois lados serem comparáveis.
 * Molejo sem molinha ensacada na estrutura (Bonnel, Fio Contínuo) conta como molejo e aparece marcado no detalhe.
 */
class ConsumoMolejoFiliais
{
    /** Empresa cuja estrutura define as molinhas por molejo (fixa nos SQLs industrial.consumoMolejoFiliais.*). */
    public const EMPRESA_ESTRUTURA = 10;

    /** Fábricas de molas, lado da produção (fixas nos SQLs industrial.consumoMolejoFiliais.*). */
    public const FABRICAS = [10, 16];

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
        $val = DashboardCache::get('industrial_molejo_fil_empresas');
        if ($val !== null) return $val;
        $val = self::sql('industrial.consumoMolejoFiliais.listarEmpresas');
        DashboardCache::set('industrial_molejo_fil_empresas', $val, 43200);
        return $val;
    }

    /**
     * Movimentos de molejo do mês por empresa, dia, item e máscara:
     *  lado 'P' = produção EPP − PPE nas fábricas (10, 16); lado 'C' = consumo REP nas filiais.
     *  A produção de molejo nas próprias filiais não entra neste relatório.
     * O mês vai como texto já validado (não como bind): com bind o Oracle não usa o índice de data do movimento.
     */
    private static function movimentos(string $anoMes): array
    {
        return self::sql('industrial.consumoMolejoFiliais.listarMovimentos', ['ano_mes' => "'$anoMes'"]);
    }

    /** Molinhas por molejo pela estrutura da empresa 10: [cod_item|masc] => [[cod, desc, qtde], ...]. */
    private static function estrutura(): array
    {
        $chave = 'industrial_molejo_fil_estrutura_' . self::EMPRESA_ESTRUTURA;
        $val = DashboardCache::get($chave);
        if ($val !== null) return $val;
        $rows = self::sql('industrial.consumoMolejoFiliais.listarEstrutura');
        $val = [];
        foreach ($rows as $r) {
            $val[$r['PAI_COD_ITEM'] . '|' . ($r['PAI_TMASC_ITEM_ID'] ?? '')][] =
                ['cod' => $r['COD_ITEM'], 'desc' => trim(preg_replace('/\s+/', ' ', $r['DESC_TECNICA'])), 'qtde' => (float) $r['QTDE_TOT']];
        }
        DashboardCache::set($chave, $val, 43200);
        return $val;
    }

    /**
     * Mês: uma linha por lado x empresa x dia x molejo (x tipo de molinha).
     * 'molejos' vem só na primeira linha de cada molejo (molejo com 2 tipos de molinha não conta 2 vezes).
     */
    public static function mes(string $anoMes): array
    {
        self::validaMes($anoMes);
        $chave = "industrial_molejo_dia6_$anoMes";
        $val = DashboardCache::get($chave);
        if ($val !== null) return $val;

        $bom = self::estrutura();
        $out = [];
        $movs = self::movimentos($anoMes);
        // Consumo: a filial requisita o molejo ENROLADO para fazer o S/BORDA e depois requisita o S/BORDA para o colchão (mesma quantidade nos dois).
        // Para não somar o mesmo molejo duas vezes: se a filial consumiu ENROLADO daquele tamanho, vale o ENROLADO; se não, valem os demais (S/BORDA etc.).
        $chaveTam = fn(array $c): ?string => preg_match('/(\d+X\d+X\d+MM)/i', (string) $c['DESC_TECNICA'], $mm) ? $c['EMPRESA'] . '|' . strtoupper($mm[1]) : null;
        $temEnrolado = [];
        foreach ($movs as $c) {
            if ($c['LADO'] === 'C' && abs((float) $c['QTD']) >= 0.0001 && stripos($c['DESC_TECNICA'], 'ENROLADO') !== false && ($k = $chaveTam($c)) !== null) $temEnrolado[$k] = true;
        }
        foreach ($movs as $c) {
            $qtd = (float) $c['QTD'];
            if (abs($qtd) < 0.0001) continue;
            if ($c['LADO'] === 'C' && stripos($c['DESC_TECNICA'], 'ENROLADO') === false && ($k = $chaveTam($c)) !== null && isset($temEnrolado[$k])) continue;
            $item = ['lado' => $c['LADO'], 'empresa' => (int) $c['EMPRESA'], 'dia' => $c['DIA'], 'cod_item' => $c['COD_ITEM'],
                     'desc' => trim($c['DESC_TECNICA']), 'mascara' => $c['MASCARA']];
            if ($c['LADO'] === 'P') {   // produção: as molinhas vêm da produção direta da molinha (abaixo), não do molejo x estrutura
                $out[] = $item + ['molejos' => $qtd, 'molinha_cod' => null, 'molinha' => '(molinhas contadas pela produção da própria molinha)', 'por_unid' => null, 'molinhas' => 0.0];
                continue;
            }
            $mol = $bom[$c['COD_ITEM'] . '|' . ($c['ID_MASC'] ?? '')] ?? $bom[$c['COD_ITEM'] . '|'] ?? null;
            if (!$mol) {
                $out[] = $item + ['molejos' => $qtd, 'molinha_cod' => null, 'molinha' => null, 'por_unid' => null, 'molinhas' => 0.0];
                continue;
            }
            foreach (array_values($mol) as $i => $m) {
                $out[] = $item + ['molejos' => $i === 0 ? $qtd : 0.0, 'molinha_cod' => $m['cod'], 'molinha' => $m['desc'],
                                  'por_unid' => $m['qtde'], 'molinhas' => $qtd * $m['qtde']];
            }
        }
        // Produção das fábricas em molinhas = molinha de fato produzida (EPP − PPE do item "MOLA ENSACADA INDIVIDUALMENTE")
        foreach (self::sql('industrial.consumoMolejoFiliais.listarMolinhasProduzidas', ['ano_mes' => "'$anoMes'"]) as $c) {
            $qtd = (float) $c['QTD'];
            if (abs($qtd) < 0.0001) continue;
            $out[] = ['lado' => 'P', 'empresa' => (int) $c['EMPRESA'], 'dia' => $c['DIA'], 'cod_item' => $c['COD_ITEM'], 'desc' => trim($c['DESC_TECNICA']),
                     'mascara' => $c['MASCARA'], 'molejos' => 0.0, 'molinha_cod' => $c['COD_ITEM'], 'molinha' => trim($c['DESC_TECNICA']), 'por_unid' => 1.0, 'molinhas' => $qtd];
        }
        DashboardCache::set($chave, $out, self::ttl($anoMes));
        return $out;
    }
}
