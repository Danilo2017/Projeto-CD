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
    'pageTitle'  => 'Conjugado — Sequência de Produção',
    'showNavbar' => true,
    'pageActive' => 'pcp-relatorio-conjugado',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.pcp-form-card {
    background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);
    padding:20px 24px;margin-bottom:16px;
}
#printArea { display:none; }
.tap-section { font-family:Arial,sans-serif;font-size:8.5pt;padding:10px 14px;background:#fff; }
.tap-header-wrap { border:1px solid #000;margin-bottom:0; }
.tap-header-row1 { display:flex;align-items:stretch; }
.tap-header-row1 .col-logo { width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0;display:flex;align-items:center; }
.tap-header-row1 .col-logo img { width:90px; }
.tap-header-row1 .col-title { flex:1;text-align:center;font-weight:bold;font-size:11pt;padding:6px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center; }
.tap-header-row1 .col-right { width:170px;font-size:8pt;padding:4px 8px;flex-shrink:0; }
.tap-header-row2 { display:flex;align-items:stretch;border-top:1px solid #000; }
.tap-header-row2 .col-logo2 { width:110px;border-right:1px solid #000;flex-shrink:0; }
.tap-header-row2 .col-code { flex:1;text-align:center;font-size:8pt;padding:2px 6px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center; }
.tap-header-row2 .col-rev { width:170px;font-size:8pt;padding:2px 8px;flex-shrink:0; }
.tap-section-title { background:#002060;color:#fff;text-align:center;font-weight:bold;font-size:12pt;padding:5px;margin-top:6px;margin-bottom:2px; }
.tap-table { width:100%;border-collapse:collapse;font-size:7.5pt; }
.tap-table th { background:#1f3864;color:#fff;border:1px solid #999;padding:2px 4px;text-align:center;font-weight:bold;white-space:nowrap; }
.tap-table td { border:1px solid #ccc;padding:1px 4px;white-space:nowrap; }
.tap-table td.td-wrap { white-space:normal;word-break:break-word; }
.tap-table tr.subtotal-row td { background:#d9d9d9;font-weight:bold;border-top:2px solid #999; }
.tap-table tr.total-row td { background:#1f3864;color:#fff;font-weight:bold; }
.tap-historico { margin-top:10px;border:1px solid #999;font-size:8pt; }
.tap-historico table { width:100%;border-collapse:collapse; }
.tap-historico th { background:#d9d9d9;border:1px solid #999;padding:2px 6px;text-align:center;font-weight:bold; }
.tap-historico td { border:1px solid #ccc;padding:2px 6px; }
#printArea.visible { display:block;border:1px solid #dee2e6;border-radius:8px;padding:12px;background:#f8f9fa;overflow:auto; }
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:10px;margin:0;">

    <div class="pcp-form-card">
        <h4 class="mb-3"><i class="bi bi-cpu"></i> Conjugado — Sequência de Produção</h4>
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold">Nº do Lote</label>
                <input type="number" id="inputLote" class="form-control form-control-sm" placeholder="Ex: 465" min="1">
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

<script src="src/js/relatorio-conjugado.js?v=<?= @filemtime(dirname(__DIR__, 4) . '/public/src/js/relatorio-conjugado.js') ?: '1' ?>"></script>
