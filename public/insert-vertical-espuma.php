<?php
/**
 * Script temporário — inserir SQL pcp.relatorioProd.verticalEspuma no Oracle
 * Remover após execução bem-sucedida.
 */
require_once '../vendor/autoload.php';

use src\models\GazinSqls;

$idsql = 'pcp.relatorioProd.verticalEspuma';

$sql = "SELECT TABLES.ORD                 ORD,
       TORDENS.NUM_LOTE_PRO           LOTE,
       TABLES.NUM_ORDEM               NUM_ORDEM,
       TABLES.DESCICAO                DESCICAO,
       TITENS.DESC_TECNICA            DESC_TECNICA,
       TMASC_ITEM.MASCARA             MASCARA,
       SUM(TDEMANDAS.QTDE)            QTDE
  FROM TITENS_PLANEJAMENTO,
       TORDENS,
       TABLE(GAZIN_UTIL_RRP.GAZIN_COLCHOES_ESPECIAIS(
               PI_EMPR_ID  => TORDENS.EMPR_ID,
               PI_LOTE     => TORDENS.NUM_LOTE_PRO,
               PI_ORDEM_ID => TORDENS.ID,
               PI_ORDEM    => ROWNUM)) TABLES,
       TDEMANDAS,
       TITENS_EMPR,
       TITENS,
       TMASC_ITEM
 WHERE TITENS_PLANEJAMENTO.ID = TDEMANDAS.ITPL_ID
   AND TORDENS.ID             = TDEMANDAS.ORDEM_ID
   AND TITENS_EMPR.ID         = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID              = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID(+)       = TDEMANDAS.TMASC_ITEM_ID
   AND TORDENS.EMPR_ID        = :empr_id
   AND TORDENS.NUM_LOTE_PRO   = :num_lote
   AND TITENS.DESC_TECNICA    LIKE 'MANTA%'
GROUP BY TABLES.ORD,
         TORDENS.NUM_LOTE_PRO,
         TABLES.NUM_ORDEM,
         TABLES.DESCICAO,
         TITENS.DESC_TECNICA,
         TMASC_ITEM.MASCARA
ORDER BY MIN(TABLES.ORDEM) ASC";

try {
    $existente = GazinSqls::buscarPorId($idsql);
    if ($existente) {
        echo "<b style='color:orange'>⚠ IDSQL já existe:</b> {$idsql} — nada foi alterado.";
        exit;
    }

    $ok = GazinSqls::inserir($idsql, $sql);
    if ($ok) {
        echo "<b style='color:green'>✅ SQL inserido com sucesso:</b> {$idsql}";
    } else {
        echo "<b style='color:red'>❌ Falha ao inserir SQL.</b>";
    }
} catch (\Throwable $e) {
    echo "<b style='color:red'>❌ Erro:</b> " . htmlspecialchars($e->getMessage());
}
