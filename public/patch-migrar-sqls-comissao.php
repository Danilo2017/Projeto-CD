<?php
/**
 * Patch: migra SQLs inline dos módulos Comissao, PD e CD para focco3i.gazin_sqls
 * Uso: acesse via browser; adicione ?force=1 para recriar SQLs já existentes.
 */
require_once __DIR__ . '/../vendor/autoload.php';
\core\Database::getInstance('focco');

$sqls = [];

// ════════════════════════════════════════════════════════════════════════════
// Comissao — ApontamentoProducao
// ════════════════════════════════════════════════════════════════════════════

$sqls['comissao.apontamento.cache_pontuacao'] = "SELECT PP.ID_PONTUACAO, PP.ID_EMPR, PP.ITEM_ID, PP.ID_ITEMPR,
       PP.ID_MASCARA, PP.ID_CENTRO_TRAB, PP.PONTOS_UP
  FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
 WHERE PP.ATIVO = 'S'
   AND PP.DT_VIGENCIA_INI <= TO_DATE(':hoje', 'YYYY-MM-DD')
   AND (PP.DT_VIGENCIA_FIM IS NULL
        OR PP.DT_VIGENCIA_FIM >= TO_DATE(':hoje', 'YYYY-MM-DD'))";

// ════════════════════════════════════════════════════════════════════════════
// Comissao — Vinculo
// ════════════════════════════════════════════════════════════════════════════

$sqls['comissao.vinculo.atualizar_com_cc'] = "UPDATE FOCCO3I.TGAZIN_VINC_FUNC
SET ID_CENTRO_TRAB = :id_centro_trab,
    ID_RECURSO     = :id_recurso,
    TIPO_VINCULO   = :tipo_vinculo,
    ID_EMP_CC      = :id_emp_cc
WHERE ID = :id";

$sqls['comissao.vinculo.atualizar_sem_cc'] = "UPDATE FOCCO3I.TGAZIN_VINC_FUNC
SET ID_CENTRO_TRAB = :id_centro_trab,
    ID_RECURSO     = :id_recurso,
    TIPO_VINCULO   = :tipo_vinculo
WHERE ID = :id";

$sqls['comissao.vinculo.alterar_status'] = "UPDATE FOCCO3I.TGAZIN_VINC_FUNC SET ATIVO = :ativo WHERE ID = :id";

// ════════════════════════════════════════════════════════════════════════════
// Comissao — PontuacaoProduto
// ════════════════════════════════════════════════════════════════════════════

$sqlListagemBase = "SELECT
    PP.ID_PONTUACAO,
    TE.COD_EMP,
    TI.COD_ITEM,
    TI.DESC_TECNICA    AS DESC_ITEM,
    PP.ID_MASCARA,
    TMI.MASCARA,
    TCT.COD_CENTRO,
    TCT.DESCRICAO      AS DESC_CENTRO,
    PP.ID_CENTRO_TRAB,
    PP.PONTOS_UP,
    TO_CHAR(PP.DT_VIGENCIA_INI, 'YYYY-MM-DD') AS DT_VIGENCIA_INI,
    TO_CHAR(PP.DT_VIGENCIA_FIM, 'YYYY-MM-DD') AS DT_VIGENCIA_FIM,
    PP.ATIVO,
    PP.ITEM_ID,
    PP.ID_ITEMPR
FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
LEFT JOIN TITENS         TI  ON TI.ID  = PP.ITEM_ID
LEFT JOIN TMASC_ITEM     TMI ON TMI.ID = PP.ID_MASCARA
LEFT JOIN TCENTROS_TRAB  TCT ON TCT.ID = PP.ID_CENTRO_TRAB
LEFT JOIN TEMPRESAS      TE  ON TE.ID  = PP.ID_EMPR";

$sqls['comissao.pontuacao.listar_ativas'] = $sqlListagemBase . "
WHERE PP.ATIVO = 'S'
:filtro_empr
:filtro_centro
ORDER BY PP.ID_PONTUACAO DESC";

$sqls['comissao.pontuacao.listar_todas'] = $sqlListagemBase . "
WHERE 1=1
:filtro_empr
ORDER BY PP.ID_PONTUACAO DESC";

$sqls['comissao.pontuacao.buscar_por_id'] = $sqlListagemBase . "
WHERE PP.ID_PONTUACAO = :id";

