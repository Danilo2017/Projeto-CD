<?php

namespace src\models\Processo;

use core\Database;

class ConsumoThermoplast
{
    public static function listar(string $dtIni, string $dtFim): array
    {
        $dtIni = preg_replace('/[^0-9\/]/', '', $dtIni);
        $dtFim = preg_replace('/[^0-9\/]/', '', $dtFim);

        $sql = "
SELECT EMPR_ID,
       COD_ITEM,
       DESC_TECNICA,
       QTDE_ESPUMA,
       QTDE_THERMOPLAST,
       NVL(QTDE_THERMOPLAST / NULLIF(QTDE_ESPUMA, 0), 0) MEDIA_THERMOPLAST,
       CASE COD_ITEM
           WHEN '2154' THEN 1.20
           WHEN '2155' THEN 1.50
           WHEN '2156' THEN 3.00
           WHEN '2158' THEN 4.20
           WHEN '2159' THEN 4.80
           ELSE 0
       END PROJETADO_THERMOPLAS,
       CASE COD_ITEM
           WHEN '2154' THEN 2.35
           WHEN '2155' THEN 2.45
           WHEN '2156' THEN 4.00
           WHEN '2158' THEN 5.30
           WHEN '2159' THEN 6.00
           ELSE 0
       END PROJETADO_THERMOPLAS2
  FROM (
    SELECT VW.EMPR_ID,
           VW.COD_ITEM,
           VW.DESC_TECNICA,
           SUM(VW.QTDE_MOVTO)   QTDE_ESPUMA,
           SUM(VW.QTDE_DEMANDA) QTDE_THERMOPLAST
      FROM VW_MOVIMENTOS_ESTOQUE_PET_DIA2 VW
     WHERE VW.DT BETWEEN TO_DATE('$dtIni', 'DD/MM/RRRR')
                     AND TO_DATE('$dtFim', 'DD/MM/RRRR')
    GROUP BY VW.EMPR_ID, VW.COD_ITEM, VW.DESC_TECNICA
  )
 ORDER BY EMPR_ID ASC, COD_ITEM ASC
";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
