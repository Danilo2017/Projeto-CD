<?php
// Verificar permissão de acesso (dados injetados pelo Controller)
$acessoComissao = $is_admin || in_array('comissao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoComissao) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Cadastro de Faixas de Comissão',
    'showNavbar' => true,
    'pageActive' => 'comissao-faixas',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container" style="width: 100%; max-width: 100%; padding: 10px; margin: 0;">
    <!-- Header com botão de adicionar -->




    <!-- Filtros -->
    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="filtroCentro">Centro de Trabalho</label>
                <select id="filtroCentro" class="form-select">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroTipo">Tipo de Faixa</label>
                <select id="filtroTipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="P">Percentual</option>
                    <option value="Q">Quantidade</option>
                </select>
            </div>
            <div class="filter-group">
                <div class="form-check mt-4">
                    <input type="checkbox" id="incluirInativas" class="form-check-input">
                    <label for="incluirInativas" class="form-check-label">Incluir inativas</label>
                </div>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-primary mt-3" onclick="carregarFaixas()">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#modalFaixa" onclick="novaFaixa()">
                    <i class="bi bi-plus-circle"></i> Nova Faixa
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela de Faixas -->
    <div class="dashboard-section" style="width: 100%; max-width: 100%;">
        <table class="table table-striped table-hover" id="tabelaFaixas" style="width: 100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Empresa</th>
                    <th>Descrição</th>
                    <th>Tipo</th>
                    <th>Aplica-se a</th>
                    <th>Ponto Inicial</th>
                    <th>Ponto Final</th>
                    <th>Valor/Percentual</th>
                    <th>Centro Trabalho</th>
                    <th>Vigência</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tabelaFaixasBody">
                <!-- Dados serão carregados via JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Modal de Cadastro/Edição -->
    <div class="modal fade" id="modalFaixa" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFaixaTitulo">
                        <i class="bi bi-layers-fill"></i> Nova Faixa de Comissão
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formFaixa">
                        <input type="hidden" id="faixaId">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="descricao" class="form-label">Descrição *</label>
                                <input type="text" id="descricao" class="form-control" maxlength="100" required>
                            </div>
                            <div class="col-md-3">
                                <label for="tipoFaixa" class="form-label">Tipo de Faixa *</label>
                                <select id="tipoFaixa" class="form-select" required onchange="atualizarLabelValor()">
                                    <option value="">Selecione</option>
                                    <option value="P">Percentual</option>
                                    <option value="Q">Quantidade (Valor Fixo)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="tipoFuncionario" class="form-label">Aplica-se a *</label>
                                <select id="tipoFuncionario" class="form-select" required>
                                    <option value="T">Todos</option>
                                    <option value="N">Normal</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="pontoInicial" class="form-label">Ponto Inicial *</label>
                                <input type="number" id="pontoInicial" class="form-control" step="0.01" min="0" max="999999" required>
                            </div>
                            <div class="col-md-4">
                                <label for="pontoFinal" class="form-label">Ponto Final</label>
                                <input type="number" id="pontoFinal" class="form-control" step="0.01" min="0" max="999999">
                                <small class="text-muted">Deixe em branco para sem limite</small>
                            </div>
                            <div class="col-md-4">
                                <label for="valorComissao" class="form-label" id="labelValorComissao">Valor *</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="prefixoValor">R$</span>
                                    <input type="number" id="valorComissao" class="form-control" step="0.01" min="0" required>
                                    <span class="input-group-text" id="sufixoValor" style="display:none;">%</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="centroTrabId" class="form-label">Centro de Trabalho</label>
                                <select id="centroTrabId" class="form-select">
                                    <option value="">Todos os centros</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="dtVigenciaIni" class="form-label">Vigência Início *</label>
                                <input type="date" id="dtVigenciaIni" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label for="dtVigenciaFim" class="form-label">Vigência Fim</label>
                                <input type="date" id="dtVigenciaFim" class="form-control">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnSalvarFaixa" onclick="salvarFaixa()">
                        <i class="bi bi-check"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $render('footer', [
    'customJS' => ['src/js/comissao-faixas.js']
]) ?>
