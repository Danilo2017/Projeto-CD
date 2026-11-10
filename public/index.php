<?php
header("Content-Type: text/html; charset=UTF-8");
// CORS com allowlist: só devolve Allow-Origin + credenciais para a MESMA origem
// (mesmo host:porta do Portal) ou para origens extras configuradas na env CORS_ORIGINS
// (separadas por vírgula, ex.: "https://portal.gazin.com.br"). Antes refletia qualquer
// origem com credenciais, o que permitia a sites externos lerem respostas autenticadas.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $originSemEsquema = preg_replace('#^https?://#i', '', $origin);
    $extras = array_filter(array_map('trim', explode(',', (string) getenv('CORS_ORIGINS'))));
    if ($originSemEsquema === $host || in_array($origin, $extras, true)) {
        header("Access-Control-Allow-Origin: " . $origin);
        header("Vary: Origin");
        header("Access-Control-Allow-Credentials: true");
    }
}
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Access-Control-Allow-Origin");

if (isset($_SERVER['REQUEST_METHOD']) && !empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}

$rotina = '';
ini_set('memory_limit', -1);
ini_set('max_execution_time', 0);
//error_reporting(0); // Desativa a exibição de todos os erros
//ini_set('display_errors', 0); // Desativa a exibição de erros na tela


// Cookie de sessão com flags seguras: HttpOnly impede leitura por JS (mitiga roubo via XSS),
// SameSite=Lax impede envio do cookie em requisições cross-site (mitiga CSRF e leitura via CORS),
// Secure só sob HTTPS (não quebra acesso interno por HTTP).
$__https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $__https,
]);
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
