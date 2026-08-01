<?php

namespace src\models\Processo;

use core\Database;

class MovEstoqueRefugoPerda
{
    public static function listar(string $dtIni, string $dtFim): array
    {
        $dtIni = preg_replace('/[^0-9\/]/', '', $dtIni);
        $dtFim = preg_replace('/[^0-9\/]/', '', $dtFim);

        $sql = "SELECT TEMPRESAS.COD_EMP,
                       focco3i.focco3i_itens.retorna_desc_clas_item_nvl(TITENS_PLANEJAMENTO.GRP_CLAS_ID, 3) FAMILIA_ITEM,
                       TITENS.COD_ITEM    COD_ITEM_FILHO,
                       TITENS.DESC_TECNICA DESC_ITEM_FILHO,
                       SUM(CASE WHEN TCUSTO_ESTQ.ENT_SAI = 'E'
                                THEN TCUSTO_ESTQ.CUSTO_MED * TMOV_ESTQ.QTDE ELSE 0 END)              ENTRADA,
                       SUM(CASE WHEN TCUSTO_ESTQ.ENT_SAI = 'S'
                                THEN TCUSTO_ESTQ.CUSTO_MED * TMOV_ESTQ.QTDE ELSE 0 END) * -1        SAIDA,
                       SUM(CASE WHEN TCUSTO_ESTQ.ENT_SAI = 'E'
                                     THEN TCUSTO_ESTQ.CUSTO_MED * TMOV_ESTQ.QTDE
                                WHEN TCUSTO_ESTQ.ENT_SAI = 'S'
                                     THEN -(TCUSTO_ESTQ.CUSTO_MED * TMOV_ESTQ.QTDE)
                                ELSE 0 END)                                                           VALOR_TOTAL
                  FROM TMOV_ESTQ
                  JOIN TCUSTO_ESTQ         ON TCUSTO_ESTQ.MOV_ESTQ_ID   = TMOV_ESTQ.ID
                  JOIN TDEMANDAS           ON TDEMANDAS.ID               = TMOV_ESTQ.DEMANDA_ID
                  JOIN TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID     = TDEMANDAS.ITPL_ID
                  JOIN TITENS_EMPR         ON TITENS_EMPR.ID             = TITENS_PLANEJAMENTO.ITEMPR_ID
                  JOIN TITENS              ON TITENS.ID                  = TITENS_EMPR.ITEM_ID
                  JOIN TORDENS             ON TORDENS.ID                 = TDEMANDAS.ORDEM_ID
                  JOIN TITENS_PLANEJAMENTO TP2 ON TP2.ID                 = TORDENS.ITPL_ID
                  JOIN TITENS_EMPR IPR     ON IPR.ID                     = TP2.ITEMPR_ID
                  JOIN TITENS TIT2         ON TIT2.ID                    = IPR.ITEM_ID
                  JOIN TEMPRESAS           ON TEMPRESAS.ID               = TORDENS.EMPR_ID
                  JOIN TORDENS_ROT         ON TORDENS_ROT.ORDEM_ID       = TORDENS.ID
                  JOIN TOPERACAO           ON TOPERACAO.ID               = TORDENS_ROT.OPERACAO_ID
                  JOIN TORDENS_MOVTO       ON TORDENS_MOVTO.TORDEN_ROT_ID = TORDENS_ROT.ID
                 WHERE TORDENS_MOVTO.QTDE_REFUGO > 0
                   AND TMOV_ESTQ.DT BETWEEN TO_DATE('$dtIni', 'DD/MM/YYYY')
                                        AND TO_DATE('$dtFim', 'DD/MM/YYYY')
                 GROUP BY TEMPRESAS.COD_EMP,
                          focco3i.focco3i_itens.retorna_desc_clas_item_nvl(TITENS_PLANEJAMENTO.GRP_CLAS_ID, 3),
                          TITENS.COD_ITEM,
                          TITENS.DESC_TECNICA
                 ORDER BY TEMPRESAS.COD_EMP,
                          focco3i.focco3i_itens.retorna_desc_clas_item_nvl(TITENS_PLANEJAMENTO.GRP_CLAS_ID, 3),
                          TITENS.COD_ITEM";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}
