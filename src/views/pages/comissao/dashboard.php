<?= $render('header', [
    'pageTitle' => 'Dashboard - Comissionamento de Produtividade',
    'showNavbar' => true,
    'pageActive' => 'comissao-dashboard',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'background: #f0f0f0; margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container">
    <!-- Empresa da sessão para JS -->
    <input type="hidden" id="sessaoEmpresaId" value="<?= $_SESSION['empresa']['id'] ?? '' ?>">
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
            <div class="filter-group">
                <label for="filtroCentro">Centro de Trabalho</label>
                <select id="filtroCentro" class="form-select">
                    <option value="">Selecione a Filial primeiro</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroRecurso">Recurso</label>
                <select id="filtroRecurso" class="form-select">
                    <option value="">Selecione a Filial primeiro</option>
                </select>
            </div>
            <div class="filter-group">
                <button type="button" class="btn btn-primary btn-sm" onclick="carregarDashboard()">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <button type="button" class="btn btn-success btn-sm" onclick="simularComissoes()">
                    <i class="bi bi-calculator"></i> Simular Comissões
                </button>
            </div>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="comissao-metrics-grid">
        <div class="comissao-metric-card">
            <div class="comissao-metric-icon">
                <i class="bi bi-star-fill"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">TOTAL DE PONTOS</div>
                <div class="comissao-metric-value" id="totalPontos">0</div>
            </div>
        </div>
        <div class="comissao-metric-card success">
            <div class="comissao-metric-icon">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">QTD. PRODUZIDA</div>
                <div class="comissao-metric-value" id="totalQtdBoa">0</div>
            </div>
        </div>
        <div class="comissao-metric-card info">
            <div class="comissao-metric-icon">
                <i class="bi bi-clipboard-check"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">APONTAMENTOS</div>
                <div class="comissao-metric-value" id="totalApontamentos">0</div>
            </div>
        </div>
        <div class="comissao-metric-card warning">
            <div class="comissao-metric-icon">
                <i class="bi bi-building"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">CENTROS DE TRABALHO</div>
                <div class="comissao-metric-value" id="totalCentros">0</div>
            </div>
        </div>
    </div>

    <!-- Container para tabelas -->
    <div class="comissao-tables-container">
        <!-- Resumo por Centro de Trabalho -->
        <div class="comissao-table-section">
            <div class="comissao-table-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart-fill"></i> RESUMO POR CENTRO DE TRABALHO</span>
                <button type="button" class="btn btn-success btn-sm" onclick="exportarExcel('centros')">
                    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                </button>
            </div>
            <table class="comissao-data-table" id="tabelaCentros">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Centro de Trabalho</th>
                        <th>Funcionários</th>
                        <th>Apontamentos</th>
                        <th>Qtd. Boa</th>
                        <th>Qtd. Refugo</th>
                        <th>Total Pontos</th>
                    </tr>
                </thead>
                <tbody id="tabelaCentrosBody">
                    <tr>
                        <td colspan="7" class="comissao-loading">⏳ Carregando dados...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Ranking de Funcionários -->
        <div class="comissao-table-section">
            <div class="comissao-table-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-trophy-fill"></i> TOP 10 - RANKING FUNCIONÁRIOS</span>
                <button type="button" class="btn btn-success btn-sm" onclick="exportarExcel('ranking')">
                    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                </button>
            </div>
            <table class="comissao-data-table" id="tabelaRanking">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Código</th>
                        <th>Funcionário</th>
                        <th>Centro Trabalho</th>
                        <th>Apontamentos</th>
                        <th>Qtd. Boa</th>
                        <th>Total Pontos</th>
                    </tr>
                </thead>
                <tbody id="tabelaRankingBody">
                    <tr>
                        <td colspan="7" class="comissao-loading">⏳ Carregando dados...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal de Simulação de Comissões -->
    <div class="modal fade" id="modalSimulacao" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calculator"></i> Simulação de Comissões
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="simulacao-resumo mb-3">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="simulacao-card">
                                    <span class="label">Período:</span>
                                    <span class="value" id="simPeriodo">-</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="simulacao-card">
                                    <span class="label">Funcionários:</span>
                                    <span class="value" id="simFuncionarios">0</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="simulacao-card">
                                    <span class="label">Total Pontos:</span>
                                    <span class="value" id="simPontos">0</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="simulacao-card success">
                                    <span class="label">Total Comissão:</span>
                                    <span class="value" id="simComissao">R$ 0,00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <table class="table table-striped table-hover" id="tabelaSimulacao">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Funcionário</th>
                                <th>Centro Trabalho</th>
                                <th>Apontamentos</th>
                                <th>Qtd. Boa</th>
                                <th>Total Pontos</th>
                                <th>Faixa</th>
                                <th>Valor Comissão</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaSimulacaoBody">
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-info btn-sm" onclick="exportarExcel('simulacao')">
                        <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="processarComissoes()">
                        <i class="bi bi-check-circle"></i> Processar Comissões
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $render('footer', [
    'customJS' => ['src/js/comissao-dashboard.js']
]) ?>
