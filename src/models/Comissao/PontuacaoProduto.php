<?php

namespace src\models\Comissao;

use core\Database;
use PDO;
use Exception;

/**
 * Model para Pontuação de Produto (UP)
 * Tabela customizada: FOCCO3I.TGAZIN_PONTUACAO_PRODUTO
 * 
 * Estrutura da tabela:
 * - ID_PONTUACAO (PK)
 * - ID_EMPR (FK para TEMPRESAS)
 * - ID_ITEM (FK para TITENS)
 * - ID_ITEMPR (FK para TITENS_EMPR)
 * - ID_MASCARA (FK para TMASC_ITEM)
 * - ID_CENTRO_TRAB (FK para TCENTROS_TRAB)
 * - PONTOS_UP
 * - DT_VIGENCIA_INI
 * - DT_VIGENCIA_FIM
 * - ATIVO (S/N)
 * - DT_CADASTRO
 * - ID_USUARIO_CAD
 * - DT_ALTERACAO
 * - ID_USUARIO_ALT
 */
class PontuacaoProduto
{
    /**
     * Cache para verificação de colunas
     */
    private static $columnCache = [];
    
    /**
     * Verifica se uma coluna existe na tabela TGAZIN_PONTUACAO_PRODUTO
     * @param string $coluna Nome da coluna
     * @return bool
     */
    private function verificarColunaExiste($coluna)
    {
        // Usar cache para evitar consultas repetidas
        if (isset(self::$columnCache[$coluna])) {
            return self::$columnCache[$coluna];
        }
        
        try {
            $pdo = Database::getInstance('focco');
            $sql = "SELECT COUNT(*) FROM ALL_TAB_COLUMNS 
                    WHERE TABLE_NAME = 'TGAZIN_PONTUACAO_PRODUTO' 
                    AND COLUMN_NAME = :coluna
                    AND OWNER = 'FOCCO3I'";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':coluna', $coluna, PDO::PARAM_STR);
            $stmt->execute();
            $exists = (int)$stmt->fetchColumn() > 0;
            self::$columnCache[$coluna] = $exists;
            return $exists;
        } catch (Exception $e) {
            // Se der erro, assumir que existe para não bloquear
            return true;
        }
    }
    
