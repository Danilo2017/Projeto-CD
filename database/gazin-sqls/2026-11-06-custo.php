<?php
/**
 * SQLs do módulo Custo — gravados em FOCCO3I.GAZIN_SQLS. O sistema lê da GAZIN_SQLS, não deste arquivo.
 * Instalar (dentro do container, na raiz do projeto):
 *   php database/gazin-sqls/instalar.php database/gazin-sqls/2026-11-06-custo.php            -> simulação
 *   php database/gazin-sqls/instalar.php database/gazin-sqls/2026-11-06-custo.php --aplicar  -> grava
 *
 * Parâmetros (substituição textual do Database::switchParams):
 *   :empr_id   número (TEMPRESAS.ID), validado como inteiro no model
 *   :ano_mes   texto já entre aspas, ex. '2026-09' (validado por regex no model)
 *
 * Regras (conferidas no Focco em 06/11/2026):
 *   - compra = tipo de nota com TTIPOS_NF_ENT.TP_NF_ENT = 1; só itens com quantidade > 0.
 *   - CUSTO_COMPRA do item é o custo unitário sem impostos recuperáveis e SEM o frete, na UNIDADE DE ESTOQUE:
 *     vale por QTDE_CORRIGIDA (quantidade convertida para o estoque), não pela QTDE da nota (ex.: 720 cx = 10.080 un).
 *   - o frete entra por outra nota (da transportadora, TP_NF_ENT = 7) com quantidade 0 no item,
 *     ligada ao item da compra por TITENS_NFE.ITNFE_ID. Outras linhas de quantidade 0 ligadas ao item
 *     (despesas de importação, complementos) vão em OUTROS_CUSTO.
 *   - custo médio: TCUSTO_MEDIO por item de estoque e máscara, do mês anterior à entrada (CM_ANT) e do mês da entrada (CM_MES).
 */
