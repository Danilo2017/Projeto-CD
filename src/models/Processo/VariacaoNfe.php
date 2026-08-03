<?php

namespace src\models\Processo;

use core\Database;

class VariacaoNfe
{
    public static function listarEmpresas(): array
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function listar(string $dtIni, string $dtFim, int $codEmp): array
    {
        $dtIni  = preg_replace('/[^0-9\/]/', '', $dtIni);
        $dtFim  = preg_replace('/[^0-9\/]/', '', $dtFim);
        $codEmp = (int) $codEmp;

        $sql = "
SELECT Y.*
  FROM (
    SELECT X.*,
           ROUND(((X.VALOR_ATUAL - X.VALOR_ANTERIOR)
           / NULLIF(X.VALOR_ANTERIOR, 0)) * 100, 2) PERC_VARIACAO
      FROM (
        SELECT TEMPRESAS.COD_EMP COD_EMP,
               TITENS_ESTOQUE.COD_ITEM COD_ITEM,
               TMOV_ESTQ.TMASC_ITEM_ID,
               TITENS.DESC_TECNICA DESC_TECNICA,
               TMASC_ITEM.MASCARA,
               TMOV_ESTQ.ID MOV_ID,
               TNFS_ENTRADA.NUM_NF NOTA_ATUAL,
               TMOV_ESTQ.DT DATA_ATUAL,
               TMOV_ESTQ.VALOR VALOR_ATUAL,
               TMOV_ESTQ.QTDE QTDE_ATUAL,
               LAG(TNFS_ENTRADA.NUM_NF)
               OVER(
                   PARTITION BY TITENS_ESTOQUE.COD_ITEM,
                                TMOV_ESTQ.TMASC_ITEM_ID
                   ORDER BY TMOV_ESTQ.ID
               ) NOTA_ANTERIOR,
               LAG(TMOV_ESTQ.VALOR)
               OVER(
                   PARTITION BY TITENS_ESTOQUE.COD_ITEM,
                                TMOV_ESTQ.TMASC_ITEM_ID
                   ORDER BY TMOV_ESTQ.ID
               ) VALOR_ANTERIOR,
               LAG(TMOV_ESTQ.DT)
               OVER(
                   PARTITION BY TITENS_ESTOQUE.COD_ITEM,
                                TMOV_ESTQ.TMASC_ITEM_ID
                   ORDER BY TMOV_ESTQ.ID
               ) DATA_ANTERIOR,
               LAG(TMOV_ESTQ.QTDE)
               OVER(
                   PARTITION BY TITENS_ESTOQUE.COD_ITEM,
                                TMOV_ESTQ.TMASC_ITEM_ID
                   ORDER BY TMOV_ESTQ.ID
               ) QTDE_ANTERIOR,
               LAG(TMOV_ESTQ.ID)
               OVER(
                   PARTITION BY TITENS_ESTOQUE.COD_ITEM,
                                TMOV_ESTQ.TMASC_ITEM_ID
                   ORDER BY TMOV_ESTQ.ID
               ) MOV_ID_ANTERIOR
          FROM TMOV_ESTQ TMOV_ESTQ,
               TITENS_ESTOQUE TITENS_ESTOQUE,
               TITENS_EMPR TITENS_EMPR,
               TITENS_CONTABIL TITENS_CONTABIL,
               TMASC_ITEM TMASC_ITEM,
               TGRP_INVENT TGRP_INVENT,
               TEMPRESAS TEMPRESAS,
               TCUSTO_ESTQ TCUSTO_ESTQ,
               TTP_MOV_ESTQ TTP_MOV_ESTQ,
               TITENS TITENS,
               TITENS_NFE TITENS_NFE,
               TNFS_ENTRADA TNFS_ENTRADA
         WHERE TMOV_ESTQ.ID(+) = TCUSTO_ESTQ.MOV_ESTQ_ID
           AND TITENS_ESTOQUE.ID = TMOV_ESTQ.ITESTQ_ID
           AND TITENS_EMPR.ID = TITENS_CONTABIL.ITEMPR_ID
           AND TITENS_EMPR.ID = TITENS_ESTOQUE.ITEMPR_ID
           AND TITENS_NFE.ID = TMOV_ESTQ.ITNFE_ID
           AND TNFS_ENTRADA.ID = TITENS_NFE.NFE_ID
           AND TMASC_ITEM.ID(+) = TMOV_ESTQ.TMASC_ITEM_ID
           AND TGRP_INVENT.ID = TITENS_CONTABIL.GRP_INVENT_ID
           AND TEMPRESAS.ID = TMOV_ESTQ.EMPR_ID
           AND TTP_MOV_ESTQ.ID = TMOV_ESTQ.TMVES_ID
           AND TITENS.ID = TITENS_EMPR.ITEM_ID
           AND TTP_MOV_ESTQ.SIGLA = 'NFE'
           AND TMOV_ESTQ.ENT_SAI = 'E'
           AND TMOV_ESTQ.QTDE > 0
           AND TEMPRESAS.COD_EMP = $codEmp
           AND TMOV_ESTQ.DT BETWEEN TO_DATE('$dtIni', 'DD/MM/RRRR')
                                 AND TO_DATE('$dtFim', 'DD/MM/RRRR')
      ) X
     WHERE X.QTDE_ANTERIOR > 0
  ) Y
 WHERE Y.PERC_VARIACAO <> 0
 ORDER BY Y.MOV_ID
";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
