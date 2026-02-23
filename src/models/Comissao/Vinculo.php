<?php

namespace src\models\Comissao;

use core\Database;
use PDO;

/**
 * Model para Vínculo de Funcionário com Centro de Trabalho e Recurso
 * Tabela: FOCCO3I.TGAZIN_VINC_FUNC
 */
class Vinculo
{
    // Tipos de vínculo
    const TIPO_NORMAL = 'N';
    const TIPO_APOIO = 'A';

    /**
     * Verifica se a coluna TIPO_VINCULO existe na tabela
     * Para compatibilidade retroativa
     */
    public static function verificarColunaApoio()
    {
        $pdo = Database::getInstance('focco');
        $sql = "
            SELECT COUNT(*) AS EXISTE
            FROM ALL_TAB_COLUMNS
            WHERE TABLE_NAME = 'TGAZIN_VINC_FUNC'
              AND COLUMN_NAME = 'TIPO_VINCULO'
              AND OWNER = 'FOCCO3I'
        ";
        $stmt = $pdo->query($sql);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($resultado['EXISTE'] ?? 0) > 0;
    }

    /**
     * Listar vínculos com filtros opcionais
     */
    public static function listar($filtros = [])
    {
        $pdo = Database::getInstance('focco');
        
        // Verificar se a coluna TIPO_VINCULO existe
        $temColunaApoio = self::verificarColunaApoio();
        $selectTipo = $temColunaApoio ? ", v.TIPO_VINCULO" : ", 'N' AS TIPO_VINCULO";
        
        $sql = "
            SELECT 
                v.ID_VINCULO,
                v.ID_EMPR,
                v.ID_FUNCIONARIO,
                f.COD_FUNC,
                f.NOME AS FUNCIONARIO_NOME,
                v.ID_CENTRO_TRAB,
                c.COD_CENTRO,
                c.DESCRICAO AS CENTRO_DESCRICAO,
                v.ID_RECURSO,
                r.COD_MAQUINA,
                r.DESCRICAO AS RECURSO_DESCRICAO,
                v.ATIVO,
                TO_CHAR(v.DT_CADASTRO, 'DD/MM/YYYY HH24:MI') AS DT_CADASTRO
                {$selectTipo}
            FROM FOCCO3I.TGAZIN_VINC_FUNC v
            INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = v.ID_FUNCIONARIO
            INNER JOIN FOCCO3I.TCENTROS_TRAB c ON c.ID = v.ID_CENTRO_TRAB
            LEFT JOIN FOCCO3I.TMAQUINAS r ON r.ID = v.ID_RECURSO
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filtros['id_empr'])) {
            $sql .= " AND v.ID_EMPR = :id_empr";
            $params[':id_empr'] = $filtros['id_empr'];
        }

        if (!empty($filtros['id_centro_trab'])) {
            $sql .= " AND v.ID_CENTRO_TRAB = :id_centro_trab";
            $params[':id_centro_trab'] = $filtros['id_centro_trab'];
        }

        if (!empty($filtros['id_recurso'])) {
            $sql .= " AND v.ID_RECURSO = :id_recurso";
            $params[':id_recurso'] = $filtros['id_recurso'];
        }

        if (!empty($filtros['id_funcionario'])) {
            $sql .= " AND v.ID_FUNCIONARIO = :id_funcionario";
            $params[':id_funcionario'] = $filtros['id_funcionario'];
        }

        if (isset($filtros['ativo'])) {
            $sql .= " AND v.ATIVO = :ativo";
            $params[':ativo'] = $filtros['ativo'];
        }

        $sql .= " ORDER BY c.DESCRICAO, f.NOME";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar funcionários de apoio de um centro de trabalho
     */
    public static function listarApoioPorCentro($idCentroTrab, $idEmpr = null)
    {
        $pdo = Database::getInstance('focco');
        
        // Verificar se a coluna TIPO_VINCULO existe
        if (!self::verificarColunaApoio()) {
            return []; // Se não tem a coluna, não há funcionários de apoio
        }
        
        $sql = "
            SELECT 
                v.ID_VINCULO,
                v.ID_FUNCIONARIO,
                f.COD_FUNC,
                f.NOME AS NOME_FUNCIONARIO,
                v.ID_CENTRO_TRAB,
                c.COD_CENTRO,
                c.DESCRICAO AS NOME_CENTRO,
                v.ID_RECURSO,
                v.TIPO_VINCULO
            FROM FOCCO3I.TGAZIN_VINC_FUNC v
            INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = v.ID_FUNCIONARIO
            INNER JOIN FOCCO3I.TCENTROS_TRAB c ON c.ID = v.ID_CENTRO_TRAB
            WHERE v.ID_CENTRO_TRAB = :id_centro_trab
              AND v.TIPO_VINCULO = 'A'
              AND v.ATIVO = 'S'
        ";

        $params = [':id_centro_trab' => $idCentroTrab];

        if ($idEmpr) {
            $sql .= " AND v.ID_EMPR = :id_empr";
            $params[':id_empr'] = $idEmpr;
        }

        $sql .= " ORDER BY f.NOME";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inserir novo vínculo
     */
    public static function inserir($idEmpr, $idFuncionario, $idCentroTrab, $idRecurso = null, $tipoVinculo = 'N')
    {
        $pdo = Database::getInstance('focco');
        
        // Verificar se a coluna TIPO_VINCULO existe
        $temColunaApoio = self::verificarColunaApoio();
        
        if ($temColunaApoio) {
            $sql = "
                INSERT INTO FOCCO3I.TGAZIN_VINC_FUNC (
                    ID_EMPR, ID_FUNCIONARIO, ID_CENTRO_TRAB, ID_RECURSO, TIPO_VINCULO, ATIVO, DT_CADASTRO
                ) VALUES (
                    :id_empr, :id_funcionario, :id_centro_trab, :id_recurso, :tipo_vinculo, 'S', SYSDATE
                )
            ";
            $params = [
                ':id_empr' => $idEmpr,
                ':id_funcionario' => $idFuncionario,
                ':id_centro_trab' => $idCentroTrab,
                ':id_recurso' => $idRecurso,
                ':tipo_vinculo' => $tipoVinculo
            ];
        } else {
            $sql = "
                INSERT INTO FOCCO3I.TGAZIN_VINC_FUNC (
                    ID_EMPR, ID_FUNCIONARIO, ID_CENTRO_TRAB, ID_RECURSO, ATIVO, DT_CADASTRO
                ) VALUES (
                    :id_empr, :id_funcionario, :id_centro_trab, :id_recurso, 'S', SYSDATE
                )
            ";
            $params = [
                ':id_empr' => $idEmpr,
                ':id_funcionario' => $idFuncionario,
                ':id_centro_trab' => $idCentroTrab,
                ':id_recurso' => $idRecurso
            ];
        }

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        return $stmt->execute();
    }

    /**
     * Atualizar vínculo
     */
    public static function atualizar($id, $idCentroTrab, $idRecurso = null, $tipoVinculo = 'N')
    {
        $pdo = Database::getInstance('focco');
        
        // Verificar se a coluna TIPO_VINCULO existe
        $temColunaApoio = self::verificarColunaApoio();
        
        if ($temColunaApoio) {
            $sql = "
                UPDATE FOCCO3I.TGAZIN_VINC_FUNC SET
                    ID_CENTRO_TRAB = :id_centro_trab,
                    ID_RECURSO = :id_recurso,
                    TIPO_VINCULO = :tipo_vinculo,
                    DT_ATUALIZACAO = SYSDATE
                WHERE ID_VINCULO = :id
            ";
            $params = [
                ':id' => $id,
                ':id_centro_trab' => $idCentroTrab,
                ':id_recurso' => $idRecurso,
                ':tipo_vinculo' => $tipoVinculo
            ];
        } else {
            $sql = "
                UPDATE FOCCO3I.TGAZIN_VINC_FUNC SET
                    ID_CENTRO_TRAB = :id_centro_trab,
                    ID_RECURSO = :id_recurso,
                    DT_ATUALIZACAO = SYSDATE
                WHERE ID_VINCULO = :id
            ";
            $params = [
                ':id' => $id,
                ':id_centro_trab' => $idCentroTrab,
                ':id_recurso' => $idRecurso
            ];
        }

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        return $stmt->execute();
    }

    /**
     * Alterar status (ativar/inativar)
     */
    public static function alterarStatus($id, $ativo)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "
            UPDATE FOCCO3I.TGAZIN_VINC_FUNC SET
                ATIVO = :ativo,
                DT_ATUALIZACAO = SYSDATE
            WHERE ID_VINCULO = :id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':ativo', $ativo ? 'S' : 'N');
        return $stmt->execute();
    }

    /**
     * Excluir vínculo
     */
    public static function excluir($id)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "DELETE FROM FOCCO3I.TGAZIN_VINC_FUNC WHERE ID_VINCULO = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        return $stmt->execute();
    }

    /**
     * Verificar se já existe vínculo
     */
    public static function existeVinculo($idFuncionario, $idCentroTrab, $idRecurso = null, $idEmpr = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "
            SELECT COUNT(*) AS TOTAL
            FROM FOCCO3I.TGAZIN_VINC_FUNC
            WHERE ID_FUNCIONARIO = :id_funcionario
              AND ID_CENTRO_TRAB = :id_centro_trab
        ";

        $params = [
            ':id_funcionario' => $idFuncionario,
            ':id_centro_trab' => $idCentroTrab
        ];

        if ($idRecurso) {
            $sql .= " AND ID_RECURSO = :id_recurso";
            $params[':id_recurso'] = $idRecurso;
        } else {
            $sql .= " AND ID_RECURSO IS NULL";
        }

        if ($idEmpr) {
            $sql .= " AND ID_EMPR = :id_empr";
            $params[':id_empr'] = $idEmpr;
        }

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($resultado['TOTAL'] ?? 0) > 0;
    }

    /**
     * Listar centros de trabalho que possuem vínculo cadastrado
     */
    public static function listarCentrosComVinculo($idEmpr = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "
            SELECT DISTINCT
                c.ID,
                c.COD_CENTRO,
                c.DESCRICAO
            FROM FOCCO3I.TGAZIN_VINC_FUNC v
            INNER JOIN FOCCO3I.TCENTROS_TRAB c ON c.ID = v.ID_CENTRO_TRAB
            INNER JOIN FOCCO3I.TEMP_CC tc ON tc.ID = c.EMP_CC_ID
            INNER JOIN FOCCO3I.TCC t ON t.ID = tc.CC_ID
            WHERE v.ATIVO = 'S'
              AND t.TIPO_CC = 'PRO'
        ";

        $params = [];

        if ($idEmpr) {
            $sql .= " AND v.ID_EMPR = :id_empr";
            $params[':id_empr'] = $idEmpr;
        }

        $sql .= " ORDER BY c.COD_CENTRO, c.DESCRICAO";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar recursos que possuem vínculo cadastrado
     */
    public static function listarRecursosComVinculo($idEmpr = null, $idCentroTrab = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "
            SELECT DISTINCT
                r.ID,
                r.COD_MAQUINA,
                r.DESCRICAO
            FROM FOCCO3I.TGAZIN_VINC_FUNC v
            INNER JOIN FOCCO3I.TMAQUINAS r ON r.ID = v.ID_RECURSO
            WHERE v.ATIVO = 'S'
              AND v.ID_RECURSO IS NOT NULL
              AND r.TP_RECURSO = 'M'
        ";

        $params = [];

        if ($idEmpr) {
            $sql .= " AND v.ID_EMPR = :id_empr";
            $params[':id_empr'] = $idEmpr;
        }

        if ($idCentroTrab) {
            $sql .= " AND v.ID_CENTRO_TRAB = :id_centro_trab";
            $params[':id_centro_trab'] = $idCentroTrab;
        }

        $sql .= " ORDER BY r.COD_MAQUINA, r.DESCRICAO";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar funcionários que possuem vínculo cadastrado
     */
    public static function listarFuncionariosComVinculo($idEmpr = null, $busca = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "
            SELECT DISTINCT
                f.ID,
                f.COD_FUNC,
                f.NOME
            FROM FOCCO3I.TGAZIN_VINC_FUNC v
            INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = v.ID_FUNCIONARIO
            WHERE v.ATIVO = 'S'
        ";

        $params = [];

        if ($idEmpr) {
            $sql .= " AND v.ID_EMPR = :id_empr";
            $params[':id_empr'] = $idEmpr;
        }

        if ($busca) {
            $sql .= " AND (UPPER(f.NOME) LIKE UPPER(:busca) OR TO_CHAR(f.COD_FUNC) LIKE :busca)";
            $params[':busca'] = '%' . $busca . '%';
        }

        $sql .= " ORDER BY f.NOME";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
