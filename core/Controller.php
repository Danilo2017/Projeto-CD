<?php
namespace core;

use Exception;
use \src\Config;

class Controller {

    protected function redirect($url) {
        header("Location: ".$this->getBaseUrl().$url);
        exit;
    }

    private function getBaseUrl() {
        $dadosServidor =[
            'HTTPS' => isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on',
            'HTTP_X_FORWARDED_PROTO' => isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
        ];

        $base = ($dadosServidor['HTTPS'] || $dadosServidor['HTTP_X_FORWARDED_PROTO']) ? 'https://' : 'http://';
        $base .= $_SERVER['SERVER_NAME'];
        if($_SERVER['SERVER_PORT'] != '80') {
            $base .= ':'.$_SERVER['SERVER_PORT'];
        }
        $base .= Config::BASE_DIR;
        
        return $base;
    }

    private function _render($folder, $viewName, $viewData = []) {
        if(file_exists('../src/views/'.$folder.'/'.$viewName.'.php')) {
            $viewData = array_merge($this->getCommonViewData(), $viewData);
            extract($viewData);
            $render = fn($vN, $vD = []) => $this->renderPartial($vN, $vD);
            $base = $this->getBaseUrl();
            require '../src/views/'.$folder.'/'.$viewName.'.php';
        }
    }

    /**
     * Dados comuns de sessão injetados automaticamente em todas as views/partials.
     */
    private function getCommonViewData() {
        return [
            'is_admin'         => $_SESSION['user']['is_admin']         ?? false,
            'rotas_permitidas' => $_SESSION['user']['rotas_permitidas'] ?? [],
            'tem_permissao'    => $_SESSION['user']['tem_permissao']    ?? false,
            'empresa'          => $_SESSION['empresa']                  ?? null,
            'user_login'       => $_SESSION['user']['login']            ?? 'Usuário',
        ];
    }

    private function renderPartial($viewName, $viewData = []) {
        $this->_render('partials', $viewName, $viewData);
    }

    public function render($viewName, $viewData = []) {
        $this->_render('pages', $viewName, $viewData);
    }

    /**
     * recebe um array e verifica item vazios se tiver algum vazio retorna true
     * @param array $error
     */
    public function AllVazio($error) {
        foreach ($error as $it){
            if(empty($it) || is_null($it)){
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica se há campos vazios ou não presentes na lista de campos a serem validados.
     * @param array $campos  Array associativo com os campos e seus respectivos valores a serem verificados.
     * @param array $validar Lista de campos obrigatórios a serem verificados.
     * @return bool Retorna true se todos os campos obrigatórios estiverem preenchidos, caso contrário, rejeita a resposta.
     */
    public static function verificarCamposVazios($campos,$validar)
    {
        if(empty($campos) || !is_array($campos) || !is_array($validar)){
            throw new Exception('Nenhum campo encontrado', 400);
        }
        // Verifica se os campos obrigatórios estão presentes e preenchidos
        foreach ($validar as $key) {
            if (!array_key_exists($key, $campos)) {
                throw new Exception('Campo obrigatório não encontrado: ' . $key, 400);
            }
            if(is_array($campos[$key])) {
                if(empty($campos[$key])){
                    throw new Exception('Campo obrigatório vazio: ' . $key, 400);
                }
            } else {
                // Aceita valor 0 (não usar empty() que considera "0" como vazio)
                if ($campos[$key] === null || trim((string)$campos[$key]) === '') {
                    throw new Exception('Campo obrigatório vazio: ' . $key, 400);
                }
            }
        }
 
        return true;
    }

    /**
     * define status e respota para usuario
     * @param array $item
     * @param int $status
    */
	 
    public static function response( $item, $status, $pure = false ) {
        header('Content-Type: application/json; charset=utf-8');
		http_response_code($status);
		if($pure==true){
			echo $item;
		}else{
		  echo json_encode( $item );	
		}
		die;
    }
	
    public static function getBody(): ?array {
        header('Content-Type: application/json; charset=utf-8');
        $body = file_get_contents('php://input');
        return json_decode($body, true);
    }
	
}