$sqls['comissao.pontuacao.buscar_vigente'] = "SELECT PP.ID_PONTUACAO, PP.PONTOS_UP, PP.ID_CENTRO_TRAB, PP.ID_MASCARA, PP.ITEM_ID, PP.ID_ITEMPR
FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
WHERE PP.ITEM_ID = :item_id
  AND PP.ATIVO = 'S'
  AND PP.DT_VIGENCIA_INI <= TO_DATE(':data_ref',  'YYYY-MM-DD')
  AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TO_DATE(':data_ref2', 'YYYY-MM-DD'))
  :filtro_centro
ORDER BY PP.ID_CENTRO_TRAB NULLS LAST
FETCH FIRST 1 ROW ONLY";

$sqls['comissao.pontuacao.proximo_id'] = "SELECT NVL(MAX(ID_PONTUACAO), 0) + 1 AS ID FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO";

$sqls['comissao.pontuacao.mapa_vigentes'] = "SELECT PP.ID_MASCARA, PP.ITEM_ID, TI.COD_ITEM, PP.PONTOS_UP
FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
LEFT JOIN TITENS TI ON TI.ID = PP.ITEM_ID
WHERE PP.ATIVO = 'S'
  AND PP.DT_VIGENCIA_INI <= TO_DATE(':hoje', 'YYYY-MM-DD')
  AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TO_DATE(':hoje2', 'YYYY-MM-DD'))
  :filtro_empr";

$sqls['comissao.pontuacao.buscar_duplicata'] = "SELECT PP.ID_PONTUACAO
FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
WHERE PP.ITEM_ID  = :item_id
  AND PP.ID_EMPR  = :empr_id
  :filtro_mascara
  :filtro_centro";

$sqls['comissao.pontuacao.inserir'] = "INSERT INTO FOCCO3I.TGAZIN_PONTUACAO_PRODUTO
    (ID_PONTUACAO, ID_EMPR, ITEM_ID, ID_ITEMPR, ID_MASCARA, ID_CENTRO_TRAB,
     PONTOS_UP, DT_VIGENCIA_INI, DT_VIGENCIA_FIM, ATIVO)
VALUES
    (:novo_id, :empr_id, :item_id, :itempr_id, :mascara_id, :centro_id,
     :pontos_up, TO_DATE(':dt_ini', 'YYYY-MM-DD'), :dt_fim_frag, 'S')";

$sqls['comissao.pontuacao.atualizar'] = "UPDATE FOCCO3I.TGAZIN_PONTUACAO_PRODUTO
SET PONTOS_UP        = :pontos_up,
    DT_VIGENCIA_INI  = TO_DATE(':dt_ini', 'YYYY-MM-DD'),
    DT_VIGENCIA_FIM  = :dt_fim_frag
WHERE ID_PONTUACAO = :id";

$sqls['comissao.pontuacao.alterar_status'] = "UPDATE FOCCO3I.TGAZIN_PONTUACAO_PRODUTO
SET ATIVO = ':ativo'
WHERE ID_PONTUACAO = :id";

$sqls['comissao.pontuacao.relatorio_itens'] = "SELECT DISTINCT
       TITENS_EMPR.EMPR_ID,
       TITENS.COD_ITEM,
       TITENS.DESC_TECNICA,
       TMASC_ITEM.MASCARA,
       TMASC_ITEM.ID TMASC_ITEM_ID,
       NVL(TITENS_PLAN_CONF.UEP, TITENS_PLANEJAMENTO.UEP) UEP,
       (SELECT DESCRICAO  FROM TTANQUES WHERE ID = NVL(TITENS_PLAN_CONF.TANQUE_ID, TITENS_PLANEJAMENTO.TANQUE_ID)) TANQUE,
       (SELECT COD_TANQUE FROM TTANQUES WHERE ID = NVL(TITENS_PLAN_CONF.TANQUE_ID, TITENS_PLANEJAMENTO.TANQUE_ID)) COD_TANQUE
  FROM TITENS_EMPR TITENS_EMPR,
       TITENS TITENS,
       TMASC_ITEM TMASC_ITEM,
       TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO,
       TITENS_PLAN_CONF TITENS_PLAN_CONF,
       TGRP_CLAS_ITE TGRP_CLAS_ITE,
       TITENS_COMERCIAL TITENS_COMERCIAL,
       TGRP_CLAS_ITE TGRP_CLAS_ITE1,
       TCLAS_AGRUP_METAS TCLAS_AGRUP_METAS,
       TAGRUP_METAS TAGRUP_METAS
 WHERE TITENS_EMPR.ID = TITENS_COMERCIAL.ITEMPR_ID
   AND TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID(+) = TITENS_PLAN_CONF.TMASC_ITEM_ID
   AND TITENS_PLANEJAMENTO.ID = TITENS_PLAN_CONF.ITPL_ID(+)
   AND TGRP_CLAS_ITE.ID = TITENS_PLANEJAMENTO.GRP_CLAS_ID
   AND TGRP_CLAS_ITE1.ID = TCLAS_AGRUP_METAS.GRP_CLAS_ID(+)
   AND TGRP_CLAS_ITE1.ID = TITENS_COMERCIAL.GRP_CLAS_ID
   AND TAGRUP_METAS.ID(+) = TCLAS_AGRUP_METAS.TAGRUP_MET_ID
   AND TITENS_EMPR.EMPR_ID = :empr_id
   :filtro_item
   :filtro_mascara
