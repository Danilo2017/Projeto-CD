<?php

namespace src\handlers\Faturamento;

use core\Controller;
use src\models\Faturamento\TransferenciaPedido;

class TransferenciaPedidoHandler
{
    public static function listarEmpresas(): array
    {
        return TransferenciaPedido::listarEmpresas();
    }

    public static function buscarPedidos(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['empr_orig_id']);

        $numeros = [];
        if (!empty($dados['numeros'])) {
            $numeros = array_values(array_filter(
                array_map('intval', preg_split('/[\s,;]+/', (string) $dados['numeros']))
            ));
        }

        $pedidos = TransferenciaPedido::buscarPedidos((int) $dados['empr_orig_id'], $numeros);

        return ['pedidos' => $pedidos, 'total' => count($pedidos)];
    }

    public static function executar(array $dados): array
    {
        Controller::verificarCamposVazios($dados, ['pdv_ids', 'empr_dest_id', 'cod_tp_nf', 'cod_preven']);

        $pdvIds    = array_map('intval', (array) ($dados['pdv_ids'] ?? []));
        $emprDest  = (int) $dados['empr_dest_id'];
        $codTpNf   = (int) $dados['cod_tp_nf'];
        $codPreven = (int) $dados['cod_preven'];

        if (empty($pdvIds)) {
            throw new \Exception('Nenhum pedido selecionado para transferência', 400);
        }

        $resultados = [];
        foreach ($pdvIds as $pdvId) {
            $res = TransferenciaPedido::executarTransferencia(
                intval($pdvId),
                $emprDest,
                $codTpNf,
                $codPreven
            );
            $res['pdv_id_orig'] = intval($pdvId);
            $resultados[]       = $res;
        }

        $sucessos = count(array_filter($resultados, fn($r) => $r['sucesso']));

        return [
            'resultados' => $resultados,
            'total'      => count($resultados),
            'sucessos'   => $sucessos,
            'erros'      => count($resultados) - $sucessos,
        ];
    }
}
