<?php

namespace src\models\Processo;

use core\Database;

class MovEstoqueVariacaoCusto
{
    public static function listar(string $dtIni, string $dtFim, string $dtIniAnt, string $dtFimAnt, int $emprId = 0): array
    {
        $dtIni    = preg_replace('/[^0-9\/]/', '', $dtIni);
        $dtFim    = preg_replace('/[^0-9\/]/', '', $dtFim);
        $dtIniAnt = preg_replace('/[^0-9\/]/', '', $dtIniAnt);
        $dtFimAnt = preg_replace('/[^0-9\/]/', '', $dtFimAnt);
        $emprId   = (int) $emprId;

        $filtroEmpr = $emprId > 0 ? "AND VW.EMPR_ID = $emprId" : '';

        $sql = "
SELECT VW.EMPR_ID                                                               EMPR_ID,
       VW.COD_ITEM                                                               COD_ITEM,
       focco3i.focco3i_itens.retorna_desc_clas_item_nvl(TGRP_CLAS_ITE.id, 3)   FAMILIA_ITEM,
       VW.ITEM || ' - ' || VW.MASCARA                                            ITEM,
       VW.TMASC_ITEM_ID                                                          TMASC_ITEM_ID,
       SUM(CASE WHEN VW.DT BETWEEN TO_DATE('$dtIni','DD/MM/RRRR') AND TO_DATE('$dtFim','DD/MM/RRRR')
               THEN VW.QTDE ELSE 0 END)                                          QTDE,
       SUM(CASE WHEN VW.DT BETWEEN TO_DATE('$dtIni','DD/MM/RRRR') AND TO_DATE('$dtFim','DD/MM/RRRR')
               THEN VW.QTDE * VW.CUSTO_MED ELSE 0 END)                           CUSTO_TOTAL,
       NVL((
           SUM(CASE WHEN VW.DT BETWEEN TO_DATE('$dtIni','DD/MM/RRRR') AND TO_DATE('$dtFim','DD/MM/RRRR')
                   THEN VW.CUSTO_MED * VW.QTDE ELSE 0 END)
           / NULLIF(SUM(CASE WHEN VW.DT BETWEEN TO_DATE('$dtIni','DD/MM/RRRR') AND TO_DATE('$dtFim','DD/MM/RRRR')
                   THEN VW.QTDE ELSE 0 END), 0)
           - SUM(CASE WHEN VW.DT BETWEEN TO_DATE('$dtIniAnt','DD/MM/RRRR') AND TO_DATE('$dtFimAnt','DD/MM/RRRR')
                   THEN VW.CUSTO_MED * VW.QTDE ELSE 0 END)
           / NULLIF(SUM(CASE WHEN VW.DT BETWEEN TO_DATE('$dtIniAnt','DD/MM/RRRR') AND TO_DATE('$dtFimAnt','DD/MM/RRRR')
                   THEN VW.QTDE ELSE 0 END), 0)
       ) * SUM(CASE WHEN VW.DT BETWEEN TO_DATE('$dtIni','DD/MM/RRRR') AND TO_DATE('$dtFim','DD/MM/RRRR')
               THEN VW.QTDE ELSE 0 END), 0)                                      VAR,
       TGRP_CLAS_ITE.COD_GRP_ITE                                                 CLASSIFICACAO
  FROM TITENS_ESTOQUE    TITENS_ESTOQUE,
       TGRP_CLAS_ITE     TGRP_CLAS_ITE,
       VW_GAZIN_RAZAO_CUSTO_MED_ESTQ VW
 WHERE TGRP_CLAS_ITE.ID          = TITENS_ESTOQUE.GRP_CLAS_ID
   AND TITENS_ESTOQUE.ID         = VW.ITESTQ_ID
   AND VW.DESC_MOV_ESTQ         IN ('ENTREGA PRODUCAO PLANEJADA','REQUISICAO PLANEJADA')
   AND VW.DT BETWEEN TO_DATE('$dtIniAnt','DD/MM/RRRR') AND TO_DATE('$dtFim','DD/MM/RRRR')
   AND TGRP_CLAS_ITE.COD_GRP_ITE LIKE '10.%'
   $filtroEmpr
GROUP BY VW.EMPR_ID,
         VW.COD_ITEM,
         VW.ITEM || ' - ' || VW.MASCARA,
         VW.TMASC_ITEM_ID,
         VW.DESC_MOV_ESTQ,
         TGRP_CLAS_ITE.COD_GRP_ITE,
         TGRP_CLAS_ITE.ID
";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