ORDER BY TITENS.COD_ITEM DESC";

// ════════════════════════════════════════════════════════════════════════════
// PD — InativacaoPreco
// ════════════════════════════════════════════════════════════════════════════

$sqls['pd.inativacao_preco.buscar_itens'] = "SELECT TE.EMPR_ID                           EMPR_ID,
       T.COD_ITEM                             COD_ITEM,
       TI.ID                                  TMASC_ITEM_ID,
       T.DESC_TECNICA                         DESC_TECNICA,
       TI.MASCARA                             MASCARA
  FROM TITENS_EMPR TE,
       TITENS T,
       TITENS_ENGENHARIA TE2,
       TITENS_ENG_CONF TEC,
       TMASC_ITEM TI
 WHERE TI.ID          = TEC.TMASC_ITEM_ID
   AND TE2.ID         = TEC.ITEG_ID
   AND TE2.ITEMPR_ID  = TE.ID
   AND TE.ITEM_ID     = T.ID
   AND TE.EMPR_ID     = :empr_id
   AND TE.COD_ITEM    = :cod_item
   AND TE.SIT         = 1
   AND TI.SIT         = 1
ORDER BY TI.MASCARA ASC";

$sqls['pd.inativacao_preco.inativar'] = "UPDATE TPRECOSVEN_IT
   SET SIT = 0, PRECO = 0
 WHERE SIT = 1
   AND TMASC_ITEM_ID = :tmasc_item_id
   AND ITCM_ID IN (
       SELECT TC.ID
         FROM TITENS_COMERCIAL TC,
              TITENS_EMPR TE,
              TITENS T
        WHERE TC.ITEMPR_ID = TE.ID
          AND TE.ITEM_ID   = T.ID
          AND TE.EMPR_ID   = :empr_id
          AND T.COD_ITEM   = :cod_item
   )";

$sqls['pd.inativacao_preco.verificar_duplicata'] = "SELECT COUNT(*) QTD FROM TGAZIN_PD_INATIV_PRECO
WHERE EMPR_ID = :empr_id AND COD_ITEM = :cod_item AND TMASC_ITEM_ID = :tmasc_item_id";

$sqls['pd.inativacao_preco.inserir_monitoramento'] = "INSERT INTO TGAZIN_PD_INATIV_PRECO
    (ID, EMPR_ID, COD_ITEM, TMASC_ITEM_ID, DESC_TECNICA, MASCARA, DT_CADASTRO, SIT)
VALUES (SEQ_TGAZIN_PD_INATIV.NEXTVAL, :empr_id, :cod_item, :tmasc_item_id,
        ':desc_tecnica', ':mascara', SYSDATE, 1)";

