<?php

namespace src\models\Processo;

use core\Database;

class TrocaAlmoxPedido
{
    public static function buscarItensPedido(int $emprId, array $numPedidos): array
    {
        $listaIds = implode(',', $numPedidos);

        $result = Database::switchParams('focco', [
            'lista_pedidos' => $listaIds,
            'empr_id'       => $emprId,
        ], 'processo.troca_almox_pedido.buscar_itens', true);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']);
        }

        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function buscarAlmoxarifado(int $emprId, string $codAlmox): ?array
    {
        $result = Database::switchParams('focco', [
            'empr_id'   => $emprId,
            'cod_almox' => $codAlmox,
        ], 'processo.troca_almox_pedido.buscar_almoxarifado', true);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']);
        }

        $rows = is_array($result['retorno']) ? $result['retorno'] : [];
        return $rows[0] ?? null;
    }

    public static function listarAlmoxarifados(int $emprId): array
    {
        $result = Database::switchParams('focco', ['empr_id' => $emprId], 'processo.almoxarifado.listarAlmoxarifados', true);
        return $result['retorno'] ?? [];
    }

    public static function trocarAlmoxarifado(int $emprId, array $numPedidos, int $almoxDestId): array
    {
        $listaIds = implode(',', $numPedidos);

        $result = Database::switchParams('focco', [
            'almox_dest'    => $almoxDestId,
            'lista_pedidos' => $listaIds,
            'empr_id'       => $emprId,
        ], 'processo.troca_almox_pedido.trocar', true);

        if (!empty($result['error'])) {
            return ['sucesso' => false, 'erro' => $result['error']];
        }

        Database::getInstance('focco')->exec('COMMIT');
        return ['sucesso' => true, 'erro' => null];
    }

    public static function listarEmpresas(): array
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }
}
