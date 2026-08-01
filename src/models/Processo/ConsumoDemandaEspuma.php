<?php

namespace src\models\Processo;

use core\Database;

class ConsumoDemandaEspuma
{
    public static function listar(string $dtIni, string $dtFim): array
    {
        $dtIni = preg_replace('/[^0-9\/]/', '', $dtIni);
        $dtFim = preg_replace('/[^0-9\/]/', '', $dtFim);

        $sql = "
SELECT VW.COD_ITEM,
       VW.DESC_TECNICA,
       AVG(VW.EMPRESA_1)        EMPRESA_1,
       AVG(VW.EMPRESA_2)        EMPRESA_2,
       AVG(VW.EMPRESA_3)        EMPRESA_3,
       AVG(VW.EMPRESA_4)        EMPRESA_4,
       AVG(VW.EMPRESA_5)        EMPRESA_5,
       AVG(VW.EMPRESA_13)       EMPRESA_13,
       AVG(VW.EMPRESA_14)       EMPRESA_14,
       AVG(VW.EMPRESA_15)       EMPRESA_15,
       MAX(VW.KG_REFERENCIA)    KG_REFERENCIA
  FROM VW_RATIO_DEMANDA_MOVTO2 VW
 WHERE VW.DT BETWEEN TO_DATE('$dtIni', 'DD/MM/RRRR')
                 AND TO_DATE('$dtFim', 'DD/MM/RRRR')
GROUP BY VW.COD_ITEM, VW.DESC_TECNICA
 ORDER BY VW.DESC_TECNICA ASC
";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
