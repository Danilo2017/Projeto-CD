<?php
require_once __DIR__ . '/../vendor/autoload.php';

$host    = \src\Config::FOCCO_HOST;
$port    = \src\Config::FOCCO_PORT;
$service = \src\Config::FOCCO_DATABASE;
$user    = \src\Config::FOCCO_USER;
$pass    = \src\Config::FOCCO_PASS;

$tns  = "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST={$host})(PORT={$port})))(CONNECT_DATA=(SERVICE_NAME={$service})))";
$conn = oci_connect($user, $pass, $tns, 'AL32UTF8');
if (!$conn) { $e = oci_error(); die("ERRO: " . $e['message'] . "\n"); }

$idsql = 'pcp.relatorio.producao';

// Verificar se já existe
$chk  = oci_parse($conn, "SELECT COUNT(1) QTD FROM FOCCO3I.GAZIN_SQLS WHERE IDSQL = :id");
oci_bind_by_name($chk, ':id', $idsql, 50);
oci_execute($chk);
$row = oci_fetch_assoc($chk);
oci_free_statement($chk);

if ((int)($row['QTD'] ?? 0) > 0) {
    die("AVISO: idsql '{$idsql}' ja existe. Nada foi alterado.\n");
}

$sql = "SELECT TABLES.ORD ORD,
       TORDENS.EMPR_ID EMPR_ID,
       TORDENS.NUM_LOTE_PRO NUM_LOTE_PRO,
       TORDENS.NUM_ORDEM NUM_ORDEM,
       TABLES.COD_ITEM ITEM,
       TABLES.TMASC_ITEM_ID ID,
       TABLES.DESC_TECNICA DESCICAO,
       TABLES.MASCARA MASCARA,
       TABLES.LARGURA_COLCHAO LARGURA_COLCHAO,
       TABLES.QTDE_OF QTDE,
       TABLES.EPS ALT_EPS,
       TABLES.mola ALT_MOLA,
       F3IMOV_RETORNA_ATRIB_PDM(TITENS_EMPR.ID,'COM EDGECLIP') BORDA,
       F3IMOV_RETORNA_ATRIB_PDM(TITENS_EMPR.ID,'LINHA (EX: STANDARD)') TNT_OU_FELTRO,
       F3IMOV_RETORNA_ATRIB_PDM(obter_itempr_id(TABLES.TMASC_ITEM_ID),'LISO/BORDADO') PILLOW,
       FOCCO3I.F3IMOV_RETORNA_ATRIB_PDM(obter_itempr_id(TABLES.TMASC_ITEM_ID),'ALTURA') ALT,
       F3IMOV_RETORNA_ATRIB_PDM(obter_itempr_id(TABLES.TMASC_ITEM_ID),'ID/TECIDO') TECIDO
  FROM TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO,
       TORDENS TORDENS,
       TABLE(GAZIN_UTIL_RRP.GAZIN_COLCHOES_ESPECIAIS(PI_EMPR_ID=>TORDENS.EMPR_ID,PI_LOTE=> TORDENS.NUM_LOTE_PRO,PI_ORDEM_ID => TORDENS.ID, PI_ORDEM=> ROWNUM)) TABLES,
       TDEMANDAS TDEMANDAS,
       TITENS_EMPR TITENS_EMPR,
       TITENS TITENS,
       TMASC_ITEM TMASC_ITEM
 WHERE TITENS_PLANEJAMENTO.ID = TDEMANDAS.ITPL_ID
   AND TORDENS.ID = TDEMANDAS.ORDEM_ID
   AND TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID(+) = TDEMANDAS.TMASC_ITEM_ID
   AND (TORDENS.EMPR_ID = :empr_id)
   AND (TORDENS.NUM_LOTE_PRO = :num_lote)
   AND TITENS.DESC_TECNICA LIKE '%CAIXOTE%'
ORDER BY TABLES.ORDEM ASC";

$stmt = oci_parse($conn, 'INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (:idsql, :sql)');
$clob = oci_new_descriptor($conn, OCI_D_LOB);
oci_bind_by_name($stmt, ':idsql', $idsql, 50);
oci_bind_by_name($stmt, ':sql',   $clob,  -1, OCI_B_CLOB);
$clob->writeTemporary($sql, OCI_TEMP_CLOB);
oci_execute($stmt, OCI_NO_AUTO_COMMIT);
oci_commit($conn);
$clob->free();
oci_free_statement($stmt);
oci_close($conn);

echo "OK: SQL '{$idsql}' inserido com sucesso.\n";
