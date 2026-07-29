<?php

namespace src\handlers\Processo;

use core\Controller;
use src\models\Processo\TrocaAlmoxCarga;

class TrocaAlmoxCargaHandler
{
    public static function listarEmpresas(): array
    {
        return TrocaAlmoxCarga::listarEmpresas();
    }

    public static function buscarItensCarga(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id', 'carga']);

        $emprId    = (int) $dados['empr_id'];
        $carga     = (int) $dados['carga'];
        $numPedido = isset($dados['num_pedido']) && $dados['num_pedido'] !== '' && $dados['num_pedido'] !== null
            ? (int) $dados['num_pedido']
            : null;

        $itens = TrocaAlmoxCarga::buscarItensCarga($emprId, $carga, $numPedido);

        $pedidos = array_values(array_unique(array_column($itens, 'NUM_PEDIDO')));
        sort($pedidos);

        return ['itens' => $itens, 'total' => count($itens), 'pedidos' => $pedidos];
    }

    public static function executar(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id', 'carga', 'almox_dest_id']);

        $emprId     = (int) $dados['empr_id'];
        $carga      = (int) $dados['carga'];
        $almoxDestId = (int) $dados['almox_dest_id'];
        $numPedido  = isset($dados['num_pedido']) && $dados['num_pedido'] !== '' && $dados['num_pedido'] !== null
            ? (int) $dados['num_pedido']
            : null;

        $result = TrocaAlmoxCarga::trocarAlmoxCarga($emprId, $carga, $numPedido, $almoxDestId);

        $escopo = $numPedido !== null
            ? "Pedido {$numPedido} da Carga {$carga}"
            : "Carga {$carga} completa";

        return array_merge($result, ['escopo' => $escopo, 'success' => true]);
    }
}
