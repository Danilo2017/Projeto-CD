<?php
// Verificar permissão de acesso
$acessoComissao = $_SESSION['user']['acesso_comissao'] ?? 'N';
if ($acessoComissao !== 'S') {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Relatório por Funcionário',
    'showNavbar' => true,
    'pageActive' => 'comissao-relatorio-funcionario',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'background: #f0f0f0; margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container" style="max-width: 100%; overflow-x: hidden;">
    <!-- Filtros -->
    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="filtroFuncionario">Funcionário *</label>
                <select id="filtroFuncionario" class="form-select" required>
                    <option value="">Selecione um funcionário</option>
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
                <button type="button" class="btn btn-secondary btn-sm" id="btnComprovante" onclick="gerarComprovante()" style="display: none;">
                    <i class="bi bi-file-earmark-text"></i> Comprovante
                </button>
            </div>
        </div>
    </div>

    <!-- Info do Funcionário -->
    <div class="card mb-4" id="cardFuncionario" style="display: none;">
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 text-center">
                    <i class="bi bi-person-circle" style="font-size: 80px; color: #6c757d;"></i>
                </div>
                <div class="col-md-10">
                    <h4 id="nomeFuncionario">-</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <small class="text-muted">Código</small>
                            <p id="codigoFuncionario" class="mb-0">-</p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Centro de Trabalho</small>
                            <p id="centroFuncionario" class="mb-0">-</p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Admissão</small>
                            <p id="admissaoFuncionario" class="mb-0">-</p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Situação</small>
                            <p id="situacaoFuncionario" class="mb-0">-</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="dashboard-metrics" id="metricsContainer" style="display: none;">
        <div class="metric-card">
            <div class="metric-icon bg-primary">
                <i class="bi bi-clipboard-data"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="totalApontamentos">-</span>
                <span class="metric-label">Apontamentos</span>
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
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="mediaDiaria">-</span>
                <span class="metric-label">Média Diária</span>
            </div>
        </div>
    </div>

    <!-- Resumo por Dia -->
    <div class="dashboard-section mb-4" id="sectionDiario" style="display: none;">
        <div class="section-header d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-calendar3"></i> Resumo Diário</h5>
            <button class="btn btn-success btn-sm" onclick="exportarTabelaExcel('tabelaDiario', 'Resumo_Diario')">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
        </div>
        <div class="section-body">
            <table class="table table-striped table-hover" id="tabelaDiario">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Qtd. Apontamentos</th>
                        <th>Centro Trab.</th>
                        <th>Recurso Utilizado</th>
                        <th>Total Pontos</th>
                        <th>Comissão</th>
                    </tr>
                </thead>
                <tbody id="tabelaDiarioBody">
                    <tr>
                        <td colspan="6" class="text-center">Aguardando dados...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detalhamento de Apontamentos -->
    <div class="dashboard-section" id="sectionApontamentos" style="display: none;">
        <div class="section-header d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-list-task"></i> Detalhamento de Apontamentos</h5>
            <button class="btn btn-success btn-sm" onclick="exportarTabelaExcel('tabelaApontamentos', 'Detalhamento_Apontamentos')">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
        </div>
        <div class="section-body">
            <div class="table-responsive">
            <table class="table table-striped table-hover table-sm" id="tabelaApontamentos" style="font-size: 0.85rem;">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descrição</th>
                        <th>Máscara</th>
                        <th>Operação</th>
                        <th>Recurso</th>
                        <th class="text-center">Quantidade</th>
                        <th class="text-end">Pontos</th>
                    </tr>
                </thead>
                <tbody id="tabelaApontamentosBody">
                    <tr>
                        <td colspan="7" class="text-center">Aguardando dados...</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- Histórico de Comissões -->
    <div class="dashboard-section" id="sectionComissoes" style="display: none;">
        <div class="section-header d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-cash-stack"></i> Histórico de Comissões</h5>
            <button class="btn btn-success btn-sm" onclick="exportarTabelaExcel('tabelaComissoes', 'Historico_Comissoes')">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
        </div>
        <div class="section-body">
            <table class="table table-striped table-hover" id="tabelaComissoes">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Total Pontos</th>
                        <th>Faixa Aplicada</th>
                        <th>Valor Comissão</th>
                        <th>Status</th>
                        <th>Data Processamento</th>
                    </tr>
                </thead>
                <tbody id="tabelaComissoesBody">
                    <tr>
                        <td colspan="6" class="text-center">Aguardando dados...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Comprovante -->
<div class="modal fade" id="modalComprovante" tabindex="-1" aria-labelledby="modalComprovanteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white no-print">
                <h5 class="modal-title" id="modalComprovanteLabel">
                    <i class="bi bi-file-earmark-text"></i> Comprovante de Comissão
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="comprovanteContent" class="comprovante-container">
                    <!-- Header do Comprovante -->
                    <div class="comprovante-header">
                        <div class="comprovante-titulo">
                            <h5 class="mb-0">COMPROVANTE DE COMISSÃO POR PRODUTIVIDADE</h5>
                            <small>Gazin Indústria de Colchões Ltda</small>
                        </div>
                        <div class="comprovante-periodo-inline">
                            <strong>Período:</strong> <span id="comprovantePeriodo">-</span>
                        </div>
                    </div>

                    <!-- Dados do Funcionário (compacto) -->
                    <div class="comprovante-funcionario-compacto">
                        <span><strong>Funcionário:</strong> <span id="comprovanteNome">-</span></span>
                        <span><strong>Cód:</strong> <span id="comprovanteCodigo">-</span></span>
                        <span><strong>Centro:</strong> <span id="comprovanteCentro">-</span></span>
                    </div>

                    <!-- Detalhamento Diário -->
                    <div class="comprovante-detalhamento">
                        <table class="table table-bordered table-sm table-striped mb-0" id="tabelaComprovante">
                            <thead class="table-dark">
                                <tr>
                                    <th>Data</th>
                                    <th class="text-center">Apontamentos</th>
                                    <th class="text-center">Pontos</th>
                                    <th class="text-end">Comissão</th>
                                </tr>
                            </thead>
                            <tbody id="comprovanteDetalhamento">
                            </tbody>
                            <tfoot class="table-secondary fw-bold">
                                <tr>
                                    <td>TOTAL</td>
                                    <td class="text-center" id="comprovanteTotalApontamentos">-</td>
                                    <td class="text-center" id="comprovanteTotalPontos">-</td>
                                    <td class="text-end" id="comprovanteTotalComissao">-</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Assinaturas -->
                    <div class="comprovante-assinaturas">
                        <div class="assinatura-box">
                            <div class="linha-assinatura"></div>
                            <small>Funcionário: <span id="comprovanteNomeAssinatura">-</span></small>
                        </div>
                        <div class="assinatura-box">
                            <div class="linha-assinatura"></div>
                            <small>Responsável RH</small>
                        </div>
                    </div>

                    <!-- Rodapé -->
                    <div class="comprovante-rodape">
                        <small>Gerado em: <span id="comprovanteDataGeracao">-</span></small>
                    </div>
                </div>
            </div>
            <div class="modal-footer no-print">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Fechar
                </button>
                <button type="button" class="btn btn-primary" onclick="imprimirComprovante()">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Estilos do Comprovante -->
<style>
.comprovante-container {
    padding: 20px;
    font-size: 13px;
    background: white;
}

.comprovante-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #333;
    padding-bottom: 8px;
    margin-bottom: 10px;
}

