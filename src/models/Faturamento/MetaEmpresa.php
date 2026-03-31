<?php

namespace src\models\Faturamento;

use core\Database;

/**
 * Model de Meta Empresa
 * Gerencia metas de faturamento e estoque por empresa/mês
 */
class MetaEmpresa
{
    /**
     * Listar todas as metas
     * @param string|null $mesAno Filtro por mês/ano (formato: YYYY-MM)
     * @return array
     */
    public static function listar(?string $mesAno = null): array
    {
        try {
            $pdo = Database::getInstance('focco');
            
            if ($mesAno) {
                $sql = "SELECT 
                    ME.EMPR_ID,
                    TO_CHAR(ME.MES_ANO, 'YYYY-MM-DD') AS MES_ANO,
                    ME.META,
                    ME.META_ESTOQUE
                FROM FOCCO3I.META_EMPRESA ME
                WHERE TRUNC(ME.MES_ANO, 'MM') = TO_DATE(:mes_ano, 'YYYY-MM-DD')
                ORDER BY ME.MES_ANO DESC, ME.EMPR_ID";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':mes_ano', $mesAno . '-01', \PDO::PARAM_STR);
            } else {
                $sql = "SELECT 
                    ME.EMPR_ID,
                    TO_CHAR(ME.MES_ANO, 'YYYY-MM-DD') AS MES_ANO,
                    ME.META,
                    ME.META_ESTOQUE
                FROM FOCCO3I.META_EMPRESA ME
                ORDER BY ME.MES_ANO DESC, ME.EMPR_ID";
                
                $stmt = $pdo->prepare($sql);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log('Erro ao listar metas: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar meta por empresa e mês/ano
     * @param int $emprId
     * @param string $mesAno
     * @return array|null
     */
    public static function buscar(int $emprId, string $mesAno): ?array
    {
        try {
            $pdo = Database::getInstance('focco');
            
            $sql = "SELECT 
                ME.EMPR_ID,
                TO_CHAR(ME.MES_ANO, 'YYYY-MM-DD') AS MES_ANO,
                ME.META,
                ME.META_ESTOQUE
            FROM FOCCO3I.META_EMPRESA ME
            WHERE ME.EMPR_ID = :empr_id
            AND TRUNC(ME.MES_ANO, 'MM') = TO_DATE(:mes_ano, 'YYYY-MM-DD')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':empr_id', $emprId, \PDO::PARAM_INT);
            $stmt->bindValue(':mes_ano', $mesAno . '-01', \PDO::PARAM_STR);
            $stmt->execute();
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Exception $e) {
            error_log('Erro ao buscar meta: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Inserir nova meta
     * @param int $emprId
     * @param string $mesAno
     * @param float $meta
     * @param float $metaEstoque
     * @return bool
     */
    public static function inserir(int $emprId, string $mesAno, float $meta, float $metaEstoque): bool
    {
        try {
            $pdo = Database::getInstance('focco');
            
            $sql = "INSERT INTO FOCCO3I.META_EMPRESA (EMPR_ID, MES_ANO, META, META_ESTOQUE) 
                    VALUES (:empr_id, TO_DATE(:mes_ano, 'YYYY-MM-DD'), :meta, :meta_estoque)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':empr_id', $emprId, \PDO::PARAM_INT);
            $stmt->bindValue(':mes_ano', $mesAno . '-01', \PDO::PARAM_STR);
            $stmt->bindParam(':meta', $meta);
            $stmt->bindParam(':meta_estoque', $metaEstoque);
            
            return $stmt->execute();
        } catch (\Exception $e) {
            error_log('Erro ao inserir meta: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualizar meta existente
     * @param int $emprId
     * @param string $mesAno
     * @param float $meta
     * @param float $metaEstoque
     * @return bool
     */
    public static function atualizar(int $emprId, string $mesAno, float $meta, float $metaEstoque): bool
    {
        try {
            $pdo = Database::getInstance('focco');
            
            $sql = "UPDATE FOCCO3I.META_EMPRESA 
                    SET META = :meta, META_ESTOQUE = :meta_estoque 
                    WHERE EMPR_ID = :empr_id 
                    AND TRUNC(MES_ANO, 'MM') = TO_DATE(:mes_ano, 'YYYY-MM-DD')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':empr_id', $emprId, \PDO::PARAM_INT);
            $stmt->bindValue(':mes_ano', $mesAno . '-01', \PDO::PARAM_STR);
            $stmt->bindParam(':meta', $meta);
            $stmt->bindParam(':meta_estoque', $metaEstoque);
            
            return $stmt->execute();
        } catch (\Exception $e) {
            error_log('Erro ao atualizar meta: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Excluir meta
     * @param int $emprId
     * @param string $mesAno
     * @return bool
     */
    public static function excluir(int $emprId, string $mesAno): bool
    {
        try {
            $pdo = Database::getInstance('focco');
            
            $sql = "DELETE FROM FOCCO3I.META_EMPRESA 
                    WHERE EMPR_ID = :empr_id 
                    AND TRUNC(MES_ANO, 'MM') = TO_DATE(:mes_ano, 'YYYY-MM-DD')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':empr_id', $emprId, \PDO::PARAM_INT);
            $stmt->bindValue(':mes_ano', $mesAno . '-01', \PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (\Exception $e) {
            error_log('Erro ao excluir meta: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Listar empresas disponíveis
     * @return array
     */
    public static function listarEmpresas(): array
    {
        // Lista fixa de empresas conhecidas
        return [
            ['EMPR_ID' => '1', 'NOME_EMPRESA' => '1 - DOURADINA PR'],
            ['EMPR_ID' => '2', 'NOME_EMPRESA' => '2 - VILHENA RO'],
            ['EMPR_ID' => '3', 'NOME_EMPRESA' => '3 - CANDELÁRIA RS'],
            ['EMPR_ID' => '4', 'NOME_EMPRESA' => '4 - F. SANTANA BA'],
            ['EMPR_ID' => '5', 'NOME_EMPRESA' => '5 - JACIARA MT'],
            ['EMPR_ID' => '6', 'NOME_EMPRESA' => '6 - COMPLEMENTO'],
            ['EMPR_ID' => '7', 'NOME_EMPRESA' => '7 - ITATINGA CE'],
            ['EMPR_ID' => '8', 'NOME_EMPRESA' => '8 - FILIAL 8'],
            ['EMPR_ID' => '9', 'NOME_EMPRESA' => '9 - S. GUIOMARD AC'],
            ['EMPR_ID' => '10', 'NOME_EMPRESA' => '10 - MOLAS DOURAD.'],
            ['EMPR_ID' => '11', 'NOME_EMPRESA' => '11 - MOLAS CAND.'],
            ['EMPR_ID' => '13', 'NOME_EMPRESA' => '13 - ELOI MENDES MG'],
            ['EMPR_ID' => '14', 'NOME_EMPRESA' => '14 - ARAGUATINS TO'],
            ['EMPR_ID' => '15', 'NOME_EMPRESA' => '15 - PATOS MINAS MG']
        ];
    }
}
