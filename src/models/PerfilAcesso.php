<?php

namespace src\models;

use core\Database;
use PDO;

/**
 * Model para gerenciar perfis de acesso (tabelas novas)
 * TGAZIN_PERFIL_ACESSO, TGAZIN_PERFIL_ROTA, TGAZIN_USUARIO_PERFIL
 */
class PerfilAcesso
{
    // ==================== PERFIS ====================

    /**
     * Lista todos os perfis disponíveis
     * @return array
     */
    public static function listarPerfis()
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    ID_PERFIL,
                    NOME,
                    DESCRICAO,
                    ATIVO,
                    DT_CADASTRO
                FROM FOCCO3I.TGAZIN_PERFIL_ACESSO
                ORDER BY NOME";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista perfis ativos
     * @return array
     */
    public static function listarPerfisAtivos()
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    ID_PERFIL,
                    NOME,
                    DESCRICAO
                FROM FOCCO3I.TGAZIN_PERFIL_ACESSO
                WHERE ATIVO = 'S'
                ORDER BY NOME";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== USUÁRIOS X PERFIS ====================

    /**
     * Lista todos os usuários com seus perfis
     * @param array $filtros
     * @return array
     */
    public static function listarUsuarios($filtros = [])
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    UP.ID_USUARIO_PERFIL,
                    UP.LOGIN_USUARIO,
                    UP.PERFIL_ID,
                    PA.NOME AS PERFIL_NOME,
                    PA.DESCRICAO AS PERFIL_DESCRICAO,
                    UP.ATIVO,
                    UP.DT_CADASTRO,
                    UP.DT_ALTERACAO
                FROM FOCCO3I.TGAZIN_USUARIO_PERFIL UP
                INNER JOIN FOCCO3I.TGAZIN_PERFIL_ACESSO PA ON PA.ID_PERFIL = UP.PERFIL_ID
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filtros['login'])) {
            $sql .= " AND UPPER(UP.LOGIN_USUARIO) LIKE UPPER(:login)";
            $params[':login'] = '%' . $filtros['login'] . '%';
        }
        
        if (isset($filtros['ativo'])) {
            $sql .= " AND UP.ATIVO = :ativo";
            $params[':ativo'] = $filtros['ativo'];
        }
        
        if (!empty($filtros['perfil_id'])) {
            $sql .= " AND UP.PERFIL_ID = :perfil_id";
            $params[':perfil_id'] = $filtros['perfil_id'];
        }
        
        $sql .= " ORDER BY UP.LOGIN_USUARIO, PA.NOME";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista usuários agrupados (um registro por usuário com array de perfis)
     * @param array $filtros
     * @return array
     */
    public static function listarUsuariosAgrupados($filtros = [])
    {
        $dados = self::listarUsuarios($filtros);
        
        $usuarios = [];
        foreach ($dados as $row) {
            $login = $row['LOGIN_USUARIO'];
            
            if (!isset($usuarios[$login])) {
                $usuarios[$login] = [
                    'LOGIN_USUARIO' => $login,
                    'ATIVO' => $row['ATIVO'],
                    'PERFIS' => [],
                    'PERFIS_IDS' => [],
                    'DT_CADASTRO' => $row['DT_CADASTRO']
                ];
            }
            
            $usuarios[$login]['PERFIS'][] = $row['PERFIL_NOME'];
            $usuarios[$login]['PERFIS_IDS'][] = $row['PERFIL_ID'];
        }
        
        return array_values($usuarios);
    }

    /**
     * Busca perfis de um usuário específico
     * @param string $login
     * @return array
     */
    public static function buscarPerfisUsuario($login)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    UP.ID_USUARIO_PERFIL,
                    UP.PERFIL_ID,
                    PA.NOME AS PERFIL_NOME,
                    PA.DESCRICAO AS PERFIL_DESCRICAO,
                    UP.ATIVO
                FROM FOCCO3I.TGAZIN_USUARIO_PERFIL UP
                INNER JOIN FOCCO3I.TGAZIN_PERFIL_ACESSO PA ON PA.ID_PERFIL = UP.PERFIL_ID
                WHERE UPPER(UP.LOGIN_USUARIO) = UPPER(:login)
                AND UP.ATIVO = 'S'
                AND PA.ATIVO = 'S'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':login', $login, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um vínculo usuário-perfil por ID
     * @param int $id
     * @return array|null
     */
    public static function buscarPorId($id)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    UP.ID_USUARIO_PERFIL,
                    UP.LOGIN_USUARIO,
                    UP.PERFIL_ID,
                    PA.NOME AS PERFIL_NOME,
                    UP.ATIVO,
                    UP.DT_CADASTRO,
                    UP.DT_ALTERACAO
                FROM FOCCO3I.TGAZIN_USUARIO_PERFIL UP
                INNER JOIN FOCCO3I.TGAZIN_PERFIL_ACESSO PA ON PA.ID_PERFIL = UP.PERFIL_ID
                WHERE UP.ID_USUARIO_PERFIL = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Adiciona um perfil a um usuário
     * @param string $login
     * @param int $perfilId
     * @return int ID inserido
     */
    public static function adicionarPerfilUsuario($login, $perfilId)
    {
        $pdo = Database::getInstance('focco');
        
        // Verificar se já existe (ativo ou inativo) - pegando todos para tratar duplicados
        $sqlCheck = "SELECT ID_USUARIO_PERFIL, ATIVO 
                     FROM FOCCO3I.TGAZIN_USUARIO_PERFIL 
                     WHERE UPPER(LOGIN_USUARIO) = UPPER(:login) 
                     AND PERFIL_ID = :perfil_id
                     ORDER BY ID_USUARIO_PERFIL DESC";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->bindParam(':login', $login, PDO::PARAM_STR);
        $stmtCheck->bindParam(':perfil_id', $perfilId, PDO::PARAM_INT);
        $stmtCheck->execute();
        $registros = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($registros)) {
            // Verificar se algum está ativo
            foreach ($registros as $reg) {
                if ($reg['ATIVO'] === 'S') {
                    return $reg['ID_USUARIO_PERFIL'];
                }
            }
            
            // Todos estão inativos - reativar o mais recente e deletar os duplicados
            $primeiroId = $registros[0]['ID_USUARIO_PERFIL'];
            
            // Deletar duplicados antigos (manter apenas o mais recente)
            if (count($registros) > 1) {
                $idsParaDeletar = [];
                for ($i = 1; $i < count($registros); $i++) {
                    $idsParaDeletar[] = $registros[$i]['ID_USUARIO_PERFIL'];
                }
                $sqlDeletar = "DELETE FROM FOCCO3I.TGAZIN_USUARIO_PERFIL WHERE ID_USUARIO_PERFIL IN (" . implode(',', $idsParaDeletar) . ")";
                $pdo->exec($sqlDeletar);
            }
            
            // Reativar o registro mais recente
            $sqlReativar = "UPDATE FOCCO3I.TGAZIN_USUARIO_PERFIL 
                            SET ATIVO = 'S', DT_ALTERACAO = SYSDATE 
                            WHERE ID_USUARIO_PERFIL = :id";
            $stmtReativar = $pdo->prepare($sqlReativar);
            $stmtReativar->bindParam(':id', $primeiroId, PDO::PARAM_INT);
            $stmtReativar->execute();
            return $primeiroId;
        }
        
        $sql = "INSERT INTO FOCCO3I.TGAZIN_USUARIO_PERFIL (
                    LOGIN_USUARIO,
                    PERFIL_ID,
                    ATIVO,
                    DT_CADASTRO
                ) VALUES (
                    UPPER(:login),
                    :perfil_id,
                    'S',
                    SYSDATE
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':login', $login, PDO::PARAM_STR);
        $stmt->bindParam(':perfil_id', $perfilId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Buscar ID inserido
        $sqlId = "SELECT MAX(ID_USUARIO_PERFIL) AS ID 
                  FROM FOCCO3I.TGAZIN_USUARIO_PERFIL 
                  WHERE UPPER(LOGIN_USUARIO) = UPPER(:login) 
                  AND PERFIL_ID = :perfil_id";
        $stmtId = $pdo->prepare($sqlId);
        $stmtId->bindParam(':login', $login, PDO::PARAM_STR);
        $stmtId->bindParam(':perfil_id', $perfilId, PDO::PARAM_INT);
        $stmtId->execute();
        $result = $stmtId->fetch(PDO::FETCH_ASSOC);
        
        return $result['ID'] ?? 0;
    }

    /**
     * Define todos os perfis de um usuário (remove anteriores e adiciona novos)
     * @param string $login
     * @param array $perfisIds Array de IDs de perfil
     * @return bool
     */
    public static function definirPerfisUsuario($login, array $perfisIds)
    {
        $pdo = Database::getInstance('focco');
        
        // Inativar todos os perfis atuais
        $sqlInativar = "UPDATE FOCCO3I.TGAZIN_USUARIO_PERFIL 
                        SET ATIVO = 'N', DT_ALTERACAO = SYSDATE 
                        WHERE UPPER(LOGIN_USUARIO) = UPPER(:login)";
        $stmtInativar = $pdo->prepare($sqlInativar);
        $stmtInativar->bindParam(':login', $login, PDO::PARAM_STR);
        $stmtInativar->execute();
        
        // Adicionar os novos perfis
        foreach ($perfisIds as $perfilId) {
            if ($perfilId) {
                self::adicionarPerfilUsuario($login, $perfilId);
            }
        }
        
        return true;
    }

    /**
     * Remove um perfil de um usuário (inativa)
     * @param int $id ID do vínculo usuário-perfil
     * @return bool
     */
    public static function removerPerfilUsuario($id)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "UPDATE FOCCO3I.TGAZIN_USUARIO_PERFIL 
                SET ATIVO = 'N', DT_ALTERACAO = SYSDATE 
                WHERE ID_USUARIO_PERFIL = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Verifica se o usuário é administrador
     * @param string $login
     * @return bool
     */
    public static function isAdmin($login)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 1 
                FROM FOCCO3I.TGAZIN_USUARIO_PERFIL UP
                INNER JOIN FOCCO3I.TGAZIN_PERFIL_ACESSO PA ON PA.ID_PERFIL = UP.PERFIL_ID
                INNER JOIN FOCCO3I.TGAZIN_PERFIL_ROTA PR ON PR.PERFIL_ID = PA.ID_PERFIL
                WHERE UPPER(UP.LOGIN_USUARIO) = UPPER(:login)
                AND UP.ATIVO = 'S'
                AND PA.ATIVO = 'S'
                AND PR.PREFIXO_ROTA = '*'
                FETCH FIRST 1 ROW ONLY";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':login', $login, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * Verifica se o usuário tem acesso a um módulo
     * @param string $login
     * @param string $modulo Prefixo do módulo (cd, comissao, etc)
     * @return bool
     */
    public static function temAcessoModulo($login, $modulo)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 1 
                FROM FOCCO3I.TGAZIN_USUARIO_PERFIL UP
                INNER JOIN FOCCO3I.TGAZIN_PERFIL_ACESSO PA ON PA.ID_PERFIL = UP.PERFIL_ID
                INNER JOIN FOCCO3I.TGAZIN_PERFIL_ROTA PR ON PR.PERFIL_ID = PA.ID_PERFIL
                WHERE UPPER(UP.LOGIN_USUARIO) = UPPER(:login)
                AND UP.ATIVO = 'S'
                AND PA.ATIVO = 'S'
                AND (PR.PREFIXO_ROTA = :modulo OR PR.PREFIXO_ROTA = '*')
                FETCH FIRST 1 ROW ONLY";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':login', $login, PDO::PARAM_STR);
        $stmt->bindParam(':modulo', $modulo, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }
}
