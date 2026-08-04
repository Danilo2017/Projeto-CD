<?php

namespace src\models\PD;

use core\Database;

class InativacaoPreco
{
    public static function buscarItens(int $emprId, int $codItem): array
    {
        $sql = "SELECT TE.EMPR_ID                           EMPR_ID,
       T.COD_ITEM                             COD_ITEM,
       TI.ID                                  TMASC_ITEM_ID,
       T.DESC_TECNICA                         DESC_TECNICA,
       TI.MASCARA                             MASCARA
  FROM TITENS_EMPR TE,
       TITENS T,
       TITENS_ENGENHARIA TE2,
       TITENS_ENG_CONF TEC,
       TMASC_ITEM TI
 WHERE TI.ID          = TEC.TMASC_ITEM_ID
   AND TE2.ID         = TEC.ITEG_ID
   AND TE2.ITEMPR_ID  = TE.ID
   AND TE.ITEM_ID     = T.ID
   AND TE.EMPR_ID     = $emprId
   AND TE.COD_ITEM    = $codItem
   AND TE.SIT         = 1
   AND TI.SIT         = 1
ORDER BY TI.MASCARA ASC";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    // Inativa imediatamente um item nas tabelas de preço
    public static function inativarItemPreco(int $emprId, int $codItem, int $tmascItemId): void
    {
        $sql = "UPDATE TPRECOSVEN_IT
   SET SIT = 0, PRECO = 0
 WHERE SIT = 1
   AND TMASC_ITEM_ID = $tmascItemId
   AND ITCM_ID IN (
       SELECT TC.ID
         FROM TITENS_COMERCIAL TC,
              TITENS_EMPR TE,
              TITENS T
        WHERE TC.ITEMPR_ID = TE.ID
          AND TE.ITEM_ID   = T.ID
          AND TE.EMPR_ID   = $emprId
          AND T.COD_ITEM   = $codItem
   )";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
    }

    // Registra na tabela de monitoramento para o job validar periodicamente
    // Falha silenciosamente se a tabela ainda não existir
    public static function registrarMonitoramento(int $emprId, int $codItem, int $tmascItemId, string $descTecnica, string $mascara): void
    {
        try {
            // Verifica duplicata
            $sqlChk = "SELECT COUNT(*) QTD FROM TGAZIN_PD_INATIV_PRECO
                        WHERE EMPR_ID = $emprId AND COD_ITEM = $codItem AND TMASC_ITEM_ID = $tmascItemId";
            $chk = Database::switchParams('focco', [], null, true, true, null, $sqlChk);
            if (!empty($chk['error'])) return; // tabela não existe — ignora
            $rows = is_array($chk['retorno']) ? $chk['retorno'] : [];
            if (((int) ($rows[0]['QTD'] ?? 0)) > 0) return;

            $descSafe    = str_replace("'", "''", $descTecnica);
            $mascaraSafe = str_replace("'", "''", $mascara);

            $sql = "INSERT INTO TGAZIN_PD_INATIV_PRECO
                       (ID, EMPR_ID, COD_ITEM, TMASC_ITEM_ID, DESC_TECNICA, MASCARA, DT_CADASTRO, SIT)
                VALUES (SEQ_TGAZIN_PD_INATIV.NEXTVAL, $emprId, $codItem, $tmascItemId,
                        '$descSafe', '$mascaraSafe', SYSDATE, 1)";
            Database::switchParams('focco', [], null, true, true, null, $sql);
        } catch (\Exception $_) {
            // Tabela não existe ainda — o job funcionará quando ela for criada
        }
    }

    // Listagem para exibição na tela — retorna [] silenciosamente se tabela não existir
    // QTD_ATIVOS: conta preços SIT=1 em TPRECOSVEN_IT (0 = inativo confirmado, >0 = reativado)
    public static function listarCadastros(int $emprId): array
    {
        try {
            $sql = "SELECT T.ID,
                          T.EMPR_ID,
                          T.COD_ITEM,
                          T.TMASC_ITEM_ID,
                          T.DESC_TECNICA,
                          T.MASCARA,
                          TO_CHAR(T.DT_CADASTRO, 'DD/MM/YYYY HH24:MI') DT_CADASTRO,
                          T.SIT,
                          (SELECT COUNT(1)
                             FROM TPRECOSVEN_IT PI
                            WHERE PI.SIT           = 1
                              AND PI.TMASC_ITEM_ID = T.TMASC_ITEM_ID
                              AND PI.ITCM_ID IN (
                                  SELECT TC.ID
                                    FROM TITENS_COMERCIAL TC,
                                         TITENS_EMPR TE,
                                         TITENS TI
                                   WHERE TC.ITEMPR_ID = TE.ID
                                     AND TE.ITEM_ID   = TI.ID
                                     AND TE.EMPR_ID   = T.EMPR_ID
                                     AND TI.COD_ITEM  = T.COD_ITEM
                              )
                          ) QTD_ATIVOS
                     FROM TGAZIN_PD_INATIV_PRECO T
                    WHERE T.EMPR_ID = $emprId
                    ORDER BY T.DT_CADASTRO DESC, T.COD_ITEM ASC";

            $result = Database::switchParams('focco', [], null, true, true, null, $sql);
            if (!empty($result['error'])) return [];
            return is_array($result['retorno']) ? $result['retorno'] : [];
        } catch (\Exception $_) {
            return [];
        }
    }

    // Pedidos com saldo para a máscara — exibidos ao clicar no status
    public static function buscarPedidosPendentes(int $tmascItemId): array
    {
        $sql = "SELECT tv.EMPR_ID,
                       TO_CHAR(tv.DT_GERACAO, 'DD/MM/YYYY') DT_GERACAO,
                       tv.NUM_PEDIDO,
                       tv.SIT_PDV,
                       tv.SIT_FAT,
                       tv.SIT_FAT_COM,
                       tv.SIT_FAT_FIN,
                       tv.SIT_PDV_COM
                  FROM TITENS_PDV tp,
                       TPEDIDOS_VENDA tv
                 WHERE tp.PDV_ID        = tv.ID
                   AND tp.TMASC_ITEM_ID = $tmascItemId
                   AND tp.QTDE_SLDO    <> 0
                 GROUP BY tv.EMPR_ID, tv.NUM_PEDIDO, tv.SIT_PDV, tv.SIT_FAT,
                          tv.SIT_FAT_COM, tv.SIT_FAT_FIN, tv.SIT_PDV_COM, tv.DT_GERACAO
                 ORDER BY tv.DT_GERACAO";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    // Filiais disponíveis para seleção na tela
    public static function listarFiliais(): array
    {
        try {
            $sql = "SELECT DISTINCT EMPR_ID FROM TITENS_EMPR ORDER BY EMPR_ID";
            $result = Database::switchParams('focco', [], null, true, true, null, $sql);
            if (!empty($result['error'])) return [];
            return is_array($result['retorno']) ? $result['retorno'] : [];
        } catch (\Exception $_) {
            return [];
        }
    }

    public static function excluirItem(int $id, int $emprId): void
    {
        $sql = "DELETE FROM TGAZIN_PD_INATIV_PRECO WHERE ID = $id AND EMPR_ID = $emprId";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        Database::getInstance('focco')->exec('COMMIT');
    }

    public static function commit(): void
    {
        Database::getInstance('focco')->exec('COMMIT');
    }
}