$sqls['pd.inativacao_preco.listar_cadastros'] = "SELECT T.ID,
       T.EMPR_ID,
       T.COD_ITEM,
       T.TMASC_ITEM_ID,
       T.DESC_TECNICA,
       T.MASCARA,
       TO_CHAR(T.DT_CADASTRO, 'DD/MM/YYYY HH24:MI') DT_CADASTRO,
       T.SIT,
       (SELECT COUNT(1)
          FROM TPRECOSVEN_IT PI
         WHERE PI.SIT           = 1
           AND PI.TMASC_ITEM_ID = T.TMASC_ITEM_ID
           AND PI.ITCM_ID IN (
               SELECT TC.ID
                 FROM TITENS_COMERCIAL TC,
                      TITENS_EMPR TE,
                      TITENS TI
                WHERE TC.ITEMPR_ID = TE.ID
                  AND TE.ITEM_ID   = TI.ID
                  AND TE.EMPR_ID   = T.EMPR_ID
                  AND TI.COD_ITEM  = T.COD_ITEM
           )
       ) QTD_ATIVOS
  FROM TGAZIN_PD_INATIV_PRECO T
 WHERE T.EMPR_ID = :empr_id
 ORDER BY T.DT_CADASTRO DESC, T.COD_ITEM ASC";

$sqls['pd.inativacao_preco.buscar_pedidos_pendentes'] = "SELECT tv.EMPR_ID,
       TO_CHAR(tv.DT_GERACAO, 'DD/MM/YYYY') DT_GERACAO,
       tv.NUM_PEDIDO,
       tv.SIT_PDV,
       tv.SIT_FAT,
       tv.SIT_FAT_COM,
       tv.SIT_FAT_FIN,
       tv.SIT_PDV_COM
  FROM TITENS_PDV tp,
       TPEDIDOS_VENDA tv
 WHERE tp.PDV_ID        = tv.ID
   AND tp.TMASC_ITEM_ID = :tmasc_item_id
   AND tp.QTDE_SLDO    <> 0
 GROUP BY tv.EMPR_ID, tv.NUM_PEDIDO, tv.SIT_PDV, tv.SIT_FAT,
          tv.SIT_FAT_COM, tv.SIT_FAT_FIN, tv.SIT_PDV_COM, tv.DT_GERACAO
 ORDER BY tv.DT_GERACAO";

$sqls['pd.inativacao_preco.listar_filiais'] = "SELECT DISTINCT EMPR_ID FROM TITENS_EMPR ORDER BY EMPR_ID";

$sqls['pd.inativacao_preco.excluir'] = "DELETE FROM TGAZIN_PD_INATIV_PRECO WHERE ID = :id AND EMPR_ID = :empr_id";

// ════════════════════════════════════════════════════════════════════════════
// CD — ProjecaoCarga
// ════════════════════════════════════════════════════════════════════════════

$sqls['cd.projecao_carga.listar'] = "SELECT
    E.COD_EMP, E.ID AS EMPR_ID, C.CARGA AS NUM_CARGA,
    TO_CHAR(C.DT_GERACAO,'DD/MM/YYYY') AS DT_GERACAO,
    C.DESCRICAO,
    ROUND(SUM(((IP.VLR_LIQ + NVL(IP.VLR_ACRES,0)) - NVL(IP.VLR_DESC_PDV,0)) * IPC.QTDE_SLDO),  2) AS VALOR_PENDENTE,
    ROUND(SUM(((IP.VLR_LIQ + NVL(IP.VLR_ACRES,0)) - NVL(IP.VLR_DESC_PDV,0)) * IPC.QTDE_ATEND), 2) AS VALOR_FATURADO,
    NVL(C.CUBAGEM_TOT,0) AS CUBAGEM,
    TO_CHAR(A.DT_CARREGAMENTO,'DD/MM/YYYY') AS DT_CARREGAMENTO,
    A.OBSERVACOES, A.NUM_DOCS, A.SITUACAO_CARGA, A.FROTA, A.PLACAS,
    A.TIPO_VEICULO, A.MOTORISTA, A.CONTATO, A.SITUACAO_CAMINHAO,
    MAX(W.AREA_COD_AREA) AS DOCA,
    NVL(A.SITUACAO,'PENDENTE') AS SITUACAO,
    A.USUARIO AS USUARIO_AGEND,
    TO_CHAR(A.DT_ALTERACAO,'DD/MM/YYYY HH24:MI') AS DT_ALTERACAO,
    C.POS_PLC,
    MAX(W.STATUS_WMS) AS STATUS_WMS,
    (
      SELECT LISTAGG(cidade || ' - ' || uf || ' - ' || cubagem, ' | ')
             WITHIN GROUP (ORDER BY seq_min)
      FROM (
          SELECT tc.CIDADE AS cidade,
                 uf.UF    AS uf,
                 ROUND(SUM(ipc2.QTDE_SLDO * ipc2.CUBAGEM), 2) AS cubagem,
                 MIN(ipc2.SEQ) AS seq_min
          FROM TITENS_PLC ipc2
          JOIN TITENS_PDV ip2       ON ip2.ID  = ipc2.ITPDV_ID
          JOIN TPEDIDOS_VENDA pdv2  ON pdv2.ID = ip2.PDV_ID
          JOIN TESTABELECIMENTOS est ON est.ID  = pdv2.EST_ID_FAT
          JOIN TCIDADES tc           ON tc.ID   = est.CID_ID
          JOIN TUFS uf               ON uf.ID   = tc.UF_ID
          WHERE ipc2.PLC_ID = C.ID
          GROUP BY tc.CIDADE, uf.UF
      )
    ) AS ROTA
