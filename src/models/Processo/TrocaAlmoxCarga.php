<?php

namespace src\models\Processo;

use core\Database;

class TrocaAlmoxCarga
{
    public static function listarEmpresas(): array
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function buscarItensCarga(int $emprId, int $carga, ?int $numPedido = null): array
    {
        $filtroPedido = $numPedido !== null ? "AND tv.NUM_PEDIDO = :num_pedido" : '';

        $params = [
            'empr_id'       => $emprId,
            'carga'         => $carga,
            'filtro_pedido' => $filtroPedido,
        ];
        if ($numPedido !== null) {
            $params['num_pedido'] = $numPedido;
        }

        $result = Database::switchParams('focco', $params, 'processo.troca_almox_carga.buscar_itens', true);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']);
        }
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function trocarAlmoxCarga(int $emprId, int $carga, ?int $numPedido, int $almoxDestId): array
    {
        $filtroPedido = $numPedido !== null ? "AND tv.NUM_PEDIDO = :num_pedido" : '';

        $params = [
            'almox_dest_id' => $almoxDestId,
            'empr_id'       => $emprId,
            'carga'         => $carga,
            'filtro_pedido' => $filtroPedido,
        ];
        if ($numPedido !== null) {
            $params['num_pedido'] = $numPedido;
        }

        $result = Database::switchParams('focco', $params, 'processo.troca_almox_carga.trocar', true);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']);
        }

        Database::getInstance('focco')->exec("COMMIT");

        return ['afetados' => $result['afetados'] ?? 0];
    }
}