return [

    'custo.entradaNf.listarItens' => ['Dashboard de Custo de Entrada: itens das notas de compra do mês com frete ligado e custo médio', <<<'SQL'
SELECT N.ID NFE_ID, N.NUM_NF, N.SERIE, TO_CHAR(N.DT_ENT, 'YYYY-MM-DD') DT_ENT,
       N.TFOR_ID FORN_ID, N.NOME FORNECEDOR, N.TP_FRETE,
       T.ID TIPO_ID, T.DESCRICAO TIPO_NF, T.ATU_CUS_MED,
       I.ID ITNFE_ID, IE.ID ITESTQ_ID, NVL(I.TMASC_ITEM_ID, 0) MASC_ID, IE.COD_ITEM, IT.DESC_TECNICA DESC_ITEM, MI.MASCARA,
       G.COD_GRP_ITE COD_GRUPO, G.DESCRICAO GRUPO,
       I.QTDE, NVL(I.QTDE_CORRIGIDA, I.QTDE) QTDE_ESTQ, I.PRECO_UNIT, I.CUSTO_COMPRA CUSTO_UNIT, I.VLR_TOTAL, NVL(I.VLR_FRETE, 0) VLR_FRETE_NF,
       FR.FRETE_CUSTO, FR.NOTAS_FRETE, FR.TRANSPORTADORA, FR.OUTROS_CUSTO,
       (SELECT CM.CUSTO_MED FROM FOCCO3I.TCUSTO_MEDIO CM
         WHERE CM.EMPR_ID = N.EMPR_ID AND CM.ITESTQ_ID = IE.ID
           AND CM.ANO_MES = ADD_MONTHS(TRUNC(N.DT_ENT, 'MM'), -1)
           AND NVL(CM.TMASC_ITEM_ID, 0) = NVL(I.TMASC_ITEM_ID, 0) AND ROWNUM = 1) CM_ANT,
       (SELECT CM.CUSTO_MED FROM FOCCO3I.TCUSTO_MEDIO CM
         WHERE CM.EMPR_ID = N.EMPR_ID AND CM.ITESTQ_ID = IE.ID
           AND CM.ANO_MES = TRUNC(N.DT_ENT, 'MM')
           AND NVL(CM.TMASC_ITEM_ID, 0) = NVL(I.TMASC_ITEM_ID, 0) AND ROWNUM = 1) CM_MES
  FROM FOCCO3I.TNFS_ENTRADA N
  JOIN FOCCO3I.TITENS_NFE I          ON I.NFE_ID = N.ID
  JOIN FOCCO3I.TTIPOS_NF_ENT T       ON T.ID = I.TTP_NF_ENT_ID
  JOIN FOCCO3I.TITENS_SUPRIMENTOS S  ON S.ID = I.ITES_ID
  JOIN FOCCO3I.TITENS_ESTOQUE IE     ON IE.ITEMPR_ID = S.ITEMPR_ID
  JOIN FOCCO3I.TITENS_EMPR TE        ON TE.ID = S.ITEMPR_ID
  JOIN FOCCO3I.TITENS IT             ON IT.ID = TE.ITEM_ID
  LEFT JOIN FOCCO3I.TGRP_CLAS_ITE G  ON G.ID = IE.GRP_CLAS_ID
  LEFT JOIN FOCCO3I.TMASC_ITEM MI    ON MI.ID = I.TMASC_ITEM_ID
  OUTER APPLY (
        SELECT SUM(CASE WHEN FT.TP_NF_ENT = 7 THEN F.CUSTO_COMPRA END) FRETE_CUSTO,
               SUM(CASE WHEN FT.TP_NF_ENT <> 7 THEN F.CUSTO_COMPRA END) OUTROS_CUSTO,
               LISTAGG(CASE WHEN FT.TP_NF_ENT = 7 THEN FN.NUM_NF END, ',') WITHIN GROUP (ORDER BY FN.NUM_NF) NOTAS_FRETE,
               MAX(CASE WHEN FT.TP_NF_ENT = 7 THEN FN.NOME END) TRANSPORTADORA
          FROM FOCCO3I.TITENS_NFE F
          JOIN FOCCO3I.TNFS_ENTRADA FN  ON FN.ID = F.NFE_ID
          JOIN FOCCO3I.TTIPOS_NF_ENT FT ON FT.ID = F.TTP_NF_ENT_ID
         WHERE F.ITNFE_ID = I.ID
           AND F.QTDE = 0
           AND NVL(FN.CANCELADA, 0) = 0
  ) FR
 WHERE N.EMPR_ID = :empr_id
   AND N.DT_ENT >= TO_DATE(:ano_mes, 'YYYY-MM')
   AND N.DT_ENT <  ADD_MONTHS(TO_DATE(:ano_mes, 'YYYY-MM'), 1)
   AND NVL(N.CANCELADA, 0) = 0
   AND T.TP_NF_ENT = 1
   AND I.QTDE > 0
 ORDER BY N.DT_ENT, N.NUM_NF, I.NUM_ITE
SQL, [':empr_id' => '1']],

    // Razão de custo do item (TCUSTO_ESTQ): cada movimentação com saldo e custo médio antes/depois e o documento de origem.
    // Ordem dentro do dia igual ao cálculo do Focco: entradas que atualizam o custo médio, outras entradas, saídas
    // (o saldo da 1ª saída do dia já inclui as entradas do dia).
    // Linhas de nota com quantidade 0 (frete/complemento) trazem a nota da mercadoria a que se referem (NF_ORIGEM).
    'custo.entradaNf.razaoItem' => ['Dashboard de Custo de Entrada: razão de custo do item (movimentações com custo médio antes e depois)', <<<'SQL'
SELECT TO_CHAR(C.DT, 'YYYY-MM-DD') DT, C.ID, C.ENT_SAI, TM.SIGLA, TM.DESCRICAO TIPO_MOV, C.IND_CUSTO_MEDIO,
       C.QTDE, C.VALOR_MOVTO, C.SALDO_QTDE, C.SALDO_VLR, C.CUSTO_MED,
       LAG(C.CUSTO_MED)  OVER (ORDER BY C.DT, CASE WHEN C.ENT_SAI = 'E' AND C.IND_CUSTO_MEDIO = 1 THEN 0 WHEN C.ENT_SAI = 'E' THEN 1 ELSE 2 END, C.ID) CM_ANTES,
       LAG(C.SALDO_QTDE) OVER (ORDER BY C.DT, CASE WHEN C.ENT_SAI = 'E' AND C.IND_CUSTO_MEDIO = 1 THEN 0 WHEN C.ENT_SAI = 'E' THEN 1 ELSE 2 END, C.ID) SALDO_ANTES,
       M.NRO_DOC, M.USUARIO, M.ALMOX_ID_ORIG, M.ALMOX_ID_DEST,
       N.NUM_NF, N.NOME EMITENTE, T.DESCRICAO TIPO_NF, T.TP_NF_ENT,
       GN.NUM_NF NF_ORIGEM, GN.NOME FORN_ORIGEM, TO_CHAR(GN.DT_ENT, 'YYYY-MM-DD') DT_ORIGEM, NVL(GI.QTDE_CORRIGIDA, GI.QTDE) QTDE_ORIGEM
  FROM FOCCO3I.TCUSTO_ESTQ C
  JOIN FOCCO3I.TTP_MOV_ESTQ TM       ON TM.ID = C.TMVES_ID
  LEFT JOIN FOCCO3I.TMOV_ESTQ M      ON M.ID = C.MOV_ESTQ_ID
  LEFT JOIN FOCCO3I.TITENS_NFE I     ON I.ID = M.ITNFE_ID
  LEFT JOIN FOCCO3I.TNFS_ENTRADA N   ON N.ID = I.NFE_ID
  LEFT JOIN FOCCO3I.TTIPOS_NF_ENT T  ON T.ID = I.TTP_NF_ENT_ID
  LEFT JOIN FOCCO3I.TITENS_NFE GI    ON GI.ID = I.ITNFE_ID AND I.QTDE = 0
  LEFT JOIN FOCCO3I.TNFS_ENTRADA GN  ON GN.ID = GI.NFE_ID
 WHERE C.EMPR_ID = :empr_id
   AND C.ITESTQ_ID = :itestq_id
   AND NVL(C.TMASC_ITEM_ID, 0) = :masc_id
   AND C.DT >= TO_DATE(:dt_ini, 'YYYY-MM-DD')
   AND C.DT <  TO_DATE(:dt_fim, 'YYYY-MM-DD') + 1
 ORDER BY C.DT, CASE WHEN C.ENT_SAI = 'E' AND C.IND_CUSTO_MEDIO = 1 THEN 0 WHEN C.ENT_SAI = 'E' THEN 1 ELSE 2 END, C.ID
SQL, [':empr_id' => '1', ':itestq_id' => '1', ':masc_id' => '0', ':dt_ini' => "'2026-09-01'", ':dt_fim' => "'2026-09-30'"]],

];
