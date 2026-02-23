<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/core/Database.php';

use src\models\Comissao\ApontamentoProducao;
use src\models\Comissao\Comissao;

// Testar diretamente os models
echo "=== TESTE MODEL ApontamentoProducao::resumoPorFuncionario ===\n";
$apontamentoModel = new ApontamentoProducao();
$resumo = $apontamentoModel->resumoPorFuncionario('2026-02-01', '2026-02-20', 1, 1002);

echo "Quantidade de registros retornados: " . count($resumo) . "\n";
if (count($resumo) > 0) {
    echo "Exemplo de registro:\n";
    print_r($resumo[0]);
}

// Agrupar por funcionário manualmente
$porFunc = [];
foreach ($resumo as $row) {
    $funcId = $row['FUNC_ID'];
    if (!isset($porFunc[$funcId])) {
        $porFunc[$funcId] = [
            'FUNC_ID' => $funcId,
            'COD_FUNC' => $row['COD_FUNC'],
            'NOME_FUNC' => $row['NOME_FUNC'],
            'TOTAL_PONTOS' => 0
        ];
    }
    $porFunc[$funcId]['TOTAL_PONTOS'] += floatval($row['TOTAL_PONTOS']);
}

echo "\n=== TOTAIS POR FUNCIONÁRIO (do model) ===\n";
foreach ($porFunc as $func) {
    echo "COD: " . $func['COD_FUNC'] . " - " . $func['NOME_FUNC'] . " | PONTOS: " . $func['TOTAL_PONTOS'] . "\n";
}

echo "\n=== TESTE MODEL ApontamentoProducao::pontosPorDia (otimizado) ===\n";
// Pegar um funcionario
$funcId = array_key_first($porFunc);
$pontosDia = $apontamentoModel->pontosPorDia('2026-02-01', '2026-02-20', $funcId, 1, 1002);
echo "Quantidade de dias para func $funcId: " . count($pontosDia) . "\n";
if (count($pontosDia) > 0) {
    $totalPontos = 0;
    foreach ($pontosDia as $dia) {
        $totalPontos += floatval($dia['TOTAL_PONTOS'] ?? 0);
    }
    echo "Total de pontos calculados: " . $totalPontos . "\n";
    echo "Exemplo de dia:\n";
    print_r($pontosDia[0]);
}

// Testar calcularComissaoCompleta
echo "\n=== TESTE MODEL Comissao::calcularComissaoCompleta ===\n";
$comissaoModel = new Comissao();
$resultado = $comissaoModel->calcularComissaoCompleta($funcId, '2026-02-01', '2026-02-20', 1, 1002);
print_r($resultado);
