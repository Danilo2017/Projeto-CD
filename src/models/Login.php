<?php

namespace src\models;

use core\Database;
use PDO;
use src\utils\DecryptPassword;

class Login
{
    public static function autenticar($username, $password)
    {
        $pdo = Database::getInstance('focco');

        // Forçar username para maiúsculo
        $username = strtoupper($username);

        // Validar usuário e senha apenas pela procedure FOCCO3I.BR_UTL_USUARIOS.AUTENTICA
        $sqlAuth = "SELECT FOCCO3I.BR_UTL_USUARIOS.AUTENTICA(:username, :password) AS RESULTADO FROM DUAL";
        $stmtAuth = $pdo->prepare($sqlAuth);
        $stmtAuth->bindParam(':username', $username);
        $stmtAuth->bindParam(':password', $password);
        $stmtAuth->execute();
        $resultAuth = $stmtAuth->fetch(PDO::FETCH_ASSOC);

        if (!$resultAuth || !isset($resultAuth['RESULTADO'])) {
            throw new \Exception('Erro ao autenticar usuário.');
        }

        $resultado = $resultAuth['RESULTADO'];
        if ($resultado === 'ERRO_01') {
            throw new \Exception('Usuário não existe');
        } elseif ($resultado === 'ERRO_02') {
            throw new \Exception('Senha inválida ou informação incorreta');
        } elseif ($resultado === 'ERRO_03') {
            throw new \Exception('Usuário inativo');
        } elseif ($resultado === 'OK_03' || $resultado === 'OK_02') {
            // prossegue
        } else {
            throw new \Exception('Erro desconhecido na autenticação: ' . $resultado);
        }

        // Buscar perfis e rotas permitidas do usuário
        $permissoes = self::buscarPerfisUsuario($username);

        // Retornar resultado da autenticação com perfis
        $_SESSION['user'] = [
            'login' => $username,
            'resultado' => $resultado,
            'perfis' => $permissoes['perfis'],
            'rotas_permitidas' => $permissoes['rotas'],
            'is_admin' => $permissoes['is_admin'],
            'tem_permissao' => !empty($permissoes['rotas'])
        ];
        return $_SESSION['user'];
    }

    /**
     * Busca os perfis e rotas permitidas do usuário
     * @param string $username
     * @return array ['perfis' => [...], 'rotas' => [...], 'is_admin' => bool]
     */
    private static function buscarPerfisUsuario($username)
    {
        try {
            $pdo = Database::getInstance('focco');
            
            // Buscar todos os prefixos de rota que o usuário pode acessar
            $sql = "SELECT DISTINCT
                        PA.NOME AS PERFIL_NOME,
                        PR.PREFIXO_ROTA
                    FROM FOCCO3I.TGAZIN_USUARIO_PERFIL UP
                    INNER JOIN FOCCO3I.TGAZIN_PERFIL_ACESSO PA ON PA.ID_PERFIL = UP.PERFIL_ID
                    INNER JOIN FOCCO3I.TGAZIN_PERFIL_ROTA PR ON PR.PERFIL_ID = PA.ID_PERFIL
                    WHERE UPPER(UP.LOGIN_USUARIO) = UPPER(:username)
                    AND UP.ATIVO = 'S'
                    AND PA.ATIVO = 'S'";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $perfis = [];
            $rotas = [];
            $isAdmin = false;
            
            foreach ($resultados as $row) {
                $perfis[] = $row['PERFIL_NOME'];
                $rotas[] = $row['PREFIXO_ROTA'];
                
                if ($row['PREFIXO_ROTA'] === '*') {
                    $isAdmin = true;
                }
            }
            
            return [
                'perfis' => array_unique($perfis),
                'rotas' => array_unique($rotas),
                'is_admin' => $isAdmin
            ];
        } catch (\Exception $e) {
            // Se as tabelas novas não existirem, tentar tabela antiga
            return self::buscarPermissoesLegado($username);
        }
    }

    /**
     * Fallback: Busca permissões na tabela antiga (compatibilidade)
     * @param string $username
     * @return array
     */
    private static function buscarPermissoesLegado($username)
    {
        try {
            $pdo = Database::getInstance('focco');
            
            $sql = "SELECT 
                        ACESSO_CD,
                        ACESSO_COMISSAO,
                        ADMIN
                    FROM FOCCO3I.TGAZIN_ACESSO_USUARIO
                    WHERE UPPER(LOGIN_USUARIO) = UPPER(:username)
                    AND ATIVO = 'S'";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                return ['perfis' => [], 'rotas' => [], 'is_admin' => false];
            }
            
            $perfis = [];
            $rotas = [];
            $isAdmin = $row['ADMIN'] === 'S';
            
            if ($isAdmin) {
                $perfis[] = 'ADMIN';
                $rotas[] = '*';
            } else {
                if ($row['ACESSO_CD'] === 'S') {
                    $perfis[] = 'CD';
                    $rotas[] = 'cd';
                }
                if ($row['ACESSO_COMISSAO'] === 'S') {
                    $perfis[] = 'COMISSAO';
                    $rotas[] = 'comissao';
                }
            }
            
            return [
                'perfis' => $perfis,
                'rotas' => $rotas,
                'is_admin' => $isAdmin
            ];
        } catch (\Exception $e) {
            return ['perfis' => [], 'rotas' => [], 'is_admin' => false];
        }
    }
}