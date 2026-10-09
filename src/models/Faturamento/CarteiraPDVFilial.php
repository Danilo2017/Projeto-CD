<?php

namespace src\models\Faturamento;

use core\Database;

class CarteiraPDVFilial
{
    private static function validaDatas(string $di, string $df): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $di)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $df);
    }

    private static function df(string $d): string
    {
        return "TO_DATE('$d','YYYY-MM-DD')";
    }

    private static function exec(string $sql): array
    {
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?? [];
    }

    private static function mcExpr(): string
    {
        return "NVL(t.PDV_VALOR_PEND_LIBERADO,0)
                        - NVL(t.FRETE,0)
                        - NVL(t.PDV_COMISSAO,0)
                        - NVL(t.PDV_VALOR_VPC,0)
                        - NVL(t.PDV_VALOR_ICMS,0)
                        - NVL(t.PDV_VALOR_PIS,0)
                        - NVL(t.PDV_VALOR_COFINS,0)
                        - NVL(t.PDV_VALOR_PEND_LIBERADO,0) * (NVL(d.PERC_DESPESAS_MC, 0) / 100)
                        - NVL(t.PDV_CUSMED,0)";
    }

    private static function joinDesp(): string
    {
        return "LEFT JOIN GAZIN_DESPESAS_MC d ON d.FILIAL = t.EMPR_ID";
    }

    private static function cteTotais(string $ddi, string $ddf): string
    {
        return "TOTAIS AS (
            SELECT EMPR_ID, NVL(SUM(PDV_VALOR_PENDENTE), 0) AS TOTAL
            FROM FOCCO3I.TGAZIN_CARTEIRA_PDV
            WHERE DT_EMIS BETWEEN $ddi AND ($ddf + 1 - 1/86400)
            GROUP BY EMPR_ID
        )";
    }

    // ── Tanques × Filiais ─────────────────────────────────────────
    public static function getTanques(string $di, string $df): array
    {
        if (!self::validaDatas($di, $df)) return [];
        $ddi = self::df($di);
        $ddf = self::df($df);
        $mc  = self::mcExpr();

        $sql = "WITH " . self::cteTotais($ddi, $ddf) . "
                SELECT t.COD_TANQUE, MAX(t.DESC_TANQUE) AS DESC_TANQUE, t.EMPR_ID,
                    SUM(t.PDV_VALOR_PENDENTE)                              AS VLR_PENDENTE,
                    ROUND(SUM(t.PDV_VALOR_PENDENTE) * 100
                          / NULLIF(tot.TOTAL, 0), 2)                      AS PERC_PARTICIPACAO,
                    SUM($mc)                                               AS MC_VALOR,
                    ROUND(SUM($mc)
                          / NULLIF(SUM(t.PDV_VALOR_PEND_LIBERADO), 0) * 100, 2) AS MC_PERC,
                    ROUND(SUM(NVL(t.PDV_VALOR_PEND_LIBERADO,0))
                          / NULLIF(SUM(NVL(t.UEP,0)), 0), 4)             AS TAXA
                FROM FOCCO3I.TGAZIN_CARTEIRA_PDV t
                JOIN TOTAIS tot ON tot.EMPR_ID = t.EMPR_ID
                " . self::joinDesp() . "
                WHERE t.DT_EMIS BETWEEN $ddi AND ($ddf + 1 - 1/86400)
                GROUP BY t.COD_TANQUE, t.EMPR_ID, tot.TOTAL
                ORDER BY LPAD(t.COD_TANQUE, 10, '0'), t.EMPR_ID";

        return self::exec($sql);
    }

    // ── Totais por Filial (linha rodapé) ──────────────────────────
    public static function getTotais(string $di, string $df): array
    {
        if (!self::validaDatas($di, $df)) return [];
        $ddi = self::df($di);
        $ddf = self::df($df);
        $mc  = self::mcExpr();

        $sql = "SELECT t.EMPR_ID,
                    SUM(t.PDV_VALOR_PENDENTE)                              AS VLR_PENDENTE,
                    100                                                    AS PERC_PARTICIPACAO,
                    SUM($mc)                                               AS MC_VALOR,
                    ROUND(SUM($mc)
                          / NULLIF(SUM(t.PDV_VALOR_PEND_LIBERADO), 0) * 100, 2) AS MC_PERC,
                    ROUND(SUM(NVL(t.PDV_VALOR_PEND_LIBERADO,0))
                          / NULLIF(SUM(NVL(t.UEP,0)), 0), 4)             AS TAXA
                FROM FOCCO3I.TGAZIN_CARTEIRA_PDV t
                " . self::joinDesp() . "
                WHERE t.DT_EMIS BETWEEN $ddi AND ($ddf + 1 - 1/86400)
                GROUP BY t.EMPR_ID
                ORDER BY t.EMPR_ID";

        return self::exec($sql);
    }

    // ── Sub Grupos × Filiais (por tanque) ─────────────────────────
    public static function getSubGrupos(string $di, string $df, string $tanque): array
    {
        if (!self::validaDatas($di, $df)) return [];
        $ddi = self::df($di);
        $ddf = self::df($df);
        $tq  = str_replace("'", "''", $tanque);
        $mc  = self::mcExpr();

        $sql = "WITH " . self::cteTotais($ddi, $ddf) . ",
                BASE AS (
                    SELECT t.SUB_GRUPO_ITEM, t.FAMILIA_ITEM, t.SUB_FAMILIA_ITEM, t.EMPR_ID,
                        SUM(t.PDV_VALOR_PENDENTE)                          AS VLR_PENDENTE,
                        ROUND(SUM(t.PDV_VALOR_PENDENTE) * 100
                              / NULLIF(tot.TOTAL, 0), 2)                  AS PERC_PARTICIPACAO,
                        SUM($mc)                                           AS MC_VALOR,
                        ROUND(SUM($mc)
                              / NULLIF(SUM(t.PDV_VALOR_PEND_LIBERADO), 0) * 100, 2) AS MC_PERC,
                        ROUND(SUM(NVL(t.PDV_VALOR_PEND_LIBERADO,0))
                              / NULLIF(SUM(NVL(t.UEP,0)), 0), 4)         AS TAXA,
                        SUM(SUM(t.PDV_VALOR_PENDENTE)) OVER (
                            PARTITION BY t.SUB_GRUPO_ITEM, t.FAMILIA_ITEM, t.SUB_FAMILIA_ITEM
                        )                                                  AS GRUPO_TOTAL
                    FROM FOCCO3I.TGAZIN_CARTEIRA_PDV t
                    JOIN TOTAIS tot ON tot.EMPR_ID = t.EMPR_ID
                    " . self::joinDesp() . "
                    WHERE t.DT_EMIS BETWEEN $ddi AND ($ddf + 1 - 1/86400)
                      AND t.COD_TANQUE = '$tq'
                    GROUP BY t.SUB_GRUPO_ITEM, t.FAMILIA_ITEM, t.SUB_FAMILIA_ITEM,
                             t.EMPR_ID, tot.TOTAL
                )
                SELECT SUB_GRUPO_ITEM, FAMILIA_ITEM, SUB_FAMILIA_ITEM, EMPR_ID,
                       VLR_PENDENTE, PERC_PARTICIPACAO, MC_VALOR, MC_PERC, TAXA
                FROM BASE
                ORDER BY GRUPO_TOTAL DESC, SUB_GRUPO_ITEM, FAMILIA_ITEM, SUB_FAMILIA_ITEM, EMPR_ID";

        return self::exec($sql);
    }

    // ── Itens × Filiais (por tanque + sub_grupo + familia + sub_familia) ──
    public static function getItens(
        string $di, string $df,
        string $tanque, string $sg, string $fam, string $sfam
    ): array {
        if (!self::validaDatas($di, $df)) return [];
        $ddi  = self::df($di);
        $ddf  = self::df($df);
        $tq   = str_replace("'", "''", $tanque);
        $sg   = str_replace("'", "''", $sg);
        $fam  = str_replace("'", "''", $fam);
        $sfam = str_replace("'", "''", $sfam);
        $mc   = self::mcExpr();

        $sql = "WITH " . self::cteTotais($ddi, $ddf) . ",
                BASE AS (
                    SELECT t.COD_ITEM, t.ITEM, t.EMPR_ID,
                        SUM(t.PDV_VALOR_PENDENTE)                          AS VLR_PENDENTE,
                        ROUND(SUM(t.PDV_VALOR_PENDENTE) * 100
                              / NULLIF(tot.TOTAL, 0), 2)                  AS PERC_PARTICIPACAO,
                        SUM($mc)                                           AS MC_VALOR,
                        ROUND(SUM($mc)
                              / NULLIF(SUM(t.PDV_VALOR_PEND_LIBERADO), 0) * 100, 2) AS MC_PERC,
                        ROUND(SUM(NVL(t.PDV_VALOR_PEND_LIBERADO,0))
                              / NULLIF(SUM(NVL(t.UEP,0)), 0), 4)         AS TAXA,
                        SUM(SUM(t.PDV_VALOR_PENDENTE)) OVER (
                            PARTITION BY t.COD_ITEM, t.ITEM
                        )                                                  AS ITEM_TOTAL
                    FROM FOCCO3I.TGAZIN_CARTEIRA_PDV t
                    JOIN TOTAIS tot ON tot.EMPR_ID = t.EMPR_ID
                    " . self::joinDesp() . "
                    WHERE t.DT_EMIS BETWEEN $ddi AND ($ddf + 1 - 1/86400)
                      AND t.COD_TANQUE = '$tq'
                      AND t.SUB_GRUPO_ITEM = '$sg'
                      AND t.FAMILIA_ITEM = '$fam'
                      AND t.SUB_FAMILIA_ITEM = '$sfam'
                    GROUP BY t.COD_ITEM, t.ITEM, t.EMPR_ID, tot.TOTAL
                )
                SELECT COD_ITEM, ITEM, EMPR_ID, VLR_PENDENTE, PERC_PARTICIPACAO,
                       MC_VALOR, MC_PERC, TAXA
                FROM BASE
                ORDER BY ITEM_TOTAL DESC, COD_ITEM, EMPR_ID";

        return self::exec($sql);
    }
}
