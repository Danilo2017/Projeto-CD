<?php

namespace src\models\Comissao;

use core\Database;
use PDO;

/**
 * Model para consulta de Funcionários no FOCCO
 * Tabela: FOCCO3I.TFUNCIONARIOS
 */
class Funcionario
{
    /**
     * Listar todos os funcionários ativos
     * @param int $emprId ID da empresa (opcional)
     * @return array
     */
    public function listarAtivos($emprId = null, $busca = null)
    {
        $sql = "SELECT 
                    F.ID,
                    F.COD_FUNC,
                    F.NOME,
                    F.SIT,
                    F.EMPR_ID,
                    E.RAZAO_SOCIAL AS EMPRESA
                FROM FOCCO3I.TFUNCIONARIOS F
                LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = F.EMPR_ID
                WHERE F.SIT = 1";
        $params = [];
        if ($emprId) {
            $sql .= " AND F.EMPR_ID = :empr_id";
            $params[':empr_id'] = $emprId;
        }
        if ($busca) {
            $sql .= " AND (F.NOME LIKE :busca OR F.COD_FUNC LIKE :busca)";
            $params[':busca'] = "%$busca%";
        }
        $sql .= " ORDER BY F.NOME";
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $key === ':empr_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar funcionário por ID
     * @param int $id
     * @return array|null
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT 
                    F.ID,
                    F.COD_FUNC,
                    F.COD_FUNC AS CODIGO,
                    F.NOME,
                    F.SIT,
                    CASE WHEN F.SIT = 1 THEN 'A' ELSE 'I' END AS SITUACAO,
                    F.EMPR_ID,
                    E.RAZAO_SOCIAL AS EMPRESA,
                    V.ID_CENTRO_TRAB,
                    C.DESCRICAO AS CENTRO_TRABALHO,
                    V.ID_RECURSO,
                    R.DESCRICAO AS RECURSO,
                    V.TIPO_VINCULO
                FROM FOCCO3I.TFUNCIONARIOS F
                LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = F.EMPR_ID
                LEFT JOIN FOCCO3I.TGAZIN_VINC_FUNC V ON V.ID_FUNCIONARIO = F.ID AND V.ATIVO = 'S'
                LEFT JOIN FOCCO3I.TCENTROS_TRAB C ON C.ID = V.ID_CENTRO_TRAB
                LEFT JOIN FOCCO3I.TMAQUINAS R ON R.ID = V.ID_RECURSO
                WHERE F.ID = :id";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar funcionário por código
     * @param string $codFunc
     * @param int $emprId
     * @return array|null
     */
    public function buscarPorCodigo($codFunc, $emprId)
    {
        $sql = "SELECT 
                    F.ID,
                    F.COD_FUNC,
                    F.NOME,
                    F.SIT,
                    F.EMPR_ID,
                    E.RAZAO_SOCIAL AS EMPRESA
                FROM FOCCO3I.TFUNCIONARIOS F
                LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = F.EMPR_ID
                WHERE F.COD_FUNC = :cod_func
                AND F.EMPR_ID = :empr_id";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':cod_func', $codFunc, PDO::PARAM_STR);
        $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Pesquisar funcionários por nome
     * @param string $nome
     * @param int $emprId
     * @return array
     */
    public function pesquisarPorNome($nome, $emprId = null)
    {
        $sql = "SELECT 
                    F.ID,
                    F.COD_FUNC,
                    F.NOME,
                    F.SIT,
                    F.EMPR_ID,
                    E.RAZAO_SOCIAL AS EMPRESA
                FROM FOCCO3I.TFUNCIONARIOS F
                LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = F.EMPR_ID
                WHERE F.SIT = 1
                AND UPPER(F.NOME) LIKE UPPER(:nome)";
        
        if ($emprId) {
            $sql .= " AND F.EMPR_ID = :empr_id";
        }
        
        $sql .= " ORDER BY F.NOME";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $nomeBusca = "%{$nome}%";
        $stmt->bindParam(':nome', $nomeBusca, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
