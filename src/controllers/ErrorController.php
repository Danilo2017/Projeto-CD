<?php
namespace src\controllers;

use \core\Controller as ctrl;

class ErrorController extends ctrl {

    public function index() {
        echo "Página não encontrada!";
    }

}
