<?php
/**
 * Migração: corrige o SQL cd.carga.listar no Oracle
 *
 * Corrige dois problemas no filtro de data da Projeção de Carga:
 *  1. PE usava SYSDATE (ignorava a data selecionada) → agora usa :data_filtro
 *  2. FT/FP dependia apenas do log F3I_LOG_TCARGAS → adiciona fallback por DT_GERACAO
 *
 * Executar no servidor: php database/fix_cd_carga_listar.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$host    = \src\Config::FOCCO_HOST;
$port    = \src\Config::FOCCO_PORT;
$service = \src\Config::FOCCO_DATABASE;
$user    = \src\Config::FOCCO_USER;
$pass    = \src\Config::FOCCO_PASS;

$tns = "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST={$host})(PORT={$port})))(CONNECT_DATA=(SERVICE_NAME={$service})))";

$conn = oci_connect($user, $pass, $tns, 'AL32UTF8');
if (!$conn) {
    $e = oci_error();
    die("ERRO ao conectar: " . $e['message'] . "\n");
}

$novoSql = 'SELECT
    E.COD_EMP, E.ID AS EMPR_ID, C.CARGA AS NUM_CARGA,
    TO_CHAR(C.DT_GERACAO,\'DD/MM/YYYY\') AS DT_GERACAO,
    C.DESCRICAO,
    ROUND(SUM(((IP.VLR_LIQ + NVL(IP.VLR_ACRES,0)) - NVL(IP.VLR_DESC_PDV,0)) * IPC.QTDE_SLDO),  2) AS VALOR_PENDENTE,
    ROUND(SUM(((IP.VLR_LIQ + NVL(IP.VLR_ACRES,0)) - NVL(IP.VLR_DESC_PDV,0)) * IPC.QTDE_ATEND), 2) AS VALOR_FATURADO,
    NVL(C.CUBAGEM_TOT,0) AS CUBAGEM,
    TO_CHAR(A.DT_CARREGAMENTO,\'DD/MM/YYYY\') AS DT_CARREGAMENTO,
    A.OBSERVACOES, A.NUM_DOCS, A.SITUACAO_CARGA, A.FROTA, A.PLACAS,
    A.TIPO_VEICULO, A.MOTORISTA, A.CONTATO, A.SITUACAO_CAMINHAO,
    NVL(A.SITUACAO,\'PENDENTE\') AS SITUACAO,
    A.USUARIO AS USUARIO_AGEND,
    TO_CHAR(A.DT_ALTERACAO,\'DD/MM/YYYY HH24:MI\') AS DT_ALTERACAO,
    C.POS_PLC,
    MAX(W.STATUS_WMS) AS STATUS_WMS,
    (
      SELECT LISTAGG(cidade || \' - \' || uf || \' - \' || cubagem, \' | \')
             WITHIN GROUP (ORDER BY cidade)
      FROM (
          SELECT tc.CIDADE AS cidade,
                 uf.UF    AS uf,
                 ROUND(SUM(ipc2.QTDE_SLDO * ipc2.CUBAGEM), 2) AS cubagem
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
LEFT JOIN (SELECT DISTINCT CARGA, EMPR_ID
           FROM FOCCO3I.F3I_LOG_TCARGAS
           WHERE TRUNC(DTA_OPERACAO_LOG) = TRUNC(TO_DATE(\':data_filtro\',\'YYYY-MM-DD\'))
             AND POS_PLC IN (\'FT\',\'FP\')
             AND IND_TIPO_LOG = 2) LG ON LG.CARGA = C.CARGA AND LG.EMPR_ID = C.EMPR_ID
LEFT JOIN (
    SELECT NUM_CARGA,
           CASE SITUACAO_WMS
               WHEN \'1\' THEN \'Importada WMS\'
               WHEN \'3\' THEN \'Em Separação\'
               WHEN \'6\' THEN \'Encerrada\'
               WHEN \'9\' THEN \'Excluída\'
               ELSE NULL
           END AS STATUS_WMS
    FROM (
        SELECT NUM_CARGA, SITUACAO_WMS,
               ROW_NUMBER() OVER (PARTITION BY NUM_CARGA ORDER BY DTHR DESC NULLS LAST) AS RN
        FROM :wms_schema.WMS_CARGAS
    ) WHERE RN = 1
) W ON W.NUM_CARGA = TO_CHAR(C.CARGA)
WHERE (
    (C.POS_PLC = \'PE\' AND TRUNC(C.DT_GERACAO) <= TRUNC(TO_DATE(\':data_filtro\',\'YYYY-MM-DD\')))
    OR
    (C.POS_PLC IN (\'FT\',\'FP\') AND (
        LG.CARGA IS NOT NULL
        OR TRUNC(C.DT_GERACAO) = TRUNC(TO_DATE(\':data_filtro\',\'YYYY-MM-DD\'))
    ))
)
AND E.ID = :empr_id
GROUP BY
    E.COD_EMP, E.ID, C.ID, C.CARGA, C.DT_GERACAO, C.DESCRICAO, C.CUBAGEM_TOT,
    A.DT_CARREGAMENTO, A.OBSERVACOES, A.NUM_DOCS, A.SITUACAO_CARGA, A.FROTA, A.PLACAS,
    A.TIPO_VEICULO, A.MOTORISTA, A.CONTATO, A.SITUACAO_CAMINHAO,
    A.SITUACAO, A.USUARIO, A.DT_ALTERACAO, C.POS_PLC
ORDER BY C.POS_PLC ASC, C.DT_GERACAO DESC';

$idsql = 'cd.carga.listar';
$stmt  = oci_parse($conn, 'UPDATE focco3i.gazin_sqls SET sql = :new_sql WHERE idsql = :idsql');

$clob = oci_new_descriptor($conn, OCI_D_LOB);
oci_bind_by_name($stmt, ':new_sql', $clob,  -1, OCI_B_CLOB);
oci_bind_by_name($stmt, ':idsql',   $idsql, 50);

$clob->writeTemporary($novoSql, OCI_TEMP_CLOB);
oci_execute($stmt, OCI_NO_AUTO_COMMIT);
oci_commit($conn);

$clob->free();
oci_free_statement($stmt);
oci_close($conn);

echo "OK: SQL 'cd.carga.listar' atualizado com sucesso.\n";
