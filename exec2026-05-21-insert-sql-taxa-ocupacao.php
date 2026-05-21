<?php
/**
 * Insere os SQLs de taxa de ocupação no GAZIN_SQLS.
 * Executar via: docker exec comissao_colchao php //var/www/html/exec2026-05-21-insert-sql-taxa-ocupacao.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$sqls = [
    'faturamento.programacao.tanques' =>
        "SELECT EMPR_ID, COD_TANQUE, DESCRICAO, CAP_UEP_DIA FROM TTANQUES t ORDER BY EMPR_ID, COD_TANQUE",

    'faturamento.programacao.dias-uteis' =>
        "SELECT EMPR_ID, SUM(UTIL_INDUS) AS DIAS_UTEIS " .
        "FROM TCALENDARIOS t " .
        "WHERE \"DATA\" >= TRUNC(SYSDATE) " .
        "AND \"DATA\" < ADD_MONTHS(TRUNC(SYSDATE, 'MM'), 1) " .
        "AND UTIL_INDUS = 1 " .
        "GROUP BY EMPR_ID " .
        "ORDER BY EMPR_ID",
];

$pdo = null;
try {
    $pdo = \core\Database::getInstance('focco');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    echo "Desabilitando trigger SQLS_TR...\n";
    $pdo->exec("ALTER TRIGGER FOCCO3I.SQLS_TR DISABLE");

    foreach ($sqls as $idsql => $sqlContent) {
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
        echo "OK: '$idsql' salvo com sucesso!\n";
    }

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