.comprovante-titulo h5 {
    margin: 0;
    font-size: 14px;
    font-weight: bold;
}

.comprovante-periodo-inline {
    font-size: 12px;
}

.comprovante-funcionario-compacto {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    padding: 8px 0;
    border-bottom: 1px solid #dee2e6;
    margin-bottom: 10px;
    font-size: 12px;
}

.comprovante-detalhamento table {
    font-size: 11px;
}

.comprovante-detalhamento th,
.comprovante-detalhamento td {
    padding: 4px 8px !important;
}

.comprovante-assinaturas {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    gap: 40px;
}

.assinatura-box {
    flex: 1;
    text-align: center;
}

.linha-assinatura {
    border-top: 1px solid #333;
    margin-bottom: 3px;
}

.comprovante-rodape {
    margin-top: 15px;
    text-align: center;
    color: #6c757d;
    font-size: 10px;
}

/* Esconder elementos DataTable no comprovante */
#modalComprovante .dataTables_wrapper > .dt-layout-row:first-child,
#modalComprovante .dataTables_wrapper > .dt-layout-row:last-child,
#modalComprovante .dataTables_length,
#modalComprovante .dataTables_filter,
#modalComprovante .dataTables_info,
#modalComprovante .dataTables_paginate,
#modalComprovante .dt-search,
#modalComprovante .dt-length,
#modalComprovante .dt-info,
#modalComprovante .dt-paging {
    display: none !important;
    visibility: hidden !important;
    height: 0 !important;
    overflow: hidden !important;
}

/* Garantir que a tabela do comprovante apareça sem wrapper */
#tabelaComprovante {
    width: 100% !important;
}

/* Estilos para impressão - CORRIGIDO */
@media print {
    /* Esconder tudo exceto o comprovante */
    body > *:not(.modal) {
        display: none !important;
    }
    
    .no-print {
        display: none !important;
    }
    
    .modal {
        position: static !important;
        display: block !important;
        overflow: visible !important;
        background: none !important;
    }
    
    .modal-dialog {
        max-width: 100% !important;
        margin: 0 !important;
        transform: none !important;
    }
    
    .modal-content {
        border: none !important;
        box-shadow: none !important;
    }
    
    .modal-body {
        padding: 0 !important;
    }
    
    .comprovante-container {
        padding: 10px;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    .table-dark {
        background-color: #333 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    .table-dark th {
        color: white !important;
    }
    
    .table-secondary {
        background-color: #e9ecef !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    .table {
        page-break-inside: auto;
    }
    
    tr {
        page-break-inside: avoid;
    }
    
    @page {
        size: A4;
        margin: 10mm;
    }
}
</style>

<!-- Chart.js para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<?= $render('footer', [
    'customJS' => ['src/js/comissao-relatorio-funcionario.js']
]) ?>
