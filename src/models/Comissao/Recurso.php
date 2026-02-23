<?php

namespace src\models\Comissao;

use core\Database;
use PDO;

/**
 * Model para consulta de Recursos/Máquinas no FOCCO
 * Tabela: FOCCO3I.TMAQUINAS
 */
class Recurso
{
    /**
     * Listar todos os recursos ativos
     * @param int $emprId ID da empresa (opcional)
     * @param int $centroTrabId ID do centro de trabalho (opcional)
     * @return array
     */
    public function listarAtivos($emprId = null, $centroTrabId = null)
    {
        $sql = "SELECT 
                    M.ID,
                    M.COD_MAQUINA,
                    M.DESCRICAO,
                    M.SIT,
                    M.CENTR_TRAB_ID,
                    M.EMPR_ID,
                    M.FUNC_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO_TRAB,
                    F.NOME AS NOME_FUNCIONARIO
                FROM FOCCO3I.TMAQUINAS M
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = M.CENTR_TRAB_ID
                LEFT JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = M.FUNC_ID
                WHERE M.SIT = 1
                  AND M.TP_RECURSO = 'M'";
        
        if ($emprId) {
            $sql .= " AND M.EMPR_ID = :empr_id";
        }
        
        if ($centroTrabId) {
            $sql .= " AND M.CENTR_TRAB_ID = :centro_trab_id";
        }
        
        $sql .= " ORDER BY M.COD_MAQUINA";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
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
     * Buscar recurso por ID
     * @param int $id
     * @return array|null
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT 
                    M.ID,
                    M.COD_MAQUINA,
                    M.DESCRICAO,
                    M.DT_AQUISICAO,
                    M.SIT,
                    M.MARCA,
                    M.CENTR_TRAB_ID,
                    M.EMPR_ID,
                    M.FUNC_ID,
                    M.TP_RECURSO,
                    M.CAPACIDADE,
                    M.LOCALIZACAO,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO_TRAB,
                    F.NOME AS NOME_FUNCIONARIO,
                    E.RAZAO_SOCIAL AS EMPRESA
                FROM FOCCO3I.TMAQUINAS M
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = M.CENTR_TRAB_ID
                LEFT JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = M.FUNC_ID
                LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = M.EMPR_ID
                WHERE M.ID = :id";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar recurso por código
     * @param string $codMaquina
     * @param int $emprId
     * @return array|null
     */
    public function buscarPorCodigo($codMaquina, $emprId)
    {
        $sql = "SELECT 
                    M.ID,
                    M.COD_MAQUINA,
                    M.DESCRICAO,
                    M.CENTR_TRAB_ID,
                    M.EMPR_ID,
                    M.FUNC_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO_TRAB
                FROM FOCCO3I.TMAQUINAS M
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = M.CENTR_TRAB_ID
                WHERE M.COD_MAQUINA = :cod_maquina
                AND M.EMPR_ID = :empr_id";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':cod_maquina', $codMaquina, PDO::PARAM_STR);
        $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Listar recursos por centro de trabalho
     * @param int $centroTrabId
     * @return array
     */
    public function listarPorCentroTrabalho($centroTrabId)
    {
        $sql = "SELECT 
                    M.ID,
                    M.COD_MAQUINA,
                    M.DESCRICAO,
                    M.SIT,
                    M.FUNC_ID,
                    F.NOME AS NOME_FUNCIONARIO
                FROM FOCCO3I.TMAQUINAS M
                LEFT JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = M.FUNC_ID
                WHERE M.CENTR_TRAB_ID = :centro_trab_id
                AND M.SIT = 1
                ORDER BY M.COD_MAQUINA";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar recursos com funcionário associado
     * @param int $funcId
     * @return array
     */
    public function listarPorFuncionario($funcId)
    {
        $sql = "SELECT 
                    M.ID,
                    M.COD_MAQUINA,
                    M.DESCRICAO,
                    M.CENTR_TRAB_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO_TRAB
                FROM FOCCO3I.TMAQUINAS M
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = M.CENTR_TRAB_ID
                WHERE M.FUNC_ID = :func_id
                AND M.SIT = 1
                ORDER BY M.COD_MAQUINA";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':func_id', $funcId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
