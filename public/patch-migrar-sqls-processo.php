<?php
/**
 * Patch: migra SQLs inline do módulo Processo e PCP para focco3i.gazin_sqls
 * Uso: acesse via browser; adicione ?force=1 para recriar SQLs já existentes.
 */
require_once __DIR__ . '/../vendor/autoload.php';
\core\Database::getInstance('focco');

$sqls = [];

// ── processo.consumo_espuma.listar ──────────────────────────────────────────
$sqls['processo.consumo_espuma.listar'] = "SELECT VW.COD_ITEM,
       VW.DESC_TECNICA,
       AVG(VW.EMPRESA_1)        EMPRESA_1,
       AVG(VW.EMPRESA_2)        EMPRESA_2,
       AVG(VW.EMPRESA_3)        EMPRESA_3,
       AVG(VW.EMPRESA_4)        EMPRESA_4,
       AVG(VW.EMPRESA_5)        EMPRESA_5,
       AVG(VW.EMPRESA_13)       EMPRESA_13,
       AVG(VW.EMPRESA_14)       EMPRESA_14,
       AVG(VW.EMPRESA_15)       EMPRESA_15,
       MAX(VW.KG_REFERENCIA)    KG_REFERENCIA
  FROM VW_RATIO_DEMANDA_MOVTO2 VW
 WHERE VW.DT BETWEEN TO_DATE(':dtIni', 'DD/MM/RRRR')
                 AND TO_DATE(':dtFim', 'DD/MM/RRRR')
GROUP BY VW.COD_ITEM, VW.DESC_TECNICA
 ORDER BY VW.DESC_TECNICA ASC";

// ── processo.consumo_thermoplast.listar ─────────────────────────────────────
$sqls['processo.consumo_thermoplast.listar'] = "SELECT EMPR_ID,
       COD_ITEM,
       DESC_TECNICA,
       QTDE_ESPUMA,
       QTDE_THERMOPLAST,
       NVL(QTDE_THERMOPLAST / NULLIF(QTDE_ESPUMA, 0), 0) MEDIA_THERMOPLAST,
       CASE COD_ITEM
           WHEN '2154' THEN 1.20
           WHEN '2155' THEN 1.50
           WHEN '2156' THEN 3.00
           WHEN '2158' THEN 4.20
           WHEN '2159' THEN 4.80
           ELSE 0
       END PROJETADO_THERMOPLAS,
       CASE COD_ITEM
           WHEN '2154' THEN 2.35
           WHEN '2155' THEN 2.45
           WHEN '2156' THEN 4.00
           WHEN '2158' THEN 5.30
           WHEN '2159' THEN 6.00
           ELSE 0
       END PROJETADO_THERMOPLAS2
  FROM (
    SELECT VW.EMPR_ID,
           VW.COD_ITEM,
           VW.DESC_TECNICA,
           SUM(VW.QTDE_MOVTO)   QTDE_ESPUMA,
           SUM(VW.QTDE_DEMANDA) QTDE_THERMOPLAST
      FROM VW_MOVIMENTOS_ESTOQUE_PET_DIA2 VW
     WHERE VW.DT BETWEEN TO_DATE(':dtIni', 'DD/MM/RRRR')
                     AND TO_DATE(':dtFim', 'DD/MM/RRRR')
    GROUP BY VW.EMPR_ID, VW.COD_ITEM, VW.DESC_TECNICA
  )
 ORDER BY EMPR_ID ASC, COD_ITEM ASC";

// ── processo.mov_estoque.refugo_perda ───────────────────────────────────────
$sqls['processo.mov_estoque.refugo_perda'] = "SELECT TEMPRESAS.COD_EMP,
                       focco3i.focco3i_itens.retorna_desc_clas_item_nvl(TITENS_PLANEJAMENTO.GRP_CLAS_ID, 3) FAMILIA_ITEM,
                       TITENS.COD_ITEM    COD_ITEM_FILHO,
                       TITENS.DESC_TECNICA DESC_ITEM_FILHO,
                       SUM(CASE WHEN TCUSTO_ESTQ.ENT_SAI = 'E'
                                THEN TCUSTO_ESTQ.CUSTO_MED * TMOV_ESTQ.QTDE ELSE 0 END)              ENTRADA,
                       SUM(CASE WHEN TCUSTO_ESTQ.ENT_SAI = 'S'
                                THEN TCUSTO_ESTQ.CUSTO_MED * TMOV_ESTQ.QTDE ELSE 0 END) * -1        SAIDA,
                       SUM(CASE WHEN TCUSTO_ESTQ.ENT_SAI = 'E'
                                     THEN TCUSTO_ESTQ.CUSTO_MED * TMOV_ESTQ.QTDE
                                WHEN TCUSTO_ESTQ.ENT_SAI = 'S'
                                     THEN -(TCUSTO_ESTQ.CUSTO_MED * TMOV_ESTQ.QTDE)
                                ELSE 0 END)                                                           VALOR_TOTAL
                  FROM TMOV_ESTQ
                  JOIN TCUSTO_ESTQ         ON TCUSTO_ESTQ.MOV_ESTQ_ID   = TMOV_ESTQ.ID
                  JOIN TDEMANDAS           ON TDEMANDAS.ID               = TMOV_ESTQ.DEMANDA_ID
                  JOIN TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID     = TDEMANDAS.ITPL_ID
                  JOIN TITENS_EMPR         ON TITENS_EMPR.ID             = TITENS_PLANEJAMENTO.ITEMPR_ID
                  JOIN TITENS              ON TITENS.ID                  = TITENS_EMPR.ITEM_ID
                  JOIN TORDENS             ON TORDENS.ID                 = TDEMANDAS.ORDEM_ID
                  JOIN TITENS_PLANEJAMENTO TP2 ON TP2.ID                 = TORDENS.ITPL_ID
                  JOIN TITENS_EMPR IPR     ON IPR.ID                     = TP2.ITEMPR_ID
                  JOIN TITENS TIT2         ON TIT2.ID                    = IPR.ITEM_ID
                  JOIN TEMPRESAS           ON TEMPRESAS.ID               = TORDENS.EMPR_ID
                  JOIN TORDENS_ROT         ON TORDENS_ROT.ORDEM_ID       = TORDENS.ID
                  JOIN TOPERACAO           ON TOPERACAO.ID               = TORDENS_ROT.OPERACAO_ID
                  JOIN TORDENS_MOVTO       ON TORDENS_MOVTO.TORDEN_ROT_ID = TORDENS_ROT.ID
                 WHERE TORDENS_MOVTO.QTDE_REFUGO > 0
                   AND TMOV_ESTQ.DT BETWEEN TO_DATE(':dtIni', 'DD/MM/YYYY')
                                        AND TO_DATE(':dtFim', 'DD/MM/YYYY')
                 GROUP BY TEMPRESAS.COD_EMP,
                          focco3i.focco3i_itens.retorna_desc_clas_item_nvl(TITENS_PLANEJAMENTO.GRP_CLAS_ID, 3),
                          TITENS.COD_ITEM,
                          TITENS.DESC_TECNICA
                 ORDER BY TEMPRESAS.COD_EMP,
                          focco3i.focco3i_itens.retorna_desc_clas_item_nvl(TITENS_PLANEJAMENTO.GRP_CLAS_ID, 3),
                          TITENS.COD_ITEM";

