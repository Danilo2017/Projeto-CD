<?php

namespace src\models\Faturamento;

use core\Database;

class CarteiraPDV
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

    private static function totalSub(string $ddi, string $ddf, ?int $emprId = null): string
    {
        $filtroEmpr = $emprId !== null ? "AND EMPR_ID = $emprId" : "";
        return "(SELECT NVL(SUM(PDV_VALOR_PENDENTE),0)
                   FROM FOCCO3I.TGAZIN_CARTEIRA_PDV
                  WHERE DT_EMIS BETWEEN $ddi AND ($ddf + 1 - 1/86400) $filtroEmpr)";
    }

    private static function measures(string $tot): string
    {
        $desp = "NVL(GAZIN_DESPESAS_MC.PERC_DESPESAS_MC, 0)";
        return "SUM(PDV_VALOR_PENDENTE)                                AS VLR_PENDENTE,
                    ROUND(SUM(PDV_VALOR_PENDENTE) * 100
                          / NULLIF($tot, 0), 2)                        AS PERC_PARTICIPACAO,
                    SUM(NVL(PDV_VALOR_PEND_LIBERADO,0)
                        - NVL(FRETE,0)
                        - NVL(PDV_COMISSAO,0)
                        - NVL(PDV_VALOR_VPC,0)
                        - NVL(PDV_VALOR_ICMS,0)
                        - NVL(PDV_VALOR_PIS,0)
                        - NVL(PDV_VALOR_COFINS,0)
                        - NVL(PDV_VALOR_PEND_LIBERADO,0) * ($desp / 100)
                        - NVL(PDV_CUSMED,0))                           AS MC_VALOR,
                    ROUND(SUM(
                        NVL(PDV_VALOR_PEND_LIBERADO,0)
                        - NVL(FRETE,0)
                        - NVL(PDV_COMISSAO,0)
                        - NVL(PDV_VALOR_VPC,0)
                        - NVL(PDV_VALOR_ICMS,0)
                        - NVL(PDV_VALOR_PIS,0)
                        - NVL(PDV_VALOR_COFINS,0)
                        - NVL(PDV_VALOR_PEND_LIBERADO,0) * ($desp / 100)
                        - NVL(PDV_CUSMED,0)
                    ) / NULLIF(SUM(PDV_VALOR_PEND_LIBERADO), 0) * 100, 2) AS MC_PERC,
                    ROUND(SUM(NVL(PDV_VALOR_PEND_LIBERADO,0))
                          / NULLIF(SUM(NVL(UEP,0)), 0), 4)             AS TAXA";
    }

    private static function joinDesp(): string
    {
        return "LEFT JOIN GAZIN_DESPESAS_MC ON TGAZIN_CARTEIRA_PDV.EMPR_ID = GAZIN_DESPESAS_MC.FILIAL";
    }

    private static function exec(string $sql): array
    {
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?? [];
    }

    // ── Nível 1: EMPR_ID ──────────────────────────────────────────
    public static function getNivel1(string $di, string $df): array
    {
        if (!self::validaDatas($di, $df)) return [];
        $ddi = self::df($di);
        $ddf = self::df($df);
        $tot = self::totalSub($ddi, $ddf);

        $sql = "SELECT EMPR_ID,
                    " . self::measures($tot) . "
                FROM FOCCO3I.TGAZIN_CARTEIRA_PDV
                " . self::joinDesp() . "
                WHERE DT_EMIS BETWEEN $ddi AND ($ddf + 1 - 1/86400)
                GROUP BY EMPR_ID
                ORDER BY EMPR_ID";

        return self::exec($sql);
    }

    // ── Nível 2: COD_TANQUE ───────────────────────────────────────
    public static function getNivel2(int $emprId, string $di, string $df): array
    {
        if (!self::validaDatas($di, $df)) return [];
        $ddi = self::df($di);
        $ddf = self::df($df);
        $tot = self::totalSub($ddi, $ddf, $emprId);

        $sql = "SELECT COD_TANQUE, MAX(DESC_TANQUE) AS DESC_TANQUE,
                    " . self::measures($tot) . "
                FROM FOCCO3I.TGAZIN_CARTEIRA_PDV
                " . self::joinDesp() . "
                WHERE EMPR_ID = $emprId
                  AND DT_EMIS BETWEEN $ddi AND ($ddf + 1 - 1/86400)
                GROUP BY COD_TANQUE
                ORDER BY LPAD(COD_TANQUE, 10, '0') ASC";

        return self::exec($sql);
    }

    // ── Nível 3: SUB_GRUPO_ITEM - FAMILIA_ITEM - SUB_FAMILIA_ITEM ─
    public static function getNivel3(int $emprId, string $di, string $df, string $tanque): array
    {
        if (!self::validaDatas($di, $df)) return [];
        $ddi = self::df($di);
        $ddf = self::df($df);
        $tot = self::totalSub($ddi, $ddf, $emprId);
        $tq  = str_replace("'", "''", $tanque);

        $sql = "SELECT SUB_GRUPO_ITEM, FAMILIA_ITEM, SUB_FAMILIA_ITEM,
                    " . self::measures($tot) . "
                FROM FOCCO3I.TGAZIN_CARTEIRA_PDV
                " . self::joinDesp() . "
                WHERE EMPR_ID = $emprId
                  AND COD_TANQUE = '$tq'
                  AND DT_EMIS BETWEEN $ddi AND ($ddf + 1 - 1/86400)
                GROUP BY SUB_GRUPO_ITEM, FAMILIA_ITEM, SUB_FAMILIA_ITEM
                ORDER BY SUM(PDV_VALOR_PENDENTE) DESC";

        return self::exec($sql);
    }

    // ── Nível 4: COD_ITEM / ITEM ──────────────────────────────────
    public static function getNivel4(int $emprId, string $di, string $df, string $tanque, string $subGrupo, string $familia, string $subFamilia): array
    {
        if (!self::validaDatas($di, $df)) return [];
        $ddi  = self::df($di);
        $ddf  = self::df($df);
        $tot  = self::totalSub($ddi, $ddf, $emprId);
        $tq   = str_replace("'", "''", $tanque);
        $sg   = str_replace("'", "''", $subGrupo);
        $fam  = str_replace("'", "''", $familia);
        $sfam = str_replace("'", "''", $subFamilia);

        $sql = "SELECT COD_ITEM, ITEM,
                    " . self::measures($tot) . "
                FROM FOCCO3I.TGAZIN_CARTEIRA_PDV
                " . self::joinDesp() . "
                WHERE EMPR_ID = $emprId
                  AND COD_TANQUE = '$tq'
                  AND SUB_GRUPO_ITEM = '$sg'
                  AND FAMILIA_ITEM = '$fam'
                  AND SUB_FAMILIA_ITEM = '$sfam'
                  AND DT_EMIS BETWEEN $ddi AND ($ddf + 1 - 1/86400)
                GROUP BY COD_ITEM, ITEM
                ORDER BY SUM(PDV_VALOR_PENDENTE) DESC";

        return self::exec($sql);
    }

    // ── Total Geral ───────────────────────────────────────────────
    public static function getTotalGeral(string $di, string $df): array
    {
        if (!self::validaDatas($di, $df)) return [];
        $ddi = self::df($di);
        $ddf = self::df($df);
        $desp = "NVL(GAZIN_DESPESAS_MC.PERC_DESPESAS_MC, 0)";

        $sql = "SELECT
                    SUM(PDV_VALOR_PENDENTE)                                AS VLR_PENDENTE,
                    100                                                    AS PERC_PARTICIPACAO,
                    SUM(NVL(PDV_VALOR_PEND_LIBERADO,0)
                        - NVL(FRETE,0)
                        - NVL(PDV_COMISSAO,0)
                        - NVL(PDV_VALOR_VPC,0)
                        - NVL(PDV_VALOR_ICMS,0)
                        - NVL(PDV_VALOR_PIS,0)
                        - NVL(PDV_VALOR_COFINS,0)
                        - NVL(PDV_VALOR_PEND_LIBERADO,0) * ($desp / 100)
                        - NVL(PDV_CUSMED,0))                              AS MC_VALOR,
                    ROUND(SUM(
                        NVL(PDV_VALOR_PEND_LIBERADO,0)
                        - NVL(FRETE,0)
                        - NVL(PDV_COMISSAO,0)
                        - NVL(PDV_VALOR_VPC,0)
                        - NVL(PDV_VALOR_ICMS,0)
                        - NVL(PDV_VALOR_PIS,0)
                        - NVL(PDV_VALOR_COFINS,0)
                        - NVL(PDV_VALOR_PEND_LIBERADO,0) * ($desp / 100)
                        - NVL(PDV_CUSMED,0)
                    ) / NULLIF(SUM(PDV_VALOR_PEND_LIBERADO), 0) * 100, 2) AS MC_PERC,
                    ROUND(SUM(NVL(PDV_VALOR_PEND_LIBERADO,0))
                          / NULLIF(SUM(NVL(UEP,0)), 0), 4)               AS TAXA
                FROM FOCCO3I.TGAZIN_CARTEIRA_PDV
                LEFT JOIN GAZIN_DESPESAS_MC ON TGAZIN_CARTEIRA_PDV.EMPR_ID = GAZIN_DESPESAS_MC.FILIAL
                WHERE DT_EMIS BETWEEN $ddi AND ($ddf + 1 - 1/86400)";

        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'][0] ?? [];
    }
}
