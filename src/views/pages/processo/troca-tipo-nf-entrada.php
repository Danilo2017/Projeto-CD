<?php
/**
 * @var bool     $is_admin
 * @var array    $rotas_permitidas
 * @var string   $base
 * @var callable $render
 * @var array    $empresas
 */
$acesso = $is_admin || in_array('processo', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acesso) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Troca de Tipo de NF Entrada',
    'showNavbar' => true,
    'pageActive' => 'processo-troca-tipo-nf',
    'customCSS'  => [],
]) ?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0 fw-bold"><i class="bi bi-file-earmark-arrow-down me-2 text-primary"></i>Troca de Tipo de NF Entrada</h4>
    </div>

    <!-- Busca -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-semibold">
            <i class="bi bi-search me-1"></i> Buscar NF de Entrada
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Empresa</label>
                    <select id="selEmpresa" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($empresas as $emp): ?>
                            <option value="<?= intval($emp['ID']) ?>">
                                FL <?= htmlspecialchars($emp['CODIGO']) ?> — <?= htmlspecialchars($emp['RAZAO_SOCIAL']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Cód. Fornecedor</label>
                    <input type="text" id="inpCodFor" class="form-control" placeholder="Ex: 001234">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Nº da NF</label>
                    <input type="number" id="inpNumNf" class="form-control" placeholder="Ex: 10783" min="1">
                </div>
                <div class="col-md-4">
                    <button id="btnBuscar" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Buscar NF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Resultado da busca -->
    <div id="secaoNf" class="d-none">

        <!-- Info da NF (capa) -->
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-text me-1"></i> Dados da NF</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label text-muted small mb-1">Nº NF</label>
                        <div id="infoNumNf" class="fw-semibold"></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-muted small mb-1">Data Entrada</label>
                        <div id="infoDtEnt" class="fw-semibold"></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-muted small mb-1">Cód. Fornecedor</label>
                        <div id="infoCodFor" class="fw-semibold"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small mb-1">Fornecedor</label>
                        <div id="infoFornecedor" class="fw-semibold"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small mb-1">Tipo NF Atual (Capa)</label>
                        <div id="infoTipoCapa" class="fw-semibold"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Itens da NF -->
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul me-1"></i> Itens da NF</span>
                <span id="totalItens" class="badge bg-secondary">0</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">
                                    <input type="checkbox" id="chkTodos" class="form-check-input" checked>
                                </th>
                                <th>Nº Item</th>
                                <th>Descrição</th>
                                <th>Cód. Tipo NF Atual</th>
                                <th>Tipo NF Atual</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaItens"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Configuração da troca -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-warning text-dark fw-semibold">
                <i class="bi bi-gear me-1"></i> Configurar Troca
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">O que trocar</label>
                        <div class="d-flex flex-column gap-2 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkTrocarCapa" checked>
                                <label class="form-check-label" for="chkTrocarCapa">Trocar Capa</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkTrocarItens" checked>
                                <label class="form-check-label" for="chkTrocarItens">Trocar Itens Selecionados</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Cód. Novo Tipo</label>
                        <input type="text" id="inpCodTipo" class="form-control"
                               placeholder="Ex: NF001" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Descrição</label>
                        <input type="text" id="inpDescTipo" class="form-control bg-light" readonly
                               placeholder="Informe o código do tipo">
                        <input type="hidden" id="inpTipoDestId">
                    </div>
                    <div class="col-md-3">
                        <button id="btnTrocar" class="btn btn-success w-100">
                            <i class="bi bi-arrow-left-right me-1"></i> Trocar Tipo NF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resultados -->
    <div id="secaoResultados" class="d-none">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check2-circle me-1"></i> Resultado da Troca</span>
                <span id="resumoResultado" class="fw-normal text-muted small"></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Alvo</th>
                                <th>Status</th>
                                <th>Novo Tipo</th>
                                <th>Detalhe</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaResultados"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="src/js/troca-tipo-nf-entrada.js?v=<?= time() ?>"></script>

<?= $render('footer') ?>
