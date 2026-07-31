<?php

namespace src\handlers\Processo;

use core\Controller;
use src\models\Processo\TrocaAlmoxPedido;

class TrocaAlmoxPedidoHandler
{
    private static function parseNumeros(mixed $valor): array
    {
        $raw = is_array($valor) ? $valor : preg_split('/[\s,;]+/', (string) $valor);
        return array_values(array_filter(array_map('intval', $raw)));
    }

    public static function buscarItensPedido(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id', 'num_pedidos']);

        $emprId     = (int) $dados['empr_id'];
        $numPedidos = self::parseNumeros($dados['num_pedidos']);

        if (empty($numPedidos)) {
            throw new \Exception('Nenhum número de pedido informado.', 400);
        }

        $itens = TrocaAlmoxPedido::buscarItensPedido($emprId, $numPedidos);
        return ['itens' => $itens, 'total' => count($itens)];
    }

    public static function buscarAlmoxarifado(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id', 'cod_almox']);

        $emprId   = (int) $dados['empr_id'];
        $codAlmox = trim((string) $dados['cod_almox']);

        $almox = TrocaAlmoxPedido::buscarAlmoxarifado($emprId, $codAlmox);
        if (!$almox) {
            throw new \Exception("Almoxarifado '$codAlmox' não encontrado.", 404);
        }

        return $almox;
    }

    public static function executar(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_id', 'num_pedidos', 'almox_dest_id']);

        $emprId      = (int) $dados['empr_id'];
        $numPedidos  = self::parseNumeros($dados['num_pedidos']);
        $almoxDestId = (int) $dados['almox_dest_id'];

        if (empty($numPedidos)) {
            throw new \Exception('Nenhum número de pedido informado.', 400);
        }

        $resultado = TrocaAlmoxPedido::trocarAlmoxarifado($emprId, $numPedidos, $almoxDestId);

        if (!$resultado['sucesso']) {
            throw new \Exception('Erro ao trocar almoxarifado: ' . $resultado['erro'], 500);
        }

        $lista = implode(', ', $numPedidos);
        return ['mensagem' => "Almoxarifado dos pedidos [$lista] atualizado com sucesso."];
    }
}
