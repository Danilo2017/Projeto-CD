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

        $sql = "SELECT DISTINCT
    TCARGAS.EMPR_ID,
    TCARGAS.CARGA,
    tv.NUM_PEDIDO,
    TITENS.COD_ITEM,
    TITENS.DESC_TECNICA,
    TMASC_ITEM.ID ID,
    TMASC_ITEM.MASCARA,
    TALMOXARIFADOS.COD_ALMOX,
    TALMOXARIFADOS.DESCRICAO
FROM TITENS_ESTOQUE,
     TITENS_PDV,
     TITENS_COMERCIAL,
     TITENS_EMPR,
     TITENS,
     TITENS_PLC,
     TMASC_ITEM,
     TITENS_PLANEJAMENTO,
     TALMOXARIFADOS,
     TCARGAS,
     TPEDIDOS_VENDA tv
WHERE TITENS_PDV.ID = TITENS_PLC.ITPDV_ID
  AND TITENS_COMERCIAL.ID = TITENS_PDV.ITCM_ID
  AND TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
  AND TITENS_EMPR.ID = TITENS_COMERCIAL.ITEMPR_ID
  AND TITENS_EMPR.ID = TITENS_ESTOQUE.ITEMPR_ID
  AND tv.ID = TITENS_PDV.PDV_ID
  AND TITENS.ID = TITENS_EMPR.ITEM_ID
  AND TMASC_ITEM.ID(+) = TITENS_PDV.TMASC_ITEM_ID
  AND TALMOXARIFADOS.ID = TITENS_PDV.ALMOX_ID
  AND TCARGAS.ID = TITENS_PLC.PLC_ID
  AND TCARGAS.EMPR_ID = :empr_id
  AND TCARGAS.CARGA = :carga
  {$filtroPedido}
GROUP BY TCARGAS.EMPR_ID, TCARGAS.CARGA, tv.NUM_PEDIDO,
         TITENS.COD_ITEM, TITENS.DESC_TECNICA,
         TMASC_ITEM.ID, TMASC_ITEM.MASCARA,
         TALMOXARIFADOS.COD_ALMOX, TALMOXARIFADOS.DESCRICAO
ORDER BY tv.NUM_PEDIDO ASC, TITENS.COD_ITEM ASC";

        $params = ['empr_id' => $emprId, 'carga' => $carga];
        if ($numPedido !== null) {
            $params['num_pedido'] = $numPedido;
        }

        $result = Database::switchParams('focco', $params, null, true, true, null, $sql);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']);
        }
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function trocarAlmoxCarga(int $emprId, int $carga, ?int $numPedido, int $almoxDestId): array
    {
        $filtroPedido = $numPedido !== null ? "AND tv.NUM_PEDIDO = :num_pedido" : '';

        $sql = "UPDATE TITENS_PDV
SET ALMOX_ID = :almox_dest_id
WHERE ID IN (
    SELECT TITENS_PDV.ID
    FROM TITENS_PDV,
         TITENS_PLC,
         TCARGAS,
         TPEDIDOS_VENDA tv
    WHERE TITENS_PDV.ID = TITENS_PLC.ITPDV_ID
      AND tv.ID = TITENS_PDV.PDV_ID
      AND TCARGAS.ID = TITENS_PLC.PLC_ID
      AND TCARGAS.EMPR_ID = :empr_id
      AND TCARGAS.CARGA = :carga
      {$filtroPedido}
)";

        $params = [
            'almox_dest_id' => $almoxDestId,
            'empr_id'       => $emprId,
            'carga'         => $carga,
        ];
        if ($numPedido !== null) {
            $params['num_pedido'] = $numPedido;
        }

        $result = Database::switchParams('focco', $params, null, true, true, null, $sql);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']);
        }

        Database::getInstance('focco')->exec("COMMIT");

        return ['afetados' => $result['afetados'] ?? 0];
    }
}
