<?php

namespace src\models\Comissao;

use core\Database;
use PDO;
use Exception;

/**
 * Model para Apontamentos de ProduÃ§Ã£o (Ordens de FabricaÃ§Ã£o)
 * 
 * Este model consulta os apontamentos de ordens de fabricaÃ§Ã£o do FOCCO e calcula 
 * a pontuaÃ§Ã£o baseada nas UPs dos produtos para o sistema de comissionamento.
 * 
 * Tabelas FOCCO consultadas:
 * - TORDENS - Ordens de fabricaÃ§Ã£o
 * - TORDENS_MOVTO - Movimentos/apontamentos da ordem
 * - TORDENS_ROT - Roteiro da ordem de fabricaÃ§Ã£o
 * - TORD_MOV_FAB_MAQ - RelaÃ§Ã£o movimento x mÃ¡quina
 * - TOPERACAO - OperaÃ§Ãµes
 * - TITENS, TITENS_EMPR, TITENS_PLANEJAMENTO, TITENS_ENGENHARIA - Produtos
 * - TMAQUINAS - MÃ¡quinas/Recursos
 * - TFUNCIONARIOS - FuncionÃ¡rios
 * - TMASC_ITEM - MÃ¡scara do item
 */
class ApontamentoProducao
{
    /**
     * Listar apontamentos de ordens de fabricaÃ§Ã£o por perÃ­odo e mÃ¡quina
     * Query baseada na estrutura real do FOCCO
     * 
     * @param string $dataInicio (DD/MM/YYYY)
     * @param string $dataFim (DD/MM/YYYY)
     * @param int $codMaquina
     * @param int $codEmp
     * @return array
     */
    public function listarApontamentosPorMaquina($dataInicio, $dataFim, $codMaquina, $codEmp)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TITENS.COD_ITEM AS CODIGO,
                    TITENS.DESC_TECNICA,
                    TMASC_ITEM.ID AS MASC_ID,
                    TMASC_ITEM.MASCARA,
                    TORDENS.NUM_ORDEM,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS QUANTIDADE,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO AS DESC_MAQUINA,
                    TMAQUINAS.ID AS ID_MAQUINA,
                    TFUNCIONARIOS.ID AS ID_FUNCIONARIO,
                    TFUNCIONARIOS.NOME AS NOME_FUNCIONARIO,
                    TORDENS_MOVTO.DT_APONT AS DATA_APONTAMENTO
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS_ENGENHARIA TITENS_ENGENHARIA ON TITENS_EMPR.ID = TITENS_ENGENHARIA.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                INNER JOIN FOCCO3I.TOPERACAO TOPERACAO ON TOPERACAO.ID = TORDENS_ROT.OPERACAO_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TMASC_ITEM TMASC_ITEM ON TMASC_ITEM.ID = TORDENS.TMASC_ITEM_ID
                LEFT JOIN FOCCO3I.TITENS_ENG_CONF TITENS_ENG_CONF ON TMASC_ITEM.ID = TITENS_ENG_CONF.TMASC_ITEM_ID
                WHERE TMAQUINAS.ID = :cod_maquina
                AND TORDENS.EMPR_ID = :cod_emp
                AND TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT BETWEEN TO_DATE(:data_inicio, 'DD/MM/YYYY') AND TO_DATE(:data_fim, 'DD/MM/YYYY')
                GROUP BY 
                    TITENS.COD_ITEM,
                    TITENS.DESC_TECNICA,
                    TMASC_ITEM.ID,
                    TMASC_ITEM.MASCARA,
                    TORDENS.NUM_ORDEM,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO,
                    TMAQUINAS.ID,
                    TFUNCIONARIOS.ID,
                    TFUNCIONARIOS.NOME,
                    TORDENS_MOVTO.DT_APONT
                ORDER BY TITENS.DESC_TECNICA ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':cod_maquina', $codMaquina, PDO::PARAM_INT);
        $stmt->bindParam(':cod_emp', $codEmp, PDO::PARAM_INT);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar apontamentos por perÃ­odo com filtros opcionais
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @param int $maquinaId
     * @param int $funcId
     * @return array
     */
    public function listarApontamentos($dataInicio, $dataFim, $emprId = null, $maquinaId = null, $funcId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TORDENS_MOVTO.ID AS ID_MOVTO,
                    TORDENS_MOVTO.DT_APONT AS DATA_APONTAMENTO,
                    TO_CHAR(TORDENS_MOVTO.DT_APONT, 'HH24:MI') AS HORA_APONTAMENTO,
                    TORDENS_MOVTO.QUANTIDADE,
                    TORDENS.ID AS ID_ORDEM,
                    TORDENS.NUM_ORDEM,
                    TITENS.ID AS ID_ITEM,
                    TITENS.COD_ITEM AS CODIGO_PRODUTO,
                    TITENS.DESC_TECNICA AS DESC_PRODUTO,
                    TMASC_ITEM.MASCARA,
                    TOPERACAO.ID AS ID_OPERACAO,
                    TOPERACAO.DESCRICAO AS DESC_OPERACAO,
                    TMAQUINAS.ID AS ID_MAQUINA,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO AS DESC_MAQUINA,
                    TFUNCIONARIOS.ID AS ID_FUNCIONARIO,
                    TFUNCIONARIOS.COD_FUNC AS COD_FUNCIONARIO,
                    TFUNCIONARIOS.NOME AS NOME_FUNCIONARIO,
                    NVL(PP.PONTOS_UP, 0) AS PONTOS_UP,
                    (TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0)) AS PONTOS_CALCULADOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                INNER JOIN FOCCO3I.TOPERACAO TOPERACAO ON TOPERACAO.ID = TORDENS_ROT.OPERACAO_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TMASC_ITEM TMASC_ITEM ON TMASC_ITEM.ID = TORDENS.TMASC_ITEM_ID
                LEFT JOIN FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP ON PP.ITEM_ID = TITENS.ID
                    AND PP.ATIVO = 'S'
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        if ($maquinaId) {
            $sql .= " AND TMAQUINAS.ID = :maquina_id";
        }
        
        if ($funcId) {
            $sql .= " AND TFUNCIONARIOS.ID = :func_id";
        }
        
        $sql .= " ORDER BY TORDENS_MOVTO.DT_APONT DESC, TFUNCIONARIOS.NOME";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        if ($maquinaId) {
            $stmt->bindParam(':maquina_id', $maquinaId, PDO::PARAM_INT);
        }
        
        if ($funcId) {
            $stmt->bindParam(':func_id', $funcId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumo de produtividade por funcionário (OTIMIZADO - agregação no banco)
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public function resumoPorFuncionario($dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $pdo = Database::getInstance('focco');
        // Agregação direta no Oracle para performance
        // Usa subquery escalar para pontuação (evita duplicação por múltiplos matches)
        $sql = "SELECT 
                    TFUNCIONARIOS.ID AS FUNC_ID,
                    TFUNCIONARIOS.COD_FUNC AS COD_FUNC,
                    TFUNCIONARIOS.NOME AS NOME_FUNC,
                    COUNT(TORDENS_MOVTO.ID) AS QTD_APONTAMENTOS,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QTD_BOA,
                    0 AS TOTAL_QTD_REFUGO,
                    SUM(TORDENS_MOVTO.QUANTIDADE * NVL((
                        SELECT PP.PONTOS_UP 
                        FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP 
                        WHERE PP.ATIVO = 'S'
                          AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                          AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                          AND (PP.ID_EMPR IS NULL OR PP.ID_EMPR = TORDENS.EMPR_ID)
                          AND (PP.ID_CENTRO_TRAB IS NULL OR PP.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID)
                          AND (PP.ITEM_ID = TITENS.ID OR PP.ID_ITEMPR = TITENS_EMPR.ID OR PP.ID_MASCARA = TORDENS.TMASC_ITEM_ID)
                        ORDER BY 
                            CASE WHEN PP.ITEM_ID IS NOT NULL THEN 1 
                                 WHEN PP.ID_ITEMPR IS NOT NULL THEN 2 
                                 WHEN PP.ID_MASCARA IS NOT NULL THEN 3 
                                 ELSE 4 END,
                            CASE WHEN PP.ID_CENTRO_TRAB IS NOT NULL THEN 1 ELSE 2 END
                        FETCH FIRST 1 ROW ONLY
                    ), 0)) AS TOTAL_PONTOS,
                    TORDENS_ROT.CENTR_TRAB_ID AS CENTRO_TRAB_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                -- Buscar máquina/recurso
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                -- Buscar vínculo do funcionário com o recurso/centro (ou vínculo tipo ajudante sem recurso)
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON 
                    (VF.ID_RECURSO = TMAQUINAS.ID OR (VF.ID_RECURSO IS NULL AND VF.TIPO_VINCULO = 'A'))
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                    AND VF.ID_EMPR = TORDENS.EMPR_ID
                -- Buscar dados do funcionário vinculado
                INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = VF.ID_FUNCIONARIO
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";

        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        if ($centroTrabId) {
            $sql .= " AND TORDENS_ROT.CENTR_TRAB_ID = :centro_trab_id";
        }
        
        $sql .= " GROUP BY TFUNCIONARIOS.ID, TFUNCIONARIOS.COD_FUNC, TFUNCIONARIOS.NOME,
                          TORDENS_ROT.CENTR_TRAB_ID, CT.COD_CENTRO, CT.DESCRICAO
                  ORDER BY TFUNCIONARIOS.NOME";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumo de produtividade por centro de trabalho
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public function resumoPorCentroTrabalho($dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    CT.ID AS CENTRO_TRAB_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    COUNT(DISTINCT TORDENS_MOVTO.ID) AS QTD_APONTAMENTOS,
                    COUNT(DISTINCT TFUNCIONARIOS.ID) AS QTD_FUNCIONARIOS,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    0 AS TOTAL_PONTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                LEFT JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        if ($centroTrabId) {
            $sql .= " AND CT.ID = :centro_trab_id";
        }
        
        $sql .= " GROUP BY 
                    CT.ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO
                  ORDER BY TOTAL_QUANTIDADE DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumo de produtividade por recurso/mÃ¡quina com filtro por centro
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public function resumoPorRecurso($dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TMAQUINAS.ID AS ID_MAQUINA,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO AS DESC_MAQUINA,
                    CT.ID AS CENTRO_TRAB_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    COUNT(DISTINCT TORDENS_MOVTO.ID) AS QTD_APONTAMENTOS,
                    COUNT(DISTINCT TFUNCIONARIOS.ID) AS QTD_FUNCIONARIOS,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    0 AS TOTAL_PONTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                INNER JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                LEFT JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($centroTrabId) {
            $sql .= " AND CT.ID = :centro_trab_id";
        }
        
        $sql .= " GROUP BY 
                    TMAQUINAS.ID,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO,
                    CT.ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO
                  ORDER BY TOTAL_QUANTIDADE DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumo de produtividade por mÃ¡quina/recurso
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @return array
     */
    public function resumoPorMaquina($dataInicio, $dataFim, $emprId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TMAQUINAS.ID AS ID_MAQUINA,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO AS DESC_MAQUINA,
                    COUNT(DISTINCT TORDENS_MOVTO.ID) AS QTD_APONTAMENTOS,
                    COUNT(DISTINCT TFUNCIONARIOS.ID) AS QTD_FUNCIONARIOS,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    0 AS TOTAL_PONTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                LEFT JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                INNER JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                INNER JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        $sql .= " GROUP BY 
                    TMAQUINAS.ID,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO
                  ORDER BY TOTAL_PONTOS DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar pontos por dia de um funcionÃ¡rio especÃ­fico
     * Usado para calcular comissÃ£o considerando faltas por dia
     * @param int $funcId
     * @param string $dataInicio
     * @param string $dataFim
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public function pontosPorDiaFuncionario($funcId, $dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TO_CHAR(TRUNC(TORDENS_MOVTO.DT_APONT), 'YYYY-MM-DD') AS DATA_APONTAMENTO,
                    SUM(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0)) AS TOTAL_PONTOS,
                    COUNT(*) AS QTD_APONTAMENTOS,
                    MAX(CT.DESCRICAO) AS CENTRO_TRABALHO,
                    MAX(TMAQUINAS.DESCRICAO) AS RECURSO
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                -- VÃ­nculo funcionÃ¡rio/recurso/centro
                LEFT JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON VF.ID_RECURSO = TMAQUINAS.ID
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                -- PontuaÃ§Ã£o do produto
                LEFT JOIN (
                    SELECT PP_SUB.*, ROW_NUMBER() OVER (PARTITION BY PP_SUB.ITEM_ID ORDER BY PP_SUB.DT_VIGENCIA_INI DESC) AS RN
                    FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP_SUB
                    WHERE PP_SUB.ATIVO = 'S'
                ) PP ON PP.ITEM_ID = TITENS.ID
                    AND PP.RN = 1
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND VF.ID_FUNCIONARIO = :func_id
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        if ($centroTrabId) {
            $sql .= " AND TORDENS_ROT.CENTR_TRAB_ID = :centro_trab_id";
        }
        
        $sql .= " GROUP BY TRUNC(TORDENS_MOVTO.DT_APONT)
                  ORDER BY 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':func_id', $funcId, PDO::PARAM_INT);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar apontamentos vinculados ao funcionário via recurso/centro
     * Agrupado por item (código + máscara)
     * @param int $funcId ID do funcionário
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public function listarApontamentosVinculados($funcId, $dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TITENS.COD_ITEM AS CODIGO_PRODUTO,
                    TITENS.DESC_TECNICA AS DESC_PRODUTO,
                    TMASC_ITEM.MASCARA,
                    MAX(TOPERACAO.DESCRICAO) AS DESC_OPERACAO,
                    MAX(TMAQUINAS.COD_MAQUINA) AS COD_MAQUINA,
                    MAX(TMAQUINAS.DESCRICAO) AS DESC_MAQUINA,
                    MAX(CT.COD_CENTRO) AS COD_CENTRO,
                    MAX(CT.DESCRICAO) AS DESC_CENTRO,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS QUANTIDADE,
                    MAX(NVL(PP.PONTOS_UP, 0)) AS PONTOS_UP,
                    SUM(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0)) AS TOTAL_PONTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                INNER JOIN FOCCO3I.TOPERACAO TOPERACAO ON TOPERACAO.ID = TORDENS_ROT.OPERACAO_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TMASC_ITEM TMASC_ITEM ON TMASC_ITEM.ID = TORDENS.TMASC_ITEM_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                -- Vínculo funcionário/recurso/centro
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON VF.ID_RECURSO = TMAQUINAS.ID
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                -- Pontuação do produto (vigente na data do apontamento)
                LEFT JOIN (
                    SELECT PP_SUB.*, ROW_NUMBER() OVER (PARTITION BY PP_SUB.ITEM_ID ORDER BY PP_SUB.DT_VIGENCIA_INI DESC) AS RN
                    FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP_SUB
                    WHERE PP_SUB.ATIVO = 'S'
                ) PP ON PP.ITEM_ID = TITENS.ID
                    AND PP.RN = 1
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND VF.ID_FUNCIONARIO = :func_id
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        if ($centroTrabId) {
            $sql .= " AND TORDENS_ROT.CENTR_TRAB_ID = :centro_trab_id";
        }
        
        $sql .= " GROUP BY TITENS.COD_ITEM, TITENS.DESC_TECNICA, TMASC_ITEM.MASCARA
                  ORDER BY TOTAL_PONTOS DESC, TITENS.DESC_TECNICA";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':func_id', $funcId, PDO::PARAM_INT);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $data Data específica (YYYY-MM-DD)
     * @param int $emprId
     * @param int $maquinaId
     * @return array
     */
    public function produtividadeDiaria($data, $emprId = null, $maquinaId = null, $centroTrabId = null, $dataFim = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TRUNC(TORDENS_MOVTO.DT_APONT) AS DT_APONT,
                    TFUNCIONARIOS.ID AS ID_FUNCIONARIO,
                    TFUNCIONARIOS.COD_FUNC AS COD_FUNCIONARIO,
                    TFUNCIONARIOS.NOME AS NOME_FUNCIONARIO,
                    TITENS.ID AS ID_ITEM,
                    TITENS.COD_ITEM AS CODIGO_PRODUTO,
                    TITENS.DESC_TECNICA AS DESC_PRODUTO,
                    TMASC_ITEM.ID AS ID_MASCARA,
                    TMASC_ITEM.MASCARA,
                    TORDENS.NUM_ORDEM,
                    TORDENS.EMPR_ID,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS QUANTIDADE,
                    TMAQUINAS.ID AS ID_MAQUINA,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO AS DESC_MAQUINA,
                    TORDENS_ROT.CENTR_TRAB_ID AS ID_CENTRO_TRAB,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    TOPERACAO.DESCRICAO AS DESC_OPERACAO,
                    -- PontuaÃ§Ã£o do produto
                    NVL(PP.PONTOS_UP, 0) AS PONTOS_UP,
                    PP.ID_PONTUACAO AS ID_PONTUACAO,
                    CASE WHEN PP.ID_PONTUACAO IS NOT NULL THEN 'S' ELSE 'N' END AS TEM_PONTUACAO,
                    -- Faixa de comissÃ£o
                    FC.ID_FAIXA AS ID_FAIXA,
                    FC.DESCRICAO AS DESC_FAIXA,
                    FC.VALOR_COMISSAO,
                    FC.TIPO AS TIPO_FAIXA,
                    CASE WHEN FC.ID_FAIXA IS NOT NULL THEN 'S' ELSE 'N' END AS TEM_FAIXA,
                    -- VÃ­nculo funcionÃ¡rio/recurso/centro
                    VF.ID_VINCULO AS ID_VINCULO,
                    CASE WHEN VF.ID_VINCULO IS NOT NULL THEN 'S' ELSE 'N' END AS TEM_VINCULO,
                    -- Quantidade de funcionÃ¡rios vinculados ao mesmo recurso/centro (para dividir pontuaÃ§Ã£o)
                    NVL(VF_COUNT.QTD_VINCULADOS, 1) AS QTD_VINCULADOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                INNER JOIN FOCCO3I.TOPERACAO TOPERACAO ON TOPERACAO.ID = TORDENS_ROT.OPERACAO_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TMASC_ITEM TMASC_ITEM ON TMASC_ITEM.ID = TORDENS.TMASC_ITEM_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                -- VÃ­nculo funcionÃ¡rio com recurso e centro (sem RN=1 para trazer TODOS os funcionÃ¡rios vinculados)
                LEFT JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON VF.ID_RECURSO = TMAQUINAS.ID
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                    AND VF.ID_EMPR = TORDENS.EMPR_ID
                -- FuncionÃ¡rio do vÃ­nculo
                LEFT JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = VF.ID_FUNCIONARIO
                -- PontuaÃ§Ã£o do produto (vigente na data do apontamento)
                LEFT JOIN (
                    SELECT PP_SUB.*, ROW_NUMBER() OVER (PARTITION BY PP_SUB.ITEM_ID ORDER BY PP_SUB.DT_VIGENCIA_INI DESC) AS RN
                    FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP_SUB
                    WHERE PP_SUB.ATIVO = 'S'
                ) PP ON PP.ITEM_ID = TITENS.ID
                    AND PP.RN = 1
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                -- Faixa de comissÃ£o do centro de trabalho
                LEFT JOIN (
                    SELECT FC_SUB.*, ROW_NUMBER() OVER (PARTITION BY FC_SUB.CENTRO_TRAB_ID ORDER BY FC_SUB.DT_VIGENCIA_INI DESC) AS RN
                    FROM FOCCO3I.TGAZIN_FAIXA_COMISSAO FC_SUB
                    WHERE FC_SUB.ATIVO = 'S'
                ) FC ON FC.CENTRO_TRAB_ID = TORDENS_ROT.CENTR_TRAB_ID
                    AND FC.RN = 1
                    AND FC.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (FC.DT_VIGENCIA_FIM IS NULL OR FC.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                -- Contagem de funcionÃ¡rios vinculados ao mesmo recurso/centro (para dividir pontuaÃ§Ã£o)
                LEFT JOIN (
                    SELECT VF_CNT.ID_RECURSO, VF_CNT.ID_CENTRO_TRAB, VF_CNT.ID_EMPR, COUNT(*) AS QTD_VINCULADOS
                    FROM FOCCO3I.TGAZIN_VINC_FUNC VF_CNT
                    WHERE VF_CNT.ATIVO = 'S'
                    GROUP BY VF_CNT.ID_RECURSO, VF_CNT.ID_CENTRO_TRAB, VF_CNT.ID_EMPR
                ) VF_COUNT ON VF_COUNT.ID_RECURSO = TMAQUINAS.ID
                    AND VF_COUNT.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF_COUNT.ID_EMPR = TORDENS.EMPR_ID
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1";
        
        // Filtro por data (perÃ­odo ou dia Ãºnico)
        if ($dataFim) {
            $sql .= " AND TORDENS_MOVTO.DT_APONT BETWEEN TO_DATE(:data_inicio, 'YYYY-MM-DD') AND TO_DATE(:data_fim, 'YYYY-MM-DD') + 0.99999";
        } else {
            $sql .= " AND TRUNC(TORDENS_MOVTO.DT_APONT) = TO_DATE(:data_inicio, 'YYYY-MM-DD')";
        }
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        if ($maquinaId) {
            $sql .= " AND TMAQUINAS.ID = :maquina_id";
        }
        
        if ($centroTrabId) {
            $sql .= " AND TORDENS_ROT.CENTR_TRAB_ID = :centro_trab_id";
        }
        
        $sql .= " GROUP BY 
                    TRUNC(TORDENS_MOVTO.DT_APONT),
                    TFUNCIONARIOS.ID, TFUNCIONARIOS.COD_FUNC, TFUNCIONARIOS.NOME,
                    TITENS.ID, TITENS.COD_ITEM, TITENS.DESC_TECNICA,
                    TMASC_ITEM.ID, TMASC_ITEM.MASCARA,
                    TORDENS.NUM_ORDEM, TORDENS.EMPR_ID,
                    TMAQUINAS.ID, TMAQUINAS.COD_MAQUINA, TMAQUINAS.DESCRICAO,
                    TORDENS_ROT.CENTR_TRAB_ID, CT.COD_CENTRO, CT.DESCRICAO,
                    TOPERACAO.DESCRICAO,
                    PP.PONTOS_UP, PP.ID_PONTUACAO,
                    FC.ID_FAIXA, FC.DESCRICAO, FC.VALOR_COMISSAO, FC.TIPO,
                    VF.ID_VINCULO, VF_COUNT.QTD_VINCULADOS
                ORDER BY TRUNC(TORDENS_MOVTO.DT_APONT), TFUNCIONARIOS.NOME, TITENS.DESC_TECNICA";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $data, PDO::PARAM_STR);
        
        if ($dataFim) {
            $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        }
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        if ($maquinaId) {
            $stmt->bindParam(':maquina_id', $maquinaId, PDO::PARAM_INT);
        }
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumo geral do perÃ­odo
     * @param string $dataInicio
     * @param string $dataFim
     * @param int $emprId
     * @return array
     */
    public function resumoGeral($dataInicio, $dataFim, $emprId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    COUNT(DISTINCT TORDENS_MOVTO.ID) AS TOTAL_APONTAMENTOS,
                    COUNT(DISTINCT TFUNCIONARIOS.ID) AS TOTAL_FUNCIONARIOS,
                    COUNT(DISTINCT TMAQUINAS.ID) AS TOTAL_MAQUINAS,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    0 AS TOTAL_PONTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                LEFT JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * EvoluÃ§Ã£o diÃ¡ria de pontos no perÃ­odo
     * @param string $dataInicio
     * @param string $dataFim
     * @param int $emprId
     * @param int $funcId
     * @param int $maquinaId
     * @return array
     */
    public function evolucaoDiaria($dataInicio, $dataFim, $emprId = null, $funcId = null, $maquinaId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TRUNC(TORDENS_MOVTO.DT_APONT) AS DATA,
                    COUNT(DISTINCT TORDENS_MOVTO.ID) AS QTD_APONTAMENTOS,
                    COUNT(DISTINCT TFUNCIONARIOS.ID) AS QTD_FUNCIONARIOS,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    SUM(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0)) AS TOTAL_PONTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP ON PP.ITEM_ID = TITENS.ID
                    AND PP.ATIVO = 'S'
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        if ($funcId) {
            $sql .= " AND TFUNCIONARIOS.ID = :func_id";
        }
        
        if ($maquinaId) {
            $sql .= " AND TMAQUINAS.ID = :maquina_id";
        }
        
        $sql .= " GROUP BY TRUNC(TORDENS_MOVTO.DT_APONT)
                  ORDER BY DATA";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        if ($funcId) {
            $stmt->bindParam(':func_id', $funcId, PDO::PARAM_INT);
        }
        
        if ($maquinaId) {
            $stmt->bindParam(':maquina_id', $maquinaId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ranking de funcionÃ¡rios por pontuaÃ§Ã£o
     * @param string $dataInicio
     * @param string $dataFim
     * @param int $emprId
     * @param int $limite
     * @return array
     */
    public function rankingFuncionarios($dataInicio, $dataFim, $emprId = null, $limite = 20)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT * FROM (
                    SELECT 
                        TFUNCIONARIOS.ID AS ID_FUNCIONARIO,
                        TFUNCIONARIOS.COD_FUNC AS COD_FUNCIONARIO,
                        TFUNCIONARIOS.NOME AS NOME_FUNCIONARIO,
                        COUNT(DISTINCT TORDENS_MOVTO.ID) AS QTD_APONTAMENTOS,
                        SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                        SUM(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0)) AS TOTAL_PONTOS,
                        ROUND(AVG(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0)), 2) AS MEDIA_PONTOS
                    FROM FOCCO3I.TORDENS TORDENS
                    INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                    INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                    INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                    INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                    INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                    INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                    LEFT JOIN FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP ON PP.ITEM_ID = TITENS.ID
                        AND PP.ATIVO = 'S'
                        AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                        AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                    WHERE TORDENS_ROT.APONTAMENTO = 1
                    AND TORDENS_ROT.OBRIGATORIO = 1
                    AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                    AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        $sql .= " GROUP BY 
                        TFUNCIONARIOS.ID,
                        TFUNCIONARIOS.COD_FUNC,
                        TFUNCIONARIOS.NOME
                      ORDER BY TOTAL_PONTOS DESC
                  ) WHERE ROWNUM <= :limite";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna pontos agrupados por dia para cálculo de comissão (otimizado)
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $funcionarioId ID do funcionário
     * @param int $emprId ID da empresa
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @return array
     */
    public function pontosPorDia($dataInicio, $dataFim, $funcionarioId, $emprId, $centroTrabId = null)
    {
        $pdo = Database::getInstance('focco');
        
        // Usa subquery escalar para pontuação (evita duplicação por múltiplos matches)
        $sql = "SELECT 
                    TO_CHAR(TRUNC(TORDENS_MOVTO.DT_APONT), 'YYYY-MM-DD') AS DATA_APONTAMENTO,
                    SUM(ROUND(TORDENS_MOVTO.QUANTIDADE * NVL((
                        SELECT PP.PONTOS_UP 
                        FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP 
                        WHERE PP.ATIVO = 'S'
                          AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                          AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                          AND (PP.ID_EMPR IS NULL OR PP.ID_EMPR = TORDENS.EMPR_ID)
                          AND (PP.ID_CENTRO_TRAB IS NULL OR PP.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID)
                          AND (PP.ITEM_ID = TITENS.ID OR PP.ID_ITEMPR = TITENS_EMPR.ID OR PP.ID_MASCARA = TORDENS.TMASC_ITEM_ID)
                        ORDER BY 
                            CASE WHEN PP.ITEM_ID IS NOT NULL THEN 1 
                                 WHEN PP.ID_ITEMPR IS NOT NULL THEN 2 
                                 WHEN PP.ID_MASCARA IS NOT NULL THEN 3 
                                 ELSE 4 END,
                            CASE WHEN PP.ID_CENTRO_TRAB IS NOT NULL THEN 1 ELSE 2 END
                        FETCH FIRST 1 ROW ONLY
                    ), 0), 2)) AS TOTAL_PONTOS,
                    COUNT(*) AS QTD_APONTAMENTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                -- Buscar máquina/recurso
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                -- Buscar vínculo do funcionário com o recurso/centro (ou vínculo tipo ajudante sem recurso)
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON 
                    (VF.ID_RECURSO = TMAQUINAS.ID OR (VF.ID_RECURSO IS NULL AND VF.TIPO_VINCULO = 'A'))
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                    AND VF.ID_EMPR = TORDENS.EMPR_ID
                    AND VF.ID_FUNCIONARIO = :funcionario_id
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1
                AND TORDENS.EMPR_ID = :empr_id";
        
        if ($centroTrabId) {
            $sql .= " AND TORDENS_ROT.CENTR_TRAB_ID = :centro_trab_id";
        }
        
        $sql .= " GROUP BY TO_CHAR(TRUNC(TORDENS_MOVTO.DT_APONT), 'YYYY-MM-DD')
                ORDER BY TO_CHAR(TRUNC(TORDENS_MOVTO.DT_APONT), 'YYYY-MM-DD')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        $stmt->bindParam(':funcionario_id', $funcionarioId, PDO::PARAM_INT);
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista apontamentos com pontuação calculada para cálculo de comissão
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param array $funcionarioIds Array de IDs de funcionários
     * @param int $emprId ID da empresa
     * @return array
     */
    public function listarApontamentosComPontuacao($dataInicio, $dataFim, $funcionarioIds, $emprId)
    {
        $pdo = Database::getInstance('focco');
        
        // Converte array de IDs para string para usar em IN clause
        $idsPlaceholders = [];
        foreach ($funcionarioIds as $index => $id) {
            $idsPlaceholders[] = ':func_id_' . $index;
        }
        $inClause = implode(',', $idsPlaceholders);
        
        $sql = "SELECT 
                    TORDENS_MOVTO.ID AS APONTAMENTO_ID,
                    TORDENS_MOVTO.DT_APONT AS DATA_APONTAMENTO,
                    TFUNCIONARIOS.ID AS FUNCIONARIO_ID,
                    TFUNCIONARIOS.COD_FUNC,
                    TFUNCIONARIOS.NOME AS NOME_FUNCIONARIO,
                    TITENS.ID AS ITEM_ID,
                    TITENS.COD_ITEM,
                    TITENS.DESC_TECNICA AS DESC_ITEM,
                    TORDENS_MOVTO.QUANTIDADE,
                    NVL(PP.PONTOS_UP, 0) AS PONTOS_UP,
                    ROUND(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0), 2) AS TOTAL_PONTOS,
                    TORDENS.EMPR_ID,
                    TORDENS_ROT.CENTR_TRAB_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    TORDENS.ID AS ORDEM_ID,
                    TORDENS.NUM_ORDEM
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                -- Buscar máquina/recurso
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                -- Buscar vínculo do funcionário com o recurso/centro (ou vínculo tipo ajudante sem recurso)
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON 
                    (VF.ID_RECURSO = TMAQUINAS.ID OR (VF.ID_RECURSO IS NULL AND VF.TIPO_VINCULO = 'A'))
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                    AND VF.ID_EMPR = TORDENS.EMPR_ID
                -- Buscar dados do funcionário vinculado
                INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = VF.ID_FUNCIONARIO
                -- Pontuação do produto (por ITEM ou por ITEM_EMPR ou por MASCARA)
                LEFT JOIN FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP ON 
                    (PP.ITEM_ID = TITENS.ID OR PP.ID_ITEMPR = TITENS_EMPR.ID OR PP.ID_MASCARA = TORDENS.TMASC_ITEM_ID)
                    AND PP.ATIVO = 'S'
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                    AND (PP.ID_EMPR IS NULL OR PP.ID_EMPR = TORDENS.EMPR_ID)
                    AND (PP.ID_CENTRO_TRAB IS NULL OR PP.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID)
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1
                AND TORDENS.EMPR_ID = :empr_id
                AND VF.ID_FUNCIONARIO IN ({$inClause})
                ORDER BY TFUNCIONARIOS.ID, TORDENS_MOVTO.DT_APONT";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        
        foreach ($funcionarioIds as $index => $id) {
            $stmt->bindValue(':func_id_' . $index, $id, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista apontamentos vinculados manualmente (sem recurso) com pontuaÃ§Ã£o
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param array $funcionarioIds Array de IDs de funcionÃ¡rios
     * @param int $emprId ID da empresa
     * @return array
     */
    public function listarApontamentosVinculadosComPontuacao($dataInicio, $dataFim, $funcionarioIds, $emprId)
    {
        $pdo = Database::getInstance('focco');
        
        // Converte array de IDs para string para usar em IN clause
        $idsPlaceholders = [];
        foreach ($funcionarioIds as $index => $id) {
            $idsPlaceholders[] = ':func_id_' . $index;
        }
        $inClause = implode(',', $idsPlaceholders);
        
        $sql = "SELECT 
                    VA.ID AS VINCULO_ID,
                    VA.APONTAMENTO_ID,
                    TORDENS_MOVTO.DT_APONT AS DATA_APONTAMENTO,
                    VA.FUNCIONARIO_ID,
                    TFUNCIONARIOS.COD_FUNC,
                    TFUNCIONARIOS.NOME AS NOME_FUNCIONARIO,
                    TITENS.ID AS ITEM_ID,
                    TITENS.COD_ITEM,
                    TITENS.DESC_TECNICA AS DESC_ITEM,
                    TORDENS_MOVTO.QUANTIDADE,
                    NVL(PP.PONTOS_UP, 0) AS PONTOS_UP,
                    ROUND(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0), 2) AS TOTAL_PONTOS,
                    TORDENS.EMPR_ID,
                    TORDENS_ROT.CENTR_TRAB_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    TORDENS.ID AS ORDEM_ID,
                    TORDENS.NUM_ORDEM
                FROM FOCCO3I.TGAZIN_VINC_APONTAMENTO VA
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_MOVTO.ID = VA.APONTAMENTO_ID
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TORDENS TORDENS ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = VA.FUNCIONARIO_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                -- Pontuação do produto (por ITEM ou por ITEM_EMPR ou por MASCARA)
                LEFT JOIN FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP ON 
                    (PP.ITEM_ID = TITENS.ID OR PP.ID_ITEMPR = TITENS_EMPR.ID OR PP.ID_MASCARA = TORDENS.TMASC_ITEM_ID)
                    AND PP.ATIVO = 'S'
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                    AND (PP.ID_EMPR IS NULL OR PP.ID_EMPR = TORDENS.EMPR_ID)
                    AND (PP.ID_CENTRO_TRAB IS NULL OR PP.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID)
                WHERE VA.ATIVO = 'S'
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1
                AND TORDENS.EMPR_ID = :empr_id
                AND VA.FUNCIONARIO_ID IN ({$inClause})
                ORDER BY VA.FUNCIONARIO_ID, TORDENS_MOVTO.DT_APONT";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        
        foreach ($funcionarioIds as $index => $id) {
            $stmt->bindValue(':func_id_' . $index, $id, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


