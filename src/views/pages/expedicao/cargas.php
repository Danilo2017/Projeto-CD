<?php
$acessoExpedicao = $is_admin || in_array('expedicao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoExpedicao) { header('Location: ' . $base . 'sem-acesso'); exit; }
$empresa = $_SESSION['empresa'] ?? [];
?>
<?= $render('header', [
    'pageTitle' => 'Cargas Fechadas - Expedição',
    'showNavbar' => true,
    'pageActive' => 'expedicao-cargas',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container" style="width: 100%; max-width: 100%; padding: 5px 10px; margin: 0;">
    <div class="d-flex justify-content-end mb-2">
        <a href="<?= $base ?>expedicao-cadastro" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
    <div id="exMsg" style="display:none"></div>

    <div class="dashboard-filters">
        <div class="filter-row d-flex align-items-end flex-wrap">
            <div class="filter-group">
                <label>Empresa</label>
                <input type="text" class="form-control" readonly style="background-color: #e9ecef;" value="<?= htmlspecialchars(($empresa['codigo'] ?? '') . ' - ' . ($empresa['nome_fantasia'] ?? '')) ?>">
            </div>
            <div class="filter-group"><label for="dtDe">Fechadas de *</label><input type="date" id="dtDe" class="form-control"></div>
            <div class="filter-group"><label for="dtAte">até *</label><input type="date" id="dtAte" class="form-control"></div>
            <div class="filter-group"><button type="button" class="btn btn-primary btn-sm" id="btnFiltrar"><i class="bi bi-search"></i> Filtrar</button></div>
            <div class="filter-group"><input type="text" id="busca" class="form-control" placeholder="Buscar carga ou descrição"></div>
        </div>
        <div class="small text-muted mt-2">A data de fechamento vem do Focco (log da carga: dia e hora em que ela foi fechada e por quem). Cargas fechadas antes de 09/10/2026 não têm registro no log: nelas vale a data de geração da carga (aparece "sem log" em "fechada por"). Se a carga for reaberta e fechada de novo, vale o último fechamento. Esta tela só consulta: para corrigir uma data, ajuste a carga no Focco. O Focco só tem esse registro a partir de 08/10/2026.</div>
    </div>

    <div class="dashboard-metrics">
        <div class="metric-card">
            <div class="metric-icon bg-primary"><i class="bi bi-truck"></i></div>
            <div class="metric-content"><span class="metric-value" id="mCargas">-</span><span class="metric-label">Cargas fechadas</span></div>
        </div>
        <div class="metric-card">
            <div class="metric-icon bg-info"><i class="bi bi-box"></i></div>
            <div class="metric-content"><span class="metric-value" id="mM3">-</span><span class="metric-label">m³ (pontos)</span></div>
        </div>
    </div>

    <div class="dashboard-section" style="width: 100%; max-width: 100%;">
        <table class="table table-striped table-hover" style="width: 100%;">
            <thead><tr><th>Carga</th><th>Descrição</th><th class="text-end">m³</th><th>Fechada em</th><th>Fechada por</th></tr></thead>
            <tbody id="corpo"></tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/_comum.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const $i = id => document.getElementById(id);
    let cargas = [];

    function desenhar() {
        const b = $i('busca').value.trim().toLowerCase();
        const rows = cargas.filter(c => !b || (c.NUM_CARGA + ' ' + (c.DESCRICAO || '')).toLowerCase().includes(b));
        $i('mCargas').textContent = rows.length; $i('mM3').textContent = EX.fmt(rows.reduce((s, c) => s + Number(c.CUBAGEM), 0));
        $i('corpo').innerHTML = rows.length ? rows.map(c => '<tr><td>' + c.NUM_CARGA + '</td><td>' + EX.esc(c.DESCRICAO) + '</td><td class="text-end">' + EX.fmt(c.CUBAGEM) + '</td><td>' + EX.esc(c.DT_HORA_FECHAMENTO) + '</td><td>' + EX.esc(c.USUARIO_FECHAMENTO) + '</td></tr>').join('')
            : '<tr><td colspan="5" class="text-center text-muted">Nenhuma carga fechada no período.</td></tr>';
    }
    async function carregar() {
        $i('btnFiltrar').disabled = true;
        try { cargas = (await EX.post('/expedicao-api-cargas', { dt_ini: $i('dtDe').value, dt_fim: $i('dtAte').value })).rows || []; desenhar(); }
        finally { $i('btnFiltrar').disabled = false; }
    }
    $i('btnFiltrar').onclick = () => EX.acao(carregar);
    $i('busca').oninput = desenhar;

    $i('dtDe').value = EX.inicioMes(); $i('dtAte').value = EX.hoje();
    EX.acao(carregar);
});
</script>
<?= $render('footer') ?>
