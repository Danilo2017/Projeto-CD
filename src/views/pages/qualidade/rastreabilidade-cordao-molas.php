<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
$acessoQualidade = $is_admin || in_array('qualidade', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoQualidade) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Rastreabilidade — Cordão de Molas',
    'showNavbar' => true,
    'pageActive' => 'qualidade-rastreabilidade-cordao-molas',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.pcp-form-card {
    background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);
    padding:20px 24px;margin-bottom:16px;
}
#printArea { display:none; }
.rastr-section { font-family:Arial,sans-serif;font-size:9pt;padding:10px 14px;background:#fff; }
.rastr-header { display:flex;align-items:stretch;border:1px solid #000; }
.rastr-header .col-logo { width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0;display:flex;align-items:center; }
.rastr-header .col-logo img { width:90px; }
.rastr-header .col-title { flex:1;text-align:center;font-weight:bold;font-size:10.5pt;padding:6px 8px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center; }
.rastr-header .col-setor { width:130px;font-size:8pt;padding:4px 8px;flex-shrink:0;display:flex;flex-direction:column;justify-content:center; }
.rastr-header .col-setor .setor-label { font-size:7pt;color:#555; }
.rastr-header .col-setor .setor-value { font-weight:bold;font-size:10pt; }
.rastr-subheader { display:flex;border:1px solid #000;border-top:none;font-size:8pt; }
.rastr-subheader .sub-code { padding:3px 8px;border-right:1px solid #000;flex:0 0 auto; }
.rastr-subheader .sub-rev  { padding:3px 8px;border-right:1px solid #000;flex:0 0 auto; }
.rastr-subheader .sub-data { padding:3px 8px; }
.rastr-table { width:100%;border-collapse:collapse;font-size:8pt;margin-top:6px; }
.rastr-table th { background:#1f3864;color:#fff;border:1px solid #999;padding:4px 6px;text-align:center;font-weight:bold;white-space:nowrap; }
.rastr-table td { border:1px solid #ccc;padding:3px 6px;white-space:nowrap; }
.rastr-table tr:nth-child(even) td { background:#f2f2f2; }
.rastr-historico { margin-top:12px;border:1px solid #999;font-size:8pt; }
.rastr-historico table { width:100%;border-collapse:collapse; }
.rastr-historico th { background:#d9d9d9;border:1px solid #999;padding:2px 6px;text-align:center;font-weight:bold; }
.rastr-historico td { border:1px solid #ccc;padding:2px 6px; }
#printArea.visible { display:block;border:1px solid #dee2e6;border-radius:8px;padding:12px;background:#f8f9fa;overflow:auto; }
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:10px;margin:0;">

    <div class="pcp-form-card">
        <h4 class="mb-3"><i class="bi bi-gear-wide-connected"></i> Rastreabilidade — Cordão de Molas</h4>
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold">Nº do Lote</label>
                <input type="number" id="inputLote" class="form-control form-control-sm" placeholder="Ex: 5" min="1">
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

<script src="src/js/qualidade-rastreabilidade-cordao-molas.js?v=<?= @filemtime(dirname(__DIR__, 4) . '/public/src/js/qualidade-rastreabilidade-cordao-molas.js') ?: '1' ?>"></script>