// ── processo.mov_estoque.relatorio ──────────────────────────────────────────
$sqls['processo.mov_estoque.relatorio'] = "SELECT COD_EMP,
                       FAMILIA_ITEM,
                       COD_ITEM,
                       DESC_TECNICA,
                       MASCARA,
                       SUM(VALOR_DEBITO)  VALOR_DEBITO,
                       SUM(VALOR_CREDITO) VALOR_CREDITO,
                       SUM(VALOR_MOVTO)   VALOR_MOVTO
                  FROM (
                       SELECT TEMPRESAS.COD_EMP,
                              focco3i.focco3i_itens.retorna_desc_clas_item_nvl(TITENS_ESTOQUE.GRP_CLAS_ID, 3) FAMILIA_ITEM,
                              TITENS_ESTOQUE.COD_ITEM,
                              TITENS.DESC_TECNICA,
                              TMASC_ITEM.MASCARA,
                              CASE WHEN TMOV_ESTQ.ENT_SAI = 'E' THEN TCUSTO_ESTQ.VALOR_MOVTO ELSE 0 END              VALOR_DEBITO,
                              CASE WHEN TMOV_ESTQ.ENT_SAI = 'S' THEN TCUSTO_ESTQ.VALOR_MOVTO ELSE 0 END              VALOR_CREDITO,
                              CASE WHEN TMOV_ESTQ.ENT_SAI = 'S' THEN TCUSTO_ESTQ.VALOR_MOVTO * -1 ELSE TCUSTO_ESTQ.VALOR_MOVTO END VALOR_MOVTO
                         FROM TMOV_ESTQ,
                              TITENS_ESTOQUE,
                              TITENS_EMPR,
                              TITENS_CONTABIL,
                              TMASC_ITEM,
                              TGRP_INVENT,
                              TEMPRESAS,
                              TCUSTO_ESTQ,
                              TTP_MOV_ESTQ,
                              TITENS
                        WHERE TMOV_ESTQ.ID(+)   = TCUSTO_ESTQ.MOV_ESTQ_ID
                          AND TITENS_ESTOQUE.ID  = TMOV_ESTQ.ITESTQ_ID
                          AND TITENS_EMPR.ID     = TITENS_CONTABIL.ITEMPR_ID
                          AND TITENS_EMPR.ID     = TITENS_ESTOQUE.ITEMPR_ID
                          AND TMASC_ITEM.ID(+)   = TMOV_ESTQ.TMASC_ITEM_ID
                          AND TGRP_INVENT.ID     = TITENS_CONTABIL.GRP_INVENT_ID
                          AND TEMPRESAS.ID       = TMOV_ESTQ.EMPR_ID
                          AND TTP_MOV_ESTQ.ID    = TMOV_ESTQ.TMVES_ID
                          AND TITENS.ID          = TITENS_EMPR.ITEM_ID
                          AND TTP_MOV_ESTQ.SIGLA IN ('RTE', 'RTS')
                          AND TMOV_ESTQ.ENT_SAI  IN ('E', 'S')
                          AND TMOV_ESTQ.DT BETWEEN TO_DATE(':dtIni', 'DD/MM/YYYY')
                                               AND TO_DATE(':dtFim', 'DD/MM/YYYY')
                  )
                 GROUP BY COD_EMP, FAMILIA_ITEM, COD_ITEM, DESC_TECNICA, MASCARA
                 ORDER BY COD_EMP, FAMILIA_ITEM, COD_ITEM";

// ── processo.mov_estoque.variacao_custo ─────────────────────────────────────
$sqls['processo.mov_estoque.variacao_custo'] = "SELECT VW.EMPR_ID                                                               EMPR_ID,
       VW.COD_ITEM                                                               COD_ITEM,
       focco3i.focco3i_itens.retorna_desc_clas_item_nvl(TGRP_CLAS_ITE.id, 3)   FAMILIA_ITEM,
       VW.ITEM || ' - ' || VW.MASCARA                                            ITEM,
       VW.TMASC_ITEM_ID                                                          TMASC_ITEM_ID,
       SUM(CASE WHEN VW.DT BETWEEN TO_DATE(':dtIni','DD/MM/RRRR') AND TO_DATE(':dtFim','DD/MM/RRRR')
               THEN VW.QTDE ELSE 0 END)                                          QTDE,
       SUM(CASE WHEN VW.DT BETWEEN TO_DATE(':dtIni','DD/MM/RRRR') AND TO_DATE(':dtFim','DD/MM/RRRR')
               THEN VW.QTDE * VW.CUSTO_MED ELSE 0 END)                           CUSTO_TOTAL,
       NVL((
           SUM(CASE WHEN VW.DT BETWEEN TO_DATE(':dtIni','DD/MM/RRRR') AND TO_DATE(':dtFim','DD/MM/RRRR')
                   THEN VW.CUSTO_MED * VW.QTDE ELSE 0 END)
           / NULLIF(SUM(CASE WHEN VW.DT BETWEEN TO_DATE(':dtIni','DD/MM/RRRR') AND TO_DATE(':dtFim','DD/MM/RRRR')
                   THEN VW.QTDE ELSE 0 END), 0)
           - SUM(CASE WHEN VW.DT BETWEEN TO_DATE(':dtIniAnt','DD/MM/RRRR') AND TO_DATE(':dtFimAnt','DD/MM/RRRR')
                   THEN VW.CUSTO_MED * VW.QTDE ELSE 0 END)
           / NULLIF(SUM(CASE WHEN VW.DT BETWEEN TO_DATE(':dtIniAnt','DD/MM/RRRR') AND TO_DATE(':dtFimAnt','DD/MM/RRRR')
                   THEN VW.QTDE ELSE 0 END), 0)
       ) * SUM(CASE WHEN VW.DT BETWEEN TO_DATE(':dtIni','DD/MM/RRRR') AND TO_DATE(':dtFim','DD/MM/RRRR')
               THEN VW.QTDE ELSE 0 END), 0)                                      VAR,
       TGRP_CLAS_ITE.COD_GRP_ITE                                                 CLASSIFICACAO
  FROM TITENS_ESTOQUE    TITENS_ESTOQUE,
       TGRP_CLAS_ITE     TGRP_CLAS_ITE,
       VW_GAZIN_RAZAO_CUSTO_MED_ESTQ VW
 WHERE TGRP_CLAS_ITE.ID          = TITENS_ESTOQUE.GRP_CLAS_ID
   AND TITENS_ESTOQUE.ID         = VW.ITESTQ_ID
   AND VW.DESC_MOV_ESTQ         IN ('ENTREGA PRODUCAO PLANEJADA','REQUISICAO PLANEJADA')
   AND VW.DT BETWEEN TO_DATE(':dtIniAnt','DD/MM/RRRR') AND TO_DATE(':dtFim','DD/MM/RRRR')
   AND TGRP_CLAS_ITE.COD_GRP_ITE LIKE '10.%'
   :filtro_empr
