<?php
/** @var bool $is_admin @var array $rotas_permitidas @var string $base @var callable $render */
$acesso = $is_admin || in_array('manutencao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acesso) { header('Location: ' . $base . 'sem-acesso'); exit; }
?>
<?= $render('header', [
    'pageTitle'  => 'Dashboard de Ordens de Manutenção',
    'showNavbar' => true,
    'pageActive' => 'manutencao-dashboard',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
/* ---- wrap ---- */
.mdb-wrap     { padding: 10px; box-sizing: border-box; background: #f0f2f5; min-height: calc(100vh - 56px); }

/* ---- filtros ---- */
.mdb-filter   { display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
                background: #fff; border-radius: 8px; padding: 9px 14px;
                box-shadow: 0 1px 4px rgba(0,0,0,.08); margin-bottom: 10px; }
.mdb-filter label { font-size: 12px; font-weight: 600; color: #555; margin: 0; }
.mdb-filter input, .mdb-filter select { font-size: 12px; border: 1px solid #ccc;
    border-radius: 6px; padding: 5px 8px; }

/* ---- KPI cards ---- */
.mdb-kpis     { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
.mdb-kpi      { flex: 1 1 100px; background: #fff; border-radius: 8px;
                box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 10px 12px; }
.mdb-kpi-lbl  { font-size: 10px; color: #888; font-weight: 700; text-transform: uppercase;
                letter-spacing: .4px; }
.mdb-kpi-val  { font-size: 28px; font-weight: 700; line-height: 1.1; margin-top: 2px; }
.kc-total  { color: #1565c0; }
.kc-aten   { color: #2e7d32; }
.kc-aber   { color: #e65100; }
.kc-corr   { color: #c62828; }
.kc-prev   { color: #6a1b9a; }
.kc-prog   { color: #1565c0; }

/* ---- rows / panels ---- */
.mdb-row      { display: flex; gap: 8px; margin-bottom: 8px; align-items: flex-start; }
.mdb-panel    { background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.08);
                display: flex; flex-direction: column; overflow: hidden; }
.mdb-ph       { background: #1565c0; color: #fff; padding: 6px 12px; font-size: 11px;
                font-weight: 700; text-transform: uppercase; letter-spacing: .4px; flex-shrink: 0; }
.mdb-pb       { flex: 1; overflow: auto; }
.mdb-pb.p10   { padding: 10px 12px; }

/* flex widths */
.w-grupo      { flex: 0 0 300px; min-width: 0; }
.w-distrib    { flex: 2 1 280px; min-width: 0; }
.w-geradas    { flex: 1 1 200px; min-width: 0; }
.w-minutos    { flex: 2 1 280px; min-width: 0; }
.w-tipo       { flex: 2 1 260px; min-width: 0; }
.w-counts     { flex: 1 1 180px; min-width: 0; }
.w-func       { flex: 2 1 260px; min-width: 0; }
.w-custos     { flex: 2 1 340px; min-width: 0; }
.w-funchoras  { flex: 3 1 420px; min-width: 0; }

/* ---- tabelas ---- */
.mdb-tbl      { width: 100%; border-collapse: collapse; font-size: 12px; }
.mdb-tbl th   { background: #e3f2fd; color: #1565c0; padding: 5px 8px; font-size: 11px;
                font-weight: 700; white-space: nowrap; position: sticky; top: 0; z-index: 1; }
.mdb-tbl td   { padding: 4px 8px; border-bottom: 1px solid #f5f5f5; white-space: nowrap; }
.mdb-tbl tr:hover td { background: #f5f9ff; }
.mdb-tbl td.r { text-align: right; }
.mdb-tbl td.c { text-align: center; }
.mdb-empty    { text-align: center; color: #aaa; font-size: 12px; padding: 18px; }
.mdb-load     { text-align: center; color: #999; font-size: 12px; padding: 14px; }

/* ---- linha colorida por tipo (tabela tipo) ---- */
.row-C { background: #ffebee !important; }
.row-P { background: #f3e5f5 !important; }
.row-G { background: #e3f2fd !important; }
.row-M { background: #e8f5e9 !important; }
.row-N { background: #fafafa !important; }
.row-T { background: #fff8e1 !important; font-weight: 700; }

/* ---- badge tipo ---- */
.bt   { display: inline-block; border-radius: 10px; font-size: 10px; font-weight: 700;
        padding: 2px 7px; color: #fff; }
.bt-C { background: #c62828; }
.bt-P { background: #6a1b9a; }
.bt-G { background: #1565c0; }
.bt-M { background: #2e7d32; }
.bt-N { background: #757575; }

/* ---- mini counts ---- */
.mini-tbl      { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 10px; }
.mini-tbl th   { background: #bbdefb; color: #0d47a1; padding: 4px 8px; font-size: 11px; font-weight: 700; }
.mini-tbl td   { padding: 5px 8px; border-bottom: 1px solid #f0f0f0; }
.mini-tbl td.r { text-align: right; font-weight: 700; color: #1565c0; }
.mini-tbl.prev th { background: #e8eaf6; color: #283593; }
.mini-tbl.prev td.r { color: #283593; }

/* ---- chart containers ---- */
.cbox         { padding: 8px; }
.cbox canvas  { width: 100% !important; }

@media (max-width: 900px) {
    .mdb-row { flex-wrap: wrap; }
    .w-grupo, .w-distrib, .w-geradas, .w-minutos,
    .w-tipo, .w-counts, .w-func, .w-custos, .w-funchoras { flex: 1 1 100%; }
}
</style>

<div class="mdb-wrap">

    <!-- Filtros -->
    <div class="mdb-filter">
        <label>Empresa:</label>
        <select id="fEmpr" style="min-width:200px"></select>
        <label>De:</label>
        <input type="date" id="fDi" value="<?= date('Y-m-01') ?>">
        <label>Até:</label>
        <input type="date" id="fDf" value="<?= date('Y-m-t') ?>">
        <button class="btn btn-sm btn-primary" onclick="filtrar()">
            <i class="bi bi-search"></i> Filtrar
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="filtrar()" title="Atualizar">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <span id="mdbStatus" style="font-size:11px;color:#888;margin-left:auto"></span>
    </div>

    <!-- KPIs -->
    <div class="mdb-kpis">
        <div class="mdb-kpi"><div class="mdb-kpi-lbl">Total</div><div class="mdb-kpi-val kc-total" id="kTotal">—</div></div>
        <div class="mdb-kpi"><div class="mdb-kpi-lbl">Geradas</div><div class="mdb-kpi-val kc-total" id="kGeradas">—</div></div>
        <div class="mdb-kpi"><div class="mdb-kpi-lbl">Atendidas</div><div class="mdb-kpi-val kc-aten" id="kAtendidas">—</div></div>
        <div class="mdb-kpi"><div class="mdb-kpi-lbl">Abertas</div><div class="mdb-kpi-val kc-aber" id="kAbertas">—</div></div>
        <div class="mdb-kpi"><div class="mdb-kpi-lbl">Corretivas</div><div class="mdb-kpi-val kc-corr" id="kCorretivas">—</div></div>
        <div class="mdb-kpi"><div class="mdb-kpi-lbl">Preventivas</div><div class="mdb-kpi-val kc-prev" id="kPreventivas">—</div></div>
        <div class="mdb-kpi"><div class="mdb-kpi-lbl">Programadas</div><div class="mdb-kpi-val kc-prog" id="kProgramadas">—</div></div>
    </div>

    <!-- Linha 1: Grupo+Valor | Distribuição% chart | Geradas/Atendidas chart | Minutos chart -->
    <div class="mdb-row">
        <div class="mdb-panel w-grupo" style="max-height:320px">
            <div class="mdb-ph">Empresa / Grupo / Valor em $</div>
            <div class="mdb-pb">
                <table class="mdb-tbl" id="tbGrupos" style="min-width:270px">
                    <thead><tr><th>Emp.</th><th>Grupo</th><th class="r" style="min-width:90px">Valor em $</th></tr></thead>
                    <tbody><tr><td colspan="3" class="mdb-load">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="mdb-panel w-distrib">
            <div class="mdb-ph">Distribuição de Atividades por Tipo de Manutenção em %</div>
            <div class="mdb-pb"><div class="cbox" style="height:280px"><canvas id="chartDistrib"></canvas></div></div>
        </div>
        <div class="mdb-panel w-geradas">
            <div class="mdb-ph">OS Geradas x OS Atendidas</div>
            <div class="mdb-pb"><div class="cbox" style="height:280px"><canvas id="chartGeradas"></canvas></div></div>
        </div>
        <div class="mdb-panel w-minutos">
            <div class="mdb-ph">Atividades por Tipo de Manutenção em Minutos</div>
            <div class="mdb-pb"><div class="cbox" style="height:280px"><canvas id="chartMinutos"></canvas></div></div>
        </div>
    </div>

    <!-- Linha 2: Tipo+Qtd+Valor | OS counts + Preventivas | Func/Ordens -->
    <div class="mdb-row">
        <div class="mdb-panel w-tipo" style="max-height:260px">
            <div class="mdb-ph">Tipo de Manutenção / Quantidade / Valor em $</div>
            <div class="mdb-pb">
                <table class="mdb-tbl" id="tbTipo">
                    <thead><tr><th>Tipo de Manutenção</th><th class="r">Quantidade</th><th class="r">Valor em $</th></tr></thead>
                    <tbody><tr><td colspan="3" class="mdb-load">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="mdb-panel w-counts">
            <div class="mdb-ph">Resumo</div>
            <div class="mdb-pb p10" id="mdbCounts">
                <div class="mdb-load">Carregando...</div>
            </div>
        </div>
        <div class="mdb-panel w-func" style="max-height:260px">
            <div class="mdb-ph">Funcionário / Ordens Fechada / Aberta</div>
            <div class="mdb-pb">
                <table class="mdb-tbl" id="tbFuncOrdens">
                    <thead><tr><th>Funcionário</th><th class="r">Fechada</th><th class="r">Aberta</th></tr></thead>
                    <tbody><tr><td colspan="3" class="mdb-load">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Linha 3: Custos chart | Func/Horas/MTTR table -->
    <div class="mdb-row">
        <div class="mdb-panel w-custos">
            <div class="mdb-ph">Custos Manutenção de Máquinas e Equipamentos</div>
            <div class="mdb-pb"><div class="cbox" style="height:220px"><canvas id="chartCustos"></canvas></div></div>
        </div>
        <div class="mdb-panel w-funchoras" style="max-height:260px">
            <div class="mdb-ph">Funcionário — Min. em Execução / MTTR</div>
            <div class="mdb-pb">
                <table class="mdb-tbl" id="tbFuncHoras">
                    <thead><tr>
                        <th>Funcionário</th>
                        <th class="r">Min. Execução</th>
                        <th class="r">OS Corretivas</th>
                        <th class="r">MTTR (min)</th>
                    </tr></thead>
                    <tbody><tr><td colspan="4" class="mdb-load">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div id="mdb-app-data"
     data-base="<?= htmlspecialchars($base) ?>"
     data-empr="<?= (int) ($_SESSION['empresa']['id'] ?? 0) ?>"
     style="display:none"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?= $base ?>src/js/manutencao-dashboard.js?v=<?= time() ?>"></script>
<?= $render('footer') ?>
