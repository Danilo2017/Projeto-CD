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

// ========== Projeção de Carga ==========
$router->get('/carga-projecao',          'CD\\ProjecaoCargaController@index',    true);
$router->post('/carga-api-listar',       'CD\\ProjecaoCargaController@listar',   true);
$router->post('/carga-api-salvar',       'CD\\ProjecaoCargaController@salvar',   true);
$router->get('/carga-api-log',           'CD\\ProjecaoCargaController@listarLog',   true);
$router->get('/carga-api-anexo-listar',  'CD\\ProjecaoCargaController@listarAnexos', true);
$router->post('/carga-api-anexo-upload', 'CD\\ProjecaoCargaController@uploadAnexo',  true);
$router->get('/carga-api-anexo-download','CD\\ProjecaoCargaController@downloadAnexo',true);
$router->post('/carga-api-anexo-excluir','CD\\ProjecaoCargaController@excluirAnexo', true);
$router->get('/carga-api-rota-listar',  'CD\\ProjecaoCargaController@listarRota',   true);
$router->post('/carga-api-rota-salvar', 'CD\\ProjecaoCargaController@salvarRota',   true);
$router->get('/carga-api-itens',           'CD\\ProjecaoCargaController@listarItens',          true);
$router->get('/carga-api-itens-expedicao', 'CD\\ProjecaoCargaController@listarItensExpedicao', true);

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

// APIs de Datas de Apoio - Funcionário NORMAL que atua como APOIO em dias específicos
$router->get('/comissao-api-vinculo-datas', 'Comissao\\ComissaoCadastroController@listarDatasApoio', true);
$router->post('/comissao-api-vinculo-datas', 'Comissao\\ComissaoCadastroController@salvarDatasApoio', true);
$router->post('/comissao-api-vinculo-data', 'Comissao\\ComissaoCadastroController@adicionarDataApoio', true);
$router->delete('/comissao-api-vinculo-data', 'Comissao\\ComissaoCadastroController@removerDataApoio', true);

// APIs de Cadastro - Pontuação
$router->get('/comissao-api-pontuacao', 'Comissao\\ComissaoCadastroController@listarPontuacoes', true);
$router->post('/comissao-api-pontuacao', 'Comissao\\ComissaoCadastroController@salvarPontuacao', true);
$router->put('/comissao-api-pontuacao', 'Comissao\\ComissaoCadastroController@atualizarPontuacao', true);
$router->delete('/comissao-api-pontuacao', 'Comissao\\ComissaoCadastroController@excluirPontuacao', true);
$router->post('/comissao-api-pontuacao-importar', 'Comissao\\ComissaoCadastroController@importarPontuacoes', true);
$router->get('/comissao-api-relatorio-itens', 'Comissao\\ComissaoCadastroController@relatorioItens', true);

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
$router->get('/comissao-api-centros-custo', 'Comissao\\ComissaoCadastroController@getCentrosCusto', true);

// API para seleção de empresa (define sessão)
$router->post('/comissao-api-selecionar-empresa', 'Comissao\\ComissaoCadastroController@selecionarEmpresa', true);
$router->get('/comissao-api-empresa-selecionada', 'Comissao\\ComissaoCadastroController@getEmpresaSelecionada', true);

// ========== Gestão de Faltas ==========
$router->get('/comissao-faltas', 'Comissao\\ComissaoCadastroController@faltasIndex', true);
$router->get('/comissao-api-faltas', 'Comissao\\ComissaoCadastroController@listarFaltas', true);
$router->post('/comissao-api-falta', 'Comissao\\ComissaoCadastroController@salvarFalta', true);
$router->post('/comissao-api-faltas-lote', 'Comissao\\ComissaoCadastroController@salvarFaltasLote', true);
$router->post('/comissao-api-faltas-import', 'Comissao\\ComissaoCadastroController@importarFaltas', true);
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
$router->get('/comissao-relatorio-faltas', 'Comissao\\ComissaoRelatorioController@faltasIndex', true);
$router->get('/comissao-extrato-analitico', 'Comissao\\ComissaoRelatorioController@extratoAnaliticoIndex', true);

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
$router->get('/comissao-api-relatorio-faltas', 'Comissao\\ComissaoRelatorioController@getRelatorioFaltas', true);
$router->get('/comissao-api-extrato-analitico', 'Comissao\\ComissaoRelatorioController@getExtratoAnalitico', true);

