<?php

namespace src\models;

use core\Database;
use src\utils\DecryptPassword;
use src\models\PerfilAcesso;

class Login
{
    public static function autenticar($username, $password)
    {
        // Forçar username para maiúsculo
        $username = strtoupper($username);

        // Validar usuário e senha apenas pela procedure FOCCO3I.BR_UTL_USUARIOS.AUTENTICA
        $resultAuth = Database::switchParams('focco', [
            'username' => "'" . str_replace("'", "''", $username) . "'",
            'password' => "'" . str_replace("'", "''", $password) . "'"
        ], 'auth.usuario.autenticar', true);

        if ($resultAuth['error']) {
            throw new \Exception('Erro ao autenticar usuário.');
        }

        $row = $resultAuth['retorno'][0] ?? null;
        if (!$row || !isset($row['RESULTADO'])) {
            throw new \Exception('Erro ao autenticar usuário.');
        }

        $resultado = $row['RESULTADO'];
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
        
        // Buscar filiais permitidas do usuário
        $filiaisPermitidas = PerfilAcesso::getFiliaisPermitidas($username);
        $filiais = PerfilAcesso::buscarFiliaisUsuario($username);

        // Retornar resultado da autenticação com perfis e filiais
        $_SESSION['user'] = [
            'login' => $username,
            'resultado' => $resultado,
            'perfis' => $permissoes['perfis'],
            'rotas_permitidas' => $permissoes['rotas'],
            'is_admin' => $permissoes['is_admin'],
            'tem_permissao' => !empty($permissoes['rotas']),
            'filiais_permitidas' => $filiaisPermitidas, // Array de IDs (vazio = todas)
            'filiais' => $filiais, // Dados completos das filiais
            'tem_restricao_filial' => !empty($filiaisPermitidas)
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
        $result = Database::switchParams('focco', [
            'username' => "'" . str_replace("'", "''", $username) . "'"
        ], 'auth.usuario.buscarPerfisUsuario', true);

        if ($result['error']) {
            throw new \Exception('Erro ao buscar perfis do usuário.');
        }

        $resultados = $result['retorno'];
        
        if (empty($resultados)) {
            throw new \Exception('Usuário não possui perfil de acesso. Solicite ao administrador.');
        }
        
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
    }
}