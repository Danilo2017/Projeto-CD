<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Config.php';

use src\models\Comissao\Funcionario;
use src\models\Comissao\ApontamentoProducao;

echo "=== TESTE FUNCIONARIO buscarPorId ===\n";
$funcModel = new Funcionario();
$func = $funcModel->buscarPorId(2147);
print_r($func);

echo "\n=== TESTE pontosPorDiaFuncionario ===\n";
$apontModel = new ApontamentoProducao();
$diario = $apontModel->pontosPorDiaFuncionario(2147, '2026-02-01', '2026-02-20', 1, null);
echo "Total registros: " . count($diario) . "\n";
if (count($diario) > 0) {
    echo "Colunas disponíveis:\n";
    print_r(array_keys($diario[0]));
    echo "\nPrimeiro registro:\n";
    print_r($diario[0]);
}
