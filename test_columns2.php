<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Config.php';

$pdo = core\Database::getInstance('focco');

// Verificar colunas de TGAZIN_VINC_FUNC
echo "=== TGAZIN_VINC_FUNC ===\n";
$sql = "SELECT COLUMN_NAME FROM ALL_TAB_COLUMNS WHERE TABLE_NAME = 'TGAZIN_VINC_FUNC' AND OWNER = 'FOCCO3I' ORDER BY COLUMN_ID";
$stmt = $pdo->query($sql);
while ($row = $stmt->fetch()) {
    echo $row['COLUMN_NAME'] . "\n";
}

// Verificar colunas de TGAZIN_REGRA_FUNC
echo "\n=== TGAZIN_REGRA_FUNC ===\n";
$sql = "SELECT COLUMN_NAME FROM ALL_TAB_COLUMNS WHERE TABLE_NAME = 'TGAZIN_REGRA_FUNC' AND OWNER = 'FOCCO3I' ORDER BY COLUMN_ID";
$stmt = $pdo->query($sql);
while ($row = $stmt->fetch()) {
    echo $row['COLUMN_NAME'] . "\n";
}

// Testar query direta de vínculos
echo "\n=== TESTE VINCULO DIRETO ===\n";
$sql = "SELECT v.ID_VINCULO, v.ID_EMPR, v.ID_FUNCIONARIO FROM FOCCO3I.TGAZIN_VINC_FUNC v WHERE ROWNUM <= 1";
$stmt = $pdo->query($sql);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);

// Testar query direta de regras
echo "\n=== TESTE REGRA DIRETO ===\n";
$sql = "SELECT r.ID_REGRA, r.ID_EMPR, r.ID_FUNCIONARIO FROM FOCCO3I.TGAZIN_REGRA_FUNC r WHERE ROWNUM <= 1";
$stmt = $pdo->query($sql);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
