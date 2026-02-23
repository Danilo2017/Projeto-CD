<?php

namespace src\models;

use core\Database;
use PDO;

/**
 * Model para gerenciar permissões de acesso dos usuários
 */
class PermissaoUsuario
{
    /**
     * Busca as permissões de um usuário pelo login
     * @param string $login
     * @return array|null
     */
    public static function buscarPorLogin($login)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    ID_ACESSO,
                    LOGIN_USUARIO,
                    ACESSO_CD,
                    ACESSO_COMISSAO,
                    ADMIN,
                    ATIVO,
                    DT_CADASTRO
                FROM FOCCO3I.TGAZIN_ACESSO_USUARIO
                WHERE UPPER(LOGIN_USUARIO) = UPPER(:login)
                AND ATIVO = 'S'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':login', $login, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Lista todas as permissões cadastradas
     * @param array $filtros
     * @return array
     */
    public static function listar($filtros = [])
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    ID_ACESSO,
                    LOGIN_USUARIO,
                    ACESSO_CD,
                    ACESSO_COMISSAO,
                    ADMIN,
                    ATIVO,
                    DT_CADASTRO,
                    DT_ALTERACAO
                FROM FOCCO3I.TGAZIN_ACESSO_USUARIO
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filtros['login'])) {
            $sql .= " AND UPPER(LOGIN_USUARIO) LIKE UPPER(:login)";
            $params[':login'] = '%' . $filtros['login'] . '%';
        }
        
        if (isset($filtros['ativo'])) {
            $sql .= " AND ATIVO = :ativo";
            $params[':ativo'] = $filtros['ativo'];
        }
        
        if (isset($filtros['acesso_cd'])) {
            $sql .= " AND ACESSO_CD = :acesso_cd";
            $params[':acesso_cd'] = $filtros['acesso_cd'];
        }
        
        if (isset($filtros['acesso_comissao'])) {
            $sql .= " AND ACESSO_COMISSAO = :acesso_comissao";
            $params[':acesso_comissao'] = $filtros['acesso_comissao'];
        }
        
        $sql .= " ORDER BY LOGIN_USUARIO";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca uma permissão por ID
     * @param int $id
     * @return array|null
     */
    public static function buscarPorId($id)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    ID_ACESSO,
                    LOGIN_USUARIO,
                    ACESSO_CD,
                    ACESSO_COMISSAO,
                    ADMIN,
                    ATIVO,
                    DT_CADASTRO,
                    DT_ALTERACAO
                FROM FOCCO3I.TGAZIN_ACESSO_USUARIO
                WHERE ID_ACESSO = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Insere uma nova permissão
     * @param string $login
     * @param string $acessoCd
     * @param string $acessoComissao
     * @param string $admin
     * @return int ID inserido
     */
    public static function inserir($login, $acessoCd, $acessoComissao, $admin = 'N')
    {
        $pdo = Database::getInstance('focco');
        
        // Verificar se já existe
        $existe = self::buscarPorLogin($login);
        if ($existe) {
            throw new \Exception('Usuário já possui permissões cadastradas');
        }
        
        $sql = "INSERT INTO FOCCO3I.TGAZIN_ACESSO_USUARIO (
                    LOGIN_USUARIO,
                    ACESSO_CD,
                    ACESSO_COMISSAO,
                    ADMIN,
                    ATIVO,
                    DT_CADASTRO
                ) VALUES (
                    UPPER(:login),
                    :acesso_cd,
                    :acesso_comissao,
                    :admin,
                    'S',
                    SYSDATE
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':login', $login, PDO::PARAM_STR);
        $stmt->bindParam(':acesso_cd', $acessoCd, PDO::PARAM_STR);
        $stmt->bindParam(':acesso_comissao', $acessoComissao, PDO::PARAM_STR);
        $stmt->bindParam(':admin', $admin, PDO::PARAM_STR);
        $stmt->execute();
        
        // Buscar ID inserido
        $sqlId = "SELECT MAX(ID_ACESSO) AS ID FROM FOCCO3I.TGAZIN_ACESSO_USUARIO WHERE UPPER(LOGIN_USUARIO) = UPPER(:login)";
        $stmtId = $pdo->prepare($sqlId);
        $stmtId->bindParam(':login', $login, PDO::PARAM_STR);
        $stmtId->execute();
        $result = $stmtId->fetch(PDO::FETCH_ASSOC);
        
        return $result['ID'] ?? 0;
    }

    /**
     * Atualiza uma permissão existente
     * @param int $id
     * @param string $acessoCd
     * @param string $acessoComissao
     * @param string $admin
     * @param string $ativo
     * @return bool
     */
    public static function atualizar($id, $acessoCd, $acessoComissao, $admin = 'N', $ativo = 'S')
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "UPDATE FOCCO3I.TGAZIN_ACESSO_USUARIO SET
                    ACESSO_CD = :acesso_cd,
                    ACESSO_COMISSAO = :acesso_comissao,
                    ADMIN = :admin,
                    ATIVO = :ativo,
                    DT_ALTERACAO = SYSDATE
                WHERE ID_ACESSO = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':acesso_cd', $acessoCd, PDO::PARAM_STR);
        $stmt->bindParam(':acesso_comissao', $acessoComissao, PDO::PARAM_STR);
        $stmt->bindParam(':admin', $admin, PDO::PARAM_STR);
        $stmt->bindParam(':ativo', $ativo, PDO::PARAM_STR);
        
        return $stmt->execute();
    }

    /**
     * Exclui (inativa) uma permissão
     * @param int $id
     * @return bool
     */
    public static function excluir($id)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "UPDATE FOCCO3I.TGAZIN_ACESSO_USUARIO SET
                    ATIVO = 'N',
                    DT_ALTERACAO = SYSDATE
                WHERE ID_ACESSO = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Verifica se o usuário tem acesso a um módulo específico
     * @param string $login
     * @param string $modulo 'cd' ou 'comissao'
     * @return bool
     */
    public static function temAcesso($login, $modulo)
    {
        $permissao = self::buscarPorLogin($login);
        
        if (!$permissao) {
            return false;
        }
        
        switch (strtolower($modulo)) {
            case 'cd':
                return $permissao['ACESSO_CD'] === 'S';
            case 'comissao':
                return $permissao['ACESSO_COMISSAO'] === 'S';
            case 'admin':
                return $permissao['ADMIN'] === 'S';
            default:
                return false;
        }
    }

    /**
     * Verifica se o usuário é administrador
     * @param string $login
     * @return bool
     */
    public static function isAdmin($login)
    {
        return self::temAcesso($login, 'admin');
    }
}
