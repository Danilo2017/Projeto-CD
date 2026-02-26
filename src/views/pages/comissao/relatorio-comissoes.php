<?php
// Verificar permissão de acesso (novo sistema de perfis)
$rotasPermitidas = $_SESSION['user']['rotas_permitidas'] ?? [];
$isAdmin = $_SESSION['user']['is_admin'] ?? false;
$acessoComissao = $isAdmin || in_array('comissao', $rotasPermitidas) || in_array('*', $rotasPermitidas);
if (!$acessoComissao) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Relatório de Comissões',
    'showNavbar' => true,
    'pageActive' => 'comissao-relatorio-comissoes',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'background: #f0f0f0; margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container">
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
            <div class="filter-group">
                <label for="filtroCentro">Centro de Trabalho</label>
                <select id="filtroCentro" class="form-select">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroStatus">Status</label>
                <select id="filtroStatus" class="form-select">
                    <option value="">Todos</option>
                    <option value="P">Pendente</option>
                    <option value="A">Aprovado</option>
                    <option value="C">Cancelado</option>
                </select>
            </div>
            <div class="filter-group">
                <button type="button" class="btn btn-primary btn-sm" onclick="carregarRelatorio()">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
            <div class="ms-auto d-flex gap-2 align-items-end" style="flex: none;">
                <button type="button" class="btn btn-success btn-sm" onclick="processarComissoes()" id="btnProcessar">
                    <i class="bi bi-calculator"></i> Processar
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportarPDF()">
                    <i class="bi bi-file-pdf"></i> PDF
                </button>
                <button type="button" class="btn btn-outline-success btn-sm" onclick="exportarExcel()">
                    <i class="bi bi-file-excel"></i> Excel
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
                <span class="metric-label">Total Funcionários</span>
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
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="metric-content">
                <span class="metric-value" id="pendentesAprovacao">-</span>
                <span class="metric-label">Pendentes</span>
            </div>
        </div>
    </div>

    <!-- Resumo por Centro de Trabalho -->
    <div class="dashboard-section mb-4">
        <div class="section-header">
            <h5><i class="bi bi-building"></i> Resumo por Centro de Trabalho</h5>
        </div>
        <div class="section-body">
            <table class="table table-striped table-hover" id="tabelaCentros">
                <thead>
                    <tr>
                        <th>Centro de Trabalho</th>
                        <th class="text-center">Funcionários</th>
                        <th class="text-end">Total Pontos</th>
                        <th class="text-end">Total Comissão</th>
                        <th class="text-end">Média/Func.</th>
                    </tr>
                </thead>
                <tbody id="tabelaCentrosBody">
                    <tr>
                        <td colspan="5" class="text-center">Selecione o período e clique em "Buscar"</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabela Principal de Comissões -->
    <div class="dashboard-section">
        <div class="section-header d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-list-check"></i> Detalhamento de Comissões</h5>
            <div>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="aprovarSelecionados()">
                    <i class="bi bi-check-lg"></i> Aprovar Selecionados
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="cancelarSelecionados()">
                    <i class="bi bi-x-lg"></i> Cancelar Selecionados
                </button>
            </div>
        </div>
        <div class="section-body">
            <table class="table table-striped table-hover" id="tabelaComissoes">
                <thead>
                    <tr>
                        <th class="text-center"><input type="checkbox" id="selecionarTodos" onchange="toggleSelecaoTodos()"></th>
                        <th>Período</th>
                        <th>Código</th>
                        <th>Funcionário</th>
                        <th>Centro Trab.</th>
                        <th class="text-end">Total Pontos</th>
                        <th>Faixa Aplicada</th>
                        <th class="text-end">Valor Comissão</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody id="tabelaComissoesBody">
                    <tr>
                        <td colspan="10" class="text-center">Selecione o período e clique em "Buscar"</td>
                    </tr>
                </tbody>
                <tfoot id="tabelaComissoesFoot">
                    <tr class="table-primary">
                        <td colspan="5" class="text-end"><strong>TOTAL:</strong></td>
                        <td class="text-end" id="footTotalPontos"><strong>-</strong></td>
                        <td>-</td>
                        <td class="text-end" id="footTotalComissao"><strong>-</strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Detalhes da Comissão -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalhesTitulo">
                    <i class="bi bi-info-circle"></i> Detalhes da Comissão
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalDetalhesBody">
                <!-- Conteúdo dinâmico -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnCancelarComissao" onclick="cancelarComissaoModal()">
                    <i class="bi bi-x-lg"></i> Cancelar Comissão
                </button>
                <button type="button" class="btn btn-success btn-sm" id="btnAprovarComissao" onclick="aprovarComissaoModal()">
                    <i class="bi bi-check-lg"></i> Aprovar Comissão
                </button>
            </div>
        </div>
    </div>
</div>

<?= $render('footer', [
    'customJS' => ['src/js/comissao-relatorio-comissoes.js']
]) ?>
