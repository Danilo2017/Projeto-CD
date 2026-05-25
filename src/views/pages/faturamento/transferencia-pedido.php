<?php
/**
 * @var bool     $is_admin
 * @var array    $rotas_permitidas
 * @var string   $base
 * @var callable $render
 * @var array    $empresas
 */
$acesso = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acesso) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Transferência de Pedidos',
    'showNavbar' => true,
    'pageActive' => 'pedidos-transferencia',
    'customCSS'  => [],
]) ?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Transferência de Pedidos</h4>
    </div>

    <!-- Busca -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-semibold">
            <i class="bi bi-search me-1"></i> Buscar Pedidos
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Filial Origem</label>
                    <select id="selOrigem" class="form-select">
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
                        Nº dos Pedidos
                        <small class="text-muted fw-normal">(opcional — separados por vírgula ou linha)</small>
                    </label>
                    <textarea id="txtNumeros" class="form-control" rows="2"
                              placeholder="Deixe em branco para trazer todos. Ex: 1863, 1867, 1825"></textarea>
                </div>
                <div class="col-md-4">
                    <button id="btnBuscar" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Buscar Pedidos
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pedidos encontrados -->
    <div id="secaoPedidos" class="d-none">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-check me-1"></i> Pedidos Encontrados</span>
                <span id="totalPedidos" class="badge bg-secondary">0</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">
                                    <input type="checkbox" id="chkTodos" class="form-check-input" checked>
                                </th>
                                <th>Nº Pedido</th>
                                <th>Cód. Cliente</th>
                                <th>Cliente</th>
                                <th>Cód. NF</th>
                                <th>Tipo NF</th>
                                <th>Cód. Divisão</th>
                                <th>Divisão</th>
                                <th class="text-end">Valor Líq.</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaPedidos"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Configuração da transferência -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-warning text-dark fw-semibold">
                <i class="bi bi-gear me-1"></i> Configurar Transferência
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filial Destino</label>
                        <select id="selDestino" class="form-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($empresas as $emp): ?>
                                <option value="<?= intval($emp['ID']) ?>">
                                    FL <?= htmlspecialchars($emp['CODIGO']) ?> — <?= htmlspecialchars($emp['RAZAO_SOCIAL']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Tipo de NF <small class="text-muted">(COD_TP_NF)</small></label>
                        <input type="number" id="inpTipoNf" class="form-control" value="2041" min="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Tabela de Preço <small class="text-muted">(COD_PREVEN)</small></label>
                        <input type="number" id="inpPreven" class="form-control" value="1003" min="1">
                    </div>
                    <div class="col-md-3">
                        <button id="btnTransferir" class="btn btn-success w-100">
                            <i class="bi bi-arrow-left-right me-1"></i> Transferir Pedidos
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
                <span><i class="bi bi-check2-circle me-1"></i> Resultado da Transferência</span>
                <span id="resumoResultado" class="fw-normal text-muted small"></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pedido Original</th>
                                <th>Status</th>
                                <th>Pedido Destino</th>
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

<script src="src/js/transferencia-pedido.js?v=<?= time() ?>"></script>

<?= $render('footer') ?>
