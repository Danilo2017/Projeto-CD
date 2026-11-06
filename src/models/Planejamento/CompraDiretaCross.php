<?php

namespace src\models\Planejamento;

use core\Database;

/**
 * Compra Direta Crossdocking — porte da tela OTM "CLI/Suprimentos/compra_dir_cross".
 *
 * A procedure OTIMIZA.PCOMP_DIR_CROSS_OTM e as funções da tela (fdias_trabalhados_auto_otm,
 * festoque_futuro_cross_otm, fsug_dt_comp_dir_cross_otm, fest_max_comp_dir_cross_otm, fdados_est_ideal_otm,
 * fdados_almox_estoque_otm, fultimo_pedc_otm, fultimo_pedc_dt_otm) foram reescritas em um SELECT só,
 * sem tabela temporária: nada é gravado no OTIMIZA. Do OTIMIZA só se LÊ: TDADOS_ESTOQUE_OTM (capacidade),
 * TDADOS_ESTOQUE_IDEAL_OTM (autonomia máx.) e TOBS_PLA_REP_CD_OTM (histórico antigo de observações).
 *
 * SQL direto no PHP enquanto a tela está em teste (a pedido do usuário); depois vai para a FOCCO3I.GAZIN_SQLS.
 */
class CompraDiretaCross
{
    /** Almoxarifados do "Estoque Principal" (fdados_almox_estoque_otm tipo 1): empresa 10 e demais. */
    private const ALMOX_PRINC_10 = '102,103,104';
    private const ALMOX_PRINC    = '1,4,5,6,7,71,73,74,100';
    /** Almoxarifados do "Estoque Produção" (tipo 2). */
    private const ALMOX_PROD     = '10,11,12,13,14,15,16,17,18,19,30,31,32,33,34,35,36,50,51,52,53,54,55,70,79,80,85,98,99';

