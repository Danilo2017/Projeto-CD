<?php

use core\Router;

$router = new Router();

// ========== Rotas Públicas ==========
$router->get('/health-check', 'HomeController@index');
$router->get('/login', 'LoginController@index');
$router->post('/login', 'LoginController@login');
$router->get('/logout', 'LoginController@logout');
$router->get('/sem-acesso', 'HomeController@semAcesso', true);

// Página inicial - redireciona para módulo correto do usuário
$router->get('/', 'HomeController@redirectInicial', true);

// ========== Rotas do CD (Centro de Distribuição) ==========
// APIs CD
$router->get('/cd-api-avisos', 'CD\\CDDashboardController@getAvisosRecebimento', true);
$router->get('/cd-api-agendamentos', 'CD\\CDDashboardController@getAgendamentosPendentes', true);
$router->get('/cd-api-calendario', 'CD\\CDCalendarioController@listar', true);
$router->post('/cd-api-calendario', 'CD\\CDCalendarioController@salvar', true);
$router->put('/cd-api-calendario', 'CD\\CDCalendarioController@atualizar', true);
$router->delete('/cd-api-calendario', 'CD\\CDCalendarioController@excluir', true);
$router->patch('/cd-api-calendario-status', 'CD\\CDCalendarioController@alterarStatus', true);

// APIs de Recibo de Descarga
$router->post('/cd-api-recibo', 'CD\\CDCalendarioController@gerarRecibo', true);
$router->get('/cd-api-recibo', 'CD\\CDCalendarioController@buscarRecibo', true);
$router->get('/cd-api-recibos', 'CD\\CDCalendarioController@listarRecibos', true);

// Páginas CD
$router->get('/cd-calendario', 'CD\\CDCalendarioController@index', true);
$router->get('/cd-dashboard', 'CD\\CDDashboardController@index', true);
$router->get('/cd', 'CD\\CDDashboardController@index', true);

// ========== Rotas do Sistema de Comissão ==========

// Páginas de Dashboard
$router->get('/comissao', 'Comissao\\ComissaoDashboardController@index', true);
$router->get('/comissao-dashboard', 'Comissao\\ComissaoDashboardController@index', true);

// APIs do Dashboard
$router->get('/comissao-api-filiais', 'Comissao\\ComissaoDashboardController@getFiliais', true);
$router->get('/comissao-api-resumo', 'Comissao\\ComissaoDashboardController@getResumoGeral', true);
$router->get('/comissao-api-ranking', 'Comissao\\ComissaoDashboardController@getRankingFuncionarios', true);
$router->get('/comissao-api-resumo-centro', 'Comissao\\ComissaoDashboardController@getResumoPorCentro', true);
$router->get('/comissao-api-resumo-recurso', 'Comissao\\ComissaoDashboardController@getResumoPorRecurso', true);
$router->get('/comissao-api-simular', 'Comissao\\ComissaoDashboardController@simularComissoes', true);
$router->get('/comissao-api-dashboard-completo', 'Comissao\\ComissaoDashboardController@getDashboardCompleto', true);

// Páginas de Cadastro
$router->get('/comissao-cadastro', 'Comissao\\ComissaoCadastroController@index', true);
$router->get('/comissao-pontuacao', 'Comissao\\ComissaoCadastroController@pontuacaoIndex', true);
$router->get('/comissao-faixas', 'Comissao\\ComissaoCadastroController@faixasIndex', true);

// Página de vínculo
$router->get('/comissao-vinculo', 'Comissao\\ComissaoCadastroController@vinculoIndex', true);
$router->get('/comissao-api-vinculos', 'Comissao\\ComissaoCadastroController@listarVinculos', true);
$router->post('/comissao-api-vinculo', 'Comissao\\ComissaoCadastroController@salvarVinculo', true);
$router->put('/comissao-api-vinculo', 'Comissao\\ComissaoCadastroController@atualizarVinculo', true);
$router->delete('/comissao-api-vinculo', 'Comissao\\ComissaoCadastroController@excluirVinculo', true);
$router->patch('/comissao-api-vinculo-status', 'Comissao\\ComissaoCadastroController@alterarStatusVinculo', true);

