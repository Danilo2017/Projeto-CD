<?php
namespace src\controllers;

use \core\Controller as ctrl;

class HomeController extends ctrl {

    public function index() {
        ctrl::response("OK", 200);
    }

    public function semAcesso() {
        $this->render('sem-acesso');
    }

}
