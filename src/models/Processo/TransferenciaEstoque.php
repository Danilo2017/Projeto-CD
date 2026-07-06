<?php

namespace src\models\Processo;

use core\Database;

class TransferenciaEstoque
{
    public static function listarAlmoxarifados(int $emprId): array
    {
        $sql = "SELECT TRIM(TO_CHAR(COD_ALMOX)) AS COD_ALMOX, DESCRICAO
                  FROM TALMOXARIFADOS
                 WHERE EMPR_ID = :empr_id
                 ORDER BY COD_ALMOX ASC";

        $result = Database::switchParams('focco', ['empr_id' => $emprId], null, true, false, null, $sql);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function buscarSaldo(int $emprId, string $almoxOrig, ?string $codItem = null): array
    {
        $almoxLista     = "'" . trim($almoxOrig) . "'";
        $filtroCodItem  = $codItem ? "AND TITENS.COD_ITEM = " . intval($codItem) : '--';

        $sql = "SELECT TITENS_EMPR.EMPR_ID                                            AS EMPR_ID,
       TITENS.COD_ITEM                                                     AS COD_ITEM,
       TITENS_ENG_CONF.TMASC_ITEM_ID                                       AS ID_MASCARA,
       TITENS.DESC_TECNICA                                                  AS DESCRICAO,
       TMASC_ITEM.MASCARA                                                   AS MASCARA,
       TUNID_MED.COD_UNID_MED                                               AS UM,
       TRIM(TO_CHAR(TALMOXARIFADOS.COD_ALMOX))                             AS COD_ALMOX,
       SUM(MAN_EST_RETORNA_SALDO_ITEM(
               TITENS_EMPR.EMPR_ID,
               TITENS.ID,
               TALMOXARIFADOS.COD_ALMOX,
               TRUNC(SYSDATE),
               TITENS_ENG_CONF.TMASC_ITEM_ID))                             AS ESTOQUE
  FROM TITENS_EMPR        TITENS_EMPR,
       TITENS              TITENS,
       TITENS_ENGENHARIA   TITENS_ENGENHARIA,
       TGRP_CLAS_ITE       TGRP_CLAS_ITE,
       TITENS_ESTOQUE      TITENS_ESTOQUE,
       TALMOXARIFADOS      TALMOXARIFADOS,
       TITENS_ENG_CONF     TITENS_ENG_CONF,
       TUNID_MED           TUNID_MED,
       TMASC_ITEM          TMASC_ITEM,
       TITENS_EMPR_ESP     TITENS_EMPR_ESP
 WHERE TITENS_EMPR.ID           = TITENS_EMPR_ESP.ESPECIAL_ID(+)
   AND TITENS_EMPR.ID           = TITENS_ESTOQUE.ITEMPR_ID
   AND TITENS_EMPR.ID           = TITENS_ENGENHARIA.ITEMPR_ID
   AND TITENS.ID                = TITENS_EMPR.ITEM_ID
   AND TITENS_ENGENHARIA.ID     = TITENS_ENG_CONF.ITEG_ID(+)
   AND TGRP_CLAS_ITE.ID         = TITENS_ENGENHARIA.GRP_CLAS_ID
   AND TUNID_MED.ID             = TITENS_ESTOQUE.UNID_MED_ID
   AND TMASC_ITEM.ID(+)         = TITENS_ENG_CONF.TMASC_ITEM_ID
   AND TALMOXARIFADOS.EMPR_ID   = TITENS_EMPR.EMPR_ID
   AND TITENS_EMPR.EMPR_ID      = :empr_id
   AND TRIM(TO_CHAR(TALMOXARIFADOS.COD_ALMOX)) IN (:almox_lista)
   :filtro_cod_item
   AND MAN_EST_RETORNA_SALDO_ITEM(
               TITENS_EMPR.EMPR_ID,
               TITENS.ID,
               TALMOXARIFADOS.COD_ALMOX,
               TRUNC(SYSDATE),
               TITENS_ENG_CONF.TMASC_ITEM_ID) <> 0
 GROUP BY TITENS_EMPR.EMPR_ID,
          TITENS.COD_ITEM,
          TITENS_ENG_CONF.TMASC_ITEM_ID,
          TITENS.DESC_TECNICA,
          TMASC_ITEM.MASCARA,
          TUNID_MED.COD_UNID_MED,
          TALMOXARIFADOS.COD_ALMOX
 ORDER BY TALMOXARIFADOS.COD_ALMOX ASC,
          TITENS.COD_ITEM ASC,
          TITENS_ENG_CONF.TMASC_ITEM_ID ASC";

        $result = Database::switchParams('focco', [
            'empr_id'        => $emprId,
            'almox_lista'    => $almoxLista,
            'filtro_cod_item'=> $filtroCodItem,
        ], null, true, false, null, $sql);

        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function executar(int $emprId, array $itens): array
    {
        $pdo  = Database::getInstance('focco');
        $erros = [];
        $ok    = 0;

        $plsql = "BEGIN\n";
        foreach ($itens as $it) {
            $codItem  = (int) $it['cod_item'];
            $idMasc   = (int) $it['id_mascara'];
            $almoxOrg = (int) $it['almox_orig'];
            $almoxDst = (int) $it['almox_dest'];
            $qtde     = (int) $it['qtde'];
            $plsql   .= "  PTRANSFERE_ESTQ_ITENS({$emprId},{$codItem},{$idMasc},{$almoxOrg},{$almoxDst},{$qtde});\n";
        }
        $plsql .= "  COMMIT;\nEND;";

        try {
            $pdo->exec($plsql);
            $ok = count($itens);
        } catch (\Exception $e) {
            $erros[] = $e->getMessage();
        }

        return ['ok' => $ok, 'erros' => $erros];
    }
}
