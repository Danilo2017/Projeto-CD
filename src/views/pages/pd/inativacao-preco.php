<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
/** @var int      $emprId */
$acessoPd = $is_admin || in_array('pd', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoPd) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'P&D — Inativação de Preço',
    'showNavbar' => true,
    'pageActive' => 'pd-inativacao-preco',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:12px;margin:0;">

    <input type="hidden" id="hEmprId" value="<?= (int)$emprId ?>">

    <!-- ── Buscar Item ─────────────────────────────────────────── -->
    <div class="card mb-3 shadow-sm">
        <div class="card-header bg-primary text-white fw-semibold py-2">
            <i class="bi bi-search"></i> Buscar Item
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold mb-1">Código do Item</label>
                    <input type="number" id="inpCodItem" class="form-control form-control-sm" placeholder="Ex: 600012" min="1">
                </div>
                <div class="col-auto">
                    <button id="btnBuscar" class="btn btn-sm btn-primary">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
                <div class="col-auto" id="msgBusca"></div>
            </div>

            <div id="areaBusca" style="display:none;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="fw-semibold">Itens encontrados:</span>
                    <span id="badgeBusca" class="badge bg-secondary">0</span>
                    <button id="btnSelecionarTodos" class="btn btn-xs btn-outline-secondary btn-sm ms-auto">
                        Selecionar Todos
                    </button>
                    <button id="btnCadastrar" class="btn btn-sm btn-success" disabled>
                        <i class="bi bi-plus-circle"></i> Cadastrar Selecionados
                    </button>
                </div>
                <div style="overflow-x:auto;max-height:320px;overflow-y:auto;">
                    <table class="table table-sm table-bordered table-hover mb-0" id="tabelaResultados">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th style="width:36px"><input type="checkbox" id="chkTodos"></th>
                                <th>Empresa</th>
                                <th>Cód. Item</th>
                                <th>Máscara ID</th>
                                <th>Descrição</th>
                                <th>Máscara</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyResultados"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Itens Cadastrados ───────────────────────────────────── -->
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white fw-semibold py-2 d-flex align-items-center gap-2">
            <i class="bi bi-list-check"></i> Fila de Inativação
            <span id="badgeFila" class="badge bg-white text-dark ms-1">0</span>
            <button id="btnProcessar" class="btn btn-sm btn-warning ms-auto" style="display:none;">
                <i class="bi bi-play-fill"></i> Executar Inativação
            </button>
            <button id="btnRecarregar" class="btn btn-sm btn-outline-light">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="card-body p-0">
            <div id="msgFila" class="px-3 pt-2"></div>
            <div style="overflow-x:auto;">
                <table class="table table-sm table-bordered table-hover mb-0" id="tabelaFila">
                    <thead class="table-secondary">
                        <tr>
                            <th>Cód. Item</th>
                            <th>Descrição</th>
                            <th>Máscara</th>
                            <th>Cadastrado em</th>
                            <th>Status</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody id="tbodyFila">
                        <tr><td colspan="6" class="text-center text-muted py-3">Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="<?= $base ?>src/js/inativacao-preco.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'].'/src/js/inativacao-preco.js') ?: date('YmdH') ?>"></script>
<?= $render('footer') ?>