// ========== Rotas de Permissões ==========
$router->get('/permissao', 'PermissaoController@index', true);
$router->get('/permissao-api-perfis', 'PermissaoController@listarPerfis', true);
$router->get('/permissao-api-empresas', 'PermissaoController@listarEmpresas', true);
$router->get('/permissao-api-listar', 'PermissaoController@listar', true);
$router->get('/permissao-api-buscar', 'PermissaoController@buscar', true);
$router->post('/permissao-api-salvar', 'PermissaoController@salvar', true);
$router->post('/permissao-api-atualizar', 'PermissaoController@atualizar', true);
$router->post('/permissao-api-excluir', 'PermissaoController@excluir', true);

// ========== Rotas do Faturamento Indústrias ==========
// Página do Dashboard
$router->get('/faturamento', 'Faturamento\\FaturamentoDashboardController@index', true);
$router->get('/faturamento-dashboard', 'Faturamento\\FaturamentoDashboardController@index', true);

// APIs de Faturamento
$router->get('/faturamento-api-resumo', 'Faturamento\\FaturamentoDashboardController@getResumoMensal', true);
$router->get('/faturamento-api-painel', 'Faturamento\\FaturamentoDashboardController@getPainelVendas', true);
$router->get('/faturamento-api-pedidos', 'Faturamento\\FaturamentoDashboardController@getPedidos', true);
$router->get('/faturamento-api-pedidos-planejado', 'Faturamento\\FaturamentoDashboardController@getPedidosPlanejado', true);
$router->get('/faturamento-api-dias-mes', 'Faturamento\\FaturamentoDashboardController@getDiasMes', true);
$router->get('/faturamento-api-dias-mes-empresa', 'Faturamento\\FaturamentoDashboardController@getDiasMesEmpresa', true);
$router->get('/faturamento-api-vlr-faltante-carga', 'Faturamento\\FaturamentoDashboardController@getVlrFaltanteCarga', true);

// ========== Programação de Pedidos ==========
$router->get('/faturamento-programacao', 'Faturamento\\FaturamentoProgramacaoController@index', true);
$router->get('/faturamento-api-programacao', 'Faturamento\\FaturamentoProgramacaoController@listar', true);
$router->get('/faturamento-api-programacao-resumo', 'Faturamento\\FaturamentoProgramacaoController@resumoDashboard', true);
$router->get('/faturamento-api-ocupacao', 'Faturamento\\FaturamentoProgramacaoController@ocupacao', true);
$router->get('/faturamento-api-programacao-flush', 'Faturamento\\FaturamentoProgramacaoController@flushCache', true);

// ========== Transferência de Pedidos ==========
$router->get('/pedidos-transferencia', 'Faturamento\\TransferenciaPedidoController@index', true);
$router->post('/pedidos-api-transferencia-buscar', 'Faturamento\\TransferenciaPedidoController@buscarPedidos', true);
$router->post('/pedidos-api-transferencia', 'Faturamento\\TransferenciaPedidoController@executar', true);

// ========== Gestão de Metas por Empresa ==========
// Página de Gestão de Metas
$router->get('/meta-empresa', 'Faturamento\\MetaEmpresaController@index', true);

// APIs de Meta Empresa
$router->get('/meta-empresa-api-listar', 'Faturamento\\MetaEmpresaController@listar', true);
$router->get('/meta-empresa-api-buscar', 'Faturamento\\MetaEmpresaController@buscar', true);
$router->get('/meta-empresa-api-empresas', 'Faturamento\\MetaEmpresaController@empresas', true);
$router->post('/meta-empresa-api-salvar', 'Faturamento\\MetaEmpresaController@salvar', true);
$router->delete('/meta-empresa-api-excluir', 'Faturamento\\MetaEmpresaController@excluir', true);

// ========== Processo ==========
$router->get('/processo-troca-almox',            'Processo\\TrocaAlmoxarifadoController@index',              true);
$router->get('/processo-api-almoxarifados',      'Processo\\TrocaAlmoxarifadoController@listarAlmoxarifados', true);
$router->post('/processo-api-troca-almox-ordens','Processo\\TrocaAlmoxarifadoController@buscarOrdens',        true);
$router->post('/processo-api-troca-almox',       'Processo\\TrocaAlmoxarifadoController@executar',            true);

$router->get('/processo-troca-almox-carga',              'Processo\\TrocaAlmoxCargaController@index',            true);
$router->post('/processo-api-troca-almox-carga-buscar', 'Processo\\TrocaAlmoxCargaController@buscarItensCarga', true);
$router->post('/processo-api-troca-almox-carga',        'Processo\\TrocaAlmoxCargaController@executar',         true);

