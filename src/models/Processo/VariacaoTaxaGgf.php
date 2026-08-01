<?php

namespace src\models\Processo;

use core\Database;

class VariacaoTaxaGgf
{
    public static function listar(string $mesAno): array
    {
        // $mesAno format: YYYY-MM  →  DATE 'YYYY-MM-01'
        [$ano, $mes] = explode('-', $mesAno);
        $dateStr = sprintf('%04d-%02d-01', (int) $ano, (int) $mes);

        $sql = "
SELECT *
  FROM (
    SELECT
        TIPO_CC,
        COD,
        CENTRO_CUSTO,
        NVL(EMP_1,  0) AS EMP_1,
        NVL(EMP_2,  0) AS EMP_2,
        NVL(EMP_3,  0) AS EMP_3,
        NVL(EMP_4,  0) AS EMP_4,
        NVL(EMP_5,  0) AS EMP_5,
        NVL(EMP_7,  0) AS EMP_7,
        NVL(EMP_9,  0) AS EMP_9,
        NVL(EMP_10, 0) AS EMP_10,
        NVL(EMP_13, 0) AS EMP_13,
        NVL(EMP_14, 0) AS EMP_14,
        NVL(EMP_15, 0) AS EMP_15,
        NVL(EMP_16, 0) AS EMP_16
      FROM (
        SELECT
            TIPO_CC,
            COD,
            CENTRO_CUSTO,
            EMPR_ID,
            (NVL(TAXA_GGF, 0) + NVL(TAXA_MOD, 0)) AS TX_TOTAL
          FROM VW_MLC_CUSTOS_PRODUTIVOS
         WHERE MES_ANO = DATE '$dateStr'
         GROUP BY
            TIPO_CC,
            COD,
            CENTRO_CUSTO,
            EMPR_ID,
            TAXA_GGF,
            TAXA_MOD
      )
      PIVOT (
        SUM(TX_TOTAL)
        FOR EMPR_ID IN (
             1 AS EMP_1,
             2 AS EMP_2,
             3 AS EMP_3,
             4 AS EMP_4,
             5 AS EMP_5,
             7 AS EMP_7,
             9 AS EMP_9,
            10 AS EMP_10,
            13 AS EMP_13,
            14 AS EMP_14,
            15 AS EMP_15,
            16 AS EMP_16
        )
      )
  )
 WHERE
       EMP_1  <> 0
    OR EMP_2  <> 0
    OR EMP_3  <> 0
    OR EMP_4  <> 0
    OR EMP_5  <> 0
    OR EMP_7  <> 0
    OR EMP_9  <> 0
    OR EMP_10 <> 0
    OR EMP_13 <> 0
    OR EMP_14 <> 0
    OR EMP_15 <> 0
    OR EMP_16 <> 0
 ORDER BY COD
";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
