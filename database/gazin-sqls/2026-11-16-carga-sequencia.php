<?php
/**
 * SQLs da sequência de carregamento e da descrição de tela na Projeção de Carga (/carga-projecao).
 * O sistema lê da FOCCO3I.GAZIN_SQLS, não deste arquivo. Instalar (dentro do container, na raiz do projeto):
 *   php database/gazin-sqls/instalar.php database/gazin-sqls/2026-11-16-carga-sequencia.php            -> simulação
 *   php database/gazin-sqls/instalar.php database/gazin-sqls/2026-11-16-carga-sequencia.php --aplicar  -> grava
 *
 * Pré-requisito: colunas SEQ_CARREGAMENTO e DESCRICAO_TELA em FOCCO3I.TGAZIN_CARGA_AGEND (database/carga-sequencia/2026-11-16-seq-carregamento.sql).
 *
 * Regras:
 *   - listarSeq é cópia de cd.projecao_carga.listar (que continua intacto) com SEQ_CARREGAMENTO, PLC_ID, DESCRICAO_TELA e DESCRICAO_ORIG;
 *     ordena pela sequência (menor primeiro); carga sem sequência segue a ordem de sempre (POS_PLC, CARGA DESC).
 *   - a descrição digitada na tela fica em TGAZIN_CARGA_AGEND.DESCRICAO_TELA e só vale na Projeção de Carga: a TCARGAS (Focco)
 *     NUNCA é alterada, só lida (para conferir a empresa da carga e mostrar a descrição original). Vazio = volta à original.
 */
return [

    'cd.projecaoCarga.listarSeq' => ['Projeção de Carga: listagem com sequência de carregamento e descrição de tela (cópia de cd.projecao_carga.listar + SEQ_CARREGAMENTO, PLC_ID, DESCRICAO_TELA)', <<<'SQL'
SELECT
    E.COD_EMP, E.ID AS EMPR_ID, C.CARGA AS NUM_CARGA, C.ID AS PLC_ID,
    TO_CHAR(C.DT_GERACAO,'DD/MM/YYYY') AS DT_GERACAO,
    NVL(A.DESCRICAO_TELA, C.DESCRICAO) AS DESCRICAO, C.DESCRICAO AS DESCRICAO_ORIG, A.SEQ_CARREGAMENTO,
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
    A.DT_CARREGAMENTO, A.SEQ_CARREGAMENTO, A.DESCRICAO_TELA, A.OBSERVACOES, A.NUM_DOCS, A.SITUACAO_CARGA, A.FROTA, A.PLACAS,
    A.TIPO_VEICULO, A.MOTORISTA, A.CONTATO, A.SITUACAO_CAMINHAO,
    A.SITUACAO, A.USUARIO, A.DT_ALTERACAO, C.POS_PLC
ORDER BY A.SEQ_CARREGAMENTO ASC NULLS LAST, C.POS_PLC ASC, C.CARGA DESC
SQL, [':empr_id' => '1', ':data_inicio' => '2026-11-16', ':data_fim' => '2026-11-16', ':wms_schema' => 'FOCCOWMS14A']],

    'cd.carga.seqBuscar' => ['Projeção de Carga: sequência de carregamento atual da carga', <<<'SQL'
SELECT SEQ_CARREGAMENTO
  FROM FOCCO3I.TGAZIN_CARGA_AGEND
 WHERE EMPR_ID = :empr_id AND NUM_CARGA = :num_carga
SQL, [':empr_id' => '1', ':num_carga' => '1']],

    'cd.carga.seqAtualizar' => ['Projeção de Carga: grava a sequência de carregamento (carga que já tem linha no agendamento)', <<<'SQL'
UPDATE FOCCO3I.TGAZIN_CARGA_AGEND
   SET SEQ_CARREGAMENTO = :seq, USUARIO = :usuario, DT_ALTERACAO = SYSDATE
 WHERE EMPR_ID = :empr_id AND NUM_CARGA = :num_carga
SQL, [':seq' => '1', ':usuario' => "'USUARIO'", ':empr_id' => '1', ':num_carga' => '1']],

    'cd.carga.seqInserir' => ['Projeção de Carga: grava a sequência de carregamento (carga ainda sem linha no agendamento)', <<<'SQL'
INSERT INTO FOCCO3I.TGAZIN_CARGA_AGEND (EMPR_ID, NUM_CARGA, SEQ_CARREGAMENTO, USUARIO, DT_CADASTRO)
VALUES (:empr_id, :num_carga, :seq, :usuario, SYSDATE)
SQL, [':empr_id' => '1', ':num_carga' => '1', ':seq' => '1', ':usuario' => "'USUARIO'"]],

    'cd.carga.descTelaBuscar' => ['Projeção de Carga: confere a carga na empresa (só leitura da TCARGAS) e traz a descrição original do Focco e a da tela', <<<'SQL'
SELECT C.CARGA AS NUM_CARGA, C.DESCRICAO AS DESCRICAO_ORIG, A.DESCRICAO_TELA
  FROM FOCCO3I.TCARGAS C
  LEFT JOIN FOCCO3I.TGAZIN_CARGA_AGEND A ON A.EMPR_ID = C.EMPR_ID AND A.NUM_CARGA = C.CARGA
 WHERE C.ID = :plc_id AND C.EMPR_ID = :empr_id
SQL, [':plc_id' => '1', ':empr_id' => '1']],

    'cd.carga.descTelaAtualizar' => ['Projeção de Carga: grava a descrição de tela (carga que já tem linha no agendamento); não altera a TCARGAS', <<<'SQL'
UPDATE FOCCO3I.TGAZIN_CARGA_AGEND
   SET DESCRICAO_TELA = :descricao, USUARIO = :usuario, DT_ALTERACAO = SYSDATE
 WHERE EMPR_ID = :empr_id AND NUM_CARGA = :num_carga
SQL, [':descricao' => "'x'", ':usuario' => "'USUARIO'", ':empr_id' => '1', ':num_carga' => '1']],

    'cd.carga.descTelaInserir' => ['Projeção de Carga: grava a descrição de tela (carga ainda sem linha no agendamento); não altera a TCARGAS', <<<'SQL'
INSERT INTO FOCCO3I.TGAZIN_CARGA_AGEND (EMPR_ID, NUM_CARGA, DESCRICAO_TELA, USUARIO, DT_CADASTRO)
VALUES (:empr_id, :num_carga, :descricao, :usuario, SYSDATE)
SQL, [':empr_id' => '1', ':num_carga' => '1', ':descricao' => "'x'", ':usuario' => "'USUARIO'"]],

];
