<?php

namespace src\models\Manutencao;

use core\Database;

class OrdemManutencao
{
    private static function dateSql(string $d): string
    {
        // Accepts YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            throw new \InvalidArgumentException("Data inválida: $d");
        }
        return "TO_DATE('$d','YYYY-MM-DD')";
    }

    private static function priCase(string $t = 'T', string $m = 'M'): string
    {
        return "CASE
                    WHEN $t.TP_OS = 'C' AND $t.TP_MAQ_PARADA = 1 AND $m.IND_CRITICO = 1 THEN 0
                    WHEN $t.TP_OS = 'C' AND $t.TP_MAQ_PARADA = 1 AND $m.IND_CRITICO = 0 THEN 1
                    WHEN $t.TP_OS = 'C' AND $t.TP_MAQ_PARADA = 2 AND $m.IND_CRITICO = 1 THEN 2
                    WHEN ($t.TP_OS = 'M' OR ($t.TP_OS = 'C' AND $t.TP_MAQ_PARADA = 2 AND $m.IND_CRITICO = 0)) THEN 3
                    ELSE 9
                END";
    }

    public static function listarEmpresas(): array
    {
        $res = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $res['retorno'] ?: [];
    }

    public static function listarAberta(int $emprId, string $dataIni, string $dataFim): array
    {
        $di  = self::dateSql($dataIni);
        $df  = self::dateSql($dataFim);
        $pri = self::priCase();
        $sql = "SELECT M.ID                                              MAQUINA_ID,
                       M.COD_MAQUINA||' - '||M.DESCRICAO               MAQUINA,
                       COUNT(T.NUM_ORDEM)                               AS TOTAL,
                       ($pri)                                           AS PRIORIDADE,
                       TO_CHAR(MAX(
                           (SELECT MIN(L.DTA_OPERACAO_LOG)
                              FROM FOCCO3I.F3I_LOG_TORDENS_MAN L
                             WHERE L.COD_OPERACAO_LOG = 'I'
                               AND L.ID = T.ID)
                       ), 'DD/MM/RRRR HH24:MI') AS DT_MAX
                  FROM FOCCO3I.TORDENS_MAN T,
                       FOCCO3I.TMAQUINAS   M
                 WHERE M.ID = T.MAQUINA_ID
                   AND T.TP_ORDEM <> 'OME'
                   AND T.TP_OS NOT IN ('P','G')
                   AND T.EMPR_ID = $emprId
                   AND T.DT_SOLICITACAO BETWEEN $di AND ($df + 1 - 1/86400)
                   AND NOT EXISTS (SELECT 1 FROM TORDENS_MAN_ATEND_OTM A WHERE A.ORDEM_ID = T.ID)
                 GROUP BY M.ID,
                          M.COD_MAQUINA||' - '||M.DESCRICAO,
                          ($pri)
                 ORDER BY ($pri), M.COD_MAQUINA||' - '||M.DESCRICAO";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?: [];
    }

    public static function detalharAberta(int $maquinaId, int $prioridade, int $emprId, string $dataIni, string $dataFim): array
    {
        $di  = self::dateSql($dataIni);
        $df  = self::dateSql($dataFim);
        $pri = self::priCase();
        $sql = "SELECT T.ID,
                       T.NUM_ORDEM,
                       TO_CHAR((SELECT MIN(L.DTA_OPERACAO_LOG)
                                  FROM FOCCO3I.F3I_LOG_TORDENS_MAN L
                                 WHERE L.COD_OPERACAO_LOG = 'I'
                                   AND L.ID = T.ID), 'DD/MM/RRRR HH24:MI') DT_SOLICITACAO,
                       M.COD_MAQUINA||' - '||M.DESCRICAO              RECURSO,
                       DECODE(T.TP_PROBLEMA,1,'Elétrico',2,'Mecânico',3,'Ferramenta',4,'Pneumático',NULL) TP_PROBLEMA,
                       DECODE(T.TP_OS,'P','Preventiva','M','Melhoria/TPM','C','Corretiva','G','Programada') TP_OS,
                       DECODE(M.IND_CRITICO,1,'Sim','Não')             IND_CRITICO,
                       DECODE(T.TP_MAQ_PARADA,1,'Sim',2,'Não',NULL)   MAQ_PARADA,
                       T.DES_PROBLEMA,
                       CASE WHEN (SELECT COUNT(*) FROM TORDENS_MAN_ATEND_OK_OTM O WHERE O.ORDEM_ID = T.ID) > 0
                            THEN 'S' ELSE 'N' END TEM_OK,
                       (SELECT MAX(F.NOME) FROM TORDENS_MAN_ATEND_OK_OTM O, FOCCO3I.TFUNCIONARIOS F
                         WHERE O.ORDEM_ID = T.ID AND F.ID = O.FUNC_ID) FUNC_OK
                  FROM FOCCO3I.TORDENS_MAN T,
                       FOCCO3I.TMAQUINAS M
                 WHERE M.ID = T.MAQUINA_ID
                   AND T.TP_ORDEM <> 'OME'
                   AND T.TP_OS NOT IN ('P','G')
                   AND M.ID = $maquinaId
                   AND NOT EXISTS (SELECT 1 FROM TORDENS_MAN_ATEND_OTM A WHERE A.ORDEM_ID = T.ID)
                   AND ($pri) = $prioridade
                 ORDER BY T.DT_SOLICITACAO, T.NUM_ORDEM";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?: [];
    }

    public static function listarAtendimento(int $emprId): array
    {
        $pri = self::priCase();
        $sql = "SELECT M.ID MAQUINA_ID,
                       M.COD_MAQUINA||' - '||M.DESCRICAO MAQUINA,
                       COUNT(T.NUM_ORDEM) TOTAL,
                       ($pri) PRIORIDADE
                  FROM FOCCO3I.TORDENS_MAN T,
                       FOCCO3I.TMAQUINAS M
                 WHERE M.ID = T.MAQUINA_ID
                   AND EXISTS (SELECT 1 FROM TORDENS_MAN_ATEND_OTM A WHERE A.ORDEM_ID = T.ID)
                   AND T.TP_ORDEM <> 'OME'
                   AND T.TP_OS NOT IN ('P','G')
                   AND T.SITUACAO <> 'F'
                   AND T.EMPR_ID = $emprId
                 GROUP BY M.ID,
                          M.COD_MAQUINA||' - '||M.DESCRICAO,
                          ($pri)
                 ORDER BY ($pri), M.COD_MAQUINA||' - '||M.DESCRICAO";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?: [];
    }

    public static function detalharAtendimento(int $maquinaId, int $prioridade): array
    {
        $pri = self::priCase();
        $sql = "SELECT T.ID,
                       T.NUM_ORDEM,
                       TO_CHAR(T.DT_SOLICITACAO,'DD/MM/RRRR HH24:MI') DT_SOLICITACAO,
                       M.COD_MAQUINA||' - '||M.DESCRICAO              RECURSO,
                       DECODE(T.TP_PROBLEMA,1,'Elétrico',2,'Mecânico',3,'Ferramenta',4,'Pneumático',NULL) TP_PROBLEMA,
                       DECODE(T.TP_OS,'P','Preventiva','M','Melhoria/TPM','C','Corretiva','G','Programada') TP_OS,
                       DECODE(M.IND_CRITICO,1,'Sim','Não')             IND_CRITICO,
                       DECODE(T.TP_MAQ_PARADA,1,'Sim',2,'Não',NULL)   MAQ_PARADA,
                       T.DES_PROBLEMA,
                       CASE WHEN (SELECT COUNT(*) FROM TORDENS_MAN_ATEND_OK_OTM O WHERE O.ORDEM_ID = T.ID) > 0
                            THEN 'S' ELSE 'N' END TEM_OK,
                       (SELECT MAX(F.NOME) FROM TORDENS_MAN_ATEND_OK_OTM O, FOCCO3I.TFUNCIONARIOS F
                         WHERE O.ORDEM_ID = T.ID AND F.ID = O.FUNC_ID) FUNC_OK
                  FROM FOCCO3I.TORDENS_MAN T,
                       FOCCO3I.TMAQUINAS M
                 WHERE M.ID = T.MAQUINA_ID
                   AND T.TP_ORDEM <> 'OME'
                   AND M.ID = $maquinaId
                   AND EXISTS (SELECT 1 FROM TORDENS_MAN_ATEND_OTM A WHERE A.ORDEM_ID = T.ID)
                   AND ($pri) = $prioridade
                 ORDER BY T.DT_SOLICITACAO, T.NUM_ORDEM";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?: [];
    }

    public static function listarLiberada(int $emprId, string $dataIni, string $dataFim): array
    {
        $di = self::dateSql($dataIni);
        $df = self::dateSql($dataFim);
        $sql = "SELECT T.ID,
                       M.COD_MAQUINA||' - '||M.DESCRICAO MAQUINA,
                       TO_CHAR(T.DT_PREVISTA,'DD/MM/RRRR') DT_PREVISTA
                  FROM FOCCO3I.TORDENS_MAN T,
                       FOCCO3I.TMAQUINAS M
                 WHERE M.ID = T.MAQUINA_ID
                   AND T.TP_ORDEM <> 'OME'
                   AND T.TP_OS IN ('P','G')
                   AND T.EMPR_ID = $emprId
                   AND T.DT_PREVISTA BETWEEN $di AND ($df + 1 - 1/86400)
                 ORDER BY T.DT_PREVISTA DESC, M.COD_MAQUINA";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?: [];
    }

    public static function listarProgramada(int $emprId, string $dataIni, string $dataFim): array
    {
        $di = self::dateSql($dataIni);
        $df = self::dateSql($dataFim);
        $sql = "SELECT P.NUM_ORDEM,
                       M.COD_MAQUINA||' - '||M.DESCRICAO MAQUINA,
                       TO_CHAR(P.DT_PREVISTA,'DD/MM/RRRR') DT_PREVISTA
                  FROM FOCCO3I.TPERFIL_ITENS_CALC P,
                       FOCCO3I.TMAQUINAS M,
                       FOCCO3I.TPLANOS_MAN PLA
                 WHERE M.ID = P.MAQUINA_ID
                   AND P.TPLANO_MAN_ID = PLA.ID
                   AND P.EMPR_ID = $emprId
                   AND P.SITUACAO = 1
                   AND P.TP_ORDEM = 'OMP'
                   AND P.TP_OS = 'P'
                   AND PLA.COD_PLANO = 1
                   AND P.DT_PREVISTA BETWEEN $di AND ($df + 1 - 1/86400)
                 ORDER BY P.NUM_ORDEM";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?: [];
    }

    public static function listarFuncionarios(int $emprId): array
    {
        $sql = "SELECT F.ID, F.NOME
                  FROM FOCCO3I.TFUNCIONARIOS F,
                       FOCCO3I.TFUNCOES FU
                 WHERE F.ID = FU.FUNC_ID
                   AND F.EMPR_ID = $emprId
                   AND FU.COD_FUNCAO = 'MEC'
                 ORDER BY F.NOME";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?: [];
    }

    public static function listarSolicitantes(int $emprId): array
    {
        $sql = "SELECT ID, COD_FUNC, NOME, COD_FUNC||' - '||NOME LABEL
                  FROM FOCCO3I.TFUNCIONARIOS
                 WHERE EMPR_ID = $emprId AND SIT = 1
                 ORDER BY NOME";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?: [];
    }

    public static function listarMaquinas(int $emprId): array
    {
        $sql = "SELECT ID, COD_MAQUINA, DESCRICAO,
                       COD_MAQUINA||' - '||DESCRICAO NOME
                  FROM FOCCO3I.TMAQUINAS
                 WHERE EMPR_ID = $emprId
                 ORDER BY COD_MAQUINA";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?: [];
    }

    public static function gerarOrdem(array $d): int
    {
        $emprId    = (int) $d['empr_id'];
        $maqId     = (int) $d['maquina_id'];
        $funcId    = $d['func_id'] ? (int) $d['func_id'] : 'NULL';
        $tpOs      = str_replace("'", "''", $d['tp_os']      ?? 'C');
        $situacao  = str_replace("'", "''", $d['situacao']   ?? 'L');
        $problema  = str_replace("'", "''", $d['des_problema'] ?? '');
        $tpProb    = $d['tp_problema']  ? (int) $d['tp_problema']  : 'NULL';
        $tpPar     = $d['tp_maq_parada'] ? (int) $d['tp_maq_parada'] : 'NULL';
        $urgente   = $d['ind_urgente']  ? 1 : 0;
        $dtSol     = str_replace("'", "''", $d['dt_solicitacao'] ?? date('d/m/Y'));
        $dtPrev    = $d['dt_prevista'] ? "TO_DATE('" . str_replace("'","''",$d['dt_prevista']) . "','DD/MM/RRRR')" : 'NULL';

        // Próximo número de ordem
        $numRes = Database::switchParams('focco', [], null, true, false, null,
            "SELECT NVL(MAX(NUM_ORDEM),0)+1 PROX FROM FOCCO3I.TORDENS_MAN WHERE EMPR_ID = $emprId");
        $numOrdem = (int)(($numRes['retorno'][0] ?? [])['PROX'] ?? 1);

        $sql = "INSERT INTO FOCCO3I.TORDENS_MAN VALUES (
                    FOCCO3I.SEQ_ID_TORDENS_MAN.NEXTVAL,
                    $maqId,
                    $numOrdem,
                    TO_DATE('$dtSol','DD/MM/RRRR'),
                    $dtPrev,
                    $urgente,
                    '$problema',
                    '$tpOs',
                    '$situacao',
                    'OMA',
                    $emprId,
                    $funcId,
                    NULL,
                    0,
                    0,
                    $tpProb,
                    $tpPar,
                    SYSDATE
                )";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        if (!empty($res['error'])) throw new \Exception('Erro ao gerar ordem: ' . $res['error']);
        Database::getInstance('focco')->exec('COMMIT');

        // Retorna o ID gerado
        $idRes = Database::switchParams('focco', [], null, true, false, null,
            "SELECT MAX(ID) ID FROM FOCCO3I.TORDENS_MAN WHERE EMPR_ID = $emprId AND NUM_ORDEM = $numOrdem");
        return (int)(($idRes['retorno'][0] ?? [])['ID'] ?? 0);
    }

    public static function atender(array $ids): void
    {
        $idsStr = implode(',', array_map('intval', $ids));
        $sql = "INSERT INTO TORDENS_MAN_ATEND_OTM
                SELECT SEQ_TORDENS_MAN_ATEND_OTM.NEXTVAL, T.ID, SYSDATE
                  FROM FOCCO3I.TORDENS_MAN T
                 WHERE T.ID IN ($idsStr)
                   AND NOT EXISTS (SELECT 1 FROM TORDENS_MAN_ATEND_OTM X WHERE X.ORDEM_ID = T.ID)";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        if (!empty($res['error'])) throw new \Exception('Erro ao registrar atendimento: ' . $res['error']);
        Database::getInstance('focco')->exec('COMMIT');
    }

    public static function marcarOk(array $ids, int $funcId): void
    {
        $idsStr = implode(',', array_map('intval', $ids));
        $sql = "INSERT INTO TORDENS_MAN_ATEND_OK_OTM (ID, ORDEM_ID, NUM_ORDEM, FUNC_ID)
                SELECT SEQ_TORDENS_MAN_ATEND_OK_OTM.NEXTVAL, T.ID, T.NUM_ORDEM, $funcId
                  FROM FOCCO3I.TORDENS_MAN T
                 WHERE T.ID IN ($idsStr)
                   AND NOT EXISTS (SELECT 1 FROM TORDENS_MAN_ATEND_OK_OTM X WHERE X.ORDEM_ID = T.ID)";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        if (!empty($res['error'])) throw new \Exception('Erro ao marcar OK: ' . $res['error']);
        Database::getInstance('focco')->exec('COMMIT');
    }

    public static function desmarcarOk(array $ids): void
    {
        $idsStr = implode(',', array_map('intval', $ids));
        $sql = "BEGIN
                    FOR c IN (SELECT T.ID ORDEM_ID FROM FOCCO3I.TORDENS_MAN T WHERE T.ID IN ($idsStr))
                    LOOP
                        DELETE FROM TORDENS_MAN_ATEND_OK_OTM WHERE ORDEM_ID = C.ORDEM_ID;
                    END LOOP;
                    COMMIT;
                END;";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        if (!empty($res['error'])) {
            throw new \Exception('Erro ao desmarcar OK: ' . $res['error']);
        }
    }

    public static function fechar(array $ids, string $obs = ''): void
    {
        $idsStr = implode(',', array_map('intval', $ids));
        $sql = "UPDATE FOCCO3I.TORDENS_MAN SET DT_FECHAMENTO = TRUNC(SYSDATE), TP_ORDEM = 'OME' WHERE ID IN ($idsStr)";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        if (!empty($res['error'])) throw new \Exception('Erro ao fechar ordem: ' . $res['error']);
        Database::getInstance('focco')->exec('COMMIT');
    }

    public static function listarLiberacao(int $emprId): array
    {
        $pri = self::priCase();
        $sql = "SELECT T.ID,
                       T.NUM_ORDEM,
                       TO_CHAR(T.DT_SOLICITACAO,'DD/MM/RRRR HH24:MI') DT_SOLICITACAO,
                       M.COD_MAQUINA||' - '||M.DESCRICAO              RECURSO,
                       DECODE(T.TP_PROBLEMA,1,'Elétrico',2,'Mecânico',3,'Ferramenta',4,'Pneumático',NULL) TP_PROBLEMA,
                       DECODE(T.TP_OS,'P','Preventiva','M','Melhoria/TPM','C','Corretiva','G','Programada') TP_OS,
                       DECODE(M.IND_CRITICO,1,'Sim','Não')             IND_CRITICO,
                       DECODE(T.TP_MAQ_PARADA,1,'Sim',2,'Não',NULL)   MAQ_PARADA,
                       T.DES_PROBLEMA,
                       CASE WHEN (SELECT COUNT(*) FROM TORDENS_MAN_ATEND_OTM C WHERE C.ORDEM_ID = T.ID) > 0
                            THEN 'S' ELSE 'N' END TEM_ATEND,
                       F.NOME FUNC_OK,
                       ($pri) PRIORIDADE
                  FROM FOCCO3I.TMAQUINAS           M,
                       FOCCO3I.TORDENS_MAN         T,
                       TORDENS_MAN_ATEND_OK_OTM    O,
                       FOCCO3I.TFUNCIONARIOS       F
                 WHERE M.ID = T.MAQUINA_ID
                   AND T.ID = O.ORDEM_ID
                   AND F.ID = O.FUNC_ID
                   AND (T.SITUACAO IS NULL OR T.SITUACAO <> 'F')
                   AND T.TP_ORDEM <> 'OME'
                   AND T.EMPR_ID = $emprId
                 ORDER BY ($pri), T.DT_SOLICITACAO, T.NUM_ORDEM";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        return $res['retorno'] ?: [];
    }

    public static function fecharLiberacao(array $ids): void
    {
        $idsStr = implode(',', array_map('intval', $ids));
        $sql = "BEGIN
                    FOR c IN (SELECT ID ORDEM_ID FROM FOCCO3I.TORDENS_MAN WHERE ID IN ($idsStr))
                    LOOP
                        UPDATE FOCCO3I.TORDENS_MAN SET SITUACAO = 'F', DT_FECHAMENTO = TRUNC(SYSDATE)
                         WHERE ID = C.ORDEM_ID;
                    END LOOP;
                    COMMIT;
                END;";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        if (!empty($res['error'])) {
            throw new \Exception('Erro ao fechar liberação: ' . $res['error']);
        }
    }

    public static function excluir(array $ids): void
    {
        $idsStr = implode(',', array_map('intval', $ids));
        $sql = "BEGIN
                    DELETE FROM FOCCO3I.TORDENS_MAN WHERE ID IN ($idsStr);
                    COMMIT;
                END;";
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        if (!empty($res['error'])) {
            throw new \Exception('Erro ao excluir ordem: ' . $res['error']);
        }
    }
}
