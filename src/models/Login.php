<?php

namespace src\models;

use core\Database;
use PDO;
use src\utils\DecryptPassword;

class Login
{
    public static function autenticar($username, $password)
    {
        $sql = "SELECT *
        FROM glb.usuario
        WHERE login = :username";

        $pdo = Database::getInstance('sabium');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            throw new \Exception('Usuário não encontrado');
        }

        $senhaDescrpit =  $usuario['senha'];
        if(DecryptPassword::decrypt($senhaDescrpit) != $password){
            throw new \Exception('Verifique seus dados de acesso');
        }

        $_SESSION['user'] = $usuario;
        return $usuario;
    }
}