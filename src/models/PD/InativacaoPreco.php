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
        } catch (\Exception $e) {
            // Tabela não existe ainda — o job funcionará quando ela for criada
        }
    }

    // Listagem para exibição na tela — retorna [] silenciosamente se tabela não existir
    public static function listarCadastros(int $emprId): array
    {
        try {
            $sql = "SELECT ID, EMPR_ID, COD_ITEM, TMASC_ITEM_ID, DESC_TECNICA, MASCARA,
                       TO_CHAR(DT_CADASTRO, 'DD/MM/YYYY HH24:MI') DT_CADASTRO,
                       SIT
                  FROM TGAZIN_PD_INATIV_PRECO
                 WHERE EMPR_ID = $emprId
                 ORDER BY DT_CADASTRO DESC, COD_ITEM ASC";

            $result = Database::switchParams('focco', [], null, true, true, null, $sql);
            if (!empty($result['error'])) return [];
            return is_array($result['retorno']) ? $result['retorno'] : [];
        } catch (\Exception $e) {
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