// APIs de Cadastro - Pontuação
$router->get('/comissao-api-pontuacao', 'Comissao\\ComissaoCadastroController@listarPontuacoes', true);
$router->post('/comissao-api-pontuacao', 'Comissao\\ComissaoCadastroController@salvarPontuacao', true);
$router->put('/comissao-api-pontuacao', 'Comissao\\ComissaoCadastroController@atualizarPontuacao', true);
$router->delete('/comissao-api-pontuacao', 'Comissao\\ComissaoCadastroController@excluirPontuacao', true);
$router->post('/comissao-api-pontuacao-importar', 'Comissao\\ComissaoCadastroController@importarPontuacoes', true);

// APIs de Cadastro - Faixas
$router->get('/comissao-api-faixas', 'Comissao\\ComissaoCadastroController@listarFaixas', true);
$router->post('/comissao-api-faixas', 'Comissao\\ComissaoCadastroController@salvarFaixa', true);
$router->put('/comissao-api-faixas', 'Comissao\\ComissaoCadastroController@atualizarFaixa', true);
$router->delete('/comissao-api-faixas', 'Comissao\\ComissaoCadastroController@inativarFaixa', true);

// APIs de Busca (Selects)
$router->get('/comissao-api-funcionarios', 'Comissao\\ComissaoCadastroController@getFuncionarios', true);
$router->get('/comissao-api-centros', 'Comissao\\ComissaoCadastroController@getCentrosTrabalho', true);
$router->get('/comissao-api-recursos', 'Comissao\\ComissaoCadastroController@getRecursos', true);
$router->get('/comissao-api-produtos', 'Comissao\\ComissaoCadastroController@getProdutos', true);
$router->get('/comissao-api-produtos-busca', 'Comissao\\ComissaoCadastroController@buscarProdutos', true);
$router->get('/comissao-api-empresas', 'Comissao\\ComissaoCadastroController@getEmpresas', false);

// APIs de Busca filtradas por vínculo (para relatórios)
$router->get('/comissao-api-centros-vinculados', 'Comissao\\ComissaoCadastroController@getCentrosComVinculo', true);
$router->get('/comissao-api-recursos-vinculados', 'Comissao\\ComissaoCadastroController@getRecursosComVinculo', true);
$router->get('/comissao-api-funcionarios-vinculados', 'Comissao\\ComissaoCadastroController@getFuncionariosComVinculo', true);

// API para seleção de empresa (define sessão)
$router->post('/comissao-api-selecionar-empresa', 'Comissao\\ComissaoCadastroController@selecionarEmpresa', true);
$router->get('/comissao-api-empresa-selecionada', 'Comissao\\ComissaoCadastroController@getEmpresaSelecionada', true);

// ========== Gestão de Faltas ==========
$router->get('/comissao-faltas', 'Comissao\\ComissaoCadastroController@faltasIndex', true);
$router->get('/comissao-api-faltas', 'Comissao\\ComissaoCadastroController@listarFaltas', true);
$router->post('/comissao-api-falta', 'Comissao\\ComissaoCadastroController@salvarFalta', true);
$router->put('/comissao-api-falta', 'Comissao\\ComissaoCadastroController@atualizarFalta', true);
$router->delete('/comissao-api-falta', 'Comissao\\ComissaoCadastroController@excluirFalta', true);

// ========== Gestão de Retrabalho ==========
$router->get('/comissao-retrabalho', 'Comissao\\ComissaoCadastroController@retrabalhoIndex', true);
$router->get('/comissao-api-retrabalhos', 'Comissao\\ComissaoCadastroController@listarRetrabalhos', true);
$router->post('/comissao-api-retrabalho', 'Comissao\\ComissaoCadastroController@salvarRetrabalho', true);
$router->put('/comissao-api-retrabalho', 'Comissao\\ComissaoCadastroController@atualizarRetrabalho', true);
$router->delete('/comissao-api-retrabalho', 'Comissao\\ComissaoCadastroController@excluirRetrabalho', true);

