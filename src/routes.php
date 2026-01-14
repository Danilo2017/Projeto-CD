<?php

use core\Router;

$router = new Router();

// ========== Rotas do CD (Centro de Distribuição) ==========
$router->get('/login', 'Logincontroller@index');

$router->post('/login', 'Logincontroller@login');
$router->get('/logout', 'Logincontroller@logout');

// Página inicial redireciona para o Dashboard do CD
$router->get('/', 'CDDashboardController@index',true);

// APIs CD
$router->get('/cd-api-avisos', 'CDDashboardController@getAvisosRecebimento',true);
$router->get('/cd-api-agendamentos', 'CDDashboardController@getAgendamentosPendentes',true);
$router->get('/cd-api-calendario', 'CDCalendarioController@listar',true);
$router->post('/cd-api-calendario', 'CDCalendarioController@salvar',true);
$router->put('/cd-api-calendario', 'CDCalendarioController@atualizar',true);
$router->delete('/cd-api-calendario', 'CDCalendarioController@excluir',true);
$router->patch('/cd-api-calendario-status', 'CDCalendarioController@alterarStatus',true);

// APIs de Recibo de Descarga
$router->post('/cd-api-recibo', 'CDCalendarioController@gerarRecibo',true);
$router->get('/cd-api-recibo', 'CDCalendarioController@buscarRecibo',true);
$router->get('/cd-api-recibos', 'CDCalendarioController@listarRecibos',true);

// Páginas CD
$router->get('/cd-calendario', 'CDCalendarioController@index',true);
$router->get('/cd-dashboard', 'CDDashboardController@index',true);
$router->get('/cd', 'CDDashboardController@index',true);