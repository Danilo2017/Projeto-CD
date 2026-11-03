<?php

namespace src\models\PD;

use core\Database;

class AnaliseItensCriacao
{
    public static function listarEmpresas(): array
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }

    private static function exec(string $sql): array
    {
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        if (!empty($res['error'])) throw new \Exception($res['error']);
        return is_array($res['retorno']) ? $res['retorno'] : [];
    }

    /**
     * Máscaras ativas de itens com classificação 30.% (colchões), com quantidade faturada,
     * saldo de pedido pendente e assistência (divisão 6) no período.
     */
    public static function listar(string $dtIni, string $dtFim, int $codEmp): array
    {
        $di = "TO_DATE('$dtIni','YYYY-MM-DD')";
        $df = "TO_DATE('$dtFim','YYYY-MM-DD')";

        $sql = "SELECT TMASC_ITEM.ID                                             ID,
                       TEMPRESAS.COD_EMP                                         COD_EMP,
                       TGRP_CLAS_ITE.COD_GRP_ITE || '-' || TGRP_CLAS_ITE.DESCRICAO CLASSIFICACAO,
                       TO_CHAR(TMASC_ITEM.DATA_INCLUSAO, 'DD/MM/YYYY')           DATA_ID,
                       TO_CHAR(TMASC_ITEM.DATA_INCLUSAO, 'YYYYMMDD')             DATA_ID_ORD,
                       TO_CHAR(TITENS.DT_CAD, 'DD/MM/YYYY')                      DATA_PAI,
                       TO_CHAR(TITENS.DT_CAD, 'YYYYMMDD')                        DATA_PAI_ORD,
                       TITENS.COD_ITEM                                           COD_ITEM,
                       TITENS.DESC_TECNICA                                       DESC_TECNICA,
                       TMASC_ITEM.MASCARA                                        MASCARA,

                       (SELECT SUM(tn.QTDE)
                          FROM focco3i.TITENS_NFS tn, focco3i.TNFS_SAIDA ts
                         WHERE ts.ID = tn.NFS_ID
                           AND tn.TMASC_ITEM_ID = TMASC_ITEM.ID
                           AND ts.DT_CANC IS NULL
                           AND ts.DT_SAIDA BETWEEN $di AND $df)                  QTDE_FATURADO,

                       (SELECT SUM(tp.QTDE_SLDO)
                          FROM focco3i.TPEDIDOS_VENDA tv, focco3i.TITENS_PDV tp
                         WHERE tv.ID = tp.PDV_ID
                           AND tp.TMASC_ITEM_ID = TMASC_ITEM.ID
                           AND tv.SIT_PDV = 'LIB'
                           AND tv.POS_PDV = 'PE')                                QTDE_PDV_PENDENTE,

                       NVL((SELECT SUM(tp.QTDE_SLDO)
                              FROM focco3i.TPEDIDOS_VENDA tv, focco3i.TITENS_PDV tp, focco3i.TDIVISOES_VENDAS tv2
                             WHERE tp.DIVD_ID = tv2.ID
                               AND tv.ID = tp.PDV_ID
                               AND tp.TMASC_ITEM_ID = TMASC_ITEM.ID
                               AND tv.SIT_PDV = 'LIB'
                               AND tv.POS_PDV = 'PE'
                               AND tv2.COD_DIVD = 6
                               AND tv.DT_EMIS BETWEEN $di AND $df), 0)
                     + NVL((SELECT SUM(tn.QTDE)
                              FROM focco3i.TITENS_NFS tn, focco3i.TNFS_SAIDA ts, focco3i.TDIVISOES_VENDAS tv
                             WHERE ts.DIVD_ID = tv.ID
                               AND ts.ID = tn.NFS_ID
                               AND tn.TMASC_ITEM_ID = TMASC_ITEM.ID
                               AND tv.COD_DIVD = 6
                               AND ts.DT_CANC IS NULL
                               AND ts.DT_SAIDA BETWEEN $di AND $df), 0)  ASSISTENCIA

                  FROM focco3i.TITENS_EMPR         TITENS_EMPR,
                       focco3i.TITENS              TITENS,
                       focco3i.TMASC_ITEM          TMASC_ITEM,
                       focco3i.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO,
                       focco3i.TITENS_PLAN_CONF    TITENS_PLAN_CONF,
                       focco3i.TGRP_CLAS_ITE       TGRP_CLAS_ITE,
                       focco3i.TEMPRESAS           TEMPRESAS,
                       focco3i.TITENS_COMERCIAL    TITENS_COMERCIAL,
                       focco3i.TGRP_CLAS_ITE       TGRP_CLAS_ITE1,
                       focco3i.TCLAS_AGRUP_METAS   TCLAS_AGRUP_METAS,
                       focco3i.TAGRUP_METAS        TAGRUP_METAS
                 WHERE TITENS_EMPR.ID          = TITENS_COMERCIAL.ITEMPR_ID
                   AND TITENS_EMPR.ID          = TITENS_PLANEJAMENTO.ITEMPR_ID
                   AND TITENS.ID               = TITENS_EMPR.ITEM_ID
                   AND TMASC_ITEM.ID(+)        = TITENS_PLAN_CONF.TMASC_ITEM_ID
                   AND TITENS_PLANEJAMENTO.ID  = TITENS_PLAN_CONF.ITPL_ID(+)
                   AND TGRP_CLAS_ITE.ID        = TITENS_PLANEJAMENTO.GRP_CLAS_ID
                   AND TEMPRESAS.ID            = TITENS_EMPR.EMPR_ID
                   AND TGRP_CLAS_ITE1.ID       = TCLAS_AGRUP_METAS.GRP_CLAS_ID(+)
                   AND TGRP_CLAS_ITE1.ID       = TITENS_COMERCIAL.GRP_CLAS_ID
                   AND TAGRUP_METAS.ID(+)      = TCLAS_AGRUP_METAS.TAGRUP_MET_ID
                   AND TMASC_ITEM.SIT          = 1
                   AND TEMPRESAS.COD_EMP       = $codEmp
                   AND TGRP_CLAS_ITE.COD_GRP_ITE LIKE '30.%'
                   AND NVL(TITENS_PLAN_CONF.SIT, TITENS_EMPR.SIT) = 1
                 GROUP BY TEMPRESAS.COD_EMP, TGRP_CLAS_ITE.COD_GRP_ITE, TGRP_CLAS_ITE.DESCRICAO,
                          TITENS.COD_ITEM, TMASC_ITEM.ID, TITENS.DESC_TECNICA, TMASC_ITEM.MASCARA,
                          TMASC_ITEM.DATA_INCLUSAO, TITENS.DT_CAD
                 ORDER BY TEMPRESAS.COD_EMP, TITENS.COD_ITEM, TMASC_ITEM.ID";

        return self::exec($sql);
    }

    /** Inativa a máscara em todas as pastas (mesma ação "Alterar" do relatório Focco). */
    public static function inativar(int $tmascItemId): void
    {
        $pdo = Database::getInstance('focco');
        $pdo->exec("BEGIN focco3i.MAN_ITE_INATIVA_TODAS_PASTAS($tmascItemId); END;");
        $pdo->exec('COMMIT');
    }
}
