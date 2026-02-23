<?php

namespace src\models\Comissao;

use core\Database;
use PDO;

/**
 * Model para Empresas/Filiais
 * 
 * Tabela FOCCO: TEMPRESAS
 * Colunas: ID, COD_EMP, RAZAO_SOCIAL, NOME_FAN, CNPJ, EMPR_ID
 */
class Empresa
{
    /**
     * Listar todas as empresas/filiais ativas
     * @return array
     */
    public function listarAtivas()
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    ID,
                    COD_EMP AS CODIGO,
                    RAZAO_SOCIAL,
                    NOME_FAN AS NOME_FANTASIA,
                    CNPJ
                FROM FOCCO3I.TEMPRESAS 
                ORDER BY COD_EMP";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar empresa por ID
     * @param int $id
     * @return array|null
     */
    public function buscarPorId($id)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    ID,
                    COD_EMP AS CODIGO,
                    RAZAO_SOCIAL,
                    NOME_FAN AS NOME_FANTASIA,
                    CNPJ
                FROM FOCCO3I.TEMPRESAS 
                WHERE ID = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Listar empresas para select (formato simplificado)
     * @return array
     */
    public function listarParaSelect()
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    ID,
                    COD_EMP AS CODIGO,
                    RAZAO_SOCIAL,
                    NOME_FAN AS NOME_FANTASIA
                FROM FOCCO3I.TEMPRESAS 
                ORDER BY COD_EMP";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