GROUP BY VW.EMPR_ID,
         VW.COD_ITEM,
         VW.ITEM || ' - ' || VW.MASCARA,
         VW.TMASC_ITEM_ID,
         VW.DESC_MOV_ESTQ,
         TGRP_CLAS_ITE.COD_GRP_ITE,
         TGRP_CLAS_ITE.ID";

// ── processo.variacao_taxa_ggf.listar ───────────────────────────────────────
$sqls['processo.variacao_taxa_ggf.listar'] = "SELECT *
  FROM (
    SELECT
        TIPO_CC,
        COD,
        CENTRO_CUSTO,
        NVL(EMP_1,  0) AS EMP_1,
        NVL(EMP_2,  0) AS EMP_2,
        NVL(EMP_3,  0) AS EMP_3,
        NVL(EMP_4,  0) AS EMP_4,
        NVL(EMP_5,  0) AS EMP_5,
        NVL(EMP_7,  0) AS EMP_7,
        NVL(EMP_9,  0) AS EMP_9,
        NVL(EMP_10, 0) AS EMP_10,
        NVL(EMP_13, 0) AS EMP_13,
        NVL(EMP_14, 0) AS EMP_14,
        NVL(EMP_15, 0) AS EMP_15,
        NVL(EMP_16, 0) AS EMP_16
      FROM (
        SELECT
            TIPO_CC,
            COD,
            CENTRO_CUSTO,
            EMPR_ID,
            (NVL(TAXA_GGF, 0) + NVL(TAXA_MOD, 0)) AS TX_TOTAL
          FROM VW_MLC_CUSTOS_PRODUTIVOS
         WHERE MES_ANO = DATE ':dateStr'
         GROUP BY
            TIPO_CC,
            COD,
            CENTRO_CUSTO,
            EMPR_ID,
            TAXA_GGF,
            TAXA_MOD
      )
      PIVOT (
        SUM(TX_TOTAL)
        FOR EMPR_ID IN (
             1 AS EMP_1,
             2 AS EMP_2,
             3 AS EMP_3,
             4 AS EMP_4,
             5 AS EMP_5,
             7 AS EMP_7,
             9 AS EMP_9,
            10 AS EMP_10,
            13 AS EMP_13,
            14 AS EMP_14,
            15 AS EMP_15,
            16 AS EMP_16
        )
      )
  )
 WHERE
       EMP_1  <> 0
    OR EMP_2  <> 0
    OR EMP_3  <> 0
    OR EMP_4  <> 0
    OR EMP_5  <> 0
    OR EMP_7  <> 0
    OR EMP_9  <> 0
    OR EMP_10 <> 0
    OR EMP_13 <> 0
    OR EMP_14 <> 0
    OR EMP_15 <> 0
    OR EMP_16 <> 0
 ORDER BY COD";

// ── processo.variacao_nfe.listar ────────────────────────────────────────────
$sqls['processo.variacao_nfe.listar'] = "SELECT Y.*
  FROM (
    SELECT X.*,
           ROUND(((X.VALOR_ATUAL - X.VALOR_ANTERIOR)
           / NULLIF(X.VALOR_ANTERIOR, 0)) * 100, 2) PERC_VARIACAO
      FROM (
        SELECT TEMPRESAS.COD_EMP COD_EMP,
               TITENS_ESTOQUE.COD_ITEM COD_ITEM,
               TMOV_ESTQ.TMASC_ITEM_ID,
               TITENS.DESC_TECNICA DESC_TECNICA,
               TMASC_ITEM.MASCARA,
               TMOV_ESTQ.ID MOV_ID,
               TNFS_ENTRADA.NUM_NF NOTA_ATUAL,
               TMOV_ESTQ.DT DATA_ATUAL,
               TMOV_ESTQ.VALOR VALOR_ATUAL,
               TMOV_ESTQ.QTDE QTDE_ATUAL,
               LAG(TNFS_ENTRADA.NUM_NF)
               OVER(
                   PARTITION BY TITENS_ESTOQUE.COD_ITEM,
                                TMOV_ESTQ.TMASC_ITEM_ID
                   ORDER BY TMOV_ESTQ.ID
               ) NOTA_ANTERIOR,
               LAG(TMOV_ESTQ.VALOR)
               OVER(
                   PARTITION BY TITENS_ESTOQUE.COD_ITEM,
                                TMOV_ESTQ.TMASC_ITEM_ID
                   ORDER BY TMOV_ESTQ.ID
               ) VALOR_ANTERIOR,
               LAG(TMOV_ESTQ.DT)
               OVER(
                   PARTITION BY TITENS_ESTOQUE.COD_ITEM,
                                TMOV_ESTQ.TMASC_ITEM_ID
                   ORDER BY TMOV_ESTQ.ID
               ) DATA_ANTERIOR,
               LAG(TMOV_ESTQ.QTDE)
               OVER(
                   PARTITION BY TITENS_ESTOQUE.COD_ITEM,
                                TMOV_ESTQ.TMASC_ITEM_ID
                   ORDER BY TMOV_ESTQ.ID
               ) QTDE_ANTERIOR,
               LAG(TMOV_ESTQ.ID)
               OVER(
                   PARTITION BY TITENS_ESTOQUE.COD_ITEM,
                                TMOV_ESTQ.TMASC_ITEM_ID
                   ORDER BY TMOV_ESTQ.ID
               ) MOV_ID_ANTERIOR
          FROM TMOV_ESTQ TMOV_ESTQ,
               TITENS_ESTOQUE TITENS_ESTOQUE,
               TITENS_EMPR TITENS_EMPR,
               TITENS_CONTABIL TITENS_CONTABIL,
               TMASC_ITEM TMASC_ITEM,
               TGRP_INVENT TGRP_INVENT,
               TEMPRESAS TEMPRESAS,
               TCUSTO_ESTQ TCUSTO_ESTQ,
               TTP_MOV_ESTQ TTP_MOV_ESTQ,
               TITENS TITENS,
               TITENS_NFE TITENS_NFE,
               TNFS_ENTRADA TNFS_ENTRADA
         WHERE TMOV_ESTQ.ID(+) = TCUSTO_ESTQ.MOV_ESTQ_ID
           AND TITENS_ESTOQUE.ID = TMOV_ESTQ.ITESTQ_ID
           AND TITENS_EMPR.ID = TITENS_CONTABIL.ITEMPR_ID
           AND TITENS_EMPR.ID = TITENS_ESTOQUE.ITEMPR_ID
           AND TITENS_NFE.ID = TMOV_ESTQ.ITNFE_ID
           AND TNFS_ENTRADA.ID = TITENS_NFE.NFE_ID
           AND TMASC_ITEM.ID(+) = TMOV_ESTQ.TMASC_ITEM_ID
           AND TGRP_INVENT.ID = TITENS_CONTABIL.GRP_INVENT_ID
           AND TEMPRESAS.ID = TMOV_ESTQ.EMPR_ID
           AND TTP_MOV_ESTQ.ID = TMOV_ESTQ.TMVES_ID
           AND TITENS.ID = TITENS_EMPR.ITEM_ID
           AND TTP_MOV_ESTQ.SIGLA = 'NFE'
           AND TMOV_ESTQ.ENT_SAI = 'E'
           AND TMOV_ESTQ.QTDE > 0
           AND TEMPRESAS.COD_EMP = :codEmp
           AND TMOV_ESTQ.DT BETWEEN TO_DATE(':dtIni', 'DD/MM/RRRR')
                                 AND TO_DATE(':dtFim', 'DD/MM/RRRR')
      ) X
     WHERE X.QTDE_ANTERIOR > 0
  ) Y
 WHERE Y.PERC_VARIACAO <> 0
 ORDER BY Y.MOV_ID";

