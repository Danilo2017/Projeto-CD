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

        // Retornar apenas o resultado da autenticação
        $_SESSION['user'] = [
            'login' => $username,
            'resultado' => $resultado
        ];
        return $_SESSION['user'];
    }
}