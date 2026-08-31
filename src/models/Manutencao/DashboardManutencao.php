<?php

namespace src\models\Manutencao;

use core\Database;

class DashboardManutencao
{
    private static function dateSql(string $d): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            throw new \InvalidArgumentException("Data inválida: $d");
        }
        return "TO_DATE('$d','YYYY-MM-DD')";
    }

    private static function query(string $sql): array
    {
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?: [];
    }

    private static function queryOne(string $sql): array
    {
        $rows = self::query($sql);
        return $rows[0] ?? [];
    }

    // KPIs: ordens abertas no período (DT_SOLICITACAO)
    public static function getResumo(int $emprId, string $di, string $df): array
    {
        $ddi = self::dateSql($di);
        $ddf = self::dateSql($df);
        $sql = "SELECT
                    COUNT(*) AS TOTAL,
                    COUNT(CASE WHEN SITUACAO = 'F' THEN 1 END) AS FECHADAS,
                    COUNT(CASE WHEN SITUACAO <> 'F' THEN 1 END) AS ABERTAS,
                    COUNT(CASE WHEN TP_OS = 'C' THEN 1 END) AS CORRETIVAS,
                    COUNT(CASE WHEN TP_OS = 'P' THEN 1 END) AS PREVENTIVAS,
                    COUNT(CASE WHEN TP_OS = 'G' THEN 1 END) AS PROGRAMADAS
                FROM FOCCO3I.TORDENS_MAN
                WHERE EMPR_ID = $emprId
                  AND TP_ORDEM <> 'OME'
                  AND DT_SOLICITACAO BETWEEN $ddi AND ($ddf + 1 - 1/86400)";
        return self::queryOne($sql);
    }

    // Distribuição: mesmo padrão de joins do OTM (old-style Oracle + TPREVEN_SERVICOS)
    public static function getDistribuicao(int $emprId, string $di, string $df): array
    {
        $ddi = self::dateSql($di);
        $ddf = self::dateSql($df);
        $sql = "SELECT TIPO, QTD,
                    ROUND(QTD * 100.0 / SUM(QTD) OVER (), 1) AS PERC_QTD,
                    VALOR
                FROM (
                    SELECT
                        CASE T.TP_OS
                            WHEN 'C' THEN 'Corretiva'
                            WHEN 'G' THEN 'Programada'
                            WHEN 'P' THEN 'Preventiva'
                            WHEN 'M' THEN 'Melhoria'
                            ELSE 'Nao Planejada'
                        END AS TIPO,
                        COUNT(DISTINCT T.ID) AS QTD,
                        ROUND(
                            SUM(NVL(FOCCO3I.MAN_EST_RETORNA_CUSTO_MEDIO(
                                IE.EMPR_ID, IE.ID, NULL, 1,
                                T.DT_FECHAMENTO, 999999999, EC.TMASC_ITEM_ID
                            ) * ISM.QTDE, 0)) +
                            SUM(NVL(S.VLR_GASTO, 0))
                        , 2) AS VALOR
                    FROM FOCCO3I.TITENS_SERV_MAN ISM,
                         FOCCO3I.TORDENS_MAN T,
                         TSERV_MAN S,
                         FOCCO3I.TPREVEN_SERVICOS PREV,
                         FOCCO3I.TITENS_ESTOQUE ESTQ,
                         FOCCO3I.TITENS_EMPR IE,
                         FOCCO3I.TITENS_ESTQ_CONF EC
                    WHERE T.ID       = S.ORD_MAN_ID(+)
                      AND S.ID       = ISM.SERV_MAN_ID(+)
                      AND PREV.ID    = S.TPRESER_ID
                      AND ESTQ.ID    = EC.ITESTQ_ID(+)
                      AND ESTQ.ID(+) = ISM.ITESTQ_ID
                      AND IE.ID(+)   = ESTQ.ITEMPR_ID
                      AND T.EMPR_ID  IN ($emprId)
                      AND T.TP_ORDEM <> 'OME'
                      AND T.DT_FECHAMENTO >= $ddi
                      AND T.DT_FECHAMENTO <  ($ddf + 1)
                    GROUP BY T.TP_OS
                )
                ORDER BY QTD DESC";
        return self::query($sql);
    }

    // Geradas = criadas no período (DT_SOLICITACAO)
    // Atendidas = fechadas no período (DT_FECHAMENTO) — igual ao OTM
    public static function getGeradasAtendidas(int $emprId, string $di, string $df): array
    {
        $ddi = self::dateSql($di);
        $ddf = self::dateSql($df);
        $sql = "SELECT
                    (SELECT COUNT(*) FROM FOCCO3I.TORDENS_MAN
                     WHERE EMPR_ID = $emprId AND TP_ORDEM <> 'OME'
                       AND DT_SOLICITACAO BETWEEN $ddi AND ($ddf + 1 - 1/86400)) AS GERADAS,
                    (SELECT COUNT(*) FROM FOCCO3I.TORDENS_MAN
                     WHERE EMPR_ID = $emprId AND TP_ORDEM <> 'OME'
                       AND DT_FECHAMENTO BETWEEN $ddi AND ($ddf + 1 - 1/86400)) AS ATENDIDAS
                FROM DUAL";
        return self::queryOne($sql);
    }

    // Grupo/Valor: SQL idêntico ao OTM (old-style Oracle joins + filtro de custo > 0)
    public static function getOrdensGrupo(int $emprId, string $di, string $df): array
    {
        $ddi = self::dateSql($di);
        $ddf = self::dateSql($df);
        $sql = "SELECT ROWNUM RN, GR.EMPR_ID, GR.GRUPO, GR.VALOR FROM (
                    SELECT T.EMPR_ID,
                           SUBSTR(M.COD_MAQUINA, 1, 4) AS GRUPO,
                           ROUND(
                               SUM(NVL(FOCCO3I.MAN_EST_RETORNA_CUSTO_MEDIO(
                                   IE.EMPR_ID, IE.ID, NULL, 1,
                                   T.DT_FECHAMENTO, 999999999, EC.TMASC_ITEM_ID
                               ) * ISM.QTDE, 0)) +
                               SUM(NVL(S.VLR_GASTO, 0))
                           , 2) AS VALOR
                    FROM FOCCO3I.TITENS_SERV_MAN ISM,
                         FOCCO3I.TORDENS_MAN T,
                         TSERV_MAN S,
                         FOCCO3I.TPREVEN_SERVICOS PREV,
                         FOCCO3I.TITENS_ESTOQUE ESTQ,
                         FOCCO3I.TITENS_EMPR IE,
                         FOCCO3I.TITENS_ESTQ_CONF EC,
                         FOCCO3I.TMAQUINAS M
                    WHERE T.ID            = S.ORD_MAN_ID(+)
                      AND S.ID            = ISM.SERV_MAN_ID(+)
                      AND PREV.ID         = S.TPRESER_ID
                      AND ESTQ.ID         = EC.ITESTQ_ID(+)
                      AND ESTQ.ID(+)      = ISM.ITESTQ_ID
                      AND IE.ID(+)        = ESTQ.ITEMPR_ID
                      AND M.ID            = T.MAQUINA_ID
                      AND T.EMPR_ID       IN ($emprId)
                      AND T.DT_FECHAMENTO >= $ddi
                      AND T.DT_FECHAMENTO <  ($ddf + 1)
                      AND (
                            NVL(S.VLR_GASTO, 0) > 0
                            OR NVL(FOCCO3I.MAN_EST_RETORNA_CUSTO_MEDIO(
                                IE.EMPR_ID, IE.ID, NULL, 1,
                                T.DT_FECHAMENTO, 999999999, EC.TMASC_ITEM_ID
                            ) * ISM.QTDE, 0) > 0
                          )
                    GROUP BY T.EMPR_ID, SUBSTR(M.COD_MAQUINA, 1, 4)
                    ORDER BY VALOR DESC, GRUPO
                ) GR WHERE ROWNUM <= 20";
        return self::query($sql);
    }

    public static function getPreventivas(int $emprId, string $di, string $df): array
    {
        $ddi = self::dateSql($di);
        $ddf = self::dateSql($df);
        $sql = "SELECT
                    (SELECT COUNT(DISTINCT TPER.NUM_ORDEM)
                     FROM FOCCO3I.TPERFIL_ITENS_CALC TPER
                     JOIN FOCCO3I.TPLANOS_MAN PLA ON PLA.ID = TPER.TPLANO_MAN_ID
                     WHERE TPER.EMPR_ID = $emprId
                       AND TPER.SITUACAO = 1
                       AND TPER.TP_ORDEM = 'OMP'
                       AND TPER.TP_OS = 'P'
                       AND PLA.COD_PLANO = 1
                       AND TPER.DT_PREVISTA BETWEEN $ddi AND ($ddf + 1 - 1/86400)) AS PROGRAMADAS,
                    (SELECT COUNT(DISTINCT NUM_ORDEM)
                     FROM FOCCO3I.TORDENS_MAN
                     WHERE EMPR_ID = $emprId AND TP_OS = 'P' AND TP_ORDEM <> 'OME'
                       AND DT_SOLICITACAO BETWEEN $ddi AND ($ddf + 1 - 1/86400)) AS LIBERADAS,
                    (SELECT COUNT(DISTINCT NUM_ORDEM)
                     FROM FOCCO3I.TORDENS_MAN
                     WHERE EMPR_ID = $emprId AND TP_OS = 'P' AND TP_ORDEM <> 'OME'
                       AND SITUACAO = 'F'
                       AND DT_FECHAMENTO BETWEEN $ddi AND ($ddf + 1 - 1/86400)) AS REALIZADAS
                FROM DUAL";
        return self::queryOne($sql);
    }

    // Funcionário/Ordens: quem executou ordens FECHADAS no período
    public static function getFuncOrdens(int $emprId, string $di, string $df): array
    {
        $ddi = self::dateSql($di);
        $ddf = self::dateSql($df);
        $sql = "SELECT ROWNUM RN, F.NOME AS FUNCIONARIO, FECHADAS, ABERTAS FROM (
                    SELECT F.NOME,
                           COUNT(CASE WHEN T.SITUACAO = 'F' THEN 1 END) AS FECHADAS,
                           COUNT(CASE WHEN T.SITUACAO <> 'F' THEN 1 END) AS ABERTAS
                    FROM FOCCO3I.TORDENS_MAN T
                    JOIN TSERV_MAN S ON S.ORD_MAN_ID = T.ID
                    JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = S.FUNC_ID
                    WHERE T.EMPR_ID = $emprId
                      AND T.TP_ORDEM <> 'OME'
                      AND T.DT_FECHAMENTO BETWEEN $ddi AND ($ddf + 1 - 1/86400)
                    GROUP BY F.NOME
                    ORDER BY FECHADAS DESC, F.NOME
                ) WHERE ROWNUM <= 20";
        $rows = self::query($sql);
        if (!empty($rows)) return $rows;

        // Fallback: via atendimento (quem registrou OK na OS)
        $sql2 = "SELECT ROWNUM RN, F.NOME AS FUNCIONARIO, FECHADAS, ABERTAS FROM (
                    SELECT F.NOME,
                           COUNT(CASE WHEN T.SITUACAO = 'F' THEN 1 END) AS FECHADAS,
                           COUNT(CASE WHEN T.SITUACAO <> 'F' THEN 1 END) AS ABERTAS
                    FROM FOCCO3I.TORDENS_MAN T
                    JOIN TORDENS_MAN_ATEND_OK_OTM OK ON OK.ORDEM_ID = T.ID
                    JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = OK.FUNC_ID
                    WHERE T.EMPR_ID = $emprId
                      AND T.TP_ORDEM <> 'OME'
                      AND T.DT_FECHAMENTO BETWEEN $ddi AND ($ddf + 1 - 1/86400)
                    GROUP BY F.NOME
                    ORDER BY FECHADAS DESC, F.NOME
                ) WHERE ROWNUM <= 20";
        return self::query($sql2);
    }

    // Custos: ordens FECHADAS no período
    public static function getCustos(int $emprId, string $di, string $df): array
    {
        $ddi = self::dateSql($di);
        $ddf = self::dateSql($df);
        $sql = "SELECT
                    ROUND(SUM(NVL(S.VLR_GASTO, 0)), 2) AS SERV_TERCEIROS,
                    ROUND(SUM(NVL(S.VLR_GASTO, 0)), 2) AS REALIZADO
                FROM FOCCO3I.TORDENS_MAN T
                LEFT JOIN TSERV_MAN S ON S.ORD_MAN_ID = T.ID
                WHERE T.EMPR_ID = $emprId
                  AND T.TP_ORDEM <> 'OME'
                  AND T.DT_FECHAMENTO BETWEEN $ddi AND ($ddf + 1 - 1/86400)";
        return self::queryOne($sql);
    }

    // Minutos por tipo: ordens FECHADAS no período (igual ao OTM)
    public static function getMinutosPorTipo(int $emprId, string $di, string $df): array
    {
        $ddi = self::dateSql($di);
        $ddf = self::dateSql($df);
        $sql = "SELECT
                    CASE T.TP_OS
                        WHEN 'C' THEN 'Corretiva'
                        WHEN 'G' THEN 'Programada'
                        WHEN 'P' THEN 'Preventiva'
                        WHEN 'M' THEN 'Melhoria'
                        ELSE 'Nao Planejada'
                    END AS TIPO,
                    ROUND(SUM(S.QTD_HORA * 60), 0) AS MINUTOS,
                    ROUND(SUM(NVL(S.VLR_GASTO, 0)), 2) AS VALOR_SERVICO
                FROM FOCCO3I.TORDENS_MAN T
                JOIN TSERV_MAN S ON S.ORD_MAN_ID = T.ID
                WHERE T.EMPR_ID = $emprId
                  AND T.TP_ORDEM <> 'OME'
                  AND T.DT_FECHAMENTO BETWEEN $ddi AND ($ddf + 1 - 1/86400)
                GROUP BY T.TP_OS
                ORDER BY MINUTOS DESC";
        return self::query($sql);
    }

    // Funcionário/Horas: ordens FECHADAS no período
    public static function getFuncHoras(int $emprId, string $di, string $df): array
    {
        $ddi = self::dateSql($di);
        $ddf = self::dateSql($df);
        $sql = "SELECT ROWNUM RN, F.NOME AS FUNCIONARIO, MIN_EXEC, QTD_CORRETIVAS, MTTR FROM (
                    SELECT F.NOME,
                           ROUND(SUM(S.QTD_HORA * 60), 0) AS MIN_EXEC,
                           COUNT(DISTINCT CASE WHEN T.TP_OS = 'C' THEN T.ID END) AS QTD_CORRETIVAS,
                           CASE COUNT(DISTINCT CASE WHEN T.TP_OS = 'C' THEN T.ID END)
                               WHEN 0 THEN 0
                               ELSE ROUND(SUM(S.QTD_HORA * 60) /
                                    COUNT(DISTINCT CASE WHEN T.TP_OS = 'C' THEN T.ID END), 1)
                           END AS MTTR
                    FROM FOCCO3I.TORDENS_MAN T
                    JOIN TSERV_MAN S ON S.ORD_MAN_ID = T.ID
                    JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = S.FUNC_ID
                    WHERE T.EMPR_ID = $emprId
                      AND T.TP_ORDEM <> 'OME'
                      AND T.DT_FECHAMENTO BETWEEN $ddi AND ($ddf + 1 - 1/86400)
                    GROUP BY F.NOME
                    ORDER BY MIN_EXEC DESC
                ) WHERE ROWNUM <= 20";
        return self::query($sql);
    }
}
