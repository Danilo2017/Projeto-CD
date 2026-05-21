<?php
/**
 * Insere faturamento.painel.vlr-faltante-carga no GAZIN_SQLS.
 * Valor faltante em cargas por empresa (POS_PLC IN FP/PE, geradas até ontem).
 * Executar via: docker exec comissao_colchao php //var/www/html/exec2026-05-21-insert-sql-vlr-faltante-carga.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$idsql = 'faturamento.painel.vlr-faltante-carga';

$sqlContent =
    "SELECT TEMPRESAS.COD_EMP, " .
    "SUM(TITENS_PDV.VLR_LIQ * TITENS_PLC.QTDE_SLDO) VLR_FALTANTE " .
    "FROM TITENS TITENS, " .
    "TPEDIDOS_VENDA TPEDIDOS_VENDA, " .
    "TMASC_ITEM TMASC_ITEM, " .
    "TCARGAS TCARGAS, " .
    "TITENS_PLC TITENS_PLC, " .
    "TITENS_PDV TITENS_PDV, " .
    "TITENS_EMPR TITENS_EMPR, " .
    "TITENS_COMERCIAL TITENS_COMERCIAL, " .
    "TEMPRESAS TEMPRESAS " .
    "WHERE TITENS.ID = TITENS_EMPR.ITEM_ID " .
    "AND TPEDIDOS_VENDA.ID = TITENS_PDV.PDV_ID " .
    "AND TMASC_ITEM.ID = TITENS_PDV.TMASC_ITEM_ID " .
    "AND TCARGAS.ID = TITENS_PLC.PLC_ID " .
    "AND TITENS_PDV.ID = TITENS_PLC.ITPDV_ID " .
    "AND TITENS_EMPR.ID = TITENS_COMERCIAL.ITEMPR_ID " .
    "AND TITENS_COMERCIAL.ID = TITENS_PDV.ITCM_ID " .
    "AND TEMPRESAS.ID = TITENS_EMPR.EMPR_ID " .
    "AND TCARGAS.POS_PLC IN ('FP','PE') " .
    "AND TRUNC(TCARGAS.DT_GERACAO) <= TRUNC(SYSDATE - 1) " .
    "AND (TITENS_PDV.VLR_LIQ * TITENS_PLC.QTDE_SLDO) > 0 " .
    "AND TPEDIDOS_VENDA.CLI_ID <> 5210 " .
    "GROUP BY TEMPRESAS.COD_EMP";

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
    echo "OK: '$idsql' salvo!\n";

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
