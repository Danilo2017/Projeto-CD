<?php
/**
 * Patch: cria SQL 'qualidade.rastreabilidade.tampoLiso'
 * copiando de 'pcp.relatorioProd.tampoLiso', removendo PILLOW e
 * adicionando LOTE_TNT — sem alterar o SQL original do PCP.
 *
 * ?dry=1   → mostra o SQL original, não grava nada
 * ?force=1 → recria mesmo se já existir
 */
require_once __DIR__ . '/../vendor/autoload.php';

$pdo     = \core\Database::getInstance('focco');
$ORIGEM  = 'pcp.relatorioProd.tampoLiso';
$DESTINO = 'qualidade.rastreabilidade.tampoLiso';

$dry   = ($_GET['dry']   ?? '0') === '1';
$force = ($_GET['force'] ?? '0') === '1';

/* 1. Lê o SQL original */
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

/* 2. Verifica se destino já existe */
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
}

/* 3. Modifica: adiciona LOTE_TNT no SELECT (antes do FROM) */
$sqlNovo = $sqlOriginal;

// Tenta encontrar o último campo antes do FROM e adiciona LOTE_TNT após ele
// O último campo do tampoLiso geralmente termina antes de "  FROM" ou "\nFROM"
$sqlNovo = preg_replace(
    '/(\s+FROM\s+)/i',
    ",\n       NULL LOTE_TNT\n$1",
    $sqlNovo,
    1
);

/* 4. Insere */
$ins = $pdo->prepare("INSERT INTO focco3i.gazin_sqls (idsql, sql) VALUES (:id, :sql)");
$ins->bindValue(':id',  $DESTINO, \PDO::PARAM_STR);
$ins->bindValue(':sql', $sqlNovo, \PDO::PARAM_STR);
$ins->execute();
$pdo->exec('COMMIT');

/* 5. Limpa cache */
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'focco_sql_cache';
if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $f) { unlink($f); }
}

echo json_encode([
    'ok'       => true,
    'msg'      => "SQL '$DESTINO' criado. LOTE_TNT adicionado como NULL — edite via Admin SQLs para preencher a fonte real.",
    'sql_novo' => $sqlNovo,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
