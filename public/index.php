<?php
header("Content-Type: text/html; charset=UTF-8");
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Vary: Origin");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Access-Control-Allow-Origin");
header("Access-Control-Allow-Credentials: true");

if (isset($_SERVER['REQUEST_METHOD']) && !empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}

$rotina = '';
ini_set('memory_limit', -1);
ini_set('max_execution_time', 0);
//error_reporting(0); // Desativa a exibição de todos os erros
//ini_set('display_errors', 0); // Desativa a exibição de erros na tela


session_start();

// Libera a trava do arquivo de sessão logo no início: o PHP segura a sessão até o fim da requisição,
// e uma consulta demorada numa aba fazia todas as outras abas do mesmo usuário esperarem (504 no nginx).
// $_SESSION continua disponível para leitura. Só as rotas abaixo GRAVAM na sessão e mantêm a trava.
$rotasQueGravamSessao = [
    'login', 'logout',
    'comissao-api-empresas', 'comissao-api-selecionar-empresa',
    'apontamento-api-iniciar-sessao', 'apontamento-api-encerrar-sessao', 'apontamento-api-set-maquina',
];
$rotaAtual = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
if (!in_array($rotaAtual, $rotasQueGravamSessao, true)) {
    session_write_close();
}

require_once '../vendor/autoload.php';
require_once '../src/routes.php';

$router->run($router->routes);
