<?php
require_once 'vendor/autoload.php';
use src\models\Comissao\Comissao;

// Calcular comissão completa com desconto de faltas
echo "=== Calculo de Comissao (com desconto de faltas) ===\n";
$comissaoModel = new Comissao();
$result = $comissaoModel->calcularComissaoCompleta(2147, '2026-02-01', '2026-02-20', 1, 1002);
echo "Total Pontos Bruto: " . $result['total_pontos_bruto'] . "\n";
echo "Total Pontos Apos Falta: " . $result['total_pontos_apos_falta'] . "\n";
echo "Dias com Falta: " . $result['dias_com_falta'] . "\n";
echo "Dias Trabalhados: " . $result['dias_trabalhados'] . "\n";
echo "Valor Comissao Bruto: " . $result['valor_comissao_bruto'] . "\n";
echo "Valor Comissao Final: " . $result['valor_comissao_final'] . "\n";
if ($result['faixa_aplicada']) {
    echo "Faixa: " . $result['faixa_aplicada']['descricao'] . " - R$ " . $result['faixa_aplicada']['valor'] . "\n";
}
echo "\nDetalhes Faltas:\n";
print_r($result['detalhes_faltas']);

// Diferença esperada
$diferenca = $result['total_pontos_bruto'] - $result['total_pontos_apos_falta'];
echo "\nPontos descontados por falta: " . $diferenca . "\n";
