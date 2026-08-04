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

    <input type="hidden" id="hEmprIdSessao" value="<?= (int)$emprId ?>">

    <!-- ── Buscar Item ─────────────────────────────────────── -->
    <div class="dashboard-filters mb-3">
        <h5 class="mb-3 fw-semibold"><i class="bi bi-search me-1 text-primary"></i> Buscar Item</h5>
        <div class="filter-row">
            <div class="filter-group" style="max-width:200px;">
                <label for="selFilial">Empresa</label>
                <select id="selFilial" class="form-select">
                    <option value="">Carregando...</option>
                </select>
            </div>
            <div class="filter-group" style="max-width:200px;">
                <label for="inpCodItem">Código do Item</label>
                <input type="number" id="inpCodItem" class="form-control" placeholder="Ex: 600012" min="1">
            </div>
            <div class="filter-group" style="max-width:140px;">
                <label>&nbsp;</label>
                <button id="btnBuscar" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
            <div class="filter-group d-flex align-items-end">
                <span id="msgBusca"></span>
            </div>
        </div>
    </div>

    <!-- ── Resultados da busca ────────────────────────────── -->
    <div id="areaBusca" style="display:none;" class="mb-3">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-2 d-flex align-items-center gap-2 border-bottom">
                <span class="fw-semibold">Itens encontrados:</span>
                <span id="badgeBusca" class="badge bg-secondary">0</span>
                <div class="ms-auto d-flex gap-2">
                    <button id="btnSelecionarTodos" class="btn btn-sm btn-outline-secondary">
                        Selecionar Todos
                    </button>
                    <button id="btnCadastrar" class="btn btn-sm btn-success" disabled>
                        <i class="bi bi-slash-circle"></i> Inativar Selecionados
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
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

    <!-- ── Histórico de Inativações ───────────────────────── -->
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white fw-semibold py-2 d-flex align-items-center gap-2">
            <i class="bi bi-list-check"></i> Histórico de Inativações
            <span id="badgeFila" class="badge bg-white text-dark ms-1">0</span>
            <button id="btnRecarregar" class="btn btn-sm btn-outline-light ms-auto">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="card-body p-0">
            <div class="px-3 pt-2 pb-1">
                <div class="input-group input-group-sm" style="max-width:320px;">
                    <span class="input-group-text"><i class="bi bi-funnel"></i></span>
                    <input type="text" id="inpFiltroHistorico" class="form-control" placeholder="Filtrar por código, descrição ou máscara...">
                    <button class="btn btn-outline-secondary" id="btnLimparFiltro" title="Limpar filtro">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
            <div id="msgFila" class="px-3 pt-1"></div>
            <div style="overflow-x:auto;">
                <table class="table table-sm table-bordered table-hover mb-0" id="tabelaFila">
                    <thead class="table-secondary">
                        <tr>
                            <th>Filial</th>
                            <th>Cód. Item</th>
                            <th>Máscara ID</th>
                            <th>Descrição</th>
                            <th>Máscara</th>
                            <th>Inativado em</th>
                            <th>Status na Tabela</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody id="tbodyFila">
                        <tr><td colspan="8" class="text-center text-muted py-3">Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ── Modal Pedidos Pendentes ─────────────────── -->
<div class="modal fade" id="modalPedidos" tabindex="-1" aria-labelledby="modalPedidosLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="modalPedidosLabel">
                    <i class="bi bi-cart-check me-1 text-primary"></i>
                    Pedidos com Saldo — <span id="modalPedidosTitulo" class="text-muted"></span>
                </h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="modalPedidosLoading" class="text-center py-4">
                    <span class="spinner-border spinner-border-sm text-primary"></span> Carregando...
                </div>
                <div id="modalPedidosContent" style="display:none;">
                    <div style="overflow-x:auto;">
                        <table class="table table-sm table-bordered table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Filial</th>
                                    <th>Dt. Geração</th>
                                    <th>Nº Pedido</th>
                                    <th>Sit. PDV</th>
                                    <th>Sit. Fat.</th>
                                    <th>Sit. Fat. Com.</th>
                                    <th>Sit. Fat. Fin.</th>
                                    <th>Sit. PDV Com.</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyPedidos"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <span class="text-muted small me-auto">
                    <strong id="modalPedidosCount">0</strong> pedido(s) com saldo
                </span>
                <button type="button" id="btnExcelPedidos" class="btn btn-sm btn-success" style="display:none;">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $base ?>src/js/inativacao-preco.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'].'/src/js/inativacao-preco.js') ?: date('YmdH') ?>"></script>
<?= $render('footer') ?>