    /**
     * Listar todas as pontuações ativas
     * @param int $emprId ID da empresa (obrigatório para filtrar)
     * @param int $centroTrabId
     * @return array
     */
    public function listarAtivas($emprId = null, $centroTrabId = null)
    {
        try {
            $sql = "SELECT 
                        PP.ID_PONTUACAO,
                        PP.ID_EMPR,
                        PP.ITEM_ID,
                        PP.ID_ITEMPR,
                        PP.ID_MASCARA,
                        PP.ID_CENTRO_TRAB,
                        PP.PONTOS_UP,
                        TO_CHAR(PP.DT_VIGENCIA_INI, 'YYYY-MM-DD') AS DT_VIGENCIA_INI,
                        TO_CHAR(PP.DT_VIGENCIA_FIM, 'YYYY-MM-DD') AS DT_VIGENCIA_FIM,
                        PP.ATIVO,
                        E.COD_EMP,
                        E.NOME_FAN AS NOME_EMPRESA,
                        COALESCE(I.COD_ITEM, I2.COD_ITEM) AS COD_ITEM,
                        COALESCE(I.DESC_TECNICA, I2.DESC_TECNICA) AS DESC_ITEM,
                        M.MASCARA,
                        CT.COD_CENTRO,
                        CT.DESCRICAO AS DESC_CENTRO
                    FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
                    LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = PP.ID_EMPR
                    LEFT JOIN FOCCO3I.TITENS I ON I.ID = PP.ITEM_ID
                    LEFT JOIN FOCCO3I.TITENS_EMPR IE ON IE.ID = PP.ID_ITEMPR
                    LEFT JOIN FOCCO3I.TITENS I2 ON I2.ID = IE.ITEM_ID
                    LEFT JOIN FOCCO3I.TMASC_ITEM M ON M.ID = PP.ID_MASCARA
                    LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = PP.ID_CENTRO_TRAB
                    WHERE PP.ATIVO = 'S'
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TRUNC(SYSDATE))
                    AND PP.DT_VIGENCIA_INI <= TRUNC(SYSDATE)";
            
            $params = [];
            
            if ($emprId) {
                $sql .= " AND PP.ID_EMPR = :empr_id";
                $params[':empr_id'] = $emprId;
            }
            
            if ($centroTrabId) {
                $sql .= " AND (PP.ID_CENTRO_TRAB = :centro_trab_id OR PP.ID_CENTRO_TRAB IS NULL)";
                $params[':centro_trab_id'] = $centroTrabId;
            }
            
            $sql .= " ORDER BY COALESCE(I.DESC_TECNICA, I2.DESC_TECNICA)";
            
            $pdo = Database::getInstance('focco');
            $stmt = $pdo->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Se a tabela não existir, retorna array vazio
            return [];
        }
    }

    /**
     * Listar todas as pontuações (ativas e inativas)
     * @param int $emprId ID da empresa
     * @return array
     */
    public function listarTodas($emprId = null)
    {
        try {
            $sql = "SELECT 
                        PP.ID_PONTUACAO,
                        PP.ID_EMPR,
                        PP.ITEM_ID,
                        PP.ID_ITEMPR,
                        PP.ID_MASCARA,
                        PP.ID_CENTRO_TRAB,
                        PP.PONTOS_UP,
                        TO_CHAR(PP.DT_VIGENCIA_INI, 'YYYY-MM-DD') AS DT_VIGENCIA_INI,
                        TO_CHAR(PP.DT_VIGENCIA_FIM, 'YYYY-MM-DD') AS DT_VIGENCIA_FIM,
                        PP.ATIVO,
                        TO_CHAR(PP.DT_CADASTRO, 'YYYY-MM-DD') AS DT_CADASTRO,
                        E.COD_EMP,
                        E.NOME_FAN AS NOME_EMPRESA,
                        COALESCE(I.COD_ITEM, I2.COD_ITEM) AS COD_ITEM,
                        COALESCE(I.DESC_TECNICA, I2.DESC_TECNICA) AS DESC_ITEM,
                        M.MASCARA,
                        CT.COD_CENTRO,
                        CT.DESCRICAO AS DESC_CENTRO
                    FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
                    LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = PP.ID_EMPR
                    LEFT JOIN FOCCO3I.TITENS I ON I.ID = PP.ITEM_ID
                    LEFT JOIN FOCCO3I.TITENS_EMPR IE ON IE.ID = PP.ID_ITEMPR
                    LEFT JOIN FOCCO3I.TITENS I2 ON I2.ID = IE.ITEM_ID
                    LEFT JOIN FOCCO3I.TMASC_ITEM M ON M.ID = PP.ID_MASCARA
                    LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = PP.ID_CENTRO_TRAB
                    WHERE 1=1";
            
            $params = [];
            
            if ($emprId) {
                $sql .= " AND PP.ID_EMPR = :empr_id";
                $params[':empr_id'] = $emprId;
            }
            
            $sql .= " ORDER BY PP.ATIVO DESC, COALESCE(I.DESC_TECNICA, I2.DESC_TECNICA)";
            
            $pdo = Database::getInstance('focco');
            $stmt = $pdo->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Buscar pontuação por ID
     * @param int $id
     * @return array|null
     */
    public function buscarPorId($id)
    {
        try {
            $sql = "SELECT 
                        PP.ID_PONTUACAO,
                        PP.ID_EMPR,
                        PP.ITEM_ID,
                        PP.ID_ITEMPR,
                        PP.ID_MASCARA,
                        PP.ID_CENTRO_TRAB,
                        PP.PONTOS_UP,
                        TO_CHAR(PP.DT_VIGENCIA_INI, 'YYYY-MM-DD') AS DT_VIGENCIA_INI,
                        TO_CHAR(PP.DT_VIGENCIA_FIM, 'YYYY-MM-DD') AS DT_VIGENCIA_FIM,
                        PP.ATIVO,
                        E.COD_EMP,
                        E.NOME_FAN AS NOME_EMPRESA,
                        COALESCE(I.COD_ITEM, I2.COD_ITEM) AS COD_ITEM,
                        COALESCE(I.DESC_TECNICA, I2.DESC_TECNICA) AS DESC_ITEM,
                        M.MASCARA,
                        CT.COD_CENTRO,
                        CT.DESCRICAO AS DESC_CENTRO
                    FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
                    LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = PP.ID_EMPR
                    LEFT JOIN FOCCO3I.TITENS I ON I.ID = PP.ITEM_ID
                    LEFT JOIN FOCCO3I.TITENS_EMPR IE ON IE.ID = PP.ID_ITEMPR
                    LEFT JOIN FOCCO3I.TITENS I2 ON I2.ID = IE.ITEM_ID
                    LEFT JOIN FOCCO3I.TMASC_ITEM M ON M.ID = PP.ID_MASCARA
                    LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = PP.ID_CENTRO_TRAB
                    WHERE PP.ID_PONTUACAO = :id";
            
            $pdo = Database::getInstance('focco');
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Buscar pontuação por item e centro de trabalho
     * @param int $itemId
     * @param int $centroTrabId
     * @param string $dataReferencia (YYYY-MM-DD)
     * @return array|null
     */
    public function buscarPontuacao($itemId, $centroTrabId = null, $dataReferencia = null)
    {
        try {
            $dataRef = $dataReferencia ?? date('Y-m-d');
            
            $sql = "SELECT 
                        PP.ID_PONTUACAO,
                        PP.PONTOS_UP,
                        PP.DT_VIGENCIA_INI,
                        PP.DT_VIGENCIA_FIM
                    FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
                    WHERE PP.ITEM_ID = :item_id
                    AND PP.ATIVO = 'S'
                    AND PP.DT_VIGENCIA_INI <= TO_DATE(:data_ref, 'YYYY-MM-DD')
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TO_DATE(:data_ref2, 'YYYY-MM-DD'))";
            
            if ($centroTrabId) {
                $sql .= " AND (PP.ID_CENTRO_TRAB = :centro_trab_id OR PP.ID_CENTRO_TRAB IS NULL)";
            }
            
            $sql .= " ORDER BY PP.ID_CENTRO_TRAB NULLS LAST, PP.DT_VIGENCIA_INI DESC FETCH FIRST 1 ROW ONLY";
            
            $pdo = Database::getInstance('focco');
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
            $stmt->bindParam(':data_ref', $dataRef, PDO::PARAM_STR);
            $stmt->bindParam(':data_ref2', $dataRef, PDO::PARAM_STR);
            
            if ($centroTrabId) {
                $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Inserir nova pontuação
     * @param array $dados
     * @return int ID inserido
     */
    public function inserir($dados)
    {
        $pdo = Database::getInstance('focco');
        
        // Colunas reais da tabela: ITEM_ID, ID_EMPR, ID_ITEMPR, ID_MASCARA, ID_CENTRO_TRAB
        $sql = "INSERT INTO FOCCO3I.TGAZIN_PONTUACAO_PRODUTO (
                    ID_PONTUACAO,
                    ID_EMPR,
                    ITEM_ID,
                    ID_ITEMPR,
                    ID_MASCARA,
                    ID_CENTRO_TRAB,
                    PONTOS_UP,
                    DT_VIGENCIA_INI,
                    DT_VIGENCIA_FIM,
                    ATIVO,
                    DT_CADASTRO,
                    ID_USUARIO_CAD
                ) VALUES (
                    FOCCO3I.SEQ_GAZIN_PONTUACAO_PROD.NEXTVAL,
                    :empr_id,
                    :item_id,
                    :itempr_id,
                    :mascara_id,
                    :centro_trab_id,
                    :pontos_up,
                    TO_DATE(:dt_vigencia_ini, 'YYYY-MM-DD'),
                    TO_DATE(:dt_vigencia_fim, 'YYYY-MM-DD'),
                    'S',
                    SYSDATE,
                    :id_usuario
                )";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindValue(':empr_id', $dados['empr_id'] ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':item_id', $dados['item_id'] ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':itempr_id', $dados['itempr_id'] ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':mascara_id', $dados['mascara_id'] ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':centro_trab_id', $dados['centro_trab_id'] ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':pontos_up', $dados['pontos_up'], PDO::PARAM_STR);
        $stmt->bindValue(':dt_vigencia_ini', $dados['dt_vigencia_ini'], PDO::PARAM_STR);
        $stmt->bindValue(':dt_vigencia_fim', $dados['dt_vigencia_fim'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':id_usuario', $dados['id_usuario'] ?? null, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // Buscar o último ID inserido
        $sqlId = "SELECT FOCCO3I.SEQ_GAZIN_PONTUACAO_PROD.CURRVAL AS ID FROM DUAL";
        $stmtId = $pdo->query($sqlId);
        $result = $stmtId->fetch(PDO::FETCH_ASSOC);
        
        return $result['ID'] ?? null;
    }

    /**
     * Atualizar pontuação
     * @param int $id
     * @param array $dados
     * @return bool
     */
    public function atualizar($id, $dados)
    {
        $sql = "UPDATE FOCCO3I.TGAZIN_PONTUACAO_PRODUTO SET
                    PONTOS_UP = :pontos_up,
                    DT_VIGENCIA_INI = TO_DATE(:dt_vigencia_ini, 'YYYY-MM-DD'),
                    DT_VIGENCIA_FIM = TO_DATE(:dt_vigencia_fim, 'YYYY-MM-DD'),
                    DT_ALTERACAO = SYSDATE,
                    ID_USUARIO_ALT = :id_usuario
                WHERE ID_PONTUACAO = :id";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindParam(':pontos_up', $dados['pontos_up'], PDO::PARAM_STR);
        $stmt->bindParam(':dt_vigencia_ini', $dados['dt_vigencia_ini'], PDO::PARAM_STR);
        $dtVigenciaFim = $dados['dt_vigencia_fim'] ?? null;
        $stmt->bindParam(':dt_vigencia_fim', $dtVigenciaFim, PDO::PARAM_STR);
        $idUsuario = $dados['id_usuario'] ?? null;
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Ativar/Desativar pontuação
     * @param int $id
     * @param string $ativo (S/N)
     * @param int $idUsuario
     * @return bool
     */
    public function alterarStatus($id, $ativo, $idUsuario = null)
    {
        $sql = "UPDATE FOCCO3I.TGAZIN_PONTUACAO_PRODUTO SET
                    ATIVO = :ativo,
                    DT_ALTERACAO = SYSDATE,
                    ID_USUARIO_ALT = :id_usuario
                WHERE ID_PONTUACAO = :id";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindParam(':ativo', $ativo, PDO::PARAM_STR);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Excluir pontuação (soft delete - desativa)
     * @param int $id
     * @param int $idUsuario
     * @return bool
     */
    public function excluir($id, $idUsuario = null)
    {
        return $this->alterarStatus($id, 'N', $idUsuario);
    }
}


