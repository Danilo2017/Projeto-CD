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
    'pageTitle' => 'Relatório Diário de Produtividade',
    'showNavbar' => true,
    'pageActive' => 'comissao-relatorio-diario',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="comissao-dashboard-container">
    <!-- Filtros -->
    <!-- Empresa da sessão (hidden) -->
    <input type="hidden" id="filtroEmpresa" value="<?= $empresa['id'] ?? '' ?>">

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
            <div class="filter-group" style="min-width: 320px;">
                <label for="filtroCentro">Centro de Trabalho</label>
                <select id="filtroCentro" class="form-select" style="width: 100%;">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group" style="min-width: 320px;">
                <label for="filtroRecurso">Recurso</label>
                <select id="filtroRecurso" class="form-select" style="width: 100%;">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group">
                <button type="button" class="btn btn-primary btn-sm" onclick="carregarRelatorio()">
                    <i class="bi bi-search"></i> Gerar Relatório
                </button>
            </div>
            <div class="filter-group ms-auto">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportarPDF()">
                    <i class="bi bi-file-pdf"></i> PDF
                </button>
                <button type="button" class="btn btn-outline-success btn-sm ms-1" onclick="exportarExcel()">
                    <i class="bi bi-file-excel"></i> Excel
                </button>
            </div>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="dashboard-metrics">
        <div class="metric-card">
            <div class="metric-icon bg-primary">
                <i class="bi bi-clipboard-data"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="totalRegistros">-</span>
                <span class="metric-label">Registros</span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon bg-info">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="totalQtdProduzida">-</span>
                <span class="metric-label">Qtd. Produzida</span>
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
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="totalFuncionarios">-</span>
                <span class="metric-label">Funcionários</span>
            </div>
        </div>
    </div>

    <!-- Cards de Validação -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <span class="badge bg-danger fs-6" id="badgeSemPontuacao">0</span>
                    </div>
                    <div>
                        <strong>Sem Pontuação</strong>
                        <br><small class="text-muted">Itens sem pontuação cadastrada</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <span class="badge bg-warning text-dark fs-6" id="badgeSemFaixa">0</span>
                    </div>
                    <div>
                        <strong>Sem Faixa</strong>
                        <br><small class="text-muted">Centros sem faixa de comissão</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <span class="badge bg-secondary fs-6" id="badgeSemVinculo">0</span>
                    </div>
                    <div>
                        <strong>Sem Vínculo</strong>
                        <br><small class="text-muted">Func./Recurso/Centro sem vínculo</small>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Tabela de Produtividade por Funcionário -->
    <div class="dashboard-section">
        <div class="section-header">
            <h5><i class="bi bi-people-fill"></i> Produtividade por Funcionário</h5>
        </div>
        <div class="section-body">
            <table class="table table-striped table-hover table-sm" style="font-size: 0.78rem;" id="tabelaProdutividade">
                <thead>
                    <tr>
                        <th>Funcionário</th>
                        <th>Centro Trab.</th>
                        <th>Alocação</th>
                        <th>Recurso</th>
                        <th class="text-center">Itens</th>
                        <th class="text-center">Qtd. Produzida</th>
                        <th class="text-end">Total Pontos</th>
                        <th class="text-center">Pontuação</th>
                        <th class="text-center">Faixa</th>
                        <th class="text-center">Vínculo</th>
                    </tr>
                </thead>
                <tbody id="tabelaProdutividadeBody">
                    <tr>
                        <td colspan="10" class="text-center">Selecione os filtros e clique em "Gerar Relatório"</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabela de Detalhamento de Apontamentos -->
    <div class="dashboard-section">
        <div class="section-header">
            <h5><i class="bi bi-list-task"></i> Detalhamento de Apontamentos</h5>
        </div>
        <div class="section-body">
            <table class="table table-striped table-hover table-sm" style="font-size: 0.75rem;" id="tabelaApontamentos">
                <thead>
                    <tr>
                        <th>Funcionário</th>
                        <th>Alocação</th>
                        <th>Produto</th>
                        <th>Centro Trab.</th>
                        <th>Operação</th>
                        <th>Recurso</th>
                        <th class="text-center">Qtd.</th>
                        <th class="text-end">Pts/Un</th>
                        <th class="text-end">Total Pts</th>
                        <th class="text-center">Pont.</th>
                        <th class="text-center">Faixa</th>
                        <th class="text-center">Vínc.</th>
                    </tr>
                </thead>
                <tbody id="tabelaApontamentosBody">
                    <tr>
                        <td colspan="12" class="text-center">Selecione os filtros e clique em "Gerar Relatório"</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?= $render('footer', [
    'customJS' => ['src/js/comissao-relatorio-diario.js']
]) ?>
