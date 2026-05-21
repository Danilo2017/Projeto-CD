<?php
/**
 * @var bool     $is_admin
 * @var array    $rotas_permitidas
 * @var string   $base
 * @var callable $render
 */
$acessoFaturamento = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoFaturamento) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Programação de Pedidos',
    'showNavbar' => true,
    'pageActive' => 'faturamento-programacao',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin: 0; padding: 0;'
]) ?>

<style>
    .prog-container { padding: 12px; }
    .pivot-table { font-size: 13px; width: 100%; border-collapse: collapse; }
    .pivot-table th, .pivot-table td { padding: 6px 10px; border: 1px solid #dee2e6; white-space: nowrap; }
    .pivot-table thead th { background: #343a40; color: #fff; text-align: center; position: sticky; top: 0; z-index: 5; }
    .pivot-table thead th.col-agenda { background: #495057; }
    .pivot-table td.val { text-align: right; }
    .pivot-table td.pct { text-align: right; color: #6c757d; font-size: 12px; }
    .pivot-table tr.row-total { background: #f1f3f5; font-weight: 700; }
    .pivot-table tr.row-group { background: #d0ebff; font-weight: 700; color: #1864ab; }
    .pivot-table tr.row-group-total { background: #e7f5ff; font-weight: 600; color: #1864ab; }
    .pivot-table tr:hover:not(.row-total):not(.row-group):not(.row-group-total) { background: #f8f9fa; }
    .pivot-wrap { overflow-x: auto; max-height: 70vh; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; }
    .loading-overlay { text-align: center; padding: 60px 20px; color: #6c757d; }
    #conteudoOcupacao .pivot-wrap { max-height: none; overflow-y: visible; }
</style>

<div class="prog-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-calendar2-range"></i> Programação de Pedidos</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" onclick="carregarDados()">
                <i class="bi bi-arrow-clockwise"></i> Atualizar
            </button>
            <button class="btn btn-sm btn-outline-success" id="btnExportarCSV" onclick="exportarCSV()" disabled>
                <i class="bi bi-download"></i> Exportar Resumo
            </button>
            <button class="btn btn-sm btn-success" id="btnExportarGeral" onclick="exportarGeral()" disabled>
                <i class="bi bi-file-earmark-spreadsheet"></i> Exportar Geral
            </button>
        </div>
    </div>

    <!-- Resumo rápido -->
    <div class="row g-2 mb-3" id="resumoCards" style="display:none!important;">
        <div class="col-auto">
            <div class="card card-body py-2 px-3 text-center" style="min-width:130px;">
                <div class="fw-bold fs-5" id="cardTotal">-</div>
                <div class="text-muted" style="font-size:12px;">Total Carteira</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card card-body py-2 px-3 text-center" style="min-width:130px;">
                <div class="fw-bold fs-5" id="cardSemAgenda">-</div>
                <div class="text-muted" style="font-size:12px;">Sem Programação</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card card-body py-2 px-3 text-center" style="min-width:130px;">
                <div class="fw-bold fs-5" id="cardMesAtual">-</div>
                <div class="text-muted" style="font-size:12px;" id="labelMesAtual">Mês Atual</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card card-body py-2 px-3 text-center" style="min-width:130px;">
                <div class="fw-bold fs-5" id="cardProxMes">-</div>
                <div class="text-muted" style="font-size:12px;" id="labelProxMes">Próx. Mês</div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-0" id="pivotTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabEmpresa">
                <i class="bi bi-building"></i> Por Empresa
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTipo">
                <i class="bi bi-tags"></i> Por Tipo
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="tabOcupacaoBtn" data-bs-toggle="tab" data-bs-target="#tabOcupacao">
                <i class="bi bi-speedometer2"></i> Taxa de Ocupação
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom p-0">
        <div class="tab-pane fade show active" id="tabEmpresa">
            <div id="conteudoEmpresa">
                <div class="loading-overlay">
                    <span class="spinner-border spinner-border-sm me-2"></span> Carregando dados...
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="tabTipo">
            <div id="conteudoTipo">
                <div class="loading-overlay text-muted">Aguardando carregamento...</div>
            </div>
        </div>
        <div class="tab-pane fade" id="tabOcupacao">
            <div id="conteudoOcupacao">
                <div class="loading-overlay text-muted">Clique na aba para carregar...</div>
            </div>
        </div>
    </div>
</div>

<?= $render('footer', [
    'customJS' => ['src/js/faturamento-programacao.js']
]) ?>
