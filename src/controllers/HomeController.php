<?php
namespace src\controllers;

use \core\Controller as ctrl;
use \core\Auth;

class HomeController extends ctrl {

    public function index() {
        ctrl::response("OK", 200);
    }

    /**
     * Redireciona para o módulo correto baseado no perfil do usuário
     */
    public function redirectInicial() {
        $auth = new Auth();
        $rota = $auth->getRotaInicialUsuario();
        $this->redirect($rota);
    }

    /**
     * Página de acesso negado
     */
    public function semAcesso() {
        $dados = [
            'titulo' => 'Acesso Negado',
            'pagina' => 'Sem Acesso'
        ];
        $this->render('sem-acesso', $dados);
    }

}
