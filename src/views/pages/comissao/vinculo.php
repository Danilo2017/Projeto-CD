<?php
// Verificar permissão de acesso (dados injetados pelo Controller)
$acessoComissao = $is_admin || in_array('comissao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoComissao) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Cadastro de Vínculos Centro/Recurso/Funcionário',
    'showNavbar' => true,
    'pageActive' => 'comissao-vinculo',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="comissao-dashboard-container" style="width: 100%; max-width: 100%; padding: 10px; margin: 0;">
    <!-- Filtros -->
    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group" style="min-width: 220px;">
                <label for="filtroFuncionario">Funcionário</label>
                <select id="filtroFuncionario" class="form-select" style="width: 100%;">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group" style="min-width: 220px;">
                <label for="filtroRecurso">Recurso</label>
                <select id="filtroRecurso" class="form-select" style="width: 100%;">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group" style="min-width: 220px;">
                <label for="filtroCentro">Centro de Trabalho</label>
                <select id="filtroCentro" class="form-select" style="width: 100%;">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-primary" onclick="carregarVinculos()">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalVinculo" onclick="novoVinculo()">
                    <i class="bi bi-plus-circle"></i> Novo Vínculo
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela de Vínculos -->
    <div class="dashboard-section" style="width: 100%; max-width: 100%;">
        <table class="table table-striped table-hover" id="tabelaVinculos" style="width: 100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Empresa</th>
                    <th>Funcionário</th>
                    <th>Tipo</th>
                    <th>Recurso</th>
                    <th>Centro de Trabalho</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tabelaVinculosBody">
                <!-- Dados serão carregados via JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Modal de Cadastro/Edição -->
    <div class="modal fade" id="modalVinculo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVinculoTitulo">
                        <i class="bi bi-link-45deg"></i> Novo Vínculo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formVinculo">
                        <input type="hidden" id="vinculoId">
                        <div class="mb-3">
                            <label for="tipo_vinculo" class="form-label">Tipo de Vínculo *</label>
                            <select class="form-select" id="tipo_vinculo" name="tipo_vinculo" required onchange="toggleRecursoObrigatorio()">
                                <option value="N">Normal (vinculado ao recurso)</option>
                                <option value="A">Apoio (ganha sobre total do centro)</option>
                            </select>
                            <small class="text-muted">Apoio: ganha sobre a produtividade total do centro, sem desconto de falta</small>
                        </div>
                        <div class="mb-3">
                            <label for="funcionario_id" class="form-label">Funcionário *</label>
                            <select class="form-select" id="funcionario_id" name="funcionario_id" style="width: 100%;" required></select>
                        </div>
                        <div class="mb-3" id="recursoGroup">
                            <label for="recurso_id" class="form-label">Recurso <span id="recursoReq">*</span></label>
                            <select class="form-select" id="recurso_id" name="recurso_id" style="width: 100%;" required></select>
                            <small class="text-muted" id="recursoHelp" style="display:none;">Opcional para funcionários de Apoio</small>
                        </div>
                        <div class="mb-3">
                            <label for="centro_id" class="form-label">Centro de Trabalho *</label>
                            <select class="form-select" id="centro_id" name="centro_id" style="width: 100%;" required></select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnSalvarVinculo" onclick="salvarVinculo()">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Dias de Apoio -->
    <div class="modal fade" id="modalDiasApoio" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalDiasApoioTitulo">
                        <i class="bi bi-calendar-event"></i> Dias de Apoio
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="diasApoioVinculoId">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        Configure os dias em que este funcionário atuará como <strong>Apoio</strong> (ganhando sobre o total do centro de trabalho).
                        Nos demais dias, ele opera como <strong>Normal</strong> (vinculado ao recurso).
                    </div>
                    
                    <!-- Informações do vínculo -->
                    <div class="mb-3 p-2 bg-light rounded">
                        <strong>Funcionário:</strong> <span id="diasApoioFuncNome">-</span><br>
                        <strong>Centro:</strong> <span id="diasApoioCentroNome">-</span>
                    </div>

                    <!-- Seletor de centro de trabalho para apoio -->
                    <div class="mb-3">
                        <label for="centroApoioSelect" class="form-label">Centro de Trabalho para Apoio *</label>
                        <select class="form-select" id="centroApoioSelect">
                            <option value="">Mesmo centro do vínculo</option>
                        </select>
                        <small class="text-muted">Centro onde o funcionário ganhará como apoio nos dias selecionados</small>
                    </div>

                    <!-- Tipo de Cálculo (fixo em Média) -->
                    <div class="mb-3">
                        <label class="form-label">Tipo de Cálculo dos Pontos</label>
                        <input type="hidden" name="tipoCalculoApoio" id="tipoCalculoApoio" value="M">
                        <div class="alert alert-info py-2 mb-0">
                            <i class="fas fa-calculator me-2"></i>
                            <strong>Média</strong> - Recebe pontos ÷ recursos ativos no dia
                        </div>
                    </div>
                    
                    <!-- Adicionar nova data -->
                    <div class="row mb-3">
                        <div class="col-md-5">
                            <label for="novaDataApoio" class="form-label">Adicionar Data</label>
                            <input type="date" class="form-control" id="novaDataApoio">
                        </div>
                        <div class="col-md-4">
                            <label for="novaDataApoioFim" class="form-label">Até (opcional)</label>
                            <input type="date" class="form-control" id="novaDataApoioFim">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-success w-100" onclick="adicionarDataApoio()">
                                <i class="bi bi-plus-lg"></i> Adicionar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Lista de datas configuradas -->
                    <div class="mb-3">
                        <label class="form-label">Datas Configuradas</label>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-bordered" id="tabelaDiasApoio">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 30%">Data</th>
                                        <th style="width: 35%">Centro Apoio</th>
                                        <th style="width: 20%">Tipo Cálculo</th>
                                        <th style="width: 15%">Ação</th>
                                    </tr>
                                </thead>
                                <tbody id="tabelaDiasApoioBody">
                                    <tr><td colspan="4" class="text-center text-muted">Nenhuma data configurada</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $render('footer', [
    'customJS' => ['src/js/comissao-vinculo.js']
]) ?>
