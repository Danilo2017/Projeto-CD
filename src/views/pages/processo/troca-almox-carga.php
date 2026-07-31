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
    'pageTitle'  => 'Troca de Almoxarifado por Carga',
    'showNavbar' => true,
    'pageActive' => 'processo-troca-almox-carga',
    'customCSS'  => [],
]) ?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0 fw-bold"><i class="bi bi-box-arrow-right me-2 text-primary"></i>Troca de Almoxarifado por Carga</h4>
    </div>

    <!-- Busca -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-semibold">
            <i class="bi bi-search me-1"></i> Buscar Itens da Carga
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
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
                    <label class="form-label fw-semibold">Nº da Carga</label>
                    <input type="number" id="inpCarga" class="form-control" placeholder="Ex: 200" min="1">
                </div>
                <div class="col-md-3">
                    <button id="btnBuscar" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Buscar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Itens encontrados -->
    <div id="secaoItens" class="d-none">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-check me-1"></i> Itens da Carga</span>
                <span id="totalItens" class="badge bg-secondary">0</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tabelaItensCarga" class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pedido</th>
                                <th>Cód. Item</th>
                                <th>Descrição</th>
                                <th>Máscara</th>
                                <th>Almox. Atual</th>
                                <th>Descrição Almox.</th>
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

                    <!-- Escopo -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Escopo da Troca</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="escopo" id="radioTodaCarga" value="carga" checked>
                                <label class="form-check-label" for="radioTodaCarga">Toda a Carga</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="escopo" id="radioPorPedido" value="pedido">
                                <label class="form-check-label" for="radioPorPedido">Por Pedido</label>
                            </div>
                        </div>
                        <div id="divPedido" class="mt-2 d-none">
                            <select id="selPedido" class="form-select form-select-sm">
                                <option value="">Selecione o pedido...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Almox destino -->
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Cód. Almoxarifado Destino</label>
                        <input type="text" id="inpCodAlmox" class="form-control"
                               placeholder="Ex: 998" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Descrição</label>
                        <input type="text" id="inpDescAlmox" class="form-control bg-light" readonly
                               placeholder="Informe o código acima">
                        <input type="hidden" id="inpAlmoxDestId">
                    </div>

                    <!-- Executar -->
                    <div class="col-md-2">
                        <button id="btnTrocar" class="btn btn-success w-100">
                            <i class="bi bi-arrow-left-right me-1"></i> Trocar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resultado -->
    <div id="secaoResultado" class="d-none">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check2-circle me-1"></i> Resultado da Troca</span>
                <span id="resumoResultado" class="fw-normal text-muted small"></span>
            </div>
            <div class="card-body" id="corpoResultado"></div>
        </div>
    </div>

</div>

<script src="<?= $base ?>src/js/troca-almox-carga.js?v=<?= time() ?>"></script>
<?= $render('footer') ?>
