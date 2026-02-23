<?php
namespace core;

use \core\RouterBase;
use \core\Auth;

class Router extends RouterBase {

    public $routes;
  
    public function get($endpoint,$trigger,$privado = false) {

        $this->routes['get'][$endpoint] = [$trigger,$privado];
    }

    public function post($endpoint, $trigger,$privado=false) {
        $this->routes['post'][$endpoint] = [$trigger,$privado];
    }

    public function put($endpoint, $trigger,$privado=false) {
        $this->routes['put'][$endpoint] = [$trigger,$privado];
    }

    public function delete($endpoint, $trigger,$privado=false) {
        $this->routes['delete'][$endpoint] = [$trigger,$privado];
    }

    public function patch($endpoint, $trigger,$privado=false) {
        $this->routes['patch'][$endpoint] = [$trigger,$privado];
    }

}