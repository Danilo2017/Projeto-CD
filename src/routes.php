<?php

use core\Router;

$router = new Router();

// ========== Rotas do CD (Centro de Distribuição) ==========
$router->get('/login', 'Logincontroller@index');

$router->post('/login', 'Logincontroller@login');
$router->get('/logout', 'Logincontroller@logout');

// Página inicial redireciona para o Dashboard do CD
$router->get('/', 'CDDashboardController@index',false);

// APIs CD
$router->get('/cd-api-avisos', 'CDDashboardController@getAvisosRecebimento',false);
$router->get('/cd-api-agendamentos', 'CDDashboardController@getAgendamentosPendentes',false);
$router->get('/cd-api-calendario', 'CDCalendarioController@listar',false);
$router->post('/cd-api-calendario', 'CDCalendarioController@salvar',false);
$router->put('/cd-api-calendario', 'CDCalendarioController@atualizar',false);
$router->delete('/cd-api-calendario', 'CDCalendarioController@excluir',false);
$router->patch('/cd-api-calendario-status', 'CDCalendarioController@alterarStatus',false);

// APIs de Recibo de Descarga
$router->post('/cd-api-recibo', 'CDCalendarioController@gerarRecibo',false);
$router->get('/cd-api-recibo', 'CDCalendarioController@buscarRecibo',false);
$router->get('/cd-api-recibos', 'CDCalendarioController@listarRecibos',false);

// Páginas CD
$router->get('/cd-calendario', 'CDCalendarioController@index',false);
$router->get('/cd-dashboard', 'CDDashboardController@index',false);
$router->get('/cd', 'CDDashboardController@index',false);