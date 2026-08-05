<?php
/**
 * Patch: cria SQL 'qualidade.rastreabilidade.linhaMontagem'
 * copiando de 'pcp.relatorioProd.robotecAbastecedor' e
 * adicionando LOTE_COLA e LOTE_EPS — sem alterar o original.
 *
 * ?dry=1   → mostra o SQL original, não grava nada
 * ?force=1 → recria mesmo se já existir
 */
require_once __DIR__ . '/../vendor/autoload.php';

$pdo     = \core\Database::getInstance('focco');
$ORIGEM  = 'pcp.relatorioProd.robotecAbastecedor';
$DESTINO = 'qualidade.rastreabilidade.linhaMontagem';

$dry   = ($_GET['dry']   ?? '0') === '1';
$force = ($_GET['force'] ?? '0') === '1';

$sel = $pdo->prepare("SELECT sql FROM focco3i.gazin_sqls WHERE idsql = :id");
$sel->execute([':id' => $ORIGEM]);
$row = $sel->fetch(\PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['ok' => false, 'msg' => "SQL '$ORIGEM' não encontrado."], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$sqlOriginal = is_resource($row['SQL']) ? stream_get_contents($row['SQL']) : ($row['SQL'] ?? '');

if ($dry) {
    echo json_encode(['ok' => true, 'dry_run' => true, 'sql' => $sqlOriginal], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$chk = $pdo->prepare("SELECT COUNT(*) QTD FROM focco3i.gazin_sqls WHERE idsql = :id");
$chk->execute([':id' => $DESTINO]);
$existe = (int) $chk->fetchColumn() > 0;

if ($existe && !$force) {
    $lerStmt = $pdo->prepare("SELECT sql FROM focco3i.gazin_sqls WHERE idsql = :id");
    $lerStmt->execute([':id' => $DESTINO]);
    $lerRow   = $lerStmt->fetch(\PDO::FETCH_ASSOC);
    $sqlAtual = is_resource($lerRow['SQL'] ?? null) ? stream_get_contents($lerRow['SQL']) : ($lerRow['SQL'] ?? '');
    echo json_encode(['ok' => false, 'msg' => "SQL '$DESTINO' já existe. Use ?force=1 para recriar.", 'sql_atual' => $sqlAtual], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($existe && $force) {
    $pdo->exec("DELETE FROM focco3i.gazin_sqls WHERE idsql = '$DESTINO'");
    $pdo->exec('COMMIT');
}

/* Adiciona LOTE_COLA e LOTE_EPS antes do FROM */
$sqlNovo = preg_replace('/(\s+FROM\s+)/i', ",\n       NULL LOTE_COLA,\n       NULL LOTE_EPS\n$1", $sqlOriginal, 1);

/* INSERT via oci8 com RETURNING INTO para escrever CLOB de qualquer tamanho.
   Usa as mesmas constantes do .env que o Database::getInstance já usa. */
$tns  = "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=" . FOCCO_HOST .
        ")(PORT=" . FOCCO_PORT . ")))(CONNECT_DATA=(SERVICE_NAME=" . FOCCO_DATABASE . ")))";
$conn = oci_connect(FOCCO_USER, FOCCO_PASS, $tns, 'AL32UTF8');
if (!$conn) {
    $e = oci_error();
    echo json_encode(['ok' => false, 'msg' => 'oci8 connect failed: ' . $e['message']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
$clob = oci_new_descriptor($conn, OCI_D_LOB);
$stm  = oci_parse($conn, "INSERT INTO focco3i.gazin_sqls (idsql, sql) VALUES (:id, EMPTY_CLOB()) RETURNING sql INTO :lob");
oci_bind_by_name($stm, ':id',  $DESTINO, -1, SQLT_CHR);
oci_bind_by_name($stm, ':lob', $clob,   -1, OCI_B_CLOB);
oci_execute($stm, OCI_NO_AUTO_COMMIT);
$clob->save($sqlNovo);
oci_commit($conn);
$clob->free();
oci_free_statement($stm);
oci_close($conn);

$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'focco_sql_cache';
if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $f) { unlink($f); }
}

echo json_encode([
    'ok'      => true,
    'msg'     => "SQL '$DESTINO' criado. LOTE_COLA e LOTE_EPS como NULL — edite via Admin SQLs para as fontes reais.",
    'sql_novo' => $sqlNovo,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