FROM TCARGAS C
INNER JOIN TITENS_PLC IPC ON IPC.PLC_ID = C.ID
INNER JOIN TITENS_PDV IP  ON IP.ID = IPC.ITPDV_ID
INNER JOIN TPEDIDOS_VENDA PDV ON PDV.ID = IP.PDV_ID AND PDV.CLI_ID <> 5210
INNER JOIN TMASC_ITEM MI  ON MI.ID  = IP.TMASC_ITEM_ID
INNER JOIN TITENS_COMERCIAL ICM ON ICM.ID = IP.ITCM_ID
INNER JOIN TITENS_EMPR IE  ON IE.ID  = ICM.ITEMPR_ID
INNER JOIN TEMPRESAS E     ON E.ID   = IE.EMPR_ID
INNER JOIN TITENS T        ON T.ID   = IE.ITEM_ID
LEFT JOIN FOCCO3I.TGAZIN_CARGA_AGEND A ON A.EMPR_ID = E.ID AND A.NUM_CARGA = C.CARGA
LEFT JOIN (
    SELECT DISTINCT CARGA, EMPR_ID
    FROM FOCCO3I.F3I_LOG_TCARGAS
    WHERE TRUNC(DTA_OPERACAO_LOG) BETWEEN TRUNC(TO_DATE(':data_inicio','YYYY-MM-DD'))
                                      AND TRUNC(TO_DATE(':data_fim','YYYY-MM-DD'))
      AND POS_PLC IN ('FT','FP')
      AND IND_TIPO_LOG = 2
) LG ON LG.CARGA = C.CARGA AND LG.EMPR_ID = C.EMPR_ID
LEFT JOIN (
    SELECT NUM_CARGA,
           AREA_COD_AREA,
           CASE SITUACAO_WMS
               WHEN '1' THEN 'Importada WMS'
               WHEN '3' THEN 'Em Separação'
               WHEN '6' THEN 'Encerrada'
               WHEN '9' THEN 'Excluída'
               ELSE NULL
           END AS STATUS_WMS
    FROM (
        SELECT NUM_CARGA, SITUACAO_WMS, AREA_COD_AREA,
               ROW_NUMBER() OVER (PARTITION BY NUM_CARGA ORDER BY DTHR DESC NULLS LAST) AS RN
        FROM :wms_schema.WMS_CARGAS
    ) WHERE RN = 1
) W ON W.NUM_CARGA = TO_CHAR(C.CARGA)
WHERE (
    (C.POS_PLC = 'PE' AND TRUNC(C.DT_GERACAO) <= TRUNC(TO_DATE(':data_fim','YYYY-MM-DD')))
    OR
    (C.POS_PLC IN ('FT','FP') AND (
        LG.CARGA IS NOT NULL
        OR TRUNC(C.DT_GERACAO) BETWEEN TRUNC(TO_DATE(':data_inicio','YYYY-MM-DD'))
                                    AND TRUNC(TO_DATE(':data_fim','YYYY-MM-DD'))
    ))
)
AND E.ID = :empr_id
GROUP BY
    E.COD_EMP, E.ID, C.ID, C.CARGA, C.DT_GERACAO, C.DESCRICAO, C.CUBAGEM_TOT,
    A.DT_CARREGAMENTO, A.OBSERVACOES, A.NUM_DOCS, A.SITUACAO_CARGA, A.FROTA, A.PLACAS,
    A.TIPO_VEICULO, A.MOTORISTA, A.CONTATO, A.SITUACAO_CAMINHAO,
    A.SITUACAO, A.USUARIO, A.DT_ALTERACAO, C.POS_PLC