    private static function query(string $sql, array $b = []): array
    {
        $st = Database::getInstance('focco')->prepare($sql);
        foreach ($b as $k => $v) {
            $st->bindValue(':' . $k, $v, $v === null ? \PDO::PARAM_NULL : (is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
        }
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) foreach ($row as &$v) if (is_resource($v)) $v = stream_get_contents($v);
        return $rows;
    }

    /** Executa PL/SQL; erro de negócio (ORA-20xxx) vira mensagem limpa. */
    private static function plsql(string $bloco, array $b): void
    {
        try {
            $st = Database::getInstance('focco')->prepare($bloco);
            foreach ($b as $k => $v) {
                $st->bindValue(':' . $k, $v, $v === null ? \PDO::PARAM_NULL : (is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
            }
            $st->execute();
        } catch (\PDOException $e) {
            if (preg_match('/ORA-20\d{3}:\s*(.+?)(?:\s*ORA-|\n|$)/s', $e->getMessage(), $m)) throw new \Exception(trim($m[1]), 409);
            throw $e;
        }
    }

    // ── Filtros ──────────────────────────────────────────────────────

    /** Mesma lista do OTM: todas as empresas. */
    public static function empresas(): array
    {
        return self::query("SELECT ID, COD_EMP, RAZAO_SOCIAL NOME FROM FOCCO3I.TEMPRESAS ORDER BY 1");
    }

    // ── Consulta principal (PCOMP_DIR_CROSS_OTM + SELECT da tela) ────

    /**
     * @param int      $empresa  TEMPRESAS.ID
     * @param string[] $classes  padrões LIKE de classificação (ex.: 10.001%), em OR
     * @param string[] $itens    códigos de item (IN)
     */
    public static function gerar(int $empresa, array $classes, array $itens, ?bool $forcarAgrupado = null): array
    {
        $b = ['empresa' => $empresa];
        $filtro = '';
        if ($itens) {
            $ph = [];
            foreach (array_values($itens) as $i => $v) { $ph[] = ":it$i"; $b["it$i"] = $v; }
            $filtro .= ' AND TITENS.COD_ITEM IN (' . implode(',', $ph) . ')';
        }
        if ($classes) {
            $or = [];
            foreach (array_values($classes) as $i => $v) { $or[] = "TGRP_CLAS_ITE.COD_GRP_ITE LIKE :cl$i"; $b["cl$i"] = $v; }
            $filtro .= ' AND (' . implode(' OR ', $or) . ')';
        }

        $pend = "FROM FOCCO3I.TPEDC_ITEM PI, FOCCO3I.TPED_COMPRA PC
                WHERE PC.ID = PI.TPEDC_ID AND PC.TIPO IN ('OCL','OCI') AND PI.POSICAO = 'P'";
        $conv = "FOCCO3I.ADM_INV_BUSCA_TAXA_CONVERSAO(PC.EMPR_ID, TE.ITEM_ID, PI.MASC_ITEM_ID, PI.UNID_MED_ID, ES.UNID_MED_ID)";
        // Saldo: uma chamada com a lista de almoxarifados (mesmo valor da soma por almoxarifado do OTM, mais rápido)
        $saldo = fn(string $almox) => "FOCCO3I.MAN_EST_RETORNA_SALDO(B.EMPR_ID, B.ITEM_ID, '$almox', TO_CHAR(SYSDATE,'DD/MM/RRRR'), 'NAO', B.MASC_ID)";
        $min10 = "CASE WHEN (NVL(F.CONSUMO_MENSAL,0)/22) > (F.MEDIA_ULT_SEM/6) THEN (NVL(F.CONSUMO_MENSAL,0)/22) * 10 ELSE (F.MEDIA_ULT_SEM/6) * 10 END";

        $bSql = "
            SELECT TEMPRESAS.ID EMPR_ID, TEMPRESAS.COD_EMP EMPRESA, TITENS.ID ITEM_ID, TITENS_EMPR.ID ITEM_EMPR_ID,
                   TITENS_SUPRIMENTOS.ID ITSUP_ID, TITENS_ESTOQUE.ID ITESTQ_ID,
                   TITENS.COD_ITEM, TITENS.DESC_TECNICA DESC_ITEM, TMASC_ITEM.ID MASC_ID, TMASC_ITEM.MASCARA,
                   FOCCO3I.FOCCO3I_ITENS.RETORNA_DESC_CLAS_ITEM_NVL(TGRP_CLAS_ITE.ID, 3) CLASSIFICACAO,
                   NVL(NVL(TITENS_PLAN_CONF.TEMPO_REP, TITENS_PLANEJAMENTO.TEMPO_REP), 0) TEMPO_REP,
                   NVL(TITENS_ENG_CONF.TIPO_PRODUTO, TITENS_ENGENHARIA.TIPO_PRODUTO) TIPO_PRODUTO
              FROM FOCCO3I.TUNID_MED TUNID_MED, FOCCO3I.TGRP_CLAS_ITE TGRP_CLAS_ITE, FOCCO3I.TEMPRESAS TEMPRESAS,
                   FOCCO3I.TITENS_ESTOQUE TITENS_ESTOQUE, FOCCO3I.TITENS_EMPR TITENS_EMPR, FOCCO3I.TITENS TITENS,
                   FOCCO3I.TITENS_ESTQ_CONF TITENS_ESTQ_CONF, FOCCO3I.TITENS_SUPRIMENTOS TITENS_SUPRIMENTOS,
                   FOCCO3I.TMASC_ITEM TMASC_ITEM, FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO,
                   FOCCO3I.TITENS_PLAN_CONF TITENS_PLAN_CONF, FOCCO3I.TITENS_ENGENHARIA TITENS_ENGENHARIA,
                   FOCCO3I.TITENS_ENG_CONF TITENS_ENG_CONF
             WHERE TUNID_MED.ID = TITENS_ESTOQUE.UNID_MED_ID
               AND TGRP_CLAS_ITE.ID = TITENS_ESTOQUE.GRP_CLAS_ID
               AND TEMPRESAS.ID = TITENS_EMPR.EMPR_ID
               AND TITENS_ESTOQUE.ID = TITENS_ESTQ_CONF.ITESTQ_ID(+)
               AND TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
               AND TITENS_EMPR.ID = TITENS_SUPRIMENTOS.ITEMPR_ID
               AND TITENS_EMPR.ID = TITENS_ESTOQUE.ITEMPR_ID
               AND TITENS.ID = TITENS_EMPR.ITEM_ID
               AND TMASC_ITEM.ID(+) = TITENS_ESTQ_CONF.TMASC_ITEM_ID
               AND TITENS_PLAN_CONF.TMASC_ITEM_ID(+) = TMASC_ITEM.ID
               AND TITENS_EMPR.ID = TITENS_ENGENHARIA.ITEMPR_ID
               AND TITENS_ENGENHARIA.ID = TITENS_ENG_CONF.ITEG_ID(+)
               AND NVL(TMASC_ITEM.ID,0) = NVL(TITENS_ENG_CONF.TMASC_ITEM_ID,0)
               AND TITENS.SIT = 1
               AND TITENS_EMPR.SIT = 1
               AND (CASE WHEN NVL(TMASC_ITEM.ID,0) > 0 THEN TMASC_ITEM.SIT ELSE 1 END) = 1
               AND TEMPRESAS.ID = :empresa
               $filtro";

        // Consumo (GVAZIN_CONS_ITEM_VENDA) e REP dos últimos 7 dias (razão): mesma regra do OTM em duas formas.
        //  - poucos itens: leitura por item (subconsulta por linha);
        //  - muitos itens: uma passada só na view, agrupada por item (os três períodos juntos; hoje está dentro dos 90 dias).
        //  Os valores são os mesmos; muda só o tempo (10.001% da empresa 1: 385s -> ~60s).
        $qtdItens = (int) (self::query("SELECT COUNT(*) N FROM ($bSql)", $b)[0]['N'] ?? 0);
        $agrupado = $forcarAgrupado ?? ($qtdItens > 150);
        $cons = fn(string $per, string $agg) => "(SELECT $agg(V.QTDE) FROM FOCCO3I.GVAZIN_CONS_ITEM_VENDA V
                     WHERE $per AND V.ITESTQ_ID = B.ITESTQ_ID AND NVL(V.TMASC_ITEM_ID,0) = NVL(B.MASC_ID,0))";
        $colsConsumo = $agrupado
            ? "CONS.S90 / 3 TRES_MES, CONS.DIA CONSUMO_DIA, CONS.S30 CONSUMO_MENSAL, NVL(RAZ.QTDE, 0) MEDIA_ULT_SEM,"
            : $cons('V.DATA_MOV BETWEEN (SYSDATE - 90) AND SYSDATE', 'SUM') . " / 3 TRES_MES,
               " . $cons('V.DATA_MOV = TRUNC(SYSDATE)', 'MAX') . " CONSUMO_DIA,
               " . $cons('V.DATA_MOV BETWEEN (SYSDATE - 30) AND SYSDATE', 'SUM') . " CONSUMO_MENSAL,
               NVL((SELECT SUM(W.QTDE) FROM FOCCO3I.VW_GAZIN_RAZAO_CUSTO_MED_ESTQ W
                     WHERE W.ITESTQ_ID = B.ITESTQ_ID AND NVL(W.TMASC_ITEM_ID,0) = NVL(B.MASC_ID,0)
                       AND W.TP_MOV_ESTQ = 'REP' AND W.DT BETWEEN TRUNC(SYSDATE-7) AND TRUNC(SYSDATE)), 0) MEDIA_ULT_SEM,";
        $joinsConsumo = $agrupado
            ? "LEFT JOIN CONS ON CONS.ITESTQ_ID = B.ITESTQ_ID AND CONS.MK = NVL(B.MASC_ID,0)
               LEFT JOIN RAZ  ON RAZ.ITESTQ_ID  = B.ITESTQ_ID AND RAZ.MK  = NVL(B.MASC_ID,0)"
            : '';
        $ctesConsumo = !$agrupado ? '' : "
        CONS AS (
            SELECT V.ITESTQ_ID, NVL(V.TMASC_ITEM_ID,0) MK,
                   SUM(V.QTDE) S90,
                   SUM(CASE WHEN V.DATA_MOV BETWEEN (SYSDATE - 30) AND SYSDATE THEN V.QTDE END) S30,
                   MAX(CASE WHEN V.DATA_MOV = TRUNC(SYSDATE) THEN V.QTDE END) DIA
              FROM FOCCO3I.GVAZIN_CONS_ITEM_VENDA V
             WHERE V.DATA_MOV BETWEEN (SYSDATE - 90) AND SYSDATE
               AND V.ITESTQ_ID IN (SELECT ITESTQ_ID FROM B)
             GROUP BY V.ITESTQ_ID, NVL(V.TMASC_ITEM_ID,0)
        ),
        -- Requisições planejadas (REP) dos últimos 7 dias
        RAZ AS (
            SELECT W.ITESTQ_ID, NVL(W.TMASC_ITEM_ID,0) MK, SUM(W.QTDE) QTDE
              FROM FOCCO3I.VW_GAZIN_RAZAO_CUSTO_MED_ESTQ W
             WHERE W.TP_MOV_ESTQ = 'REP' AND W.DT BETWEEN TRUNC(SYSDATE-7) AND TRUNC(SYSDATE)
               AND W.ITESTQ_ID IN (SELECT ITESTQ_ID FROM B)
             GROUP BY W.ITESTQ_ID, NVL(W.TMASC_ITEM_ID,0)
        ),";

        $sql = "
        WITH CAL AS (   -- TTEMP_DIA_UTIL_CROSS_OTM: dias úteis gerais a partir de amanhã, em sequência
            SELECT DATA, ROW_NUMBER() OVER (ORDER BY ID) SEQ
              FROM FOCCO3I.TCALENDARIOS WHERE EMPR_ID = 1 AND DATA > TRUNC(SYSDATE) AND UTIL_GER_DIA = 1
        ),
        VDIA AS (       -- OTIMIZA.VDIA_UTIL_OTM
            SELECT DATA, RANK() OVER (ORDER BY DATA) RNK
              FROM FOCCO3I.TCALENDARIOS WHERE EMPR_ID = 1 AND UTIL_INDUS = 1 AND DATA >= TRUNC(SYSDATE)
        ),
        B AS ($bSql
        ),$ctesConsumo
        C AS (
            SELECT B.*,
                   $colsConsumo
                   (SELECT MIN(PC.COD_PEDC) $pend AND PI.ITEM_ID = B.ITSUP_ID AND PI.QTDE_SALDO > 0
                       AND NVL(PI.MASC_ITEM_ID,0) = NVL(B.MASC_ID,0)) PEDIDO_COMPRA,
                   (SELECT COUNT(*) FROM FOCCO3I.TPEDC_ITEM PI, FOCCO3I.TPED_COMPRA PC, FOCCO3I.TITENS_SUPRIMENTOS TS
                     WHERE PC.ID = PI.TPEDC_ID AND TS.ID = PI.ITEM_ID AND PC.TIPO IN ('OCL','OCI') AND PI.POSICAO = 'P'
                       AND PI.QTDE_SALDO > 0 AND TS.ITEMPR_ID = B.ITEM_EMPR_ID
                       AND NVL(PI.MASC_ITEM_ID,0) = NVL(B.MASC_ID,0)) QTD_LINHAS_PED,
                   -- fultimo_pedc_*: pedido com a menor data de entrega (menor ID no empate)
                   (SELECT MIN(PC.ID) $pend AND PI.ITEM_ID = B.ITSUP_ID AND NVL(PI.MASC_ITEM_ID,0) = NVL(B.MASC_ID,0)
                       AND TRUNC(PI.DT_ENTREGA) = (SELECT MIN(PI2.DT_ENTREGA)
                                                     FROM FOCCO3I.TPEDC_ITEM PI2, FOCCO3I.TPED_COMPRA PC2
                                                    WHERE PC2.ID = PI2.TPEDC_ID AND PC2.TIPO IN ('OCL','OCI') AND PI2.POSICAO = 'P'
                                                      AND PI2.ITEM_ID = B.ITSUP_ID AND NVL(PI2.MASC_ITEM_ID,0) = NVL(B.MASC_ID,0))) ULT_PED_ID,
                   CASE WHEN B.EMPR_ID = 10 THEN " . $saldo(self::ALMOX_PRINC_10) . "
                        ELSE " . $saldo(self::ALMOX_PRINC) . " END ESTOQUE_PRINC,
                   " . $saldo(self::ALMOX_PROD) . " ESTOQUE_PROD,
                   (SELECT MAX(D.CAP_ARMAZENA) FROM OTIMIZA.TDADOS_ESTOQUE_OTM D
                     WHERE D.ITEM_EMPR_ID = B.ITEM_EMPR_ID AND NVL(D.MASC_ID,0) = NVL(B.MASC_ID,0)) CAP_ARMAZENA,
                   (SELECT MAX(I.AUTONOMIA_MAX) FROM OTIMIZA.TDADOS_ESTOQUE_IDEAL_OTM I WHERE I.CLASSIFICACAO = B.CLASSIFICACAO) AUTONOMIA_MAX,
                   (SELECT MAX(V.DATA) FROM VDIA V WHERE V.RNK = B.TEMPO_REP + 1) DATA_LIMITE_UTIL
              FROM B
              $joinsConsumo
        ),
        D AS (
            SELECT C.*,
                   (SELECT MIN(PI.DT_ENTREGA) $pend AND PI.ITEM_ID = C.ITSUP_ID AND NVL(PI.MASC_ITEM_ID,0) = NVL(C.MASC_ID,0)
                       AND PC.ID = C.ULT_PED_ID) DT_PREV_ENTREGA,
                   (SELECT MIN(PC.DT_EMIS) $pend AND PI.ITEM_ID = C.ITSUP_ID AND NVL(PI.MASC_ITEM_ID,0) = NVL(C.MASC_ID,0)
                       AND PC.ID = C.ULT_PED_ID) DT_INCLUSAO,
                   (SELECT SUM(PI.QTDE * $conv)
                      FROM FOCCO3I.TPEDC_ITEM PI, FOCCO3I.TPED_COMPRA PC, FOCCO3I.TITENS_SUPRIMENTOS TS,
                           FOCCO3I.TITENS_EMPR TE, FOCCO3I.TITENS_ESTOQUE ES
                     WHERE PI.TPEDC_ID = PC.ID AND TS.ID = PI.ITEM_ID AND TE.ID = TS.ITEMPR_ID AND TE.ID = ES.ITEMPR_ID
                       AND PI.TPEDC_ID = C.ULT_PED_ID AND PI.ITEM_ID = C.ITSUP_ID
                       AND NVL(PI.MASC_ITEM_ID,0) = NVL(C.MASC_ID,0)) QTDE,
                   (SELECT SUM(PI.QTDE_SALDO * $conv)
                      FROM FOCCO3I.TPEDC_ITEM PI, FOCCO3I.TPED_COMPRA PC, FOCCO3I.TITENS_SUPRIMENTOS TS,
                           FOCCO3I.TITENS_EMPR TE, FOCCO3I.TITENS_ESTOQUE ES
                     WHERE PI.TPEDC_ID = PC.ID AND TS.ID = PI.ITEM_ID AND TE.ID = TS.ITEMPR_ID AND TE.ID = ES.ITEMPR_ID
                       AND PI.TPEDC_ID = C.ULT_PED_ID AND PI.ITEM_ID = C.ITSUP_ID
                       AND NVL(PI.MASC_ITEM_ID,0) = NVL(C.MASC_ID,0)) QTDE_PDC,
                   (SELECT SUM(PI.QTDE_SALDO * $conv)
                      FROM FOCCO3I.TPEDC_ITEM PI, FOCCO3I.TPED_COMPRA PC, FOCCO3I.TITENS_SUPRIMENTOS TS,
                           FOCCO3I.TITENS_EMPR TE, FOCCO3I.TITENS_ESTOQUE ES
                     WHERE PC.ID = PI.TPEDC_ID AND TS.ID = PI.ITEM_ID AND TE.ID = TS.ITEMPR_ID AND TE.ID = ES.ITEMPR_ID
                       AND PI.ITEM_ID = C.ITSUP_ID AND PC.TIPO IN ('OCL','OCI') AND PI.POSICAO = 'P'
                       AND NVL(PI.MASC_ITEM_ID,0) = NVL(C.MASC_ID,0)) QTDE_PEDIDOS,
                   -- festoque_futuro_cross_otm: saldo OCL/OCI que chega até a data limite (dia útil)
                   NVL((SELECT SUM(PI.QTDE_SALDO)
                          FROM FOCCO3I.TPEDC_ITEM PI, FOCCO3I.TPED_COMPRA PC, FOCCO3I.TITENS_SUPRIMENTOS TS
                         WHERE PC.ID = PI.TPEDC_ID AND TS.ID = PI.ITEM_ID AND TS.ITEMPR_ID = C.ITEM_EMPR_ID
                           AND PC.TIPO IN ('OCL','OCI') AND PI.POSICAO = 'P' AND PI.QTDE_SALDO > 0
                           AND NVL(PI.MASC_ITEM_ID,0) = NVL(C.MASC_ID,0)
                           AND PI.DT_ENTREGA <= C.DATA_LIMITE_UTIL), 0) PED_ATE_LIMITE,
                   ROUND(CASE WHEN NVL(C.CONSUMO_DIA,0) > 0 THEN C.ESTOQUE_PRINC / NVL(C.CONSUMO_DIA,0) ELSE 0 END) AUT_DIAS
              FROM C
        ),
        E AS (
            SELECT D.*,
                   -- fdias_trabalhados_auto_otm
                   CASE WHEN D.AUT_DIAS > 0
                        THEN NVL((SELECT MAX(TO_CHAR(CAL.DATA,'DD/MM/YYYY')) FROM CAL WHERE CAL.SEQ = D.AUT_DIAS), 'FORA DO CALENDÁRIO')
                        ELSE TO_CHAR(SYSDATE,'DD/MM/YYYY') END AUTONOMIA_EST,
                   -- fest_max_comp_dir_cross_otm
                   NVL(CASE WHEN NVL(D.CAP_ARMAZENA,0) > 0 THEN D.CAP_ARMAZENA
                            ELSE D.AUTONOMIA_MAX * NVL(D.CONSUMO_DIA,0) END, 0) EST_MAX,
                   -- festoque_futuro_cross_otm
                   NVL((NVL(D.ESTOQUE_PRINC,0) - ((NVL(D.CONSUMO_MENSAL,0)/22) * NVL(D.TEMPO_REP,0))) + D.PED_ATE_LIMITE, 0) EST_FUTURO
              FROM D
        ),
        F AS (
            SELECT E.*,
                   CASE WHEN E.AUTONOMIA_EST = 'FORA DO CALENDÁRIO' THEN NULL
                        WHEN E.AUT_DIAS = 0 THEN TRUNC(SYSDATE)
                        ELSE TO_DATE(E.AUTONOMIA_EST,'DD/MM/YYYY') - 10 END DT_SUG
              FROM E
        )
        SELECT F.ITEM_EMPR_ID || '-' || NVL(F.MASC_ID,0) CHAVE,
               F.EMPR_ID, F.EMPRESA, F.ITEM_ID, F.ITEM_EMPR_ID, F.COD_ITEM, F.DESC_ITEM, F.MASC_ID, F.MASCARA,
               F.TIPO_PRODUTO, F.CLASSIFICACAO,
               F.ESTOQUE_PRINC, F.ESTOQUE_PROD,
               $min10 + (NVL(F.TEMPO_REP,0) * (NVL(F.CONSUMO_MENSAL,0)/22)) PONTO_REPOSICAO,
               ROUND(CASE WHEN (NVL(F.CONSUMO_MENSAL,0)/22) > 0 THEN F.ESTOQUE_PRINC / (NVL(F.CONSUMO_MENSAL,0)/22) ELSE 0 END) AUTONOMIA,
               F.AUTONOMIA_EST,
               F.EST_FUTURO,
               -- fsug_dt_comp_dir_cross_otm
               CASE WHEN F.DT_SUG IS NULL THEN 'FORA DO CALENDÁRIO'
                    WHEN F.QTD_LINHAS_PED > 0 THEN CASE WHEN F.DT_PREV_ENTREGA > F.DT_SUG THEN 'ANTECIPAR' ELSE 'POSTERGAR' END
                    WHEN (SYSDATE + NVL(F.TEMPO_REP,0)) > F.DT_SUG THEN 'PEDIDO URGENTE' END SUGESTAO_PROGR,
               CASE WHEN F.AUTONOMIA_EST = 'FORA DO CALENDÁRIO' THEN 'FORA DO CALENDÁRIO' ELSE TO_CHAR(F.DT_SUG,'DD/MM/YYYY') END NOVA_DATA,
               TO_CHAR(TRUNC(SYSDATE + NVL(F.TEMPO_REP,0)),'DD/MM/YYYY') DATA_LIMITE,
               CASE WHEN F.EST_FUTURO > F.EST_MAX THEN 0 ELSE F.EST_MAX - F.EST_FUTURO END SUGESTAO_COMPRA,
               NVL(F.TRES_MES,0) MEDIA_TRI,
               NVL(F.CONSUMO_MENSAL,0) MEDIA_MENSAL,
               NVL(F.CONSUMO_MENSAL,0)/22 CONSUMO_DIA_CALC,
               (F.MEDIA_ULT_SEM/7) * 22 PROJECAO_MES,
               (F.MEDIA_ULT_SEM/7) * 132 DESVIO_6M,
               F.PEDIDO_COMPRA,
               TO_CHAR(F.DT_INCLUSAO,'DD/MM/YYYY') DT_INCLUSAO,
               TO_CHAR(F.DT_PREV_ENTREGA,'DD/MM/YYYY') DT_PREV_ENTREGA,
               F.QTDE, F.QTDE_PDC, F.QTDE_PEDIDOS,
               NVL(F.TEMPO_REP,0) LEAD_TIME,
               F.AUTONOMIA_MAX,
               $min10 ESTOQUE_MIN_10,
               F.EST_MAX,
               CASE WHEN F.ESTOQUE_PRINC = 0 THEN 'RUPTURA'
                    WHEN F.ESTOQUE_PRINC > F.EST_MAX THEN 'EXCESSO'
                    WHEN F.ESTOQUE_PRINC < $min10 THEN 'RISCO'
                    ELSE 'OK' END ANALISE,
               TO_CHAR(F.DATA_LIMITE_UTIL,'DD/MM/YYYY') DATA_LIMITE_UTIL
          FROM F
         ORDER BY F.EMPRESA, TO_NUMBER(F.COD_ITEM), F.MASC_ID";

        $rows = self::query($sql, $b);
        // última observação (histórico novo + o do Focco antigo, igual à Reposição CD)
        $obs = self::observacoes(array_unique(array_map(fn($r) => (int) $r['ITEM_EMPR_ID'], $rows)));
        foreach ($rows as &$r) $r['OBS'] = $obs[$r['ITEM_EMPR_ID'] . '-' . ($r['MASC_ID'] ?? 0)] ?? null;
        return $rows;
    }

    /** Última observação por item_empr/máscara: [item_empr-masc => obs]. */
    public static function observacoes(array $itemEmprIds): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_filter($itemEmprIds)), 900) as $lote) {
            $in = implode(',', array_map('intval', $lote));
            $base = "SELECT ITEM_EMPR_ID, MASC_ID, OBS, DATA, ID FROM FOCCO3I.TGAZIN_PLA_REP_CD_OBS WHERE ITEM_EMPR_ID IN ($in)";
            $otm  = " UNION ALL SELECT ITEM_EMPR_ID, MASC_ID, OBS, DATA, ID FROM OTIMIZA.TOBS_PLA_REP_CD_OTM WHERE ITEM_EMPR_ID IN ($in)";
            $sql  = fn($f) => "SELECT ITEM_EMPR_ID, NVL(MASC_ID,0) MK, MAX(OBS) KEEP (DENSE_RANK LAST ORDER BY DATA, ID) OBS
                                 FROM ($f) GROUP BY ITEM_EMPR_ID, NVL(MASC_ID,0)";
            try { $rows = self::query($sql($base . $otm)); }
            catch (\PDOException) { $rows = self::query($sql($base)); }   // sem acesso ao histórico do OTIMIZA
            foreach ($rows as $r) $out[$r['ITEM_EMPR_ID'] . '-' . $r['MK']] = $r['OBS'];
        }
        return $out;
    }

    // ── Perfil do item (drill PERFIL) ────────────────────────────────

    /** Perfil do plano 1 da empresa por data; o "Projetado" é calculado no handler (mesma regra do OTM). */
    public static function perfil(int $empresa, int $itemEmprId, ?int $mascId): array
    {
        $sub = fn(string $tipos) => "(SELECT SUM(PER2.QTDE) FROM FOCCO3I.TPERFIL_ITENS PER2
                                       WHERE PER2.ITPL_ID = PER.ITPL_ID AND NVL(PER2.TMASC_ITEM_ID,0) = NVL(PER.TMASC_ITEM_ID,0)
                                         AND PER2.PLANO_ID = PER.PLANO_ID AND PER2.DT_FIM = PER.DT_FIM
                                         AND PER2.TIPO_ORD_DEM IN ($tipos))";
        return self::query(
            "SELECT TO_CHAR(TRUNC(PER.DT_FIM),'DD/MM/YYYY') DATA, TO_CHAR(TRUNC(PER.DT_FIM),'YYYYMMDD') DATA_ORD,
                    " . $sub("'OFA','OFM','OFF'") . " OFA_OFM,
                    " . $sub("'OFP'") . " OFP,
                    " . $sub("'DC','DD','DP','DE','DI'") . " DEMANDA,
                    " . $sub("'OCL'") . " OCL,
                    FOCCO3I.MAN_EST_RETORNA_SALDO(PLA.EMPR_ID, TITENS_EMPR.ITEM_ID, NULL, TO_CHAR(SYSDATE,'DD/MM/RRRR'), 'NAO', PER.TMASC_ITEM_ID) ESTOQUE
               FROM FOCCO3I.TPERFIL_ITENS PER, FOCCO3I.TPLANOS PLA, FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO, FOCCO3I.TITENS_EMPR TITENS_EMPR
              WHERE PER.ITPL_ID = TITENS_PLANEJAMENTO.ID
                AND NVL(PER.TMASC_ITEM_ID,0) = NVL(:masc,0)
                AND PLA.ID = PER.PLANO_ID
                AND PLA.EMPR_ID = :emp
                AND TITENS_PLANEJAMENTO.ITEMPR_ID = TITENS_EMPR.ID
                AND TITENS_EMPR.ID = :item_empr
                AND PLA.ID = (SELECT ID FROM FOCCO3I.TPLANOS WHERE COD_PLANO = 1 AND EMPR_ID = :emp2)
              GROUP BY PER.DT_FIM, PER.ITPL_ID, PER.PLANO_ID, PER.TMASC_ITEM_ID, PLA.EMPR_ID, TITENS_EMPR.ITEM_ID
              ORDER BY PER.DT_FIM",
            ['masc' => $mascId, 'emp' => $empresa, 'item_empr' => $itemEmprId, 'emp2' => $empresa]
        );
    }

    // ── Pedidos (drill PEDIDOS / ITENS_PED) ──────────────────────────

    /** Empresa 1: pedidos de venda (cliente 1 / estab. 1). Demais: pedidos de compra pendentes. */
    public static function pedidos(int $empresa, int $itemId, ?int $mascId): array
    {
        if ($empresa === 1) {
            $conv = "FOCCO3I.ADM_INV_BUSCA_TAXA_CONVERSAO(PV.EMPR_ID, TE.ITEM_ID, IP.TMASC_ITEM_ID, IP.UNID_MED_ID, ES.UNID_MED_ID)";
            return self::query(
                "SELECT 'PDV' TIPO, PV.ID PED_ID, PV.NUM_PEDIDO PEDIDO,
                        IP.QTDE * $conv QTDE_ORIGINAL, IP.QTDE_SLDO * $conv QTDE_PENDENTE, UM.COD_UNID_MED UM,
                        TO_CHAR(PV.DT_ENTREGA,'DD/MM/YYYY') DT_ENTREGA, CASE WHEN PV.DT_ENTREGA < TRUNC(SYSDATE) THEN 1 ELSE 0 END ATRASADO,
                        CL.COD_CLI || ' - ' || CL.DESCRICAO PARCEIRO, NULL COMPRADOR
                   FROM FOCCO3I.TPEDIDOS_VENDA PV, FOCCO3I.TITENS_PDV IP, FOCCO3I.TITENS_COMERCIAL IC, FOCCO3I.TITENS_EMPR TE,
                        FOCCO3I.TCLIENTES CL, FOCCO3I.TESTABELECIMENTOS EB, FOCCO3I.TUNID_MED UM, FOCCO3I.TITENS_ESTOQUE ES
                  WHERE PV.ID = IP.PDV_ID AND IC.ID = IP.ITCM_ID AND TE.ID = IC.ITEMPR_ID AND CL.ID = PV.CLI_ID
                    AND EB.ID = PV.EST_ID_FAT AND TE.ID = ES.ITEMPR_ID AND UM.ID = ES.UNID_MED_ID
                    AND CL.COD_CLI = 1 AND EB.COD_EST = 1 AND PV.EMPR_ID = 1 AND PV.TIPO = 'PDV' AND IP.QTDE_SLDO > 0
                    AND TE.ITEM_ID = :item AND NVL(IP.TMASC_ITEM_ID,0) = NVL(:masc,0)",
                ['item' => $itemId, 'masc' => $mascId]
            );
        }
        $conv = "FOCCO3I.ADM_INV_BUSCA_TAXA_CONVERSAO(PC.EMPR_ID, TE.ITEM_ID, PI.MASC_ITEM_ID, PI.UNID_MED_ID, ES.UNID_MED_ID)";
        return self::query(
            "SELECT 'PDC' TIPO, PC.ID PED_ID, PC.COD_PEDC PEDIDO,
                    PI.QTDE * $conv QTDE_ORIGINAL, PI.QTDE_SALDO * $conv QTDE_PENDENTE, UM.COD_UNID_MED UM,
                    TO_CHAR(PI.DT_ENTREGA,'DD/MM/YYYY') DT_ENTREGA, CASE WHEN PI.DT_ENTREGA < TRUNC(SYSDATE) THEN 1 ELSE 0 END ATRASADO,
                    FO.COD_FOR || ' - ' || FO.DESCRICAO PARCEIRO, FU.NOME COMPRADOR
               FROM FOCCO3I.TPED_COMPRA PC, FOCCO3I.TPEDC_ITEM PI, FOCCO3I.TITENS_SUPRIMENTOS TS, FOCCO3I.TITENS_EMPR TE,
                    FOCCO3I.TFORNECEDORES FO, FOCCO3I.TUNID_MED UM, FOCCO3I.TITENS_ESTOQUE ES, FOCCO3I.TFUNCIONARIOS FU
              WHERE PC.ID = PI.TPEDC_ID AND TS.ID = PI.ITEM_ID AND TE.ID = TS.ITEMPR_ID AND FO.ID = PC.TFOR_ID
                AND UM.ID = ES.UNID_MED_ID AND TE.ID = ES.ITEMPR_ID AND FU.ID(+) = PC.FUNC_ID
                AND PC.POSICAO = 'P' AND PI.QTDE_SALDO > 0
                AND PC.EMPR_ID = :emp AND TE.ITEM_ID = :item AND NVL(PI.MASC_ITEM_ID,0) = NVL(:masc,0)
              ORDER BY PI.DT_ENTREGA, PC.COD_PEDC",
            ['emp' => $empresa, 'item' => $itemId, 'masc' => $mascId]
        );
    }

    /** Itens pendentes de um pedido de compra (o OTM só abre esse detalhe para pedido de compra). */
    public static function pedidoItens(int $pedcId): array
    {
        $conv = "FOCCO3I.ADM_INV_BUSCA_TAXA_CONVERSAO(PC.EMPR_ID, TE.ITEM_ID, PI.MASC_ITEM_ID, PI.UNID_MED_ID, ES.UNID_MED_ID)";
        return self::query(
            "SELECT T.COD_ITEM, T.DESC_TECNICA DESC_ITEM, M.ID MASC_ID, M.MASCARA,
                    TO_CHAR(PC.DT_EMIS,'DD/MM/YYYY') DT_INCLUSAO, TO_CHAR(PI.DT_ENTREGA,'DD/MM/YYYY') DT_ENTREGA,
                    PI.QTDE * $conv QTDE_ORIGINAL, PI.QTDE_SALDO * $conv QTDE_PENDENTE, UM.COD_UNID_MED UM
               FROM FOCCO3I.TPED_COMPRA PC, FOCCO3I.TPEDC_ITEM PI, FOCCO3I.TITENS_SUPRIMENTOS TS, FOCCO3I.TITENS_EMPR TE,
                    FOCCO3I.TITENS T, FOCCO3I.TMASC_ITEM M, FOCCO3I.TUNID_MED UM, FOCCO3I.TITENS_ESTOQUE ES
              WHERE PC.ID = PI.TPEDC_ID AND TS.ID = PI.ITEM_ID AND TE.ID = TS.ITEMPR_ID AND T.ID = TE.ITEM_ID
                AND M.ID(+) = PI.MASC_ITEM_ID AND UM.ID = ES.UNID_MED_ID AND TE.ID = ES.ITEMPR_ID
                AND PI.QTDE_SALDO > 0 AND PC.ID = :id
              ORDER BY PI.DT_ENTREGA",
            ['id' => $pedcId]
        );
    }

    // ── Alterar status (drill ALTERAR_STATUS) ────────────────────────

    /**
     * Mesma regra do OTM: altera o tipo de produto do item (ou da configuração com a mesma máscara)
     * em TODAS as empresas e grava uma observação por empresa. O histórico vai para a tabela nova
     * FOCCO3I.TGAZIN_PLA_REP_CD_OBS (a mesma da Reposição CD), não para o OTIMIZA.
     */
    public static function alterarStatus(int $itemId, ?int $mascId, ?string $mascara, string $status, string $obs, string $usuario): void
    {
        self::plsql(
            "DECLARE
                 v_status  VARCHAR2(1)   := CASE WHEN :status = 'N' THEN 'N' ELSE NULL END;
                 v_item    NUMBER        := :item_id;
                 v_temmasc NUMBER        := :tem_masc;
                 v_mascara VARCHAR2(4000):= :mascara;
                 v_obs     VARCHAR2(100) := SUBSTR(UPPER(:obs), 1, 100);
                 v_usuario VARCHAR2(60)  := SUBSTR(:usuario, 1, 60);
             BEGIN
                 IF v_temmasc = 0 THEN
                     FOR C IN (SELECT EN.ID, TE.ID ITEM_EMPR_ID
                                 FROM FOCCO3I.TITENS T, FOCCO3I.TITENS_EMPR TE, FOCCO3I.TITENS_ENGENHARIA EN
                                WHERE T.ID = TE.ITEM_ID AND TE.ID = EN.ITEMPR_ID AND T.ID = v_item) LOOP
                         UPDATE FOCCO3I.TITENS_ENGENHARIA SET TIPO_PRODUTO = v_status WHERE ID = C.ID;
                         INSERT INTO FOCCO3I.TGAZIN_PLA_REP_CD_OBS (ID, ITEM_ID, ITEM_EMPR_ID, MASC_ID, STATUS, DATA, OBS, USUARIO)
                              VALUES (FOCCO3I.SEQ_TGAZIN_PLA_REP_CD_OBS.NEXTVAL, v_item, C.ITEM_EMPR_ID, NULL, v_status, SYSDATE, v_obs, v_usuario);
                     END LOOP;
                 ELSE
                     FOR C IN (SELECT EC.ID, TE.ID ITEM_EMPR_ID, M.ID MASC_ID
                                 FROM FOCCO3I.TITENS T, FOCCO3I.TITENS_EMPR TE, FOCCO3I.TITENS_ENGENHARIA EN,
                                      FOCCO3I.TITENS_ENG_CONF EC, FOCCO3I.TMASC_ITEM M
                                WHERE T.ID = TE.ITEM_ID AND TE.ID = EN.ITEMPR_ID AND EN.ID = EC.ITEG_ID AND M.ID = EC.TMASC_ITEM_ID
                                  AND T.ID = v_item AND M.MASCARA = v_mascara) LOOP
                         UPDATE FOCCO3I.TITENS_ENG_CONF SET TIPO_PRODUTO = v_status WHERE ID = C.ID;
                         INSERT INTO FOCCO3I.TGAZIN_PLA_REP_CD_OBS (ID, ITEM_ID, ITEM_EMPR_ID, MASC_ID, STATUS, DATA, OBS, USUARIO)
                              VALUES (FOCCO3I.SEQ_TGAZIN_PLA_REP_CD_OBS.NEXTVAL, v_item, C.ITEM_EMPR_ID, C.MASC_ID, v_status, SYSDATE, v_obs, v_usuario);
                     END LOOP;
                 END IF;
                 COMMIT;
             END;",
            ['status' => $status, 'item_id' => $itemId, 'tem_masc' => $mascId === null ? 0 : 1, 'mascara' => $mascara,
             'obs' => $obs, 'usuario' => $usuario]
        );
    }

    // ── Gerar Linha (drill GERAR_DEMANDA) ────────────────────────────

    /** Planos 2 e 6 da empresa (mesma lista do OTM). */
    public static function planos(int $empresa): array
    {
        return self::query("SELECT ID, COD_PLANO, DESCRICAO FROM FOCCO3I.TPLANOS WHERE EMPR_ID = :e AND COD_PLANO IN (2,6) ORDER BY 2", ['e' => $empresa]);
    }

    /** Centros de custo auxiliares da empresa (mesma lista do OTM). */
    public static function centrosCusto(int $empresa): array
    {
        return self::query(
            "SELECT T.ID, C.COD, C.DESCRICAO FROM FOCCO3I.TEMP_CC T, FOCCO3I.TCC C
              WHERE T.CC_ID = C.ID AND C.TIPO_CC = 'AUX' AND T.EMPR_ID = :e ORDER BY 2",
            ['e' => $empresa]
        );
    }

    /** Job do MRP do plano (TPAR_JOB_MRP); null = não existe job. */
    public static function jobMrp(int $empresa, int $codPlano): ?int
    {
        $r = self::query("SELECT MAX(ID) X FROM FOCCO3I.TPAR_JOB_MRP WHERE EMPR_ID = :e AND PLANO_COD = :p",
                         ['e' => $empresa, 'p' => (string) $codPlano]);
        return isset($r[0]['X']) && $r[0]['X'] !== null ? (int) $r[0]['X'] : null;
    }

    /** PL/SQL com um bind de saída numérico (:saida); devolve o valor. */
    private static function plsqlSaida(string $bloco, array $b): ?float
    {
        $saida = self::plsqlTexto($bloco, $b, 40);
        return $saida === null ? null : (float) str_replace(',', '.', $saida);
    }

    /** PL/SQL com um bind de saída texto (:saida); devolve o texto. */
    private static function plsqlTexto(string $bloco, array $b, int $tam): ?string
    {
        $saida = '';   // PDO_OCI: com null o bind de saída volta vazio; com '' volta preenchido (e INT|INPUT_OUTPUT corrompe a memória)
        try {
            $st = Database::getInstance('focco')->prepare($bloco);
            foreach ($b as $k => $v) {
                $st->bindValue(':' . $k, $v, $v === null ? \PDO::PARAM_NULL : (is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
            }
            $st->bindParam(':saida', $saida, \PDO::PARAM_STR | \PDO::PARAM_INPUT_OUTPUT, $tam);
            $st->execute();
        } catch (\PDOException $e) {
            if (preg_match('/ORA-20\d{3}:\s*(.+?)(?:\s*ORA-|\n|$)/s', $e->getMessage(), $m)) throw new \Exception(trim($m[1]), 409);
            throw $e;
        }
        return $saida === null || $saida === '' ? null : (string) $saida;
    }

    /**
     * Cria as demandas independentes: um número por linha, sequencial dentro da empresa
     * (o banco tem chave única NUM_DEMANDA + EMPR_ID — DEMAND_IND_EMP_NUM_DEM_UK).
     * Se outro usuário pegar o mesmo número ao mesmo tempo, tenta o próximo.
     * O MRP roda à parte (rodarMrp), para a tela acompanhar o andamento.
     * @param array $linhas [['item_empr_id' => int, 'masc_id' => ?int, 'qtde' => float], ...] já validadas
     * @return int[] número da demanda de cada linha (mesma ordem de $linhas; 0 = item sem planejamento)
     */
    public static function criarDemanda(int $empresa, int $ccId, array $linhas): array
    {
        $chamadas = implode("\n", array_map(fn($l) => sprintf(
            '                 ins(%d, %s, %s);',
            (int) $l['item_empr_id'], $l['masc_id'] === null ? 'NULL' : (string) (int) $l['masc_id'],
            rtrim(rtrim(number_format((float) $l['qtde'], 6, '.', ''), '0'), '.')
        ), $linhas));
        $marca = (int) (self::query("SELECT NVL(MAX(ID),0) M FROM FOCCO3I.TDEMANDAS_INDEP")[0]['M'] ?? 0);
        $saida = self::plsqlTexto(
            "DECLARE
                 v_emp   NUMBER := :emp;
                 v_cc    NUMBER := :cc;
                 v_out   VARCHAR2(4000);
                 PROCEDURE ins(p_ie NUMBER, p_masc NUMBER, p_qtde NUMBER) IS
                     v_itpl NUMBER;
                     v_num  NUMBER;
                 BEGIN
                     BEGIN
                         SELECT ID INTO v_itpl FROM FOCCO3I.TITENS_PLANEJAMENTO WHERE ITEMPR_ID = p_ie AND ROWNUM = 1;
                     EXCEPTION WHEN NO_DATA_FOUND THEN
                         v_out := v_out || '0,';
                         RETURN;
                     END;
                     FOR t IN 1 .. 10 LOOP
                         SELECT NVL(MAX(NUM_DEMANDA), 0) + 1 INTO v_num FROM FOCCO3I.TDEMANDAS_INDEP WHERE EMPR_ID = v_emp;
                         BEGIN
                             INSERT INTO FOCCO3I.TDEMANDAS_INDEP (ID, NUM_DEMANDA, QUANTIDADE, DATA, SITUACAO, ITPL_ID, TMASC_ITEM_ID, EMPR_ID, EMP_CC_ID)
                             VALUES (FOCCO3I.SEQ_ID_TDEMANDAS_INDEP.NEXTVAL, v_num, p_qtde, TRUNC(SYSDATE), 'L', v_itpl, p_masc, v_emp, v_cc);
                             v_out := v_out || v_num || ',';
                             RETURN;
                         EXCEPTION WHEN DUP_VAL_ON_INDEX THEN
                             IF t = 10 THEN RAISE; END IF;
                         END;
                     END LOOP;
                 END;
             BEGIN
$chamadas
                 COMMIT;
                 :saida := RTRIM(v_out, ',');
             END;",
            ['emp' => $empresa, 'cc' => $ccId], 4000
        );
        if ($saida !== null) {
            $nums = array_map('intval', explode(',', $saida));
        } else {
            // garantia: as demandas já foram gravadas — pega os números pelas linhas que acabaram de entrar
            $r = self::query(
                "SELECT D.NUM_DEMANDA, P.ITEMPR_ID, D.TMASC_ITEM_ID FROM FOCCO3I.TDEMANDAS_INDEP D, FOCCO3I.TITENS_PLANEJAMENTO P
                  WHERE P.ID = D.ITPL_ID AND D.ID > :marca AND D.EMPR_ID = :emp AND D.EMP_CC_ID = :cc AND D.DATA = TRUNC(SYSDATE)
                  ORDER BY D.ID",
                ['marca' => $marca, 'emp' => $empresa, 'cc' => $ccId]
            );
            $nums = [];
            foreach ($linhas as $l) {
                $n = 0;
                foreach ($r as $k => $x) {
                    if ((int) $x['ITEMPR_ID'] === (int) $l['item_empr_id'] && ($x['TMASC_ITEM_ID'] === null ? null : (int) $x['TMASC_ITEM_ID']) === $l['masc_id']) {
                        $n = (int) $x['NUM_DEMANDA']; unset($r[$k]); break;
                    }
                }
                $nums[] = $n;
            }
        }
        if (count($nums) !== count($linhas) || !array_filter($nums)) {
            throw new \Exception('A demanda pode não ter sido gravada. Confira em Demandas Independentes antes de gerar de novo.', 500);
        }
        return $nums;
    }

    /** MRP do plano (FOCCO3I.FOCCO3I_JOB.MRP_AUTO, mesma chamada do OTM). Demora vários minutos. */
    public static function rodarMrp(int $empresa, int $planoId, int $mrpId): void
    {
        self::plsql(
            "BEGIN FOCCO3I.FOCCO3I_JOB.MRP_AUTO(:plano, :emp, 1, 1, :mrp); COMMIT; END;",
            ['plano' => (string) $planoId, 'emp' => $empresa, 'mrp' => $mrpId]
        );
    }

    /** Data/hora do banco (para comparar com o log do MRP). */
    public static function agoraBanco(): string
    {
        return (string) self::query("SELECT TO_CHAR(SYSDATE,'YYYY-MM-DD HH24:MI:SS') A FROM DUAL")[0]['A'];
    }

    /**
     * Andamento do MRP: etapas do plano desde o início (TLOG_MRP), cálculos vivos da empresa (TMENS_MRP —
     * a MRP_AUTO espera em ciclos de 60s enquanto houver outro cálculo da empresa) e o último cálculo do plano.
     */
    public static function andamentoMrp(int $empresa, int $planoId, string $desde): array
    {
        $etapas = self::query(
            "SELECT TO_CHAR(DATA_INICIO,'HH24:MI:SS') INICIO, TO_CHAR(DATA_FIM,'HH24:MI:SS') FIM,
                    ROUND((NVL(DATA_FIM, SYSDATE) - DATA_INICIO) * 86400) SEGUNDOS, NIVEL, DESCRICAO
               FROM FOCCO3I.TLOG_MRP
              WHERE PLANO_ID = :p AND DATA_INICIO >= TO_DATE(:d,'YYYY-MM-DD HH24:MI:SS')
              ORDER BY DATA_INICIO",
            ['p' => $planoId, 'd' => $desde]
        );
        $vivos = self::query(
            "SELECT P.ID PLANO_ID, P.COD_PLANO, P.DESCRICAO PLANO, MAX(M.DESCRICAO) ETAPA
               FROM FOCCO3I.TMENS_MRP M, FOCCO3I.TPLANOS P
              WHERE P.ID = M.PLANO_ID AND P.EMPR_ID = :e
                AND M.DESCRICAO <> 'Fim do Cálculo.'
                AND M.SESSAO IN (SELECT AUDSID FROM V\$SESSION)
              GROUP BY P.ID, P.COD_PLANO, P.DESCRICAO",
            ['e' => $empresa]
        );
        $ult = self::query("SELECT TO_CHAR(ULTIMA_GERACAO,'YYYY-MM-DD HH24:MI:SS') U FROM FOCCO3I.TPLANOS WHERE ID = :p", ['p' => $planoId]);
        return ['etapas' => $etapas, 'em_calculo' => $vivos, 'ultima_geracao' => $ult[0]['U'] ?? null];
    }

    /**
     * Ordens planejadas (OCP/OIF) que o MRP gerou para a demanda: mesmo plano, item e máscara, mesma data da
     * linha "DI" da demanda e logo depois dela no perfil (é a reposição do que a demanda consumiu).
     */
    public static function ordensDaDemanda(int $planoId, int $numDemanda, int $itemEmprId, ?int $mascId): array
    {
        $di = self::query(
            "SELECT D.ID, D.QTDE, ROUND(D.ESTQ_PROJETADO,4) PROJ, TO_CHAR(D.DT_FIM,'DD/MM/YYYY') DATA, D.ITPL_ID
               FROM FOCCO3I.TPERFIL_ITENS D, FOCCO3I.TITENS_PLANEJAMENTO ITPL
              WHERE ITPL.ITEMPR_ID = :ie AND D.ITPL_ID = ITPL.ID AND D.PLANO_ID = :p
                AND NVL(D.TMASC_ITEM_ID,0) = NVL(:m,0) AND D.TIPO_ORD_DEM = 'DI' AND D.REFERENCIA = :num",
            ['ie' => $itemEmprId, 'p' => $planoId, 'm' => $mascId, 'num' => (string) $numDemanda]
        );
        if (!$di) return ['demanda' => null, 'ordens' => []];
        $ordens = self::query(
            "SELECT P.ID PERFIL_ID, P.REFERENCIA ORDEM, P.TIPO_ORD_DEM TIPO, P.QTDE,
                    TO_CHAR(P.DT_INICIO,'DD/MM/YYYY') DT_COMPRA, TO_CHAR(P.DT_FIM,'DD/MM/YYYY') DT_ENTREGA,
                    TO_CHAR(P.DT_FIM,'YYYY-MM-DD') DT_ENTREGA_ISO, ROUND(P.ESTQ_PROJETADO,4) PROJ,
                    P.ITPL_ID, P.TMASC_ITEM_ID MASC_ID, P.EMPR_ID,
                    (SELECT MAX(L.STATUS) FROM FOCCO3I.TGAZIN_LIB_ORD_COMPRA L WHERE L.PERFIL_ITE_ID = P.ID) STATUS_LIB
               FROM FOCCO3I.TPERFIL_ITENS P, FOCCO3I.TPERFIL_ITENS D
              WHERE D.ID = :di
                AND P.PLANO_ID = D.PLANO_ID AND P.ITPL_ID = D.ITPL_ID
                AND NVL(P.TMASC_ITEM_ID,0) = NVL(D.TMASC_ITEM_ID,0)
                AND P.TIPO_ORD_DEM IN ('OCP','OIF') AND P.SITUACAO = 1 AND P.QTDE > 0
                AND P.DT_FIM = D.DT_FIM AND P.ID > D.ID
              ORDER BY P.ID",
            ['di' => (int) $di[0]['ID']]
        );
        // o MRP repõe o saldo do dia, não cada demanda: mostra todas as demandas do mesmo dia (outras DI do item)
        $doDia = self::query(
            "SELECT D.REFERENCIA NUM_DEMANDA, D.QTDE, ROUND(D.ESTQ_PROJETADO,4) PROJ
               FROM FOCCO3I.TPERFIL_ITENS D, FOCCO3I.TPERFIL_ITENS X
              WHERE X.ID = :di AND D.PLANO_ID = X.PLANO_ID AND D.ITPL_ID = X.ITPL_ID
                AND NVL(D.TMASC_ITEM_ID,0) = NVL(X.TMASC_ITEM_ID,0) AND D.DT_FIM = X.DT_FIM AND D.TIPO_ORD_DEM = 'DI'
              ORDER BY D.ID",
            ['di' => (int) $di[0]['ID']]
        );
        $antes = self::query(
            "SELECT ROUND(MAX(P.ESTQ_PROJETADO) KEEP (DENSE_RANK LAST ORDER BY P.DT_FIM, P.ID),4) PROJ
               FROM FOCCO3I.TPERFIL_ITENS P, FOCCO3I.TPERFIL_ITENS X
              WHERE X.ID = :di AND P.PLANO_ID = X.PLANO_ID AND P.ITPL_ID = X.ITPL_ID
                AND NVL(P.TMASC_ITEM_ID,0) = NVL(X.TMASC_ITEM_ID,0) AND P.DT_FIM < X.DT_FIM AND P.TIPO_ORD_DEM <> 'DIP'",
            ['di' => (int) $di[0]['ID']]
        );
        return ['demanda' => $di[0], 'ordens' => $ordens, 'demandas_dia' => $doDia, 'proj_antes' => $antes[0]['PROJ'] ?? null];
    }

    /** Linha do perfil (para validar a liberação). */
    public static function linhaPerfil(int $perfilId): ?array
    {
        $r = self::query(
            "SELECT P.ID, P.PLANO_ID, P.ITPL_ID, P.TMASC_ITEM_ID MASC_ID, P.EMPR_ID, P.TIPO_ORD_DEM TIPO, P.SITUACAO, P.REFERENCIA, P.QTDE
               FROM FOCCO3I.TPERFIL_ITENS P WHERE P.ID = :id",
            ['id' => $perfilId]
        );
        return $r[0] ?? null;
    }

    /** Quantidade ajustada ao lote mínimo/múltiplo do plano (mesma rotina da FGAZIN_SUP004). */
    public static function qtdeLote(int $empresa, int $planoId, int $itplId, ?int $mascId, float $qtde): float
    {
        $v = self::plsqlSaida(
            "DECLARE v NUMBER := TO_NUMBER(:q, '999999999990D999999', 'NLS_NUMERIC_CHARACTERS=''.,''');
             BEGIN FOCCO3I.GAZIN_SUP_VAL_LOTE_MIN_MULT(:e, :p, :i, :m, v); :saida := TO_CHAR(v, 'TM9', 'NLS_NUMERIC_CHARACTERS=''.,'''); END;",
            ['q' => rtrim(rtrim(number_format($qtde, 6, '.', ''), '0'), '.'), 'e' => $empresa, 'p' => $planoId, 'i' => $itplId, 'm' => $mascId]
        );
        return $v ?? $qtde;
    }

    /**
     * Libera a ordem planejada para o comprador — o mesmo que o botão "Liberar" da FGAZIN_SUP004:
     * grava a TGAZIN_LIB_ORD_COMPRA com STATUS 1 (Pendente de Liberação do Comprador) pelo pacote do Focco;
     * se a ordem tinha sido rejeitada pelo comprador (STATUS 0), atualiza o mesmo registro.
     * O login da tela vai para o log da tabela (F3I_LOG_TGAZIN_LIB_ORD_COMPRA), como no Focco.
     */
    public static function liberarOrdem(int $perfilId, float $qtde, string $dtEntrega, string $usuario): void
    {
        self::plsql(
            "DECLARE
                 r        FOCCO3I.TGAZIN_LIB_ORD_COMPRA%ROWTYPE;
                 v_id     NUMBER;
                 v_status NUMBER;
                 v_ok     NUMBER;
                 v_perfil NUMBER       := :perfil;
                 v_qtde   NUMBER       := TO_NUMBER(:qtde, '999999999990D999999', 'NLS_NUMERIC_CHARACTERS=''.,''');
                 v_dt     DATE         := TO_DATE(:dt, 'YYYY-MM-DD');
                 v_login  VARCHAR2(60) := :login;
             BEGIN
                 BEGIN
                     EXECUTE IMMEDIATE 'BEGIN FOCCO3I.FOCCO3I_MENU.SET_LOGIN_TELA(:1); END;' USING v_login;
                 EXCEPTION WHEN OTHERS THEN NULL;
                 END;

                 SELECT COUNT(*) INTO v_ok FROM FOCCO3I.TPERFIL_ITENS
                  WHERE ID = v_perfil AND SITUACAO = 1 AND TIPO_ORD_DEM = 'OCP';
                 IF v_ok = 0 THEN
                     RAISE_APPLICATION_ERROR(-20001, 'A ordem não está mais disponível no perfil (o MRP pode ter rodado de novo). Consulte na FGAZIN_SUP004.');
                 END IF;

                 BEGIN
                     SELECT ID, STATUS INTO v_id, v_status FROM FOCCO3I.TGAZIN_LIB_ORD_COMPRA
                      WHERE PERFIL_ITE_ID = v_perfil AND ROWNUM = 1;
                 EXCEPTION WHEN NO_DATA_FOUND THEN v_id := NULL;
                 END;
                 IF v_id IS NOT NULL AND v_status IN (1, 2) THEN
                     RAISE_APPLICATION_ERROR(-20002, 'Esta ordem já foi liberada para o comprador.');
                 END IF;

                 r.PERFIL_ITE_ID     := v_perfil;
                 r.QTDE_PLANEJADA    := v_qtde;
                 r.STATUS            := 1;
                 r.DT_ENTR_PLANEJADA := v_dt;
                 IF v_id IS NULL THEN
                     r.ID := FOCCO3I.SEQ_ID_TGAZIN_LIB_ORD_COMPRA.NEXTVAL;
                     FOCCO3I.DB_SUP_TGAZIN_LIB_ORD_COMPRA.DO_INSERT(r);
                 ELSE
                     r.ID := v_id;
                     FOCCO3I.DB_SUP_TGAZIN_LIB_ORD_COMPRA.DO_UPDATE(v_id, r);
                 END IF;
                 COMMIT;
             END;",
            ['perfil' => $perfilId, 'qtde' => rtrim(rtrim(number_format($qtde, 6, '.', ''), '0'), '.'), 'dt' => $dtEntrega,
             'login' => mb_substr(strtoupper($usuario), 0, 60)]
        );
    }

    /** Situação da liberação de ordens (para mostrar depois de liberar). */
    public static function liberacoes(array $perfilIds): array
    {
        $ids = implode(',', array_map('intval', $perfilIds)) ?: '0';
        return self::query(
            "SELECT L.ID, L.PERFIL_ITE_ID PERFIL_ID, P.REFERENCIA ORDEM, P.TIPO_ORD_DEM TIPO, L.QTDE_PLANEJADA, L.STATUS,
                    DECODE(L.STATUS, 1, 'Pendente do comprador (FGAZIN_SUP005)', 0, 'Pendente do planejador', TO_CHAR(L.STATUS)) DESC_STATUS,
                    TO_CHAR(L.DT_ENTR_PLANEJADA,'DD/MM/YYYY') DT_ENTR_PLANEJADA
               FROM FOCCO3I.TGAZIN_LIB_ORD_COMPRA L LEFT JOIN FOCCO3I.TPERFIL_ITENS P ON P.ID = L.PERFIL_ITE_ID
              WHERE L.PERFIL_ITE_ID IN ($ids)
              ORDER BY P.REFERENCIA"
        );
    }
}
