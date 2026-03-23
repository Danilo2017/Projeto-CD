<?php
$acessoComissao = $is_admin || in_array('comissao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoComissao) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Dashboard - Comissão',
    'showNavbar' => true,
    'pageActive' => 'comissao-dashboard',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container">
    <input type="hidden" id="sessaoEmpresaId" value="<?= $empresa['id'] ?? '' ?>">

    <!-- Filtros -->
    <div class="comissao-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="filtroFilial">Filial</label>
                <select id="filtroFilial" class="form-select" onchange="atualizarFiltrosPorFilial()">
                    <option value="">Selecione a Filial</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroDataInicio">Data Início</label>
                <input type="date" id="filtroDataInicio" class="form-control">
            </div>
            <div class="filter-group">
                <label for="filtroDataFim">Data Fim</label>
                <input type="date" id="filtroDataFim" class="form-control">
            </div>
            <div class="filter-group filter-actions">
                <button type="button" class="btn btn-primary btn-sm" onclick="carregarDashboard()">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="comissao-metrics-grid">
        <div class="comissao-metric-card">
            <div class="comissao-metric-icon"><i class="bi bi-people-fill"></i></div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">FUNCIONÁRIOS</div>
                <div class="comissao-metric-value" id="totalFuncionarios">-</div>
            </div>
        </div>
        <div class="comissao-metric-card success">
            <div class="comissao-metric-icon"><i class="bi bi-currency-dollar"></i></div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">TOTAL COMISSÃO MÊS</div>
                <div class="comissao-metric-value" id="totalComissao">-</div>
            </div>
        </div>
        <div class="comissao-metric-card warning">
            <div class="comissao-metric-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">COM FALTA</div>
                <div class="comissao-metric-value" id="totalComFalta">-</div>
            </div>
        </div>
        <div class="comissao-metric-card info">
            <div class="comissao-metric-icon"><i class="bi bi-building"></i></div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">CENTROS DE TRABALHO</div>
                <div class="comissao-metric-value" id="totalCentros">-</div>
            </div>
        </div>
    </div>

    <!-- Comissão por Centro de Trabalho -->
    <div class="comissao-table-section full-width">
        <div class="comissao-table-header">
            <span><i class="bi bi-bar-chart-fill"></i> COMISSÃO POR CENTRO DE TRABALHO</span>
        </div>
        <div class="table-responsive">
            <table class="comissao-data-table" id="tabelaCentros">
                <thead>
                    <tr>
                        <th>Centro de Trabalho</th>
                        <th class="text-center">Funcionários</th>
                        <th class="text-end">Total Pontos</th>
                        <th class="text-end">Total Comissão</th>
                        <th class="text-center">Com Falta</th>
                        <th>% do Total</th>
                    </tr>
                </thead>
                <tbody id="tabelaCentrosBody">
                    <tr><td colspan="6" class="comissao-loading">Selecione uma filial e clique em Filtrar</td></tr>
                </tbody>
                <tfoot id="tabelaCentrosFoot" style="display:none;">
                    <tr class="linha-totais">
                        <td><strong>TOTAL GERAL</strong></td>
                        <td class="text-center" id="centroTotalFunc">0</td>
                        <td class="text-end" id="centroTotalPontos">0</td>
                        <td class="text-end" id="centroTotalComissao">R$ 0,00</td>
                        <td class="text-center" id="centroTotalFaltas">0</td>
                        <td>100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Container para tabelas lado a lado -->
    <div class="comissao-tables-container">
        <!-- Funcionários com Falta -->
        <div class="comissao-table-section">
            <div class="comissao-table-header">
                <span><i class="bi bi-exclamation-circle-fill"></i> FUNCIONÁRIOS COM FALTA</span>
            </div>
            <div class="table-responsive">
                <table class="comissao-data-table" id="tabelaFaltas">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Funcionário</th>
                            <th>Centro</th>
                            <th class="text-center">Dias Falta</th>
                            <th class="text-end">Pontos</th>
                            <th class="text-end">Comissão</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaFaltasBody">
                        <tr><td colspan="6" class="comissao-loading">Aguardando dados...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Ranking por Centro de Trabalho -->
        <div class="comissao-table-section">
            <div class="comissao-table-header">
                <span><i class="bi bi-trophy-fill"></i> RANKING COMISSÃO POR CENTRO</span>
            </div>
            <div class="ranking-filtro-centro">
                <select id="filtroRankingCentro" class="form-select form-select-sm" onchange="filtrarRankingPorCentro()">
                    <option value="">Todos os Centros</option>
                </select>
            </div>
            <div class="table-responsive">
                <table class="comissao-data-table" id="tabelaRanking">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th>Funcionário</th>
                            <th>Centro</th>
                            <th class="text-end">Pontos</th>
                            <th class="text-end">Comissão</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaRankingBody">
                        <tr><td colspan="5" class="comissao-loading">Aguardando dados...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabela completa de funcionários -->
    <div class="comissao-table-section full-width">
        <div class="comissao-table-header">
            <span><i class="bi bi-list-ul"></i> TODOS OS FUNCIONÁRIOS</span>
            <button type="button" class="btn btn-success btn-sm" onclick="exportarExcel()">
                <i class="bi bi-file-earmark-excel"></i> Exportar Excel
            </button>
        </div>
        <div class="table-responsive">
            <table class="comissao-data-table" id="tabelaFuncionarios">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Funcionário</th>
                        <th>Centro de Trabalho</th>
                        <th class="text-center">Dias Trab.</th>
                        <th class="text-center">Dias Falta</th>
                        <th class="text-end">Total Pontos</th>
                        <th class="text-end">Valor Comissão</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody id="tabelaFuncionariosBody">
                    <tr><td colspan="8" class="comissao-loading">Selecione uma filial e clique em Filtrar</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $render('footer', [
    'customJS' => ['src/js/comissao-dashboard.js']
]) ?>
