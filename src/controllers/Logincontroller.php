<?php

namespace src\controllers;

use src\models\Login;
use \core\Controller as ctrl;

class Logincontroller extends ctrl
{

    public function index() {
        $dados = [
            'titulo' => 'Login - Sistema CD',
            'pagina' => 'Login'
        ];

        $this->render('login', $dados);
    }

    public function login()
    {
        try {
            
            // $usuario = $_POST['usuario'] ?? '';
            // $senha = $_POST['senha'] ?? '';

            $body = ctrl::getBody();
            $usuario = $body['usuario'] ?? '';
            $senha = $body['senha'] ?? '';

            $user = Login::autenticar($usuario, $senha);
            self::response([
                'success' => true,
                'message' => 'Autenticação bem-sucedida',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout()
    {
        unset($_SESSION['user']);
        session_destroy();
        header('Location: /login');
        exit;
    }
}
