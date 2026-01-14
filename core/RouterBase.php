<?php
namespace core;

use \src\Config;

class RouterBase extends Auth {

    public $token;
    public $auth;

    public function __construct() {
        $headers = getallheaders();
        $tk = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        // $tk = isset($headers['Authorization'])?$headers['Authorization']:null;
        $tk2 = isset($_REQUEST['jwt'])?'Bearer '.$_REQUEST['jwt']:null;
        $this->token = ( !empty($tk) && strlen($tk) > 8)? $tk : $tk2;
        $this->auth = new Auth();
    }

    public function run($routes) {

        $method = Request::getMethod();
        $url = Request::getUrl();

        // Define os itens padrão
        $controller = Config::ERROR_CONTROLLER;
        $action = Config::DEFAULT_ACTION;
        $privado = false;
        $args = [];
		
        if(isset($routes[$method])) {
            
            foreach($routes[$method] as $route => $callback) {
                
                // Identifica os argumentos e substitui por regex
                //$pattern = preg_replace('(\{[a-z0-9]{1,}\})', '([a-z0-9-]{1,})', $route);
                $pattern = preg_replace('(\{[a-z0-9_]+\})', '([^\/]+)', $route);
                
                //if(preg_match('#^('.$pattern.')*$#i', $url, $matches) === 1) {
                if (preg_match('#^'.$pattern.'$#i', $url, $matches) === 1) {    
                    array_shift($matches);
                
                   
                    // Pega todos os argumentos para associar
                    $itens = array();
                    if(preg_match_all('(\{[a-z0-9_]+\})', $route, $m)) {
                        $itens = preg_replace('(\{|\})', '', $m[0]);
                
                        $args = array_combine($itens, $matches);
                    }

                    // Faz a associação
                    $args = array();
                    foreach($matches as $key => $match) {
                        $args[$itens[$key]] = $match;
                    }

                    // Seta o controller/action
                    $callbackSplit = explode('@', $callback[0]);
                    $controller = $callbackSplit[0];
					
					// Verifica se teve parametros extras a ser considerados 
					if( isset($callbackSplit[2]) ){
						foreach( explode( '&',$callbackSplit[2] ) as $key => $match) {
							$partes = explode("=",$match);
							$args[ $partes[0]] = $partes[1];
						}
					}
					
                    $privado =  $callback[1];
                    if(isset($callbackSplit[1])) {
                        $action = $callbackSplit[1];
                    }

                    break;
                }
            }
        }

        if($privado){
            $auth = new Auth();
                $auth->validaToken($this->token);
            
        }

        $controller = "\src\controllers\\$controller";
        $definedController = new $controller();
        $definedController->$action($args);
    }
    
}