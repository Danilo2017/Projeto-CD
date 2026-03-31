<?php
/**
 * Script para inserir SQLs de Meta Empresa no GAZIN_SQLS
 */

require_once '/var/www/html/vendor/autoload.php';
require_once '/var/www/html/src/Config.php';
require_once '/var/www/html/core/Database.php';

$db = \core\Database::getInstance('focco');

$sqls = [
    [
        'idsql' => 'meta.empresa.listar',
        'sql' => "SELECT 
    ME.EMPR_ID,
    TO_CHAR(ME.MES_ANO, 'YYYY-MM-DD') AS MES_ANO,
    ME.META,
    ME.META_ESTOQUE
FROM FOCCO3I.META_EMPRESA ME
WHERE (:mes_ano IS NULL OR TRUNC(ME.MES_ANO, 'MM') = TO_DATE(:mes_ano, 'YYYY-MM-DD'))
ORDER BY ME.MES_ANO DESC, ME.EMPR_ID"
    ],
    [
        'idsql' => 'meta.empresa.buscar',
        'sql' => "SELECT 
    ME.EMPR_ID,
    TO_CHAR(ME.MES_ANO, 'YYYY-MM-DD') AS MES_ANO,
    ME.META,
    ME.META_ESTOQUE
FROM FOCCO3I.META_EMPRESA ME
WHERE ME.EMPR_ID = :empr_id
AND TRUNC(ME.MES_ANO, 'MM') = TO_DATE(:mes_ano, 'YYYY-MM-DD')"
    ]
];

echo "Inserindo SQLs de Meta Empresa no GAZIN_SQLS...\n\n";

foreach ($sqls as $item) {
    try {
        // Verifica se já existe
        $checkSql = "SELECT COUNT(*) as total FROM FOCCO3I.GAZIN_SQLS WHERE IDSQL = :idsql";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindParam(':idsql', $item['idsql'], PDO::PARAM_STR);
        $checkStmt->execute();
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['TOTAL'] > 0) {
            // Atualiza
            $updateSql = "UPDATE FOCCO3I.GAZIN_SQLS SET SQL = :sql WHERE IDSQL = :idsql";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->bindParam(':idsql', $item['idsql'], PDO::PARAM_STR);
            $updateStmt->bindParam(':sql', $item['sql'], PDO::PARAM_STR);
            $updateStmt->execute();
            echo "✓ Atualizado: {$item['idsql']}\n";
        } else {
            // Insere
            $insertSql = "INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (:idsql, :sql)";
            $insertStmt = $db->prepare($insertSql);
            $insertStmt->bindParam(':idsql', $item['idsql'], PDO::PARAM_STR);
            $insertStmt->bindParam(':sql', $item['sql'], PDO::PARAM_STR);
            $insertStmt->execute();
            echo "✓ Inserido: {$item['idsql']}\n";
        }
    } catch (Exception $e) {
        echo "✗ Erro em {$item['idsql']}: " . $e->getMessage() . "\n";
    }
}

echo "\nConcluído!\n";
