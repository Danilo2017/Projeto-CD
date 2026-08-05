<?php
/**
 * Patch: insere SQL 'pcp.relatorioProd.discoCorte'
 */
require_once __DIR__ . '/../vendor/autoload.php';

$pdo   = \core\Database::getInstance('focco');
$IDSQL = 'pcp.relatorioProd.discoCorte';

/* Verifica se já existe */
$chk = $pdo->query("SELECT COUNT(*) FROM focco3i.gazin_sqls WHERE idsql = '$IDSQL'")->fetchColumn();
if ($chk > 0 && !isset($_GET['force'])) {
    echo json_encode([
        'ok'  => false,
        'msg' => "SQL '$IDSQL' já existe. Use ?force=1 para recriar.",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$sqlNovo = "SELECT TORDENS.NUM_LOTE_PRO NUM_LOTE_PRO,
       FOCCO3I.F3IMOV_RETORNA_ATRIB_PDM(TGAZIN_ADMIN_TANQUE_MOLA.ITEMPR_ID, 'TECIDO/ID 1') COD_ITEM_TECIDO,
       F3IMOV_MAN_ITE_RETORNA_RESP (TGAZIN_ADMIN_TANQUE_MOLA.TMASC_ITEM_ID,'ALTURA_FAIXA',TABLES.EMPR_ID) ALTURA_FAIXA,
       SUM((((TABLES.LARGURA_COLCHAO/1000)+(NVL (F3IMOV_MAN_ITE_RETORNA_RESP (TABLES.TMASC_ITEM_ID,'COMPRIMENTO_COLCHAO',TABLES.EMPR_ID),F3IMOV_MAN_ITE_RETORNA_RESP (TABLES.TMASC_ITEM_ID,'COMPRIMENTO_BASE',TABLES.EMPR_ID))/1000))*2)*TABLES.QTDE_OF) LINEAR,
       SUM(((((((TABLES.LARGURA_COLCHAO/1000) +(NVL(F3IMOV_MAN_ITE_RETORNA_RESP(TABLES.TMASC_ITEM_ID,'COMPRIMENTO_COLCHAO',TABLES.EMPR_ID),F3IMOV_MAN_ITE_RETORNA_RESP(TABLES.TMASC_ITEM_ID,'COMPRIMENTO_BASE',TABLES.EMPR_ID))/1000)) * 2) * TABLES.QTDE_OF)/100))) FATIA
  FROM TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO,
       TORDENS TORDENS,
       TABLE(GAZIN_UTIL_RRP.GAZIN_COLCHOES_ESPECIAIS(PI_EMPR_ID=>TORDENS.EMPR_ID,PI_LOTE=> TORDENS.NUM_LOTE_PRO,PI_ORDEM_ID => TORDENS.ID, PI_ORDEM=> ROWNUM)) TABLES,
       TDEMANDAS TDEMANDAS,
       TITENS_EMPR TITENS_EMPR,
       TITENS TITENS,
       TMASC_ITEM TMASC_ITEM,
       TMASC_ITEM TMASC_ITEM1,
       TGAZIN_ADMIN_TANQUE_MOLA TGAZIN_ADMIN_TANQUE_MOLA
 WHERE TITENS_PLANEJAMENTO.ID = TDEMANDAS.ITPL_ID
   AND TORDENS.ID = TDEMANDAS.ORDEM_ID
   AND TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID(+) = TDEMANDAS.TMASC_ITEM_ID
   AND TGAZIN_ADMIN_TANQUE_MOLA.PAI_ITEMPR_ID = TITENS_EMPR.ID
   AND (TORDENS.EMPR_ID = :empr_id)
   AND TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA LIKE ('FAIXA SEMI-ACABADA%')
   AND (TORDENS.NUM_LOTE_PRO = :num_lote)
   AND TMASC_ITEM1.id (+)= TGAZIN_ADMIN_TANQUE_MOLA.TMASC_ITEM_ID
   AND TGAZIN_ADMIN_TANQUE_MOLA.PAI_TMASC_ITEM_ID=TDEMANDAS.TMASC_ITEM_ID
   AND (TITENS.DESC_TECNICA LIKE 'PILLOW%' OR TITENS.DESC_TECNICA LIKE 'CAPA%')
   AND TGAZIN_ADMIN_TANQUE_MOLA.DESC_TECNICA NOT LIKE '%BORDADO%'
GROUP BY TORDENS.EMPR_ID,
         TORDENS.NUM_LOTE_PRO,
         FOCCO3I.F3IMOV_RETORNA_ATRIB_PDM(TGAZIN_ADMIN_TANQUE_MOLA.ITEMPR_ID, 'TECIDO/ID 1'),
         F3IMOV_MAN_ITE_RETORNA_RESP (TGAZIN_ADMIN_TANQUE_MOLA.TMASC_ITEM_ID,'ALTURA_FAIXA',TABLES.EMPR_ID)
ORDER BY F3IMOV_MAN_ITE_RETORNA_RESP (TGAZIN_ADMIN_TANQUE_MOLA.TMASC_ITEM_ID,'ALTURA_FAIXA',TABLES.EMPR_ID) ASC";

/* oci8 RETURNING INTO — PDO falha ao fazer bind de string em CLOB */
$tns  = "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=" . FOCCO_HOST .
        ")(PORT=" . FOCCO_PORT . ")))(CONNECT_DATA=(SERVICE_NAME=" . FOCCO_DATABASE . ")))";
$conn = oci_connect(FOCCO_USER, FOCCO_PASS, $tns, 'AL32UTF8');
if (!$conn) {
    $e = oci_error();
    echo json_encode(['ok' => false, 'msg' => 'oci8 connect failed: ' . $e['message']], JSON_UNESCAPED_UNICODE);
    exit;
}

$clob = oci_new_descriptor($conn, OCI_D_LOB);
if ($chk > 0) {
    $dml = "UPDATE focco3i.gazin_sqls SET sql = EMPTY_CLOB() WHERE idsql = :id RETURNING sql INTO :lob";
    $acao = 'atualizado';
} else {
    $dml = "INSERT INTO focco3i.gazin_sqls (idsql, sql) VALUES (:id, EMPTY_CLOB()) RETURNING sql INTO :lob";
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
