<?php
/**
 * Atualiza faturamento.dashboard.dias-mes-empresa com DIAS_RESTANTES >= SYSDATE (inclui hoje).
 * Executar via: docker exec comissao_colchao php //var/www/html/exec2026-05-21-update-sql-dias-mes-por-empresa.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$idsql = 'faturamento.dashboard.dias-mes-empresa';

$sqlContent =
    "SELECT t.EMPR_ID, " .
    "SUM(CASE WHEN TRUNC(\"DATA\") < TRUNC(SYSDATE) THEN 1 ELSE 0 END) AS DIAS_PASSADOS, " .
    "SUM(CASE WHEN TRUNC(\"DATA\") >= TRUNC(SYSDATE) THEN 1 ELSE 0 END) AS DIAS_RESTANTES, " .
    "COUNT(*) AS TOTAL_DIAS_UTEIS_MES " .
    "FROM TCALENDARIOS t " .
    "WHERE \"DATA\" >= TRUNC(SYSDATE, 'MM') " .
    "AND \"DATA\" < ADD_MONTHS(TRUNC(SYSDATE, 'MM'), 1) " .
    "AND t.UTIL_INDUS = 1 " .
    "GROUP BY t.EMPR_ID " .
    "ORDER BY t.EMPR_ID";

$pdo = null;
try {
    $pdo = \core\Database::getInstance('focco');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    echo "Desabilitando trigger SQLS_TR...\n";
    $pdo->exec("ALTER TRIGGER FOCCO3I.SQLS_TR DISABLE");

    echo "\nProcessando '$idsql'...\n";

    $pdo->exec("DELETE FROM FOCCO3I.GAZIN_SQLS WHERE IDSQL = '$idsql'");
    echo "  DELETE ok\n";

    $pdo->exec("INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES ('$idsql', EMPTY_CLOB())");
    echo "  INSERT ok\n";

    $chunkSize = 2000;
    $chunks    = str_split($sqlContent, $chunkSize);
    $total     = count($chunks);

    foreach ($chunks as $i => $chunk) {
        $escaped = str_replace("'", "''", $chunk);
        if ($i === 0) {
            $pdo->exec("UPDATE FOCCO3I.GAZIN_SQLS SET SQL = TO_CLOB('$escaped') WHERE IDSQL = '$idsql'");
        } else {
            $pdo->exec("UPDATE FOCCO3I.GAZIN_SQLS SET SQL = SQL || TO_CLOB('$escaped') WHERE IDSQL = '$idsql'");
        }
        echo "  chunk " . ($i + 1) . "/$total\n";
    }

    $pdo->exec("COMMIT");
    echo "OK: '$idsql' atualizado!\n";

    echo "\nReabilitando trigger SQLS_TR...\n";
    $pdo->exec("ALTER TRIGGER FOCCO3I.SQLS_TR ENABLE");

    echo "\nConcluído.\n";

} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    if ($pdo) {
        try { $pdo->exec("ALTER TRIGGER FOCCO3I.SQLS_TR ENABLE"); } catch (\Exception $e2) {}
    }
    exit(1);
}
