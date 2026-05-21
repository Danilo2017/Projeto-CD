<?php
/**
 * Variáveis injetadas pelo Controller via extract():
 * @var bool     $is_admin
 * @var array    $rotas_permitidas
 * @var string   $base
 * @var callable $render
 */
// Verificar permissão de acesso (dados injetados pelo Controller)
$acessoComissao = $is_admin || in_array('comissao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoComissao) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Relatório por Centro de Trabalho',
    'showNavbar' => true,
    'pageActive' => 'comissao-relatorio-centro-trabalho',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="comissao-dashboard-container" style="max-width: 100%; overflow-x: hidden;">
    <div class="d-flex justify-content-end mb-2">
        <a href="<?= $base ?>comissao-relatorio" class="btn btn-sm btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
    <!-- Filtros -->
    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group" style="min-width: 350px;">
                <label for="filtroCentro">Centro de Trabalho *</label>
                <select id="filtroCentro" class="form-select" style="width: 100%;" required>
                    <option value="">Digite código ou nome...</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroDataInicio">Data Início *</label>
                <input type="date" id="filtroDataInicio" class="form-control" required>
            </div>
            <div class="filter-group">
                <label for="filtroDataFim">Data Fim *</label>
                <input type="date" id="filtroDataFim" class="form-control" required>
            </div>
            <div class="d-flex gap-2 align-items-end mt-3" style="flex: none;">
                <button type="button" class="btn btn-primary btn-sm" onclick="carregarRelatorio()">
                    <i class="bi bi-search"></i> Gerar Relatório
                </button>
            </div>
        </div>
    </div>

    <!-- Info do Centro de Trabalho -->
    <div class="card mb-4" id="cardCentro" style="display: none;">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-1 text-center">
                    <i class="bi bi-building" style="font-size: 50px; color: #6c757d;"></i>
                </div>
                <div class="col-md-11">
                    <h4 id="nomeCentro">-</h4>
                    <small class="text-muted">Código: <span id="codigoCentro">-</span></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="dashboard-metrics" id="metricsContainer" style="display: none;">
        <div class="metric-card">
            <div class="metric-icon bg-primary">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="totalFuncionarios">-</span>
                <span class="metric-label">Funcionários</span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon bg-success">
                <i class="bi bi-star-fill"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="totalPontos">-</span>
                <span class="metric-label">Total Pontos</span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon bg-info">
                <i class="bi bi-cash"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="totalComissao">-</span>
                <span class="metric-label">Total Comissão</span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon bg-warning">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="totalComFalta">-</span>
                <span class="metric-label">Com Falta</span>
            </div>
        </div>
    </div>

    <!-- Tabela de Funcionários -->
    <div class="dashboard-section" id="sectionFuncionarios" style="display: none;">
        <div class="section-header d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-people"></i> Funcionários do Centro</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="exportarExcel()">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </button>
                <button type="button" class="btn btn-sm btn-info text-white" onclick="imprimirComprovantesSelecionados()">
                    <i class="bi bi-printer"></i> Imprimir Selecionados
                </button>
                <button type="button" class="btn btn-sm btn-primary" onclick="imprimirTodosComprovantes()">
                    <i class="bi bi-printer-fill"></i> Imprimir Todos
                </button>
            </div>
        </div>
        <div class="section-body">
            <table class="table table-striped table-hover" id="tabelaFuncionarios">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 40px;">
                            <input type="checkbox" id="selecionarTodos" onchange="toggleSelecaoTodos()" class="form-check-input">
                        </th>
                        <th>Código</th>
                        <th>Funcionário</th>
                        <th>Alocação</th>
                        <th class="text-end">Total Pontos</th>
                        <th>Faixa Aplicada</th>
                        <th class="text-center">Dias Trab.</th>
                        <th class="text-center">Faltas</th>
                        <th class="text-end">Valor Comissão</th>
                        <th class="text-center" style="width: 100px;">Comprovante</th>
                    </tr>
                </thead>
                <tbody id="tabelaFuncionariosBody">
                    <tr>
                        <td colspan="10" class="text-center">Selecione o centro e período e clique em "Gerar Relatório"</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <td colspan="4" class="text-end"><strong>TOTAL:</strong></td>
                        <td class="text-end" id="footTotalPontos"><strong>-</strong></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="text-end" id="footTotalComissao"><strong>-</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>

<?= $render('footer', [
    'customJS' => ['src/js/comissao-relatorio-centro-trabalho.js']
]) ?>