// ── processo.transferencia_estoque.listar_almoxarifados ─────────────────────
$sqls['processo.transferencia_estoque.listar_almoxarifados'] = "SELECT TRIM(TO_CHAR(COD_ALMOX)) AS COD_ALMOX, DESCRICAO
  FROM TALMOXARIFADOS
 WHERE EMPR_ID = :empr_id
 ORDER BY COD_ALMOX ASC";

// ── processo.transferencia_estoque.buscar_saldo ─────────────────────────────
$sqls['processo.transferencia_estoque.buscar_saldo'] = "SELECT TITENS_EMPR.EMPR_ID                                            AS EMPR_ID,
       TITENS.COD_ITEM                                                     AS COD_ITEM,
       TITENS_ENG_CONF.TMASC_ITEM_ID                                       AS ID_MASCARA,
       TITENS.DESC_TECNICA                                                  AS DESCRICAO,
       TMASC_ITEM.MASCARA                                                   AS MASCARA,
       TUNID_MED.COD_UNID_MED                                               AS UM,
       TRIM(TO_CHAR(TALMOXARIFADOS.COD_ALMOX))                             AS COD_ALMOX,
       SUM(MAN_EST_RETORNA_SALDO_ITEM(
               TITENS_EMPR.EMPR_ID,
               TITENS.ID,
               TALMOXARIFADOS.COD_ALMOX,
               TRUNC(SYSDATE),
               TITENS_ENG_CONF.TMASC_ITEM_ID))                             AS ESTOQUE
  FROM TITENS_EMPR        TITENS_EMPR,
       TITENS              TITENS,
       TITENS_ENGENHARIA   TITENS_ENGENHARIA,
       TGRP_CLAS_ITE       TGRP_CLAS_ITE,
       TITENS_ESTOQUE      TITENS_ESTOQUE,
       TALMOXARIFADOS      TALMOXARIFADOS,
       TITENS_ENG_CONF     TITENS_ENG_CONF,
       TUNID_MED           TUNID_MED,
       TMASC_ITEM          TMASC_ITEM,
       TITENS_EMPR_ESP     TITENS_EMPR_ESP
 WHERE TITENS_EMPR.ID           = TITENS_EMPR_ESP.ESPECIAL_ID(+)
   AND TITENS_EMPR.ID           = TITENS_ESTOQUE.ITEMPR_ID
   AND TITENS_EMPR.ID           = TITENS_ENGENHARIA.ITEMPR_ID
   AND TITENS.ID                = TITENS_EMPR.ITEM_ID
   AND TITENS_ENGENHARIA.ID     = TITENS_ENG_CONF.ITEG_ID(+)
   AND TGRP_CLAS_ITE.ID         = TITENS_ENGENHARIA.GRP_CLAS_ID
   AND TUNID_MED.ID             = TITENS_ESTOQUE.UNID_MED_ID
   AND TMASC_ITEM.ID(+)         = TITENS_ENG_CONF.TMASC_ITEM_ID
   AND TALMOXARIFADOS.EMPR_ID   = TITENS_EMPR.EMPR_ID
   AND TITENS_EMPR.EMPR_ID      = :empr_id
   AND TRIM(TO_CHAR(TALMOXARIFADOS.COD_ALMOX)) IN (:almox_lista)
   :filtro_cod_item
   AND MAN_EST_RETORNA_SALDO_ITEM(
               TITENS_EMPR.EMPR_ID,
               TITENS.ID,
               TALMOXARIFADOS.COD_ALMOX,
               TRUNC(SYSDATE),
               TITENS_ENG_CONF.TMASC_ITEM_ID) <> 0
 GROUP BY TITENS_EMPR.EMPR_ID,
          TITENS.COD_ITEM,
          TITENS_ENG_CONF.TMASC_ITEM_ID,
          TITENS.DESC_TECNICA,
          TMASC_ITEM.MASCARA,
          TUNID_MED.COD_UNID_MED,
          TALMOXARIFADOS.COD_ALMOX
 ORDER BY TALMOXARIFADOS.COD_ALMOX ASC,
          TITENS.COD_ITEM ASC,
          TITENS_ENG_CONF.TMASC_ITEM_ID ASC";

// ── processo.troca_almox_carga.buscar_itens ─────────────────────────────────
$sqls['processo.troca_almox_carga.buscar_itens'] = "SELECT DISTINCT
    TCARGAS.EMPR_ID,
    TCARGAS.CARGA,
    tv.NUM_PEDIDO,
    TITENS.COD_ITEM,
    TITENS.DESC_TECNICA,
    TMASC_ITEM.ID ID,
    TMASC_ITEM.MASCARA,
    TALMOXARIFADOS.COD_ALMOX,
    TALMOXARIFADOS.DESCRICAO
FROM TITENS_ESTOQUE,
     TITENS_PDV,
     TITENS_COMERCIAL,
     TITENS_EMPR,
     TITENS,
     TITENS_PLC,
     TMASC_ITEM,
     TITENS_PLANEJAMENTO,
     TALMOXARIFADOS,
     TCARGAS,
     TPEDIDOS_VENDA tv
WHERE TITENS_PDV.ID = TITENS_PLC.ITPDV_ID
  AND TITENS_COMERCIAL.ID = TITENS_PDV.ITCM_ID
  AND TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
  AND TITENS_EMPR.ID = TITENS_COMERCIAL.ITEMPR_ID
  AND TITENS_EMPR.ID = TITENS_ESTOQUE.ITEMPR_ID
  AND tv.ID = TITENS_PDV.PDV_ID
  AND TITENS.ID = TITENS_EMPR.ITEM_ID
  AND TMASC_ITEM.ID(+) = TITENS_PDV.TMASC_ITEM_ID
  AND TALMOXARIFADOS.ID = TITENS_PDV.ALMOX_ID
  AND TCARGAS.ID = TITENS_PLC.PLC_ID
  AND TCARGAS.EMPR_ID = :empr_id
  AND TCARGAS.CARGA = :carga
  :filtro_pedido
