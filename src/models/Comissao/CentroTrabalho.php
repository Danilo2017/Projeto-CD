<?php

namespace src\models\Comissao;

use core\Database;
use PDO;

/**
 * Model para consulta de Centros de Trabalho no FOCCO
 * Tabela: FOCCO3I.TCENTROS_TRAB
 */
class CentroTrabalho
{
    /**
     * Listar todos os centros de trabalho
     * @param int $emprId ID da empresa (opcional)
     * @return array
     */
    public function listarTodos($emprId = null)
    {
        $sql = "SELECT 
                    CT.ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO,
                    CT.EMPR_ID
                FROM FOCCO3I.TCENTROS_TRAB CT
                INNER JOIN FOCCO3I.TEMP_CC tc ON tc.ID = CT.EMP_CC_ID
                INNER JOIN FOCCO3I.TCC t ON t.ID = tc.CC_ID
                WHERE t.TIPO_CC = 'PRO'";
        
        if ($emprId) {
            $sql .= " AND CT.EMPR_ID = :empr_id";
        }
        
        $sql .= " ORDER BY CT.COD_CENTRO";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar centro de trabalho por ID
     * @param int $id
     * @return array|null
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT 
                    CT.ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO,
                    CT.EMP_CC_ID,
                    CT.EMPR_ID,
                    CT.CAPACIDADE,
                    E.RAZAO_SOCIAL AS EMPRESA
                FROM FOCCO3I.TCENTROS_TRAB CT
                LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = CT.EMPR_ID
                WHERE CT.ID = :id";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar centro de trabalho por código
     * @param string $codCentro
     * @param int $emprId
     * @return array|null
     */
    public function buscarPorCodigo($codCentro, $emprId = null)
    {
        $sql = "SELECT 
                    CT.ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO,
                    CT.EMP_CC_ID,
                    CT.EMPR_ID,
                    CT.CAPACIDADE,
                    E.RAZAO_SOCIAL AS EMPRESA
                FROM FOCCO3I.TCENTROS_TRAB CT
                LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = CT.EMPR_ID
                WHERE CT.COD_CENTRO = :cod_centro";
        
        if ($emprId) {
            $sql .= " AND CT.EMPR_ID = :empr_id";
        }
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':cod_centro', $codCentro, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Listar centros de trabalho com recursos associados
     * @param int $emprId
     * @return array
     */
    public function listarComRecursos($emprId = null)
    {
        $sql = "SELECT 
                    CT.ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO,
                    CT.EMPR_ID,
                    COUNT(M.ID) AS QTD_RECURSOS
                FROM FOCCO3I.TCENTROS_TRAB CT
                LEFT JOIN FOCCO3I.TMAQUINAS M ON M.CENTR_TRAB_ID = CT.ID AND M.SIT = 1";
        
        if ($emprId) {
            $sql .= " WHERE CT.EMPR_ID = :empr_id";
        }
        
        $sql .= " GROUP BY CT.ID, CT.COD_CENTRO, CT.DESCRICAO, CT.EMPR_ID
                  ORDER BY CT.COD_CENTRO";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
