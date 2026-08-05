<?php
/**
 * Patch: cria SQL 'qualidade.rastreabilidade.tampoBordado'
 * copiando de 'pcp.relatorioProd.tampoBordado' — sem alterar o original.
 *
 * ?dry=1   → mostra o SQL original, não insere nada
 * ?force=1 → recria mesmo se já existir
 */
require_once __DIR__ . '/../vendor/autoload.php';

$pdo    = \core\Database::getInstance('focco');
$ORIGEM  = 'pcp.relatorioProd.tampoBordado';
$DESTINO = 'qualidade.rastreabilidade.tampoBordado';

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
    echo json_encode([
        'ok'      => true,
        'dry_run' => true,
        'origem'  => $ORIGEM,
        'sql'     => $sqlOriginal,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/* 2. Verifica se destino já existe */
$chk = $pdo->prepare("SELECT COUNT(*) QTD FROM focco3i.gazin_sqls WHERE idsql = :id");
$chk->execute([':id' => $DESTINO]);
$existe = (int) $chk->fetchColumn() > 0;

if ($existe && !$force) {
    /* Lê o que já tem */
    $lerStmt = $pdo->prepare("SELECT sql FROM focco3i.gazin_sqls WHERE idsql = :id");
    $lerStmt->execute([':id' => $DESTINO]);
    $lerRow = $lerStmt->fetch(\PDO::FETCH_ASSOC);
    $sqlAtual = is_resource($lerRow['SQL'] ?? null) ? stream_get_contents($lerRow['SQL']) : ($lerRow['SQL'] ?? '');

    echo json_encode([
        'ok'  => false,
        'msg' => "SQL '$DESTINO' já existe. Use ?force=1 para recriar.",
        'sql_atual' => $sqlAtual,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/* 3. Deleta entrada anterior se force=1 */
if ($existe && $force) {
    $pdo->exec("DELETE FROM focco3i.gazin_sqls WHERE idsql = '$DESTINO'");
}

/* 4. Insere cópia */
$ins = $pdo->prepare("INSERT INTO focco3i.gazin_sqls (idsql, sql) VALUES (:id, :sql)");
$ins->bindValue(':id',  $DESTINO,     \PDO::PARAM_STR);
$ins->bindValue(':sql', $sqlOriginal, \PDO::PARAM_STR);
$ins->execute();

$pdo->exec('COMMIT');

/* 5. Limpa cache do SQL */
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'focco_sql_cache';
$deleted  = [];
if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $f) {
        if (unlink($f)) $deleted[] = basename($f);
    }
}

echo json_encode([
    'ok'            => true,
    'msg'           => "SQL '$DESTINO' criado com sucesso. Agora edite-o em Admin SQLs para adicionar LOTE_ESPUMA, LOTE_TECIDO, LOTE_TNT, LOTE_FIBRA.",
    'cache_deleted' => $deleted,
    'sql_copiado'   => $sqlOriginal,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
