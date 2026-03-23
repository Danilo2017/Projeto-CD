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

<div class="comissao-dashboard-container" style="width: 100%; max-width: 100%; padding: 10px; margin: 0;">
    <!-- Filtros -->
    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="filtroFuncionario">Funcionário</label>
                <select id="filtroFuncionario" class="form-select">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroRecurso">Recurso</label>
                <select id="filtroRecurso" class="form-select">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroCentro">Centro de Trabalho</label>
                <select id="filtroCentro" class="form-select">
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
                            <select class="form-select" id="funcionario_id" name="funcionario_id" required></select>
                        </div>
                        <div class="mb-3" id="recursoGroup">
                            <label for="recurso_id" class="form-label">Recurso <span id="recursoReq">*</span></label>
                            <select class="form-select" id="recurso_id" name="recurso_id" required></select>
                            <small class="text-muted" id="recursoHelp" style="display:none;">Opcional para funcionários de Apoio</small>
                        </div>
                        <div class="mb-3">
                            <label for="centro_id" class="form-label">Centro de Trabalho *</label>
                            <select class="form-select" id="centro_id" name="centro_id" required></select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="salvarVinculo()">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $render('footer', [
    'customJS' => ['src/js/comissao-vinculo.js']
]) ?>
