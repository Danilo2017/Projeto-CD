<?php
namespace core;

use src\Config;

class Request {

    public static function getUrl() {
        $url = filter_input(INPUT_GET, 'request');
        $url = !empty($url) ? str_replace(Config::BASE_DIR, '', $url) : $url;
        return '/'.$url;
    }

    public static function getMethod() {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    /**
     * Obtém o corpo da requisição como JSON decodificado
     * @return array|null
     */
    public static function getJsonBody() {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }

    /**
     * Obtém um parâmetro GET
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null) {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    /**
     * Obtém um parâmetro POST
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function post($key, $default = null) {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

}