ORDER BY C.POS_PLC ASC, C.CARGA DESC";

$sqls['cd.projecao_carga.listar_itens'] = "SELECT TITENS.COD_ITEM                    COD_ITEM,
       TITENS.DESC_TECNICA                DESC_TECNICA,
       TMASC_ITEM.ID                      ID,
       TMASC_ITEM.MASCARA                 MASCARA,
       SUM(TITENS_PLC.QTDE)               QTDE_CARGA,
       MAX(MAN_EST_RETORNA_SALDO_ITEM(TITENS_EMPR.EMPR_ID, TITENS.ID, 998, SYSDATE, TMASC_ITEM.ID, NULL, NULL, NULL, 1, 0)) ESTOQUE_998,
       MAX(MAN_EST_RETORNA_SALDO_ITEM(TITENS_EMPR.EMPR_ID, TITENS.ID,  90, SYSDATE, TMASC_ITEM.ID, NULL, NULL, NULL, 1, 0)) ESTOQUE_90,
       MAX(MAN_EST_RETORNA_SALDO_ITEM(TITENS_EMPR.EMPR_ID, TITENS.ID, 997, SYSDATE, TMASC_ITEM.ID, NULL, NULL, NULL, 1, 0)) ESTOQUE_997,
       NULL                               G_TOTAL_GERAL
  FROM TITENS_ESTOQUE        TITENS_ESTOQUE,
       TITENS_PDV             TITENS_PDV,
       TITENS_COMERCIAL       TITENS_COMERCIAL,
       TITENS_EMPR            TITENS_EMPR,
       TITENS                 TITENS,
       TITENS_PLC             TITENS_PLC,
       TMASC_ITEM             TMASC_ITEM,
       TITENS_PLANEJAMENTO    TITENS_PLANEJAMENTO,
       TALMOXARIFADOS         TALMOXARIFADOS,
       TCARGAS                TCARGAS
 WHERE TITENS_PDV.ID          = TITENS_PLC.ITPDV_ID
   AND TITENS_COMERCIAL.ID    = TITENS_PDV.ITCM_ID
   AND TITENS_EMPR.ID         = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS_EMPR.ID         = TITENS_COMERCIAL.ITEMPR_ID
   AND TITENS_EMPR.ID         = TITENS_ESTOQUE.ITEMPR_ID
   AND TITENS.ID              = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID(+)       = TITENS_PDV.TMASC_ITEM_ID
   AND TALMOXARIFADOS.ID      = TITENS_ESTOQUE.ALMOX_ID
   AND TCARGAS.ID             = TITENS_PLC.PLC_ID
   AND TCARGAS.EMPR_ID        = :empr_id
   AND TCARGAS.CARGA          = :num_carga
 GROUP BY TITENS.COD_ITEM,
          TITENS.DESC_TECNICA,
          TMASC_ITEM.ID,
          TMASC_ITEM.MASCARA";

$sqls['cd.projecao_carga.listar_itens_expedicao'] = "SELECT ws.NUM_CARGA,
       CASE ws.SITUACAO_WMS
           WHEN '1' THEN 'Importada WMS'
           WHEN '3' THEN 'Em Separação'
           WHEN '6' THEN 'Encerrada'
           WHEN '9' THEN 'Excluída'
           ELSE 'Desconhecida'
       END AS DESCRICAO_STATUS,
       pe.NUM_PEDIDO,
       i.CODIGO,
       i.DESCRICAO,
       lpe.QTDE,
       lpe.QTDE_EXECUTADA,
       lpe.QTDE_EXECUTADA_ORIGINAL AS QTDE_DISTRIBUIDA,
       NVL(lpe.QTDE / NULLIF(lpe.QTDE_EXECUTADA_ORIGINAL,0),0)*100 AS PERCENTUAL
  FROM :wms_schema.WMS_CARGAS ws,
       :wms_schema.PEDIDOS_ERP pe,
       :wms_schema.LINHAS_PEDIDOS_ERP lpe,
       :wms_schema.ITEM i
 WHERE i.CODIGO      = lpe.ITEM
   AND lpe.PEDIDO_ID = pe.ID
   AND pe.CARGA_ID   = ws.ID
   AND ws.NUM_CARGA  = :num_carga";

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
