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

    public static function listarCadastros(int $emprId): array
    {
        $sql = "SELECT ID, EMPR_ID, COD_ITEM, TMASC_ITEM_ID, DESC_TECNICA, MASCARA,
               TO_CHAR(DT_CADASTRO, 'DD/MM/YYYY HH24:MI') DT_CADASTRO,
               SIT
          FROM TGAZIN_PD_INATIV_PRECO
         WHERE EMPR_ID = $emprId
         ORDER BY DT_CADASTRO DESC, COD_ITEM ASC";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function verificarExistencia(int $emprId, int $codItem, int $tmascItemId): bool
    {
        $sql = "SELECT COUNT(*) QTD
          FROM TGAZIN_PD_INATIV_PRECO
         WHERE EMPR_ID       = $emprId
           AND COD_ITEM      = $codItem
           AND TMASC_ITEM_ID = $tmascItemId
           AND SIT           = 1";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) return false;
        $rows = is_array($result['retorno']) ? $result['retorno'] : [];
        return ((int) ($rows[0]['QTD'] ?? 0)) > 0;
    }

    public static function cadastrarItem(int $emprId, int $codItem, int $tmascItemId, string $descTecnica, string $mascara): void
    {
        $descSafe    = str_replace("'", "''", $descTecnica);
        $mascaraSafe = str_replace("'", "''", $mascara);

        $sql = "INSERT INTO TGAZIN_PD_INATIV_PRECO
               (ID, EMPR_ID, COD_ITEM, TMASC_ITEM_ID, DESC_TECNICA, MASCARA, DT_CADASTRO, SIT)
        VALUES (SEQ_TGAZIN_PD_INATIV.NEXTVAL, $emprId, $codItem, $tmascItemId, '$descSafe', '$mascaraSafe', SYSDATE, 1)";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        Database::getInstance('focco')->exec('COMMIT');
    }

    public static function excluirItem(int $id, int $emprId): void
    {
        $sql = "DELETE FROM TGAZIN_PD_INATIV_PRECO WHERE ID = $id AND EMPR_ID = $emprId AND SIT = 1";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        Database::getInstance('focco')->exec('COMMIT');
    }

    public static function processarInativacao(int $emprId): int
    {
        // Inativa nas tabelas de preço os itens pendentes para esta empresa
        $sqlUpdate = "UPDATE TPRECOSVEN_IT
   SET SIT = 0, PRECO = 0
 WHERE SIT = 1
   AND EXISTS (
       SELECT 1
         FROM TGAZIN_PD_INATIV_PRECO P,
              TITENS_COMERCIAL TC,
              TITENS_EMPR TE,
              TITENS T
        WHERE P.EMPR_ID       = $emprId
          AND P.SIT            = 1
          AND P.TMASC_ITEM_ID  = TPRECOSVEN_IT.TMASC_ITEM_ID
          AND T.COD_ITEM       = P.COD_ITEM
          AND TE.ITEM_ID       = T.ID
          AND TE.EMPR_ID       = P.EMPR_ID
          AND TC.ITEMPR_ID     = TE.ID
          AND TC.ID            = TPRECOSVEN_IT.ITCM_ID
   )";

        $result = Database::switchParams('focco', [], null, true, true, null, $sqlUpdate);
        if (!empty($result['error'])) throw new \Exception($result['error']);

        // Marca os registros do cadastro como processados
        $sqlMarca = "UPDATE TGAZIN_PD_INATIV_PRECO SET SIT = 0 WHERE EMPR_ID = $emprId AND SIT = 1";
        Database::switchParams('focco', [], null, true, true, null, $sqlMarca);

        Database::getInstance('focco')->exec('COMMIT');

        // Retorna quantos registros de preço foram processados
        $rowsAffected = $result['rows_affected'] ?? $result['retorno'] ?? 0;
        return is_numeric($rowsAffected) ? (int) $rowsAffected : 0;
    }
}