GROUP BY TCARGAS.EMPR_ID, TCARGAS.CARGA, tv.NUM_PEDIDO,
         TITENS.COD_ITEM, TITENS.DESC_TECNICA,
         TMASC_ITEM.ID, TMASC_ITEM.MASCARA,
         TALMOXARIFADOS.COD_ALMOX, TALMOXARIFADOS.DESCRICAO
ORDER BY tv.NUM_PEDIDO ASC, TITENS.COD_ITEM ASC";

// ── processo.troca_almox_carga.trocar ───────────────────────────────────────
$sqls['processo.troca_almox_carga.trocar'] = "UPDATE TITENS_PDV
SET ALMOX_ID = :almox_dest_id
WHERE ID IN (
    SELECT TITENS_PDV.ID
    FROM TITENS_PDV,
         TITENS_PLC,
         TCARGAS,
         TPEDIDOS_VENDA tv
    WHERE TITENS_PDV.ID = TITENS_PLC.ITPDV_ID
      AND tv.ID = TITENS_PDV.PDV_ID
      AND TCARGAS.ID = TITENS_PLC.PLC_ID
      AND TCARGAS.EMPR_ID = :empr_id
      AND TCARGAS.CARGA = :carga
      :filtro_pedido
)";

// ── processo.troca_almox_pedido.buscar_itens ────────────────────────────────
$sqls['processo.troca_almox_pedido.buscar_itens'] = "SELECT tv.EMPR_ID,
                       tv.NUM_PEDIDO,
                       TITENS.COD_ITEM,
                       TITENS.DESC_TECNICA,
                       TMASC_ITEM.ID         TMASC_ITEM_ID,
                       TMASC_ITEM.MASCARA,
                       TALMOXARIFADOS.ID     ALMOX_ID,
                       TALMOXARIFADOS.COD_ALMOX,
                       TALMOXARIFADOS.DESCRICAO DESCRICAO_ALMOX
                  FROM TITENS_ESTOQUE,
                       TITENS_PDV,
                       TITENS_COMERCIAL,
                       TITENS_EMPR,
                       TITENS,
                       TMASC_ITEM,
                       TITENS_PLANEJAMENTO,
                       TALMOXARIFADOS,
                       TPEDIDOS_VENDA tv
                 WHERE TITENS_COMERCIAL.ID        = TITENS_PDV.ITCM_ID
                   AND TITENS_EMPR.ID             = TITENS_PLANEJAMENTO.ITEMPR_ID
                   AND TITENS_EMPR.ID             = TITENS_COMERCIAL.ITEMPR_ID
                   AND TITENS_EMPR.ID             = TITENS_ESTOQUE.ITEMPR_ID
                   AND tv.ID                      = TITENS_PDV.PDV_ID
                   AND TITENS.ID                  = TITENS_EMPR.ITEM_ID
                   AND TMASC_ITEM.ID(+)           = TITENS_PDV.TMASC_ITEM_ID
                   AND TALMOXARIFADOS.ID          = TITENS_PDV.ALMOX_ID
                   AND tv.NUM_PEDIDO              IN (:lista_pedidos)
                   AND tv.EMPR_ID                 = :empr_id
                 GROUP BY tv.EMPR_ID, tv.NUM_PEDIDO, TITENS.COD_ITEM, TITENS.DESC_TECNICA,
                          TMASC_ITEM.ID, TMASC_ITEM.MASCARA,
                          TALMOXARIFADOS.ID, TALMOXARIFADOS.COD_ALMOX, TALMOXARIFADOS.DESCRICAO
                 ORDER BY tv.NUM_PEDIDO, TITENS.COD_ITEM";

// ── processo.troca_almox_pedido.buscar_almoxarifado ─────────────────────────
$sqls['processo.troca_almox_pedido.buscar_almoxarifado'] = "SELECT ID, COD_ALMOX, DESCRICAO
  FROM TALMOXARIFADOS
 WHERE EMPR_ID   = :empr_id
   AND COD_ALMOX = ':cod_almox'";

// ── processo.troca_almox_pedido.trocar ──────────────────────────────────────
$sqls['processo.troca_almox_pedido.trocar'] = "UPDATE TITENS_PDV
   SET ALMOX_ID = :almox_dest
 WHERE PDV_ID IN (
       SELECT ID FROM TPEDIDOS_VENDA
        WHERE NUM_PEDIDO IN (:lista_pedidos)
          AND EMPR_ID   = :empr_id
 )";

// ── pcp.relatorio_prod.vertical_espuma ──────────────────────────────────────
$sqls['pcp.relatorio_prod.vertical_espuma'] = "SELECT TABLES.ORD                       ORD,
       TORDENS.NUM_LOTE_PRO               LOTE,
       TORDENS.NUM_ORDEM                  NUM_ORDEM,
       TABLES.DESC_TECNICA                DESCRICAO,
       TABLES.MASCARA                     MASCARA,
       TABLES.QTDE_OF                     QTDE,
       TITENS.COD_ITEM                    COD_ITEM,
       TITENS.DESC_TECNICA                DESC_TECNICA,
       TMASC_ITEM.MASCARA                 MASCARA_ITEM
  FROM TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO,
       TORDENS TORDENS,
       TABLE(GAZIN_UTIL_RRP.GAZIN_COLCHOES_ESPECIAIS(PI_EMPR_ID=>TORDENS.EMPR_ID,PI_LOTE=>TORDENS.NUM_LOTE_PRO,PI_ORDEM_ID=>TORDENS.ID,PI_ORDEM=>ROWNUM)) TABLES,
       TDEMANDAS TDEMANDAS,
       TITENS_EMPR TITENS_EMPR,
       TITENS TITENS,
       TMASC_ITEM TMASC_ITEM
 WHERE TITENS_PLANEJAMENTO.ID = TDEMANDAS.ITPL_ID
   AND TORDENS.ID             = TDEMANDAS.ORDEM_ID
   AND TITENS_EMPR.ID         = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID              = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID(+)       = TDEMANDAS.TMASC_ITEM_ID
   AND TORDENS.EMPR_ID        = :empr_id
   AND TORDENS.NUM_LOTE_PRO   = :num_lote
   AND TITENS.DESC_TECNICA    LIKE 'MANTA%'
   AND NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 3)), 9999) < 1000
   AND NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 1)), 0)    > 100
ORDER BY TABLES.DESC_TECNICA ASC,
         NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 3)), 9999) ASC,
         NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 1)), 9999) ASC,
         TABLES.ORDEM ASC";

