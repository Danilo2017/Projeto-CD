<?php
/**
 * Patch: insere SQL 'qualidade.rastreabilidade.moloEnsacado'
 * Derivado do SQL pcp.molas — sem alterar o original.
 */
require_once __DIR__ . '/../vendor/autoload.php';

/* Carrega constantes de ambiente via Database */
\core\Database::getInstance('focco');

$IDSQL = 'qualidade.rastreabilidade.moloEnsacado';

$sqlNovo = "SELECT DISTINCT
    TO_CHAR(TORDENS.DT_INICIAL, 'DD/MM/RR') DATA_PROD,
    TORDENS.NUM_LOTE_PRO NUM_LOTE_PRO,
    TORDENS.NUM_ORDEM NUM_ORDEM,
    TITENS.COD_ITEM CODIGO,
    TITENS.DESC_TECNICA DESCRICAO,
    NULL MAQUINA,
    NULL NF_LOTE_ARAME,
    NULL NF_LOTE_TNT
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
         TORDENS.NUM_ORDEM ASC";

$tns  = "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=" . FOCCO_HOST .
        ")(PORT=" . FOCCO_PORT . ")))(CONNECT_DATA=(SERVICE_NAME=" . FOCCO_DATABASE . ")))";
$conn = oci_connect(FOCCO_USER, FOCCO_PASS, $tns, 'AL32UTF8');
if (!$conn) {
    $e = oci_error();
    echo json_encode(['ok' => false, 'msg' => 'oci8 connect failed: ' . $e['message']], JSON_UNESCAPED_UNICODE);
    exit;
}

/* Verifica se já existe */
$stmChk = oci_parse($conn, "SELECT COUNT(*) CNT FROM focco3i.gazin_sqls WHERE idsql = :id");
oci_bind_by_name($stmChk, ':id', $IDSQL, -1, SQLT_CHR);
oci_execute($stmChk);
$rowChk = oci_fetch_assoc($stmChk);
$existe  = (int)($rowChk['CNT'] ?? 0) > 0;
oci_free_statement($stmChk);

if ($existe && !isset($_GET['force'])) {
    oci_close($conn);
    echo json_encode([
        'ok'  => false,
        'msg' => "SQL '$IDSQL' já existe. Use ?force=1 para recriar.",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
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
oci_bind_by_name($stm, ':id',  $IDSQL, -1, SQLT_CHR);
oci_bind_by_name($stm, ':lob', $clob,  -1, OCI_B_CLOB);
oci_execute($stm, OCI_NO_AUTO_COMMIT);
$clob->save($sqlNovo);
oci_commit($conn);
$clob->free();
oci_free_statement($stm);
oci_close($conn);

/* Limpa cache */
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'focco_sql_cache';
if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $f) { unlink($f); }
}

echo json_encode([
    'ok'  => true,
    'msg' => "SQL '$IDSQL' $acao com sucesso.",
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
