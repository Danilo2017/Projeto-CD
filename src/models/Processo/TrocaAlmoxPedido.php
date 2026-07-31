<?php

namespace src\models\Processo;

use core\Database;

class TrocaAlmoxPedido
{
    public static function buscarItensPedido(int $emprId, array $numPedidos): array
    {
        $listaIds = implode(',', $numPedidos);
        $sql = "SELECT tv.EMPR_ID,
                       tv.NUM_PEDIDO,
                       TITENS.COD_ITEM,
                       TITENS.DESC_TECNICA,
                       TMASC_ITEM.ID         TMASC_ITEM_ID,
                       TMASC_ITEM.MASCARA,
                       TALMOXARIFADOS.ID     ALMOX_ID,
                       TALMOXARIFADOS.COD_ALMOX,
                       TALMOXARIFADOS.DESCRICAO DESCRICAO_ALMOX
                  FROM TITENS_ESTOQUE,
                       TITENS_PDV,
                       TITENS_COMERCIAL,
                       TITENS_EMPR,
                       TITENS,
                       TMASC_ITEM,
                       TITENS_PLANEJAMENTO,
                       TALMOXARIFADOS,
                       TPEDIDOS_VENDA tv
                 WHERE TITENS_COMERCIAL.ID        = TITENS_PDV.ITCM_ID
                   AND TITENS_EMPR.ID             = TITENS_PLANEJAMENTO.ITEMPR_ID
                   AND TITENS_EMPR.ID             = TITENS_COMERCIAL.ITEMPR_ID
                   AND TITENS_EMPR.ID             = TITENS_ESTOQUE.ITEMPR_ID
                   AND tv.ID                      = TITENS_PDV.PDV_ID
                   AND TITENS.ID                  = TITENS_EMPR.ITEM_ID
                   AND TMASC_ITEM.ID(+)           = TITENS_PDV.TMASC_ITEM_ID
                   AND TALMOXARIFADOS.ID          = TITENS_PDV.ALMOX_ID
                   AND tv.NUM_PEDIDO              IN ($listaIds)
                   AND tv.EMPR_ID                 = $emprId
                 GROUP BY tv.EMPR_ID, tv.NUM_PEDIDO, TITENS.COD_ITEM, TITENS.DESC_TECNICA,
                          TMASC_ITEM.ID, TMASC_ITEM.MASCARA,
                          TALMOXARIFADOS.ID, TALMOXARIFADOS.COD_ALMOX, TALMOXARIFADOS.DESCRICAO
                 ORDER BY tv.NUM_PEDIDO, TITENS.COD_ITEM";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']);
        }

        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function buscarAlmoxarifado(int $emprId, string $codAlmox): ?array
    {
        $codSafe = str_replace("'", "''", $codAlmox);
        $sql = "SELECT ID, COD_ALMOX, DESCRICAO
                  FROM TALMOXARIFADOS
                 WHERE EMPR_ID  = $emprId
                   AND COD_ALMOX = '$codSafe'";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);

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
        $sql = "UPDATE TITENS_PDV
                   SET ALMOX_ID = $almoxDestId
                 WHERE PDV_ID IN (
                       SELECT ID FROM TPEDIDOS_VENDA
                        WHERE NUM_PEDIDO IN ($listaIds)
                          AND EMPR_ID   = $emprId
                 )";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);

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
