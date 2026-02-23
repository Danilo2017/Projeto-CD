<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/core/Database.php';

use src\models\Comissao\FaixaComissao;
use src\models\Comissao\ApontamentoProducao;

$apontamentoModel = new ApontamentoProducao();
$faixaModel = new FaixaComissao();

// Verificar pontos do DANILO
echo "=== PONTOS POR DIA - DANILO (func_id = 7629) ===\n";
$pontosDanilo = $apontamentoModel->pontosPorDia('2026-02-01', '2026-02-20', 7629, 1, 1002);
$totalPontosDanilo = 0;
foreach ($pontosDanilo as $dia) {
    $totalPontosDanilo += floatval($dia['TOTAL_PONTOS']);
}
echo "Total pontos DANILO: $totalPontosDanilo\n";

// Buscar faixa para DANILO
echo "\n=== BUSCAR FAIXA PARA DANILO ===\n";
$faixaDanilo = $faixaModel->buscarFaixaAplicavel($totalPontosDanilo, 1002, '2026-02-20');
echo "Faixa encontrada para DANILO: ";
var_dump($faixaDanilo);

// Verificar pontos do ADELCIO
echo "\n=== PONTOS POR DIA - ADELCIO (func_id = 2147) ===\n";
$pontosAdelcio = $apontamentoModel->pontosPorDia('2026-02-01', '2026-02-20', 2147, 1, 1002);
$totalPontosAdelcio = 0;
foreach ($pontosAdelcio as $dia) {
    $totalPontosAdelcio += floatval($dia['TOTAL_PONTOS']);
}
echo "Total pontos ADELCIO: $totalPontosAdelcio\n";

// Buscar faixa para ADELCIO
echo "\n=== BUSCAR FAIXA PARA ADELCIO ===\n";
$faixaAdelcio = $faixaModel->buscarFaixaAplicavel($totalPontosAdelcio, 1002, '2026-02-20');
echo "Faixa encontrada para ADELCIO: ";
var_dump($faixaAdelcio);
