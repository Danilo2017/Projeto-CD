<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
/** @var array    $empresas */
$acessoPcp = $is_admin || in_array('pcp', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoPcp) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Relatório de Produção',
    'showNavbar' => true,
    'pageActive' => 'pcp-relatorio-producao',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
/* ── Tela ───────────────────────────────────────── */
.pcp-form-card {
    background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);
    padding:20px 24px;margin-bottom:16px;
}

/* ── Área de impressão ──────────────────────────── */
#printArea { display:none; }

@media print {
    body * { visibility: hidden !important; }
    #printArea, #printArea * { visibility: visible !important; }
    #printArea { display: block !important; position: fixed; top:0; left:0; width:100%; }
    .pcp-section { page-break-after: always; }
    .pcp-section:last-child { page-break-after: avoid; }
}

/* ── Layout do relatório (ecrã e impressão) ─────── */
.pcp-section {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    padding: 10px 14px;
    background: #fff;
}

/* Cabeçalho do relatório */
.pcp-report-header {
    display: flex;
    align-items: center;
    border: 1px solid #000;
    margin-bottom: 0;
}
.pcp-report-header .col-logo {
    width: 110px;
    padding: 4px 8px;
    border-right: 1px solid #000;
    flex-shrink: 0;
}
.pcp-report-header .col-logo img { width: 90px; }
.pcp-report-header .col-title {
    flex: 1;
    text-align: center;
    font-weight: bold;
    font-size: 11pt;
    padding: 6px;
    border-right: 1px solid #000;
}
.pcp-report-header .col-right {
    width: 170px;
    font-size: 8pt;
    padding: 4px 8px;
    flex-shrink: 0;
}
.pcp-report-header .col-right div { margin-bottom: 2px; }

/* Revisão */
.pcp-revisao {
    border: 1px solid #000;
    border-top: none;
    padding: 2px 8px;
    font-size: 8pt;
    text-align: right;
    margin-bottom: 0;
}

/* Título da seção */
.pcp-section-title {
    background: #002060;
    color: #fff;
    text-align: center;
    font-weight: bold;
    font-size: 12pt;
    padding: 5px;
    margin-top: 6px;
    margin-bottom: 2px;
}

/* Tabela de dados */
.pcp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8pt;
}
.pcp-table th {
    background: #1f3864;
    color: #fff;
    border: 1px solid #999;
    padding: 3px 4px;
    text-align: center;
    font-weight: bold;
    white-space: nowrap;
}
.pcp-table td {
    border: 1px solid #ccc;
    padding: 2px 4px;
    white-space: nowrap;
}
.pcp-table tr:nth-child(even) td { background: #f2f2f2; }
.pcp-table tr.subtotal-row td {
    background: #dce6f1;
    font-weight: bold;
    text-align: right;
}
.pcp-table tr.total-row td {
    background: #1f3864;
    color: #fff;
    font-weight: bold;
    text-align: right;
}

/* Histórico de revisões */
.pcp-historico {
    margin-top: 10px;
    border: 1px solid #999;
    font-size: 8pt;
}
.pcp-historico table {
    width: 100%;
    border-collapse: collapse;
}
.pcp-historico th {
    background: #d9d9d9;
    border: 1px solid #999;
    padding: 2px 6px;
    text-align: center;
    font-weight: bold;
}
.pcp-historico td {
    border: 1px solid #ccc;
    padding: 2px 6px;
}

/* Prévia na tela */
#printArea.visible {
    display: block;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px;
    background: #f8f9fa;
    overflow: auto;
}
.pcp-section + .pcp-section {
    border-top: 3px dashed #ccc;
    margin-top: 16px;
    padding-top: 16px;
}
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:10px;margin:0;">

    <div class="pcp-form-card">
        <h4 class="mb-3"><i class="bi bi-file-earmark-bar-graph"></i> Relatório de Produção — PCP</h4>
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Filial</label>
                <select id="selEmpresa" class="form-select form-select-sm">
                    <option value="">Selecione a filial...</option>
                    <?php foreach ($empresas as $e): ?>
                    <option value="<?= (int)($e['ID'] ?? 0) ?>">
                        <?= htmlspecialchars(($e['COD_EMP'] ?? '') . ' — ' . ($e['NOME_FANTASIA'] ?? $e['RAZAO_SOCIAL'] ?? '')) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
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

<script src="src/js/relatorio-producao.js"></script>