// ── pcp.relatorio_prod.horizontal_espuma ────────────────────────────────────
$sqls['pcp.relatorio_prod.horizontal_espuma'] = "SELECT TABLES.ORD                       ORD,
       TORDENS.NUM_LOTE_PRO               LOTE,
       TORDENS.NUM_ORDEM                  NUM_ORDEM,
       TABLES.DESC_TECNICA                DESCRICAO,
       TABLES.MASCARA                     MASCARA,
       TABLES.QTDE_OF                     QTDE,
       TITENS.COD_ITEM                    COD_ITEM,
       TITENS.DESC_TECNICA                DESC_TECNICA,
       TMASC_ITEM.MASCARA                 MASCARA_ITEM
  FROM TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO,
       TORDENS TORDENS,
       TABLE(GAZIN_UTIL_RRP.GAZIN_COLCHOES_ESPECIAIS(PI_EMPR_ID=>TORDENS.EMPR_ID,PI_LOTE=>TORDENS.NUM_LOTE_PRO,PI_ORDEM_ID=>TORDENS.ID,PI_ORDEM=>ROWNUM)) TABLES,
       TDEMANDAS TDEMANDAS,
       TITENS_EMPR TITENS_EMPR,
       TITENS TITENS,
       TMASC_ITEM TMASC_ITEM
 WHERE TITENS_PLANEJAMENTO.ID = TDEMANDAS.ITPL_ID
   AND TORDENS.ID             = TDEMANDAS.ORDEM_ID
   AND TITENS_EMPR.ID         = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID              = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID(+)       = TDEMANDAS.TMASC_ITEM_ID
   AND TORDENS.EMPR_ID        = :empr_id
   AND TORDENS.NUM_LOTE_PRO   = :num_lote
   AND TITENS.DESC_TECNICA    LIKE 'MANTA%'
   AND (  NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 3)), 0)    >= 1000
       OR NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 1)), 9999) <= 100 )
ORDER BY NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 2)), 9999) ASC,
         NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 3)), 9999) ASC,
         NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 1)), 9999) ASC,
         TABLES.ORDEM ASC";

// ── pcp.relatorio_prod.pcp_molas ────────────────────────────────────────────
$sqls['pcp.relatorio_prod.pcp_molas'] = "SELECT DISTINCT
    TEMPRESAS.COD_EMP||'-'||TEMPRESAS.RAZAO_SOCIAL EMPRESA,
    TORDENS.NUM_LOTE_PRO NUM_LOTE_PRO,
    TORDENS.NUM_ORDEM NUM_ORDEM,
    TO_CHAR(TORDENS.DT_INICIAL, 'DD/MM/RR') DT_INICIAL,
    TITENS.COD_ITEM COD_ITEM,
    TITENS.DESC_TECNICA DESC_TECNICA,
    TMASC_ITEM.ID ID,
    TMASC_ITEM.MASCARA MASCARA,
    TORDENS.QTDE QTDE_OF,
    TORDENS.QTDE_ENTREGUE QTDE_ENTREG,
    TORDENS.QTDE_PENDENTE QTDE_PEND,
    TGAZIN_ADMIN_TANQUE_MOLA.COD_ITEM COD_ITEM_MOLA,
    TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA DESC_MOLA,
    TORDENS.QTDE * TGAZIN_ADMIN_TANQUE_MOLA.QTDE_TOT TOTAL_MOLINHA,
    NULL G_TOTAL_GERAL
FROM TCENTROS_TRAB, TEMPRESAS, TORDENS, TITENS_PLANEJAMENTO,
     TITENS_EMPR, TITENS, TMASC_ITEM, TORDENS_ROT,
     TDEMANDAS_FAN, TGAZIN_ADMIN_TANQUE_MOLA
WHERE TGAZIN_ADMIN_TANQUE_MOLA.PAI_ITEMPR_ID(+) = TITENS_EMPR.ID
  AND TCENTROS_TRAB.ID = TORDENS_ROT.CENTR_TRAB_ID
  AND TEMPRESAS.ID = TORDENS.EMPR_ID
  AND TORDENS.ID = TDEMANDAS_FAN.ORDEM_ID(+)
  AND TORDENS.ID = TORDENS_ROT.ORDEM_ID
  AND TITENS_PLANEJAMENTO.ID = TDEMANDAS_FAN.ITPL_ID(+)
  AND TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
  AND TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
  AND TITENS.ID = TITENS_EMPR.ITEM_ID
  AND TMASC_ITEM.ID = TDEMANDAS_FAN.TMASC_ITEM_ID(+)
  AND TMASC_ITEM.ID(+) = TORDENS.TMASC_ITEM_ID
  AND TORDENS.EMPR_ID = :empr_id
  AND TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA LIKE '%MOLA ENSACADA INDIVIDUALMENTE%'
  AND TORDENS.NUM_LOTE_PRO = :num_lote
  AND TCENTROS_TRAB.COD_CENTRO IN ('15.007.1','15.014.1')
ORDER BY TO_CHAR(TORDENS.DT_INICIAL, 'DD/MM/RR') ASC,
         TITENS.COD_ITEM ASC,
         TORDENS.NUM_ORDEM ASC";

// ── pcp.relatorio_prod.pcp_cordao ───────────────────────────────────────────
$sqls['pcp.relatorio_prod.pcp_cordao'] = "SELECT DISTINCT
    TEMPRESAS.COD_EMP||'-'||TEMPRESAS.RAZAO_SOCIAL EMPRESA,
    TORDENS.NUM_LOTE_PRO NUM_LOTE_PRO,
    TORDENS.NUM_ORDEM NUM_ORDEM,
    TO_CHAR(TORDENS.DT_INICIAL, 'DD/MM/RR') DT_INICIAL,
    TITENS.COD_ITEM COD_ITEM,
    TITENS.DESC_TECNICA DESC_TECNICA,
    TMASC_ITEM.ID ID,
    TMASC_ITEM.MASCARA MASCARA,
    TORDENS.QTDE QTDE_OF,
    TORDENS.QTDE_ENTREGUE QTDE_ENTREG,
    TORDENS.QTDE_PENDENTE QTDE_PEND,
    TGAZIN_ADMIN_TANQUE_MOLA.COD_ITEM COD_ITEM_CORDAO,
    TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA DESC_CORDAO,
    TMASC_ITEM1.MASCARA MASCARA_CORDAO,
    TORDENS.QTDE * TGAZIN_ADMIN_TANQUE_MOLA.QTDE_TOT TOTAL_MOLINHA,
    NULL G_TOTAL_GERAL
FROM TCENTROS_TRAB,
     TEMPRESAS,
     TORDENS,
     TITENS_PLANEJAMENTO,
     TITENS_EMPR,
     TITENS,
     TMASC_ITEM,
     TMASC_ITEM TMASC_ITEM1,
     TORDENS_ROT,
     TDEMANDAS_FAN,
     TGAZIN_ADMIN_TANQUE_MOLA
