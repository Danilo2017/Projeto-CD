<?php
namespace core;

class Auth extends Controller {

    public function validaToken($token) {
        $islogado = $_SESSION['user'] ?? null;
        if (!$islogado || empty($islogado) || !isset($_SESSION['user'])) {
            $this->redirect('login');
            die;
        }
    }
    
}