$router->get('/processo-troca-almox-pedido',                    'Processo\\TrocaAlmoxPedidoController@index',            true);
$router->post('/processo-api-troca-almox-pedido-buscar-itens',  'Processo\\TrocaAlmoxPedidoController@buscarItensPedido', true);
$router->post('/processo-api-troca-almox-pedido-buscar-almox',  'Processo\\TrocaAlmoxPedidoController@buscarAlmoxarifado', true);
$router->post('/processo-api-troca-almox-pedido',               'Processo\\TrocaAlmoxPedidoController@executar',         true);

$router->get('/processo-troca-tipo-nf',           'Processo\\TrocaTipoNfEntradaController@index',       true);
$router->get('/processo-api-tipos-nf-ent',        'Processo\\TrocaTipoNfEntradaController@listarTipos', true);
$router->post('/processo-api-troca-tipo-nf-buscar','Processo\\TrocaTipoNfEntradaController@buscarNf',   true);
$router->post('/processo-api-troca-tipo-nf',      'Processo\\TrocaTipoNfEntradaController@executar',    true);

$router->get('/processo-transferencia-estoque',          'Processo\\TransferenciaEstoqueController@index',              true);
$router->get('/processo-api-transf-almox',               'Processo\\TransferenciaEstoqueController@listarAlmoxarifados',true);
$router->post('/processo-api-transf-saldo',              'Processo\\TransferenciaEstoqueController@buscarSaldo',        true);
$router->post('/processo-api-transf-executar',           'Processo\\TransferenciaEstoqueController@executar',           true);

// ========== PCP ==========
$router->get('/pcp-relatorio-producao',      'PCP\\RelatorioProdController@index',        true);
$router->post('/pcp-api-relatorio-producao', 'PCP\\RelatorioProdController@buscar',       true);
$router->get('/pcp-relatorio-pillow',        'PCP\\RelatorioProdController@indexPillow',  true);
$router->post('/pcp-api-relatorio-pillow',   'PCP\\RelatorioProdController@buscarPillow', true);
$router->get('/pcp-relatorio-fpt',            'PCP\\RelatorioProdController@indexFpt',        true);
$router->post('/pcp-api-relatorio-fpt',       'PCP\\RelatorioProdController@buscarFpt',       true);
$router->get('/pcp-relatorio-mesa-faixa',     'PCP\\RelatorioProdController@indexMesaFaixa',  true);
$router->post('/pcp-api-relatorio-mesa-faixa','PCP\\RelatorioProdController@buscarMesaFaixa', true);
$router->get('/pcp-relatorio-optron',          'PCP\\RelatorioProdController@indexOptron',      true);
$router->post('/pcp-api-relatorio-optron',     'PCP\\RelatorioProdController@buscarOptron',     true);
$router->get('/pcp-relatorio-tampo-liso',         'PCP\\RelatorioProdController@indexTampoLiso',    true);
$router->post('/pcp-api-relatorio-tampo-liso',    'PCP\\RelatorioProdController@buscarTampoLiso',   true);
$router->get('/pcp-relatorio-tampo-bordado',           'PCP\\RelatorioProdController@indexTampoBordado',     true);
$router->post('/pcp-api-relatorio-tampo-bordado',      'PCP\\RelatorioProdController@buscarTampoBordado',    true);
$router->get('/pcp-relatorio-tampo-bordado-mesa',      'PCP\\RelatorioProdController@indexTampoBordadoMesa', true);
$router->post('/pcp-api-relatorio-tampo-bordado-mesa', 'PCP\\RelatorioProdController@buscarTampoBordadoMesa',true);
$router->get('/pcp-relatorio-manta',                   'PCP\\RelatorioProdController@indexManta',            true);
$router->post('/pcp-api-relatorio-manta',              'PCP\\RelatorioProdController@buscarManta',           true);
$router->get('/pcp-relatorio-manta-mesa',              'PCP\\RelatorioProdController@indexMantaMesa',        true);
$router->post('/pcp-api-relatorio-manta-mesa',         'PCP\\RelatorioProdController@buscarMantaMesa',       true);
$router->get('/pcp-relatorio-mesa-de-corte',           'PCP\\RelatorioProdController@indexMesaDeCorte',      true);
$router->post('/pcp-api-relatorio-mesa-de-corte',      'PCP\\RelatorioProdController@buscarMesaDeCorte',     true);
$router->get('/pcp-relatorio-bordadeira',              'PCP\\RelatorioProdController@indexBordadeira',       true);
$router->post('/pcp-api-relatorio-bordadeira',         'PCP\\RelatorioProdController@buscarBordadeira',      true);
$router->get('/pcp-relatorio-tapecaria',               'PCP\\RelatorioProdController@indexTapecaria',        true);
$router->post('/pcp-api-relatorio-tapecaria',          'PCP\\RelatorioProdController@buscarTapecaria',       true);
$router->get('/pcp-relatorio-robotec',                 'PCP\\RelatorioProdController@indexRobotec',          true);
$router->post('/pcp-api-relatorio-robotec',            'PCP\\RelatorioProdController@buscarRobotec',         true);
$router->get('/pcp-relatorio-rolo-bordado',            'PCP\\RelatorioProdController@indexRoloBordado',      true);
$router->post('/pcp-api-relatorio-rolo-bordado',       'PCP\\RelatorioProdController@buscarRoloBordado',     true);
$router->get('/pcp-relatorio-conjugado',               'PCP\\RelatorioProdController@indexConjugado',        true);
$router->post('/pcp-api-relatorio-conjugado',          'PCP\\RelatorioProdController@buscarConjugado',       true);
$router->get('/pcp-relatorio-trave-peze',              'PCP\\RelatorioProdController@indexTravePeze',        true);
$router->post('/pcp-api-relatorio-trave-peze',         'PCP\\RelatorioProdController@buscarTravePeze',       true);
$router->get('/pcp-relatorio-molas-bordas',            'PCP\\RelatorioProdController@indexMolasBordas',      true);
$router->post('/pcp-api-relatorio-molas-bordas',       'PCP\\RelatorioProdController@buscarMolasBordas',     true);
$router->get('/pcp-relatorio-caixote',                 'PCP\\RelatorioProdController@indexCaixote',          true);
$router->post('/pcp-api-relatorio-caixote',            'PCP\\RelatorioProdController@buscarCaixote',         true);
$router->get('/pcp-relatorio-caixa-box',                    'PCP\\RelatorioProdController@indexCaixaBox',              true);
$router->post('/pcp-api-relatorio-caixa-box',               'PCP\\RelatorioProdController@buscarCaixaBox',             true);
$router->get('/pcp-relatorio-robotec-abastecedor',          'PCP\\RelatorioProdController@indexRobotecAbastecedor',    true);
$router->post('/pcp-api-relatorio-robotec-abastecedor',     'PCP\\RelatorioProdController@buscarRobotecAbastecedor',   true);
$router->get('/pcp-relatorio-vertical-espuma',              'PCP\\RelatorioProdController@indexVerticalEspuma',        true);
$router->post('/pcp-api-relatorio-vertical-espuma',         'PCP\\RelatorioProdController@buscarVerticalEspuma',       true);
$router->get('/pcp-relatorio-horizontal-espuma',            'PCP\\RelatorioProdController@indexHorizontalEspuma',      true);
$router->post('/pcp-api-relatorio-horizontal-espuma',       'PCP\\RelatorioProdController@buscarHorizontalEspuma',     true);
$router->get('/pcp-resumo-lote',                            'PCP\\RelatorioProdController@indexResumoDeLote',          true);
$router->post('/pcp-api-resumo-lote',                       'PCP\\RelatorioProdController@buscarResumoDeLote',         true);
$router->get('/pcp-relatorio-pcp-molas',                    'PCP\\RelatorioProdController@indexPcpMolas',              true);
$router->post('/pcp-api-relatorio-pcp-molas',               'PCP\\RelatorioProdController@buscarPcpMolas',             true);

$router->get('/pcp-relatorio-pcp-tampo',                    'PCP\\RelatorioProdController@indexPcpTampo',              true);
$router->post('/pcp-api-relatorio-pcp-tampo',               'PCP\\RelatorioProdController@buscarPcpTampo',             true);
$router->get('/pcp-relatorio-pcp-cordao',                   'PCP\\RelatorioProdController@indexPcpCordao',             true);
$router->post('/pcp-api-relatorio-pcp-cordao',              'PCP\\RelatorioProdController@buscarPcpCordao',            true);

// TEMPORÁRIO — remover após inserção do SQL verticalEspuma
$router->get('/tmp-insert-vertical-espuma', 'AdminSqlsController@tmpInsertVerticalEspuma', true);

// ========== Admin - Gerenciamento de SQLs ==========
$router->get('/admin-sqls', 'AdminSqlsController@index', true);
$router->get('/admin-api-sqls', 'AdminSqlsController@listar', true);
$router->get('/admin-api-sql', 'AdminSqlsController@buscar', true);
$router->get('/admin-api-sql-historico', 'AdminSqlsController@historico', true);
$router->post('/admin-api-sql-salvar', 'AdminSqlsController@salvar', true);
$router->post('/admin-api-sql-validar', 'AdminSqlsController@validar', true);
$router->put('/admin-api-sql-atualizar', 'AdminSqlsController@atualizar', true);
$router->delete('/admin-api-sql-excluir', 'AdminSqlsController@excluir', true);
