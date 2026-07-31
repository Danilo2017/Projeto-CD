<?php
/**
 * @var bool     $is_admin
 * @var array    $rotas_permitidas
 * @var string   $base
 * @var callable $render
 * @var int      $emprId
 */
$acesso = $is_admin || in_array('processo', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acesso) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Troca de Almoxarifado por Ordem',
    'showNavbar' => true,
    'pageActive' => 'processo-troca-almox-pedido',
    'customCSS'  => [],
]) ?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0 fw-bold">
            <i class="bi bi-box-arrow-in-right me-2 text-primary"></i>Troca de Almoxarifado por Ordem
        </h4>
    </div>

    <!-- Busca -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-semibold">
            <i class="bi bi-search me-1"></i> Buscar Itens das Ordens
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        Nº das Ordens
                        <small class="text-muted fw-normal">(separados por vírgula ou linha)</small>
                    </label>
                    <textarea id="txtNumeros" class="form-control" rows="2"
                              placeholder="Ex: 3638, 3639, 3640"></textarea>
                </div>
                <div class="col-md-3">
                    <button id="btnBuscar" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Buscar Itens
                    </button>
                </div>
            </div>
            <input type="hidden" id="hEmprId" value="<?= intval($emprId) ?>">
        </div>
    </div>

    <!-- Itens encontrados -->
    <div id="secaoItens" class="d-none">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-check me-1"></i> Itens Encontrados</span>
                <span id="totalItens" class="badge bg-secondary">0</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tabelaItensOrdem" class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pedido</th>
                                <th>Cód. Item</th>
                                <th>Descrição</th>
                                <th>Máscara</th>
                                <th>Cód. Almox. Atual</th>
                                <th>Almoxarifado Atual</th>
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
                        <label class="form-label fw-semibold">Cód. Almoxarifado Destino</label>
                        <input type="text" id="inpCodAlmox" class="form-control"
                               placeholder="Ex: 998" autocomplete="off">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Descrição</label>
                        <input type="text" id="inpDescAlmox" class="form-control bg-light" readonly
                               placeholder="Informe o código acima">
                        <input type="hidden" id="inpAlmoxDestId">
                    </div>
                    <div class="col-md-4">
                        <button id="btnTrocar" class="btn btn-success w-100">
                            <i class="bi bi-arrow-left-right me-1"></i> Trocar Almoxarifado
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resultado -->
    <div id="secaoResultado" class="d-none">
        <div class="card shadow-sm">
            <div class="card-body" id="corpoResultado"></div>
        </div>
    </div>

</div>

<script src="<?= $base ?>src/js/troca-almox-pedido.js?v=<?= time() ?>"></script>
<?= $render('footer') ?>