// ========== Vínculo de Apontamentos sem Recurso ==========
$router->get('/comissao-vinculo-apontamento', 'Comissao\\ComissaoCadastroController@vinculoApontamentoIndex', true);
$router->get('/comissao-api-apontamentos-sem-recurso', 'Comissao\\ComissaoCadastroController@listarApontamentosSemRecurso', true);
$router->get('/comissao-api-vinculos-apontamento', 'Comissao\\ComissaoCadastroController@listarVinculosApontamento', true);
$router->post('/comissao-api-vincular-recurso', 'Comissao\\ComissaoCadastroController@vincularRecurso', true);
$router->post('/comissao-api-vincular-apontamento', 'Comissao\\ComissaoCadastroController@vincularApontamento', true);
$router->post('/comissao-api-vincular-apontamentos-lote', 'Comissao\\ComissaoCadastroController@vincularApontamentosLote', true);
$router->put('/comissao-api-vinculo-apontamento', 'Comissao\\ComissaoCadastroController@atualizarVinculoApontamento', true);
$router->delete('/comissao-api-vinculo-apontamento', 'Comissao\\ComissaoCadastroController@excluirVinculoApontamento', true);

// ========== Regras Específicas por Funcionário ==========
$router->get('/comissao-regras', 'Comissao\\ComissaoCadastroController@regrasIndex', true);
$router->get('/comissao-api-regras', 'Comissao\\ComissaoCadastroController@listarRegras', true);
$router->get('/comissao-api-regra', 'Comissao\\ComissaoCadastroController@buscarRegra', true);
$router->post('/comissao-api-regra', 'Comissao\\ComissaoCadastroController@salvarRegra', true);
$router->put('/comissao-api-regra', 'Comissao\\ComissaoCadastroController@atualizarRegra', true);
$router->delete('/comissao-api-regra', 'Comissao\\ComissaoCadastroController@inativarRegra', true);

// Páginas de Relatórios
$router->get('/comissao-relatorio', 'Comissao\\ComissaoRelatorioController@index', true);
$router->get('/comissao-relatorio-diario', 'Comissao\\ComissaoRelatorioController@produtividadeDiariaIndex', true);
$router->get('/comissao-relatorio-comissoes', 'Comissao\\ComissaoRelatorioController@comissoesIndex', true);
$router->get('/comissao-relatorio-funcionario', 'Comissao\\ComissaoRelatorioController@porFuncionarioIndex', true);
$router->get('/comissao-relatorio-centro-trabalho', 'Comissao\\ComissaoRelatorioController@porCentroTrabalhoIndex', true);

// APIs de Relatórios
$router->get('/comissao-api-produtividade-diaria', 'Comissao\\ComissaoRelatorioController@getProdutividadeDiaria', true);
$router->get('/comissao-api-comissoes', 'Comissao\\ComissaoRelatorioController@getComissoes', true);
$router->get('/comissao-api-comissao-detalhes', 'Comissao\\ComissaoRelatorioController@getComissaoDetalhes', true);
$router->post('/comissao-api-processar', 'Comissao\\ComissaoRelatorioController@processarComissoes', true);
$router->post('/comissao-api-processar-completo', 'Comissao\\ComissaoRelatorioController@processarComissoesCompleto', true);
$router->post('/comissao-api-aprovar', 'Comissao\\ComissaoRelatorioController@aprovarComissao', true);
$router->post('/comissao-api-cancelar', 'Comissao\\ComissaoRelatorioController@cancelarComissao', true);
$router->get('/comissao-api-funcionario', 'Comissao\\ComissaoRelatorioController@getRelatorioFuncionario', true);
$router->get('/comissao-api-relatorio-centro-trabalho', 'Comissao\\ComissaoRelatorioController@getRelatorioCentroTrabalho', true);

// ========== Rotas de Permissões ==========
$router->get('/permissao', 'PermissaoController@index', true);
$router->get('/permissao-api-perfis', 'PermissaoController@listarPerfis', true);
$router->get('/permissao-api-listar', 'PermissaoController@listar', true);
$router->get('/permissao-api-buscar', 'PermissaoController@buscar', true);
$router->post('/permissao-api-salvar', 'PermissaoController@salvar', true);
$router->post('/permissao-api-atualizar', 'PermissaoController@atualizar', true);
$router->post('/permissao-api-excluir', 'PermissaoController@excluir', true);

// ========== Admin - Gerenciamento de SQLs ==========
$router->get('/admin-sqls', 'AdminSqlsController@index', true);
$router->get('/admin-api-sqls', 'AdminSqlsController@listar', true);
$router->get('/admin-api-sql', 'AdminSqlsController@buscar', true);
$router->post('/admin-api-sql-salvar', 'AdminSqlsController@salvar', true);
$router->put('/admin-api-sql-atualizar', 'AdminSqlsController@atualizar', true);
$router->delete('/admin-api-sql-excluir', 'AdminSqlsController@excluir', true);
