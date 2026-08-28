<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
$acesso = $is_admin || in_array('manutencao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acesso) { header('Location: ' . $base . 'sem-acesso'); exit; }
?>
<?= $render('header', [
    'pageTitle'  => 'Gestão de Ordens de Manutenção',
    'showNavbar' => true,
    'pageActive' => 'manutencao-gestao-ordens',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
/* ── Layout ── */
.man-wrap { padding: 10px; display: flex; flex-direction: column; gap: 10px; height: calc(100vh - 56px); box-sizing: border-box; }
.man-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; background: #fff; border-radius: 8px; padding: 10px 14px; box-shadow: 0 1px 4px rgba(0,0,0,.08); flex-shrink: 0; }
.man-filters label { font-size: 11px; font-weight: 600; color: #555; text-transform: uppercase; letter-spacing: .4px; display: block; margin-bottom: 3px; }
.man-filters input, .man-filters select { font-size: 13px; }
.man-panels { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; flex: 1; min-height: 0; }

/* ── Panel ── */
.man-panel { display: flex; flex-direction: column; background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.08); overflow: hidden; }
.man-panel-header { background: #1565c0; color: #fff; padding: 8px 12px; font-size: 13px; font-weight: 700; text-align: center; text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; }
.man-panel-count { font-size: 10px; font-weight: 400; opacity: .85; margin-top: 1px; }
.man-panel-body { flex: 1; overflow-y: auto; min-height: 0; }

/* ── Table ── */
.man-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.man-table thead th { background: #1565c0; color: #fff; padding: 5px 8px; font-size: 11px; font-weight: 600; position: sticky; top: 0; z-index: 1; white-space: nowrap; }
.man-table tbody tr { cursor: pointer; border-bottom: 1px solid #f0f0f0; }
.man-table tbody tr:hover { background: #e3f2fd; }
.man-table tbody tr.selected { background: #bbdefb; }
.man-table td { padding: 4px 8px; vertical-align: middle; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; }
.man-table td.c { text-align: center; }
.man-empty { text-align: center; color: #aaa; font-size: 12px; padding: 20px 8px; }

/* ── Priority badge ── */
.prio { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 11px; font-weight: 700; color: #fff; }
.prio-0 { background: #e53935; }
.prio-1 { background: #f9a825; color: #222; }
.prio-2 { background: #ef6c00; }
.prio-3 { background: #388e3c; }
.prio-9 { background: #212121; }

/* ── Detail modal ── */
.man-det-table { font-size: 12px; }
.man-det-table thead th { background: #1565c0; color: #fff; padding: 5px 8px; white-space: nowrap; }
.man-det-table tbody td { padding: 4px 8px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; }
.man-det-table tbody tr:hover { background: #f5f5f5; }
.man-det-table tbody tr.row-selected { background: #bbdefb; }
.ok-badge { color: #2e7d32; font-weight: 600; }
.prob-icon { color: #e65100; cursor: help; }

/* ── Action bar ── */
.man-actions { display: flex; gap: 6px; flex-wrap: wrap; padding: 8px 10px; background: #f5f5f5; border-top: 1px solid #ddd; }
</style>

<div class="man-wrap">

    <!-- Filtros -->
    <div class="man-filters">
        <div>
            <label>Empresa</label>
            <select id="selEmpresa" class="form-select form-select-sm" style="min-width:200px"></select>
        </div>
        <div>
            <label>Período inicial</label>
            <input type="date" id="dataIni" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
        </div>
        <div>
            <label>Período final</label>
            <input type="date" id="dataFim" class="form-control form-control-sm" value="<?= date('Y-m-t') ?>">
        </div>
        <div>
            <label>&nbsp;</label>
            <button class="btn btn-sm btn-primary" onclick="carregarTodos()">
                <i class="bi bi-search"></i> Filtrar
            </button>
            <button class="btn btn-sm btn-outline-secondary ms-1" onclick="carregarTodos()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- Painéis -->
    <div class="man-panels">

        <!-- ABERTA -->
        <div class="man-panel">
            <div class="man-panel-header">
                ABERTA
                <div class="man-panel-count" id="cntAberta">—</div>
            </div>
            <div class="man-panel-body">
                <table class="man-table">
                    <thead><tr>
                        <th>Recurso</th>
                        <th class="c">Ordem</th>
                        <th class="c">Prior.</th>
                        <th class="c">Data</th>
                    </tr></thead>
                    <tbody id="tbAberta"><tr><td colspan="4" class="man-empty">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- EM ATENDIMENTO -->
        <div class="man-panel">
            <div class="man-panel-header">
                EM ATENDIMENTO
                <div class="man-panel-count" id="cntAtend">—</div>
            </div>
            <div class="man-panel-body">
                <table class="man-table">
                    <thead><tr>
                        <th>Recurso</th>
                        <th class="c">Ordem</th>
                        <th class="c">Prior.</th>
                    </tr></thead>
                    <tbody id="tbAtend"><tr><td colspan="3" class="man-empty">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- PREVENTIVAS LIBERADAS -->
        <div class="man-panel">
            <div class="man-panel-header">
                PREVENTIVAS LIBERADAS
                <div class="man-panel-count" id="cntLib">—</div>
            </div>
            <div class="man-panel-body">
                <table class="man-table">
                    <thead><tr>
                        <th>Recurso</th>
                        <th class="c">Data Prevista</th>
                    </tr></thead>
                    <tbody id="tbLib"><tr><td colspan="2" class="man-empty">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- PREVENTIVAS PROGRAMADAS -->
        <div class="man-panel">
            <div class="man-panel-header">
                PREVENTIVAS PROGRAMADAS
                <div class="man-panel-count" id="cntProg">—</div>
            </div>
            <div class="man-panel-body">
                <table class="man-table">
                    <thead><tr>
                        <th class="c" style="width:70px">Ordem</th>
                        <th>Recurso</th>
                        <th class="c">Data Prev.</th>
                    </tr></thead>
                    <tbody id="tbProg"><tr><td colspan="3" class="man-empty">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal Detalhe -->
<div class="modal fade" id="modalDetalhe" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title mb-0" id="modalDetalheTitulo">Detalhes</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="man-det-table w-100" id="tblDetalhe">
                        <thead id="tblDetalheHead"></thead>
                        <tbody id="tblDetalheBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="man-actions" id="modalAcoes" style="display:none">
                <button class="btn btn-sm btn-primary"   id="btnAtender"   onclick="acaoAtender()">
                    <i class="bi bi-play-circle"></i> Atender
                </button>
                <button class="btn btn-sm btn-success"   id="btnOk"        onclick="abrirModalOk()">
                    <i class="bi bi-check-circle"></i> OK
                </button>
                <button class="btn btn-sm btn-warning"   id="btnDesOk"     onclick="acaoDesOk()">
                    <i class="bi bi-x-circle"></i> Desmarcar OK
                </button>
                <button class="btn btn-sm btn-secondary" id="btnFechar"    onclick="abrirModalFechar()">
                    <i class="bi bi-lock"></i> Fechar
                </button>
                <button class="btn btn-sm btn-danger"    id="btnExcluir"   onclick="acaoExcluir()">
                    <i class="bi bi-trash"></i> Excluir
                </button>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal OK (selecionar funcionário) -->
<div class="modal fade" id="modalOk" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-check-circle"></i> Registrar OK</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-semibold">Funcionário (Mecânico)</label>
                <select id="selFuncionario" class="form-select form-select-sm"></select>
            </div>
            <div class="modal-footer py-1">
                <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-sm btn-success" onclick="acaoOk()">
                    <i class="bi bi-check-lg"></i> Confirmar OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Fechar -->
<div class="modal fade" id="modalFechar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-lock"></i> Fechar Ordem</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-semibold">Observação</label>
                <input type="text" id="inpObs" class="form-control form-control-sm" maxlength="100" placeholder="Opcional">
            </div>
            <div class="modal-footer py-1">
                <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-sm btn-dark" onclick="acaoFechar()">
                    <i class="bi bi-lock"></i> Fechar Ordem
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $base ?>src/js/manutencao-gestao-ordens.js?v=<?= @filemtime(dirname(__DIR__, 4) . '/public/src/js/manutencao-gestao-ordens.js') ?: '1' ?>"></script>
<script>
const BASE       = '<?= $base ?>';
const EMPR_SESS  = <?= (int) ($_SESSION['empresa']['id'] ?? 0) ?>;
</script>
<?= $render('footer') ?>
