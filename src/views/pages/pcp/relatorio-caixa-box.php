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
    'pageTitle'  => 'Caixa Box — Sequência de Produção',
    'showNavbar' => true,
    'pageActive' => 'pcp-relatorio-caixa-box',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.pcp-form-card {
    background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);
    padding:20px 24px;margin-bottom:16px;
}
#printArea { display:none; }
.cb-section { font-family:Arial,sans-serif;font-size:8.5pt;padding:10px 14px;background:#fff; }
.cb-header-wrap { border:1px solid #000;margin-bottom:0; }
.cb-header-row1 { display:flex;align-items:stretch; }
.cb-header-row1 .col-logo { width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0;display:flex;align-items:center; }
.cb-header-row1 .col-logo img { width:90px; }
.cb-header-row1 .col-title { flex:1;text-align:center;font-weight:bold;font-size:11pt;padding:6px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center; }
.cb-header-row1 .col-right { width:170px;font-size:8pt;padding:4px 8px;flex-shrink:0; }
.cb-header-row2 { display:flex;align-items:stretch;border-top:1px solid #000; }
.cb-header-row2 .col-logo2 { width:110px;border-right:1px solid #000;flex-shrink:0; }
.cb-header-row2 .col-code { flex:1;text-align:center;font-size:8pt;padding:2px 6px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center; }
.cb-header-row2 .col-rev { width:170px;font-size:8pt;padding:2px 8px;flex-shrink:0; }
.cb-section-title { background:#1f3864;color:#fff;text-align:center;font-weight:bold;font-size:13pt;padding:6px;margin-top:6px;margin-bottom:2px; }
.cb-table { width:100%;border-collapse:collapse;font-size:7.5pt; }
.cb-table th { background:#1f3864;color:#fff;border:1px solid #999;padding:2px 4px;text-align:center;font-weight:bold;white-space:nowrap; }
.cb-table td { border:1px solid #ccc;padding:1px 4px;white-space:nowrap; }
.cb-table td.td-wrap { white-space:normal;word-break:break-word; }
.cb-table tr.total-row td { background:#d9d9d9;font-weight:bold;border-top:2px solid #999; }
.cb-historico { margin-top:10px;border:1px solid #999;font-size:8pt; }
.cb-historico table { width:100%;border-collapse:collapse; }
.cb-historico th { background:#d9d9d9;border:1px solid #999;padding:2px 6px;text-align:center;font-weight:bold; }
.cb-historico td { border:1px solid #ccc;padding:2px 6px; }
#printArea.visible { display:block;border:1px solid #dee2e6;border-radius:8px;padding:12px;background:#f8f9fa;overflow:auto; }
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:10px;margin:0;">

    <div class="pcp-form-card">
        <h4 class="mb-3"><i class="bi bi-cpu"></i> Caixa Box — Sequência de Produção</h4>
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

<script src="src/js/relatorio-caixa-box.js?v=<?= @filemtime(dirname(__DIR__, 4) . '/public/src/js/relatorio-caixa-box.js') ?: '1' ?>"></script>