WHERE TGAZIN_ADMIN_TANQUE_MOLA.PAI_ITEMPR_ID(+) = TITENS_EMPR.ID
  AND TCENTROS_TRAB.ID = TORDENS_ROT.CENTR_TRAB_ID
  AND TEMPRESAS.ID = TORDENS.EMPR_ID
  AND TMASC_ITEM1.ID = TGAZIN_ADMIN_TANQUE_MOLA.TMASC_ITEM_ID
  AND TORDENS.ID = TDEMANDAS_FAN.ORDEM_ID(+)
  AND TORDENS.ID = TORDENS_ROT.ORDEM_ID
  AND TITENS_PLANEJAMENTO.ID = TDEMANDAS_FAN.ITPL_ID(+)
  AND TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
  AND TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
  AND TITENS.ID = TITENS_EMPR.ITEM_ID
  AND TMASC_ITEM.ID = TDEMANDAS_FAN.TMASC_ITEM_ID(+)
  AND TMASC_ITEM.ID(+) = TORDENS.TMASC_ITEM_ID
  AND TORDENS.EMPR_ID = :empr_id
  AND TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA LIKE '%CORDAO%'
  AND TORDENS.NUM_LOTE_PRO = :num_lote
  AND TCENTROS_TRAB.COD_CENTRO IN ('15.007.1','15.014.1')
ORDER BY TO_CHAR(TORDENS.DT_INICIAL, 'DD/MM/RR') ASC,
         TITENS.COD_ITEM ASC,
         TORDENS.NUM_ORDEM ASC";

// ── pcp.relatorio_prod.pcp_tampo ────────────────────────────────────────────
$sqls['pcp.relatorio_prod.pcp_tampo'] = "SELECT
    TORDENS.NUM_LOTE_PRO                              NUM_LOTE_PRO,
    TORDENS.NUM_ORDEM                                 NUM_ORDEM,
    TO_CHAR(TORDENS.DT_INICIAL, 'DD/MM/RR')           DT_INICIAL,
    TITENS.COD_ITEM                                   COD_ITEM,
    TITENS.DESC_TECNICA                               DESC_TECNICA,
    TMASC_ITEM.MASCARA                                MASCARA,
    TORDENS.QTDE                                      QTDE_OF,
    TGAZIN_ADMIN_TANQUE_MOLA.COD_ITEM                 COD_ITEM_TAMPO,
    TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA             DESC_TAMPO,
    TMASC_ITEM1.MASCARA                               MASCARA_TAMPO,
    TORDENS.QTDE * TGAZIN_ADMIN_TANQUE_MOLA.QTDE_TOT  TOTAL_TAMPO,
    NULL                                              G_TOTAL_GERAL
  FROM TCENTROS_TRAB,
       TEMPRESAS,
       TORDENS,
       TITENS_PLANEJAMENTO,
       TITENS_EMPR,
       TITENS,
       TMASC_ITEM,
       TMASC_ITEM TMASC_ITEM1,
       TORDENS_ROT,
       TDEMANDAS_FAN,
       TGAZIN_ADMIN_TANQUE_MOLA
 WHERE TGAZIN_ADMIN_TANQUE_MOLA.PAI_ITEMPR_ID(+) = TITENS_EMPR.ID
   AND TCENTROS_TRAB.ID                           = TORDENS_ROT.CENTR_TRAB_ID
   AND TEMPRESAS.ID                               = TORDENS.EMPR_ID
   AND TMASC_ITEM1.ID(+)                          = TGAZIN_ADMIN_TANQUE_MOLA.TMASC_ITEM_ID
   AND TORDENS.ID                                 = TDEMANDAS_FAN.ORDEM_ID(+)
   AND TORDENS.ID                                 = TORDENS_ROT.ORDEM_ID
   AND TITENS_PLANEJAMENTO.ID                     = TDEMANDAS_FAN.ITPL_ID(+)
   AND TITENS_PLANEJAMENTO.ID                     = TORDENS.ITPL_ID
   AND TITENS_EMPR.ID                             = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID                                  = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID                              = TDEMANDAS_FAN.TMASC_ITEM_ID(+)
   AND TMASC_ITEM.ID(+)                           = TORDENS.TMASC_ITEM_ID
   AND TORDENS.EMPR_ID                            = :empr_id
   AND TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA      LIKE '%TAMPO%'
   AND TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA      NOT LIKE '%FANTASMA%'
   AND TORDENS.NUM_LOTE_PRO                       = :num_lote
   AND TCENTROS_TRAB.COD_CENTRO                   IN ('15.007.1','15.014.1')
ORDER BY TO_CHAR(TORDENS.DT_INICIAL, 'DD/MM/RR') ASC,
         TITENS.COD_ITEM ASC,
         TORDENS.NUM_ORDEM ASC";

// ── pcp.relatorio_prod.pcp_expedicao_rolo ───────────────────────────────────
$sqls['pcp.relatorio_prod.pcp_expedicao_rolo'] = "SELECT
    TORDENS.NUM_LOTE_PRO                        NUM_LOTE_PRO,
    TORDENS.NUM_ORDEM                           NUM_ORDEM,
    TO_CHAR(TORDENS.DT_INICIAL, 'DD/MM/RR')     DT_INICIAL,
    TITENS.COD_ITEM                             COD_ITEM,
    TITENS.DESC_TECNICA                         DESC_TECNICA,
    TMASC_ITEM.MASCARA                          MASCARA,
    TORDENS.QTDE                                QTDE_OF,
    TORDENS.QTDE / TC.FAT_CONV_VOL              QTE_ROLO,
    NULL                                        G_TOTAL_GERAL
  FROM TCENTROS_TRAB,
       TEMPRESAS,
       TORDENS,
       TITENS_PLANEJAMENTO,
       TITENS_EMPR,
       TITENS,
       TMASC_ITEM,
       TMASC_ITEM TMASC_ITEM1,
       TORDENS_ROT,
       TDEMANDAS_FAN,
       TGAZIN_ADMIN_TANQUE_MOLA,
       TITENS_COMERCIAL TC
 WHERE TGAZIN_ADMIN_TANQUE_MOLA.PAI_ITEMPR_ID(+) = TITENS_EMPR.ID
   AND TC.ITEMPR_ID                              = TITENS_EMPR.ID
   AND TCENTROS_TRAB.ID                          = TORDENS_ROT.CENTR_TRAB_ID
   AND TEMPRESAS.ID                              = TORDENS.EMPR_ID
   AND TMASC_ITEM1.ID(+)                         = TGAZIN_ADMIN_TANQUE_MOLA.TMASC_ITEM_ID
   AND TORDENS.ID                                = TDEMANDAS_FAN.ORDEM_ID(+)
   AND TORDENS.ID                                = TORDENS_ROT.ORDEM_ID
   AND TITENS_PLANEJAMENTO.ID                    = TDEMANDAS_FAN.ITPL_ID(+)
   AND TITENS_PLANEJAMENTO.ID                    = TORDENS.ITPL_ID
   AND TITENS_EMPR.ID                            = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID                                 = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID                             = TDEMANDAS_FAN.TMASC_ITEM_ID(+)
   AND TMASC_ITEM.ID(+)                          = TORDENS.TMASC_ITEM_ID
   AND TORDENS.EMPR_ID                           = :empr_id
   AND TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA     LIKE '%MOLA ENSACADA INDIVIDUALMENTE%'
   AND TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA     NOT LIKE '%FANTASMA%'
   AND TORDENS.NUM_LOTE_PRO                      = :num_lote
   AND TCENTROS_TRAB.COD_CENTRO                  IN ('15.007.1','15.014.1')
