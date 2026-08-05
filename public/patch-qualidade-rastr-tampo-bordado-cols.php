<?php
/**
 * Patch 2: adiciona colunas LOTE_ESPUMA, LOTE_TECIDO, LOTE_TNT, LOTE_FIBRA
 * no SQL 'qualidade.rastreabilidade.tampoBordado' (substitui PILLOW que não é usado aqui).
 * O SQL do PCP não é alterado.
 */
require_once __DIR__ . '/../vendor/autoload.php';

$pdo     = \core\Database::getInstance('focco');
$DESTINO = 'qualidade.rastreabilidade.tampoBordado';

$sel = $pdo->prepare("SELECT sql FROM focco3i.gazin_sqls WHERE idsql = :id");
$sel->execute([':id' => $DESTINO]);
$row = $sel->fetch(\PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['ok' => false, 'msg' => "SQL '$DESTINO' não encontrado. Rode o patch-qualidade-rastr-tampo-bordado.php primeiro."], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$sqlAtual = is_resource($row['SQL']) ? stream_get_contents($row['SQL']) : ($row['SQL'] ?? '');

$DE  = "F3IMOV_RETORNA_ATRIB_PDM(obter_itempr_id(TABLES.TMASC_ITEM_ID),'LISO/BORDADO') PILLOW";
$PARA = "NULL LOTE_ESPUMA,\n       NULL LOTE_TECIDO,\n       NULL LOTE_TNT,\n       NULL LOTE_FIBRA";

if (strpos($sqlAtual, $DE) === false) {
    echo json_encode([
        'ok'  => false,
        'msg' => 'Padrão PILLOW não encontrado — SQL já pode ter sido alterado.',
        'sql_atual' => $sqlAtual,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$sqlNovo = str_replace($DE, $PARA, $sqlAtual);

if (($_GET['dry'] ?? '0') === '1') {
    echo json_encode([
        'ok'      => true,
        'dry_run' => true,
        'sql_novo' => $sqlNovo,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$upd = $pdo->prepare("UPDATE focco3i.gazin_sqls SET sql = :sql WHERE idsql = :id");
$upd->bindValue(':id',  $DESTINO, \PDO::PARAM_STR);
$upd->bindValue(':sql', $sqlNovo, \PDO::PARAM_STR);
$upd->execute();
$pdo->exec('COMMIT');

// Limpa cache
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'focco_sql_cache';
if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $f) {
        unlink($f);
    }
}

echo json_encode([
    'ok'      => true,
    'msg'     => 'Colunas LOTE adicionadas. Acesse Admin SQLs para preencher as fontes reais.',
    'sql_novo' => $sqlNovo,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
