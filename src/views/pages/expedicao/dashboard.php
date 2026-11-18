<?php
$acessoExpedicao = $is_admin || in_array('expedicao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoExpedicao) { header('Location: ' . $base . 'sem-acesso'); exit; }
$empresa = $_SESSION['empresa'] ?? [];
?>
<?= $render('header', [
    'pageTitle' => 'Dashboard - Expedição',
    'showNavbar' => true,
    'pageActive' => 'expedicao-dashboard',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    .exd-chart-box { position: relative; height: 320px; padding: 0 12px 12px; }
    .dashboard-section > h6 { padding: 12px 12px 0; }
    .exd-dia { cursor: pointer; }
    .exd-dia .bi-chevron-right { transition: transform .15s; display: inline-block; }
    .exd-dia.aberto .bi-chevron-right { transform: rotate(90deg); }
    .exd-detalhe > td { background: #f8f9fa; padding: 0 !important; }
    .exd-detalhe table { margin: 0; font-size: 0.78rem; }
</style>

<div class="comissao-dashboard-container">
    <div id="exMsg" style="display:none"></div>

    <div class="dashboard-filters">
        <div class="filter-row d-flex align-items-end flex-wrap">
            <div class="filter-group">
                <label>Empresa</label>
                <input type="text" class="form-control" readonly style="background-color: #e9ecef;" value="<?= htmlspecialchars(($empresa['codigo'] ?? '') . ' - ' . ($empresa['nome_fantasia'] ?? '')) ?>">
            </div>
            <div class="filter-group"><label for="dtIni">Data Início *</label><input type="date" id="dtIni" class="form-control"></div>
            <div class="filter-group"><label for="dtFim">Data Fim *</label><input type="date" id="dtFim" class="form-control"></div>
            <div class="filter-group"><button type="button" class="btn btn-primary btn-sm" id="btnGerar"><i class="bi bi-search"></i> Atualizar</button></div>
            <div class="filter-group">
                <div class="form-check mt-4">
                    <input type="checkbox" id="autoAtualizar" class="form-check-input">
                    <label for="autoAtualizar" class="form-check-label">Atualizar sozinho (5 min)</label>
                </div>
            </div>
            <div class="filter-group ms-auto"><button type="button" class="btn btn-outline-secondary btn-sm" id="btnTela"><i class="bi bi-fullscreen"></i> Tela cheia</button></div>
        </div>
    </div>

    <div class="dashboard-metrics">
        <div class="metric-card"><div class="metric-icon bg-primary"><i class="bi bi-truck"></i></div>
            <div class="metric-content"><span class="metric-value" id="mCargas">-</span><span class="metric-label">Cargas carregadas</span></div></div>
        <div class="metric-card"><div class="metric-icon bg-info"><i class="bi bi-box"></i></div>
            <div class="metric-content"><span class="metric-value" id="mM3">-</span><span class="metric-label">m³ carregados</span></div></div>
        <div class="metric-card"><div class="metric-icon bg-warning"><i class="bi bi-calendar-check"></i></div>
            <div class="metric-content"><span class="metric-value" id="mMedia">-</span><span class="metric-label">Média de cargas por dia</span></div></div>
        <div class="metric-card"><div class="metric-icon bg-success"><i class="bi bi-trophy"></i></div>
            <div class="metric-content"><span class="metric-value" id="mMelhor">-</span><span class="metric-label" id="mMelhorL">Melhor dia</span></div></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="dashboard-section h-100">
                <h6 class="mb-2"><i class="bi bi-bar-chart"></i> Cargas e m³ carregados por dia</h6>
                <div class="exd-chart-box"><canvas id="grafDia"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="dashboard-section h-100">
                <h6 class="mb-2"><i class="bi bi-graph-up"></i> m³ acumulado no período</h6>
                <div class="exd-chart-box"><canvas id="grafAcum"></canvas></div>
            </div>
        </div>
    </div>

    <div class="dashboard-section">
        <h6 class="mb-2"><i class="bi bi-list-nested"></i> Desdobramento por dia <small class="text-muted">(clique no dia para ver as cargas)</small></h6>
        <table class="table table-hover table-sm" style="font-size: 0.85rem;">
            <thead><tr><th>Dia</th><th>Dia da semana</th><th class="text-end">Cargas</th><th class="text-end">m³</th><th class="text-end">m³ por carga</th></tr></thead>
            <tbody id="corpo"><tr><td colspan="5" class="text-center text-muted">Carregando...</td></tr></tbody>
            <tfoot id="rodape"></tfoot>
        </table>
    </div>
</div>

<?php include __DIR__ . '/_comum.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const $i = id => document.getElementById(id);
    const SEM = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    const dow = iso => SEM[new Date(iso + 'T12:00:00').getDay()];
    let graf = {}, timer = null, dias = [];

    function desenhar(id, cfg) { if (graf[id]) graf[id].destroy(); graf[id] = new Chart($i(id), cfg); }

    function montar(rows) {
        const m = {};
        rows.forEach(c => { const d = c.DT_FECHAMENTO; (m[d] = m[d] || { dia: d, cargas: [], m3: 0 }); m[d].cargas.push(c); m[d].m3 += Number(c.CUBAGEM) || 0; });
        dias = Object.values(m).sort((a, b) => a.dia.localeCompare(b.dia));

        const totC = rows.length, totM = dias.reduce((s, d) => s + d.m3, 0);
        $i('mCargas').textContent = totC; $i('mM3').textContent = EX.fmt(totM);
        $i('mMedia').textContent = dias.length ? (totC / dias.length).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) : '-';
        const melhor = dias.reduce((b, d) => (!b || d.cargas.length > b.cargas.length) ? d : b, null);
        $i('mMelhor').textContent = melhor ? melhor.cargas.length + ' cargas' : '-';
        $i('mMelhorL').textContent = melhor ? 'Melhor dia: ' + EX.dbr(melhor.dia) : 'Melhor dia';

        const rot = dias.map(d => EX.dbr(d.dia).slice(0, 5));
        desenhar('grafDia', {
            data: { labels: rot, datasets: [
                { type: 'bar', label: 'Cargas', data: dias.map(d => d.cargas.length), backgroundColor: '#2563eb', borderRadius: 4, yAxisID: 'y', order: 2 },
                { type: 'line', label: 'm³', data: dias.map(d => Math.round(d.m3 * 10) / 10), borderColor: '#f59e0b', backgroundColor: '#f59e0b', tension: .3, pointRadius: 3, yAxisID: 'y1', order: 1 }
            ] },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Cargas' }, ticks: { precision: 0 } },
                          y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'm³' } } } }
        });
        let acc = 0;
        desenhar('grafAcum', {
            type: 'line',
            data: { labels: rot, datasets: [{ label: 'm³ acumulado', data: dias.map(d => Math.round((acc += d.m3) * 10) / 10), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.15)', fill: true, tension: .3, pointRadius: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });

        const desc = dias.slice().reverse();
        $i('corpo').innerHTML = desc.length ? desc.map((d, k) =>
            '<tr class="exd-dia" data-k="' + k + '"><td><i class="bi bi-chevron-right"></i> ' + EX.dbr(d.dia) + '</td><td>' + dow(d.dia) + '</td><td class="text-end"><strong>' + d.cargas.length + '</strong></td><td class="text-end">' + EX.fmt(d.m3) + '</td><td class="text-end">' + EX.fmt(d.m3 / d.cargas.length) + '</td></tr>').join('')
            : '<tr><td colspan="5" class="text-center text-muted">Nenhuma carga fechada neste período.</td></tr>';
        $i('rodape').innerHTML = desc.length ? '<tr class="table-primary fw-bold"><td colspan="2">Total</td><td class="text-end">' + totC + '</td><td class="text-end">' + EX.fmt(totM) + '</td><td class="text-end">' + EX.fmt(totM / totC) + '</td></tr>' : '';
        dias = desc;
    }

    $i('corpo').addEventListener('click', ev => {
        const tr = ev.target.closest('.exd-dia'); if (!tr) return;
        const prox = tr.nextElementSibling;
        if (prox && prox.classList.contains('exd-detalhe')) { prox.remove(); tr.classList.remove('aberto'); return; }
        const d = dias[Number(tr.dataset.k)];
        const linhas = d.cargas.slice().sort((a, b) => String(a.DT_HORA_FECHAMENTO).localeCompare(String(b.DT_HORA_FECHAMENTO)))
            .map(c => '<tr><td style="width:90px">' + EX.esc(c.NUM_CARGA) + '</td><td>' + EX.esc(c.DESCRICAO) + '</td><td class="text-end" style="width:110px">' + EX.fmt(c.CUBAGEM) + ' m³</td></tr>').join('');
        tr.insertAdjacentHTML('afterend', '<tr class="exd-detalhe"><td colspan="5"><table class="table table-sm"><thead><tr><th>Carga</th><th>Descrição</th><th class="text-end">m³</th></tr></thead><tbody>' + linhas + '</tbody></table></td></tr>');
        tr.classList.add('aberto');
    });

    async function carregar() {
        $i('btnGerar').disabled = true;
        try {
            const r = await EX.post('/expedicao-api-cargas', { dt_ini: $i('dtIni').value, dt_fim: $i('dtFim').value });
            EX.aviso('', true); montar(r.rows || []);
        } catch (e) { EX.aviso(e.message, false); }
        $i('btnGerar').disabled = false;
    }
    $i('btnGerar').onclick = carregar;
    $i('autoAtualizar').onchange = ev => { clearInterval(timer); if (ev.target.checked) timer = setInterval(carregar, 300000); };
    $i('btnTela').onclick = () => { if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen().catch(() => {}); };

    $i('dtIni').value = EX.inicioMes(); $i('dtFim').value = EX.hoje();
    carregar();
});
</script>
<?= $render('footer') ?>