ORDER BY TO_CHAR(TORDENS.DT_INICIAL, 'DD/MM/RR') ASC,
         TITENS.COD_ITEM ASC,
         TORDENS.NUM_ORDEM ASC";

// ── pcp.relatorio_prod.pcp_borda_aco ────────────────────────────────────────
$sqls['pcp.relatorio_prod.pcp_borda_aco'] = "SELECT
    TORDENS.NUM_LOTE_PRO                              NUM_LOTE_PRO,
    TORDENS.NUM_ORDEM                                 NUM_ORDEM,
    TO_CHAR(TORDENS.DT_INICIAL, 'DD/MM/RR')           DT_INICIAL,
    TITENS.COD_ITEM                                   COD_ITEM,
    TITENS.DESC_TECNICA                               DESC_TECNICA,
    TMASC_ITEM.MASCARA                                MASCARA,
    TORDENS.QTDE                                      QTDE_OF,
    TGAZIN_ADMIN_TANQUE_MOLA.COD_ITEM                 COD_ITEM_BORDA,
    TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA             DESC_BORDA,
    TMASC_ITEM1.MASCARA                               MASCARA_BORDA,
    TORDENS.QTDE * TGAZIN_ADMIN_TANQUE_MOLA.QTDE_TOT  TOTAL_BORDA,
    NULL                                              G_TOTAL_GERAL
  FROM TCENTROS_TRAB,
       TEMPRESAS,
       TORDENS,
       TITENS_PLANEJAMENTO,
       TITENS_EMPR,
       TITENS,
       TMASC_ITEM,
       TMASC_ITEM TMASC_ITEM1,
       TORDENS_ROT,
       TDEMANDAS_FAN,
       TGAZIN_ADMIN_TANQUE_MOLA
 WHERE TGAZIN_ADMIN_TANQUE_MOLA.PAI_ITEMPR_ID(+) = TITENS_EMPR.ID
   AND TCENTROS_TRAB.ID                           = TORDENS_ROT.CENTR_TRAB_ID
   AND TEMPRESAS.ID                               = TORDENS.EMPR_ID
   AND TMASC_ITEM1.ID(+)                          = TGAZIN_ADMIN_TANQUE_MOLA.TMASC_ITEM_ID
   AND TORDENS.ID                                 = TDEMANDAS_FAN.ORDEM_ID(+)
   AND TORDENS.ID                                 = TORDENS_ROT.ORDEM_ID
   AND TITENS_PLANEJAMENTO.ID                     = TDEMANDAS_FAN.ITPL_ID(+)
   AND TITENS_PLANEJAMENTO.ID                     = TORDENS.ITPL_ID
   AND TITENS_EMPR.ID                             = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID                                  = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID                              = TDEMANDAS_FAN.TMASC_ITEM_ID(+)
   AND TMASC_ITEM.ID(+)                           = TORDENS.TMASC_ITEM_ID
   AND TORDENS.EMPR_ID                            = :empr_id
   AND TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA      LIKE '%BORDA AÇO%'
   AND TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA      NOT LIKE '%FANTASMA%'
   AND TORDENS.NUM_LOTE_PRO                       = :num_lote
   AND TCENTROS_TRAB.COD_CENTRO                   IN ('15.007.1','15.014.1')
ORDER BY TO_CHAR(TORDENS.DT_INICIAL, 'DD/MM/RR') ASC,
         TITENS.COD_ITEM ASC,
         TORDENS.NUM_ORDEM ASC";

// ════════════════════════════════════════════════════════════════════════════
// Execução
// ════════════════════════════════════════════════════════════════════════════

$tns  = "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=" . FOCCO_HOST .
        ")(PORT=" . FOCCO_PORT . ")))(CONNECT_DATA=(SERVICE_NAME=" . FOCCO_DATABASE . ")))";
$conn = oci_connect(FOCCO_USER, FOCCO_PASS, $tns, 'AL32UTF8');
if (!$conn) {
    $e = oci_error();
    echo json_encode(['ok' => false, 'msg' => 'oci8 connect failed: ' . $e['message']], JSON_UNESCAPED_UNICODE);
    exit;
}

$force      = isset($_GET['force']);
$resultados = [];

foreach ($sqls as $idsql => $sqlNovo) {
    $stmChk = oci_parse($conn, "SELECT COUNT(*) CNT FROM focco3i.gazin_sqls WHERE idsql = :id");
    oci_bind_by_name($stmChk, ':id', $idsql, -1, SQLT_CHR);
    oci_execute($stmChk);
    $rowChk = oci_fetch_assoc($stmChk);
    $existe  = (int)($rowChk['CNT'] ?? 0) > 0;
    oci_free_statement($stmChk);

    if ($existe && !$force) {
        $resultados[] = ['idsql' => $idsql, 'ok' => false, 'msg' => 'já existe — use ?force=1 para recriar'];
        continue;
    }

    $clob = oci_new_descriptor($conn, OCI_D_LOB);
    if ($existe) {
        $dml  = "UPDATE focco3i.gazin_sqls SET sql = EMPTY_CLOB() WHERE idsql = :id RETURNING sql INTO :lob";
        $acao = 'atualizado';
    } else {
        $dml  = "INSERT INTO focco3i.gazin_sqls (idsql, sql) VALUES (:id, EMPTY_CLOB()) RETURNING sql INTO :lob";
        $acao = 'inserido';
    }
    $stm = oci_parse($conn, $dml);
    oci_bind_by_name($stm, ':id',  $idsql, -1, SQLT_CHR);
    oci_bind_by_name($stm, ':lob', $clob,  -1, OCI_B_CLOB);
    oci_execute($stm, OCI_NO_AUTO_COMMIT);
    $clob->save($sqlNovo);
    oci_commit($conn);
    $clob->free();
    oci_free_statement($stm);

    $resultados[] = ['idsql' => $idsql, 'ok' => true, 'msg' => $acao];
}

oci_close($conn);

/* Limpa cache */
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'focco_sql_cache';
if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $f) { unlink($f); }
}

$total = count($sqls);
$ok    = count(array_filter($resultados, fn($r) => $r['ok']));
echo json_encode([
    'ok'       => $ok === $total,
    'msg'      => "$ok/$total SQLs processados com sucesso.",
    'detalhes' => $resultados,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
