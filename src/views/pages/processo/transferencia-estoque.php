<?php
/** @var array  $almoxs */
$acessoProcesso = $is_admin || in_array('processo', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoProcesso) { header('Location: ' . $base . 'sem-acesso'); exit; }
?>
<?= $render('header', [
    'pageTitle'  => 'Transferência de Estoque',
    'showNavbar' => true,
    'pageActive' => 'processo-transferencia-estoque',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:10px;margin:0;">

    <!-- Cabeçalho -->
    <div class="dashboard-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-arrow-left-right"></i> Transferência de Estoque</h4>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold mb-1">Almox. Origem</label>
                    <select id="selAlmoxOrig" class="form-select form-select-sm">
                        <option value="">— Selecione —</option>
                        <?php foreach ($almoxs as $a): ?>
                        <option value="<?= htmlspecialchars($a['COD_ALMOX']) ?>">
                            <?= htmlspecialchars($a['COD_ALMOX']) ?> — <?= htmlspecialchars($a['DESCRICAO']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold mb-1">Almox. Destino</label>
                    <select id="selAlmoxDest" class="form-select form-select-sm">
                        <option value="">— Selecione —</option>
                        <?php foreach ($almoxs as $a): ?>
                        <option value="<?= htmlspecialchars($a['COD_ALMOX']) ?>">
                            <?= htmlspecialchars($a['COD_ALMOX']) ?> — <?= htmlspecialchars($a['DESCRICAO']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold mb-1">Buscar por item (opcional)</label>
                    <input type="text" id="inputCodItem" class="form-control form-control-sm" placeholder="Cód. item...">
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-sm" onclick="buscarSaldo()">
                        <i class="bi bi-search"></i> Buscar Saldo
                    </button>
                </div>
                <div class="col-auto ms-auto d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm" onclick="downloadTemplate()">
                        <i class="bi bi-download"></i> Template CSV
                    </button>
                    <label class="btn btn-outline-primary btn-sm mb-0" title="Importar CSV">
                        <i class="bi bi-upload"></i> Importar CSV
                        <input type="file" id="inputCsv" accept=".csv" class="d-none" onchange="importarCsv(this)">
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <div>
                <strong><i class="bi bi-table"></i> Itens para Transferência</strong>
                <span id="totalItens" class="badge bg-secondary ms-2">0</span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="limparTabela()">
                    <i class="bi bi-trash"></i> Limpar
                </button>
                <button id="btnTransferir" class="btn btn-sm btn-success" onclick="executarTransferencia()" disabled>
                    <i class="bi bi-arrow-left-right"></i> Transferir Selecionados (<span id="cntSel">0</span>)
                </button>
            </div>
        </div>
        <div class="card-body p-0" style="overflow-x:auto;">
            <table class="table table-sm table-striped table-hover align-middle mb-0" style="min-width:900px;">
                <thead class="table-dark">
                    <tr>
                        <th width="32"><input type="checkbox" id="checkTodos" onchange="toggleTodos()"></th>
                        <th>CÓD. ITEM</th>
                        <th>ID MÁSCARA</th>
                        <th>DESCRIÇÃO</th>
                        <th>MÁSCARA</th>
                        <th class="text-center">UM</th>
                        <th class="text-center">ORIG.</th>
                        <th class="text-center">DEST.</th>
                        <th class="text-center">SALDO</th>
                        <th class="text-center" style="width:110px">QTDE TRANSF.</th>
                        <th class="text-center">STATUS</th>
                    </tr>
                </thead>
                <tbody id="tabelaBody">
                    <tr><td colspan="11" class="text-center text-muted py-5">
                        <i class="bi bi-arrow-left-right me-2"></i>
                        Selecione os almoxarifados e clique em <strong>Buscar Saldo</strong> ou <strong>Importe um CSV</strong>.
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="toastTransf" class="toast align-items-center text-white border-0 bg-danger" role="alert">
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="<?= $base ?>src/js/transferencia-estoque.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'].'/src/js/transferencia-estoque.js') ?: date('YmdH') ?>"></script>
<?= $render('footer') ?>
