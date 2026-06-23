<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
$acessoPcp = $is_admin || in_array('pcp', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoPcp) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Resumo do Lote',
    'showNavbar' => true,
    'pageActive' => 'pcp-resumo-lote',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.pcp-form-card {
    background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);
    padding:20px 24px;margin-bottom:16px;
}
#printArea { display:none; }
#printArea.visible { display:block; }

.resumo-header-card {
    background:#1f3864;color:#fff;border-radius:8px;
    padding:12px 20px;margin-bottom:14px;display:flex;gap:32px;align-items:center;
}
.resumo-header-card .rh-item { display:flex;flex-direction:column; }
.resumo-header-card .rh-label { font-size:10px;opacity:.75;text-transform:uppercase;letter-spacing:.5px; }
.resumo-header-card .rh-value { font-size:18px;font-weight:bold; }

.resumo-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
    gap:12px;
}
.resumo-card {
    border:1px solid #dee2e6;border-radius:8px;overflow:hidden;
}
.resumo-card-header {
    background:#002060;color:#fff;
    display:flex;justify-content:space-between;align-items:center;
    padding:6px 12px;font-weight:bold;font-size:11pt;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;
}
.resumo-card-header .rc-total {
    background:rgba(255,255,255,.2);border-radius:4px;
    padding:1px 8px;font-size:12pt;min-width:50px;text-align:right;
}
.resumo-card-body { padding:0; }
.resumo-row {
    display:flex;justify-content:space-between;align-items:center;
    padding:4px 12px;border-bottom:1px solid #f0f0f0;font-size:9pt;
}
.resumo-row:last-child { border-bottom:none; }
.resumo-row.row-bold { font-weight:bold;background:#f8f9fa; }
.resumo-row .rr-label { color:#333; }
.resumo-row .rr-value { font-weight:600;color:#1f3864;min-width:60px;text-align:right; }
.resumo-row.row-sub {
    padding-left:24px;font-size:8.5pt;color:#555;background:#fcfcfc;
}
.resumo-row.row-sub .rr-value { color:#555;font-weight:normal; }
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:10px;margin:0;">

    <div class="pcp-form-card">
        <h4 class="mb-3"><i class="bi bi-clipboard-data"></i> Resumo do Lote</h4>
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold">Nº do Lote</label>
                <input type="number" id="inputLote" class="form-control form-control-sm" placeholder="Ex: 470" min="1">
            </div>
            <div class="col-auto">
                <button id="btnGerar" class="btn btn-sm btn-primary">
                    <i class="bi bi-search"></i> Gerar
                </button>
                <button id="btnImprimir" class="btn btn-sm btn-success ms-2" style="display:none;">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
            <div class="col-auto" id="statusMsg"></div>
        </div>
    </div>

    <div id="printArea"></div>

</div>

<script src="src/js/resumo-lote.js?v=<?= @filemtime(dirname(__DIR__, 4) . '/public/src/js/resumo-lote.js') ?: '1' ?>"></script>
