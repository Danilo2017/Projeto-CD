<?php
// Verificar permissão de acesso (dados injetados pelo Controller)
$acessoComissao = $is_admin || in_array('comissao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoComissao) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Extrato Analítico de Comissão',
    'showNavbar' => true,
    'pageActive' => 'comissao-extrato-analitico',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    .extrato-table th {
        position: sticky;
        top: 0;
        background: #f8f9fa;
        z-index: 10;
    }
    .status-normal { background-color: #d4edda; color: #155724; }
    .status-apoio { background-color: #cce5ff; color: #004085; }
    .status-falta-integral { background-color: #f8d7da; color: #721c24; }
    .status-falta-parcial { background-color: #fff3cd; color: #856404; }
    .status-sem-apontamento { background-color: #e2e3e5; color: #383d41; }
    .funcionario-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 15px;
        border-radius: 8px 8px 0 0;
        font-weight: 600;
    }
    .funcionario-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .funcionario-footer {
        background: #f8f9fa;
        padding: 10px 15px;
        border-radius: 0 0 8px 8px;
        border-top: 1px solid #dee2e6;
    }
    .legenda-item {
        display: inline-flex;
        align-items: center;
        margin-right: 15px;
        font-size: 12px;
    }
    .legenda-cor {
        width: 16px;
        height: 16px;
        border-radius: 3px;
        margin-right: 5px;
    }
    .table-extrato {
        font-size: 13px;
    }
    .table-extrato td, .table-extrato th {
        padding: 6px 10px;
        vertical-align: middle;
    }
    .badge-status {
        font-size: 11px;
        padding: 4px 8px;
    }
</style>

<div class="comissao-dashboard-container">
    <div class="d-flex justify-content-end mb-2">
        <a href="<?= $base ?>comissao-relatorio" class="btn btn-sm btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
    <!-- Filtros -->
    <div class="dashboard-filters">
        <div class="filter-row d-flex align-items-end flex-wrap">
            <div class="filter-group">
                <label for="filtroDataInicio">Data Início *</label>
                <input type="date" id="filtroDataInicio" class="form-control" required>
            </div>
            <div class="filter-group">
                <label for="filtroDataFim">Data Fim *</label>
                <input type="date" id="filtroDataFim" class="form-control" required>
            </div>
            <div class="filter-group" style="min-width: 350px;">
                <label for="filtroCentro">Centro de Trabalho *</label>
                <select id="filtroCentro" class="form-select" style="width: 100%;" required>
                    <option value="">Selecione um centro...</option>
                </select>
            </div>
            <div class="filter-group">
                <button type="button" class="btn btn-primary btn-sm" onclick="carregarExtrato()" id="btnBuscar">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
            <div class="ms-auto d-flex gap-2 align-items-end" style="flex: none;">
                <button type="button" class="btn btn-outline-success btn-sm" onclick="exportarCSV()" id="btnExportarCSV" disabled>
                    <i class="bi bi-download"></i> Exportar CSV
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportarExcel()" id="btnExportarExcel" disabled>
                    <i class="bi bi-file-excel"></i> Exportar Excel
                </button>
            </div>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="dashboard-metrics">
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
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="totalDiasNormais">-</span>
                <span class="metric-label">Dias Normais</span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon bg-warning">
                <i class="bi bi-people"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="totalDiasApoio">-</span>
                <span class="metric-label">Dias Apoio</span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon bg-danger">
                <i class="bi bi-x-circle"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="totalDiasFalta">-</span>
                <span class="metric-label">Dias Falta</span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon bg-secondary">
                <i class="bi bi-cash"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="totalValorEstimado">-</span>
                <span class="metric-label">Valor Estimado</span>
            </div>
        </div>
    </div>

    <!-- Legenda -->
    <div class="mb-3 p-2 bg-light rounded">
        <strong>Legenda:</strong>
        <span class="legenda-item"><span class="legenda-cor status-normal"></span> Normal</span>
        <span class="legenda-item"><span class="legenda-cor status-apoio"></span> Apoio</span>
        <span class="legenda-item"><span class="legenda-cor status-falta-integral"></span> Falta Integral</span>
        <span class="legenda-item"><span class="legenda-cor status-falta-parcial"></span> Falta Parcial</span>
    </div>

    <!-- Conteúdo do Extrato -->
    <div id="extratoContainer">
        <div class="text-center text-muted py-5">
            <i class="bi bi-bar-chart-line display-4"></i>
            <p class="mt-3">Selecione o período e o centro de trabalho para gerar o extrato analítico</p>
        </div>
    </div>
</div>

<?= $render('footer', [
    'customJS' => ['src/js/comissao-extrato-analitico.js']
]) ?>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>

<!-- SheetJS para exportação Excel -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
