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
    'pageTitle'  => 'Troca de Almoxarifado',
    'showNavbar' => true,
    'pageActive' => 'processo-troca-almox',
    'customCSS'  => [],
]) ?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Troca de Almoxarifado</h4>
    </div>

    <!-- Busca -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-semibold">
            <i class="bi bi-search me-1"></i> Buscar Ordens
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
                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        Nº das Ordens
                        <small class="text-muted fw-normal">(opcional — separados por vírgula ou linha)</small>
                    </label>
                    <textarea id="txtNumeros" class="form-control" rows="2"
                              placeholder="Deixe em branco para trazer todas. Ex: 1001, 1002, 1003"></textarea>
                </div>
                <div class="col-md-4">
                    <button id="btnBuscar" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Buscar Ordens
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ordens encontradas -->
    <div id="secaoOrdens" class="d-none">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-check me-1"></i> Ordens Encontradas</span>
                <span id="totalOrdens" class="badge bg-secondary">0</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tabelaOrdens" class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">
                                    <input type="checkbox" id="chkTodos" class="form-check-input" checked>
                                </th>
                                <th>Nº Ordem</th>
                                <th>Situação</th>
                                <th>Tipo</th>
                                <th>Cód. Almox.</th>
                                <th>Almoxarifado Atual</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaOrdens"></tbody>
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
                               placeholder="Selecione a empresa e informe o código">
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

    <!-- Resultados -->
    <div id="secaoResultados" class="d-none">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check2-circle me-1"></i> Resultado da Troca</span>
                <span id="resumoResultado" class="fw-normal text-muted small"></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tabelaResultados" class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nº Ordem</th>
                                <th>Status</th>
                                <th>Almox. Destino</th>
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

<script src="src/js/troca-almoxarifado.js?v=<?= time() ?>"></script>

<?= $render('footer') ?>
