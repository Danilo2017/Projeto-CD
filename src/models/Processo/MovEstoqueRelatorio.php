<?php

namespace src\models\Processo;

use core\Database;

class MovEstoqueRelatorio
{
    public static function listar(string $dtIni, string $dtFim): array
    {
        $dtIni = preg_replace('/[^0-9\/]/', '', $dtIni);
        $dtFim = preg_replace('/[^0-9\/]/', '', $dtFim);

        $sql = "SELECT COD_EMP,
                       FAMILIA_ITEM,
                       COD_ITEM,
                       DESC_TECNICA,
                       MASCARA,
                       SUM(VALOR_DEBITO)  VALOR_DEBITO,
                       SUM(VALOR_CREDITO) VALOR_CREDITO,
                       SUM(VALOR_MOVTO)   VALOR_MOVTO
                  FROM (
                       SELECT TEMPRESAS.COD_EMP,
                              focco3i.focco3i_itens.retorna_desc_clas_item_nvl(TITENS_ESTOQUE.GRP_CLAS_ID, 3) FAMILIA_ITEM,
                              TITENS_ESTOQUE.COD_ITEM,
                              TITENS.DESC_TECNICA,
                              TMASC_ITEM.MASCARA,
                              CASE WHEN TMOV_ESTQ.ENT_SAI = 'E' THEN TCUSTO_ESTQ.VALOR_MOVTO ELSE 0 END              VALOR_DEBITO,
                              CASE WHEN TMOV_ESTQ.ENT_SAI = 'S' THEN TCUSTO_ESTQ.VALOR_MOVTO ELSE 0 END              VALOR_CREDITO,
                              CASE WHEN TMOV_ESTQ.ENT_SAI = 'S' THEN TCUSTO_ESTQ.VALOR_MOVTO * -1 ELSE TCUSTO_ESTQ.VALOR_MOVTO END VALOR_MOVTO
                         FROM TMOV_ESTQ,
                              TITENS_ESTOQUE,
                              TITENS_EMPR,
                              TITENS_CONTABIL,
                              TMASC_ITEM,
                              TGRP_INVENT,
                              TEMPRESAS,
                              TCUSTO_ESTQ,
                              TTP_MOV_ESTQ,
                              TITENS
                        WHERE TMOV_ESTQ.ID(+)   = TCUSTO_ESTQ.MOV_ESTQ_ID
                          AND TITENS_ESTOQUE.ID  = TMOV_ESTQ.ITESTQ_ID
                          AND TITENS_EMPR.ID     = TITENS_CONTABIL.ITEMPR_ID
                          AND TITENS_EMPR.ID     = TITENS_ESTOQUE.ITEMPR_ID
                          AND TMASC_ITEM.ID(+)   = TMOV_ESTQ.TMASC_ITEM_ID
                          AND TGRP_INVENT.ID     = TITENS_CONTABIL.GRP_INVENT_ID
                          AND TEMPRESAS.ID       = TMOV_ESTQ.EMPR_ID
                          AND TTP_MOV_ESTQ.ID    = TMOV_ESTQ.TMVES_ID
                          AND TITENS.ID          = TITENS_EMPR.ITEM_ID
                          AND TTP_MOV_ESTQ.SIGLA IN ('RTE', 'RTS')
                          AND TMOV_ESTQ.ENT_SAI  IN ('E', 'S')
                          AND TMOV_ESTQ.DT BETWEEN TO_DATE('$dtIni', 'DD/MM/YYYY')
                                               AND TO_DATE('$dtFim', 'DD/MM/YYYY')
                  )
                 GROUP BY COD_EMP, FAMILIA_ITEM, COD_ITEM, DESC_TECNICA, MASCARA
                 ORDER BY COD_EMP, FAMILIA_ITEM, COD_ITEM";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
