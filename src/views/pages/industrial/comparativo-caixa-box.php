<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
$acessoIndustrial = $is_admin || in_array('industrial', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoIndustrial) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
$mesPadrao = date('Y-m', strtotime('first day of last month'));
?>
<?= $render('header', [
    'pageTitle'  => 'Processos Industriais — Comparativo Filiais',
    'showNavbar' => true,
    'pageActive' => 'industrial-comparativo-caixa-box',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.cf-root {
    --surface-0: #f0f2f5;
    --surface-1: #ffffff;
    --border:    #e5e8ee;
    --text-primary:   #1f2937;
    --text-secondary: #52514e;
    --text-muted:     #8a8f98;
    --head:      #1565c0;
    --series-1:  #2a78d6;   /* blue  — valor principal */
    --series-2:  #eb6834;   /* orange — 2ª série (GGF) */
    --meta:      #52514e;   /* marcador da Meta A3 */
    --good:      #0ca30c;
    --critical:  #d03b3b;
    padding: 12px; background: var(--surface-0); min-height: calc(100vh - 56px); box-sizing: border-box; color: var(--text-primary);
}
.cf-filtros { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; background:var(--surface-1); border-radius:10px;
              box-shadow:0 1px 6px rgba(0,0,0,.08); padding:10px 16px; margin-bottom:12px; }
.cf-filtros label { display:block; font-size:.72rem; font-weight:600; color:var(--text-secondary); margin-bottom:2px; }
.cf-filtros .form-select, .cf-filtros .form-control { font-size:.8rem; }
.cf-status  { font-size:12px; color:var(--text-muted); margin-left:auto; align-self:center; }

.cf-kpis    { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px; margin-bottom:12px; }
.cf-kpi     { background:var(--surface-1); border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); padding:10px 14px; }
.cf-kpi .l  { font-size:10.5px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.3px; font-weight:700; }
.cf-kpi .v  { font-size:20px; font-weight:700; color:var(--text-primary); line-height:1.2; margin-top:2px; font-variant-numeric:tabular-nums; }
.cf-kpi .s  { font-size:11px; color:var(--text-secondary); margin-top:2px; }
.st-ok      { color:var(--good); font-weight:700; }
.st-ruim    { color:var(--critical); font-weight:700; }

.cf-grid    { display:grid; gap:12px; margin-bottom:12px; }
.cf-grid.g2 { grid-template-columns:repeat(2, minmax(0, 1fr)); }
.cf-grid.g3 { grid-template-columns:repeat(3, minmax(0, 1fr)); }
@media (max-width: 1100px) { .cf-grid.g2, .cf-grid.g3 { grid-template-columns:1fr; } }
.cf-card    { background:var(--surface-1); border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; display:flex; flex-direction:column; }
.cf-head    { background:var(--head); color:#fff; padding:7px 14px; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.4px;
              display:flex; justify-content:space-between; align-items:center; gap:8px; }
.cf-head small { font-weight:400; text-transform:none; letter-spacing:0; opacity:.9; }
.cf-body    { padding:10px 12px; position:relative; }
.cf-chart   { position:relative; height:300px; }
.cf-chart.sm { height:260px; }
.cf-legend  { display:flex; gap:14px; font-size:11px; color:var(--text-secondary); padding:0 2px 6px; flex-wrap:wrap; }
.cf-legend i { display:inline-block; width:12px; height:3px; border-radius:2px; vertical-align:middle; margin-right:5px; }
.cf-legend i.bar { height:10px; width:10px; border-radius:2px; }
.cf-legend i.dash { background:repeating-linear-gradient(90deg, var(--meta) 0 4px, transparent 4px 7px); }
.cf-vazio   { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:12px; }

.cf-tbl     { width:100%; border-collapse:separate; border-spacing:0; }
.cf-tbl th  { background:#e3f2fd !important; color:var(--head) !important; font-size:10.5px !important; font-weight:700 !important; padding:6px 8px !important;
              text-align:right; white-space:normal; line-height:1.2; vertical-align:bottom; border-bottom:2px solid #bbdefb !important; }
.cf-tbl th:first-child, .cf-tbl td:first-child { text-align:left; }
.cf-tbl td  { padding:5px 8px !important; font-size:12px !important; text-align:right; white-space:nowrap; border-bottom:1px solid #f0f0f0 !important;
              font-variant-numeric:tabular-nums; color:var(--text-primary); }
.cf-tbl tr.tot td { font-weight:700; background:#f5f8fe !important; border-top:2px solid #bbdefb !important; }
.cf-tbl tbody tr:hover td { background:#f5f9ff !important; }
.cf-tbl .badge-st { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; }
.cf-nota    { font-size:11px; color:var(--text-muted); font-style:italic; padding:8px 12px 10px; }
</style>

<div class="cf-root">
    <div class="cf-filtros">
        <div>
            <label for="fSeg">Segmento</label>
            <select id="fSeg" class="form-select form-select-sm" style="min-width:140px">
                <option value="CAIXA_BOX">Caixa Box</option>
                <option value="COLCHOES">Colchões</option>
                <option value="ESTOFADOS">Estofados</option>
                <option value="ESPUMA">Espuma</option>
            </select>
        </div>
        <div>
            <label for="fMes">Mês</label>
            <input type="month" id="fMes" class="form-control form-control-sm" value="<?= $mesPadrao ?>">
        </div>
        <div>
            <label for="fMeses">Evolução</label>
            <select id="fMeses" class="form-select form-select-sm">
                <option value="6">Últimos 6 meses</option>
                <option value="12">Últimos 12 meses</option>
            </select>
        </div>
        <div>
            <label for="fFilial">Filial (evolução)</label>
            <select id="fFilial" class="form-select form-select-sm" style="min-width:180px"><option value="TOTAL">Total do segmento</option></select>
        </div>
        <div>
            <button class="btn btn-sm btn-primary" id="btnAtualizar"><i class="bi bi-arrow-clockwise"></i> Atualizar</button>
        </div>
        <span class="cf-status" id="status"></span>
    </div>

    <div class="cf-kpis" id="kpis"></div>

    <div class="cf-grid g2">
        <div class="cf-card">
            <div class="cf-head"><span>GGF / Faturamento por filial</span><small id="mesRot1"></small></div>
            <div class="cf-body">
                <div class="cf-legend" id="legGgfFat"></div>
                <div class="cf-chart"><canvas id="chGgfFat"></canvas></div>
            </div>
        </div>
        <div class="cf-card">
            <div class="cf-head"><span>Faturamento por colaborador</span><small id="mesRot2"></small></div>
            <div class="cf-body"><div class="cf-chart"><canvas id="chFatCol"></canvas></div></div>
        </div>
    </div>

    <div class="cf-grid g2">
        <div class="cf-card">
            <div class="cf-head"><span>Peças por funcionário</span><small id="mesRot3"></small></div>
            <div class="cf-body"><div class="cf-chart sm"><canvas id="chPecasCol"></canvas></div></div>
        </div>
        <div class="cf-card">
            <div class="cf-head"><span>GGF por peça</span><small id="mesRot4"></small></div>
            <div class="cf-body"><div class="cf-chart sm"><canvas id="chGgfPeca"></canvas></div></div>
        </div>
    </div>

    <div class="cf-grid g2">
        <div class="cf-card">
            <div class="cf-head"><span>Evolução GGF / Faturamento</span><small id="trendRot1"></small></div>
            <div class="cf-body">
                <div class="cf-legend" id="legTrend1"></div>
                <div class="cf-chart"><canvas id="chTrendPct"></canvas></div>
            </div>
        </div>
        <div class="cf-card">
            <div class="cf-head"><span>Evolução Faturamento x GGF</span><small id="trendRot2"></small></div>
            <div class="cf-body">
                <div class="cf-legend">
                    <span><i class="bar" style="background:var(--series-1)"></i>Faturamento</span>
                    <span><i class="bar" style="background:var(--series-2)"></i>GGF</span>
                </div>
                <div class="cf-chart"><canvas id="chTrendVal"></canvas></div>
            </div>
        </div>
    </div>

    <div class="cf-card">
        <div class="cf-head"><span id="tblTitulo">Indicadores por filial</span><small id="mesRot5"></small></div>
        <div style="overflow:auto"><table class="cf-tbl" id="tbl"></table></div>
        <div class="cf-nota" id="nota"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
(function () {
    const BASE = <?= json_encode($base) ?>;
    const API  = BASE + 'industrial-api-comparativo-caixa-box';
    const MES  = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    const css  = n => getComputedStyle(document.querySelector('.cf-root')).getPropertyValue(n).trim();
    const C = { s1: css('--series-1'), s2: css('--series-2'), meta: css('--meta'), txt: css('--text-secondary'),
                muted: css('--text-muted'), grid: '#eef0f3', good: css('--good'), bad: css('--critical') };

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 11;
    Chart.defaults.color = C.txt;

    const cache = {};          // "SEG|YYYY-MM" -> dados do mês
    const charts = {};
    let geracao = 0;           // descarta respostas de filtros antigos

    // ── formatação ──
    const brl  = (v, d = 0) => v == null ? '-' : 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: d, maximumFractionDigits: d });
    const brlC = v => v == null ? '-' : (Math.abs(v) >= 1e6 ? 'R$ ' + (v / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + ' mi'
                                       : Math.abs(v) >= 1e3 ? 'R$ ' + (v / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : brl(v));
    const int  = v => v == null ? '-' : Math.round(Number(v)).toLocaleString('pt-BR');
    const pct  = (v, d = 2) => v == null ? '-' : (Number(v) * 100).toLocaleString('pt-BR', { minimumFractionDigits: d, maximumFractionDigits: d }) + '%';
    const rot  = m => MES[parseInt(m.slice(5), 10) - 1] + '/' + m.slice(2, 4);
    const esc  = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const curto = n => String(n).replace(/^(\d+) - /, '$1 · ');

    function mesesAte(fim, n) {
        const [a, m] = fim.split('-').map(Number), out = [];
        for (let i = n - 1; i >= 0; i--) { const d = new Date(a, m - 1 - i, 1); out.push(d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0')); }
        return out;
    }

    async function buscar(seg, mes) {
        const k = seg + '|' + mes;
        if (cache[k]) return cache[k];
        const res = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ segmento: seg, mes }) });
        let d; try { d = await res.json(); } catch (_) { throw new Error('Resposta inválida do servidor (HTTP ' + res.status + ').'); }
        if (d.error) throw new Error(d.error);
        return (cache[k] = d);
    }

    // ── KPIs ──
    function kpis(d) {
        const t = d.total, temMeta = t.meta != null;
        const st = temMeta && t.ggf_fat != null
            ? (t.ggf_fat <= t.meta ? '<span class="st-ok">✔ dentro da meta</span>' : '<span class="st-ruim">▲ acima da meta</span>') + ' (' + pct(t.meta, 1) + ')'
            : '';
        const k = [
            ['Faturamento líquido', brlC(t.faturamento), ''],
            ['GGF', brlC(t.ggf), ''],
            ['GGF / Faturamento', pct(t.ggf_fat), st],
            ['Colaboradores', int(t.colab), ''],
            ['Peças produzidas', int(t.pecas), ''],
            ['Peças por funcionário', int(t.pecas_col), ''],
            ['Faturamento por colaborador', brlC(t.fat_col), ''],
            ['GGF por peça', brl(t.ggf_peca, 2), ''],
        ];
        document.getElementById('kpis').innerHTML = k.map(([l, v, s]) =>
            '<div class="cf-kpi"><div class="l">' + l + '</div><div class="v">' + v + '</div>' + (s ? '<div class="s">' + s + '</div>' : '') + '</div>').join('');
    }

    // ── barras horizontais por filial (uma série; rótulo no fim da barra) ──
    function barras(id, filiais, campo, fmt, opts = {}) {
        const linhas = filiais.filter(f => f[campo] != null);
        const vals = linhas.map(f => f[campo]);
        const cap = opts.cap ? opts.cap(vals, linhas) : null;     // eixo limitado (valores fora mostram o número real)
        if (charts[id]) charts[id].destroy();
        const ctx = document.getElementById(id);
        charts[id] = new Chart(ctx, {
            type: 'bar',
            plugins: [ChartDataLabels].concat(opts.plugins || []),
            data: { labels: linhas.map(f => curto(f.nome)),
                    datasets: [{ data: vals.map(v => cap != null ? Math.min(v, cap) : v), backgroundColor: C.s1,
                                 borderRadius: 4, borderSkipped: 'start', barPercentage: .72, categoryPercentage: .86 }] },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                layout: { padding: { right: 64 } },
                scales: {
                    x: { beginAtZero: true, max: cap ?? undefined, grid: { color: C.grid }, border: { display: false },
                         ticks: { color: C.muted, callback: v => opts.tick ? opts.tick(v) : v } },
                    y: { grid: { display: false }, border: { display: false }, ticks: { color: C.txt } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: c => fmt(vals[c.dataIndex]) + (opts.extraTip ? opts.extraTip(linhas[c.dataIndex]) : '') } },
                    datalabels: { anchor: 'end', align: 'end', offset: 4, clamp: true, color: C.txt, font: { weight: 600, size: 10.5 },
                                  formatter: (v, c) => fmt(vals[c.dataIndex]) + (cap != null && vals[c.dataIndex] > cap ? ' ▸' : '') }
                }
            }
        });
        charts[id]._linhas = linhas;
    }

    // marcador vertical da Meta A3 em cada barra
    const metaPlugin = {
        id: 'metaA3',
        afterDatasetsDraw(chart) {
            const linhas = chart._linhas || [], meta = chart.getDatasetMeta(0), x = chart.scales.x, g = chart.ctx;
            g.save(); g.strokeStyle = C.meta; g.lineWidth = 2;
            meta.data.forEach((bar, i) => {
                const m = linhas[i] && linhas[i].meta; if (m == null || m > x.max) return;
                const px = x.getPixelForValue(m), h = bar.height / 2 + 3;
                g.beginPath(); g.moveTo(px, bar.y - h); g.lineTo(px, bar.y + h); g.stroke();
            });
            g.restore();
        }
    };

    function desenharMes(d) {
        const f = d.filiais, temMeta = d.total.meta != null, rm = rot(d.mes);
        ['mesRot1', 'mesRot2', 'mesRot3', 'mesRot4', 'mesRot5'].forEach(i => document.getElementById(i).textContent = rm);
        kpis(d);

        document.getElementById('legGgfFat').innerHTML = '<span><i class="bar" style="background:var(--series-1)"></i>GGF / Faturamento</span>' +
            (temMeta ? '<span><i style="background:var(--meta);width:3px;height:12px"></i>Meta A3</span>' : '');
        barras('chGgfFat', f, 'ggf_fat', v => pct(v, 2), {
            tick: v => (v * 100).toLocaleString('pt-BR') + '%',
            // filial com faturamento muito baixo distorce a escala: limita o eixo e mostra o número real
            cap: vals => { const ok = vals.filter(v => v <= 0.5); const mx = Math.max(...(ok.length ? ok : vals), 0.1); return Math.max(...vals) > mx ? mx * 1.15 : null; },
            plugins: temMeta ? [metaPlugin] : [],
            extraTip: l => l.meta != null ? ' · meta ' + pct(l.meta, 1) + (l.ggf_fat <= l.meta ? ' ✔' : ' ▲') : ''
        });
        barras('chFatCol', f, 'fat_col', v => brlC(v), { tick: v => brlC(v) });
        barras('chPecasCol', f, 'pecas_col', v => int(v), {});
        barras('chGgfPeca', f, 'ggf_peca', v => brl(v, 2), {
            tick: v => 'R$ ' + v.toLocaleString('pt-BR'),
            cap: vals => { const s = [...vals].sort((a, b) => a - b), med = s[Math.floor(s.length / 2)] || 0; return Math.max(...vals) > med * 4 ? med * 2.5 : null; }
        });
        tabela(d);
        filiaisTrend(f);
    }

    function tabela(d) {
        const temMeta = d.total.meta != null;
        const st = r => r.meta == null || r.ggf_fat == null ? '' :
            (r.ggf_fat <= r.meta ? '<span class="badge-st st-ok">✔ Dentro</span>' : '<span class="badge-st st-ruim">▲ Acima</span>');
        const lin = (r, cls) => '<tr class="' + (cls || '') + '"><td>' + esc(r.nome) + '</td><td>' + brl(r.faturamento, 2) + '</td><td>' + brl(r.ggf, 2) + '</td><td>' +
            int(r.colab) + '</td><td>' + int(r.pecas) + '</td><td>' + pct(r.ggf_fat) + '</td>' +
            (temMeta ? '<td>' + pct(r.meta, 1) + '</td><td>' + st(r) + '</td>' : '') +
            '<td>' + brl(r.ggf_peca, 2) + '</td><td>' + int(r.pecas_col) + '</td><td>' + brl(r.fat_col, 2) + '</td></tr>';
        document.getElementById('tblTitulo').textContent = 'Total ' + document.getElementById('fSeg').selectedOptions[0].text + ' — indicadores por filial';
        document.getElementById('tbl').innerHTML = '<thead><tr><th>Filial</th><th>Faturamento</th><th>GGF</th><th>Colaboradores</th><th>Peças produzidas</th><th>GGF / Faturamento</th>' +
            (temMeta ? '<th>Meta A3</th><th>Situação</th>' : '') + '<th>GGF / Peça</th><th>Peças / funcionário</th><th>Faturamento / colaborador</th></tr></thead><tbody>' +
            d.filiais.map(r => lin(r)).join('') + lin({ nome: 'Total', ...d.total }, 'tot') + '</tbody>';
        document.getElementById('nota').textContent =
            'Faturamento e peças: agrupadores do segmento (Caixa Box = BASE + CONJUGADO). GGF e colaboradores: centros de custo do segmento' +
            (d.segmento === 'CAIXA_BOX' ? ' (140), somando as fábricas de molas 10 → Douradina e 11 → Candelária. Meta A3: Douradina 7,8%, Elói Mendes 6,5%, demais 7,5%; meta do total ponderada pelo faturamento.' : '.') +
            ' Dados dos relatórios Peças, Faturamento e GGF por Colaborador.';
    }

    function filiaisTrend(f) {
        const sel = document.getElementById('fFilial'), atual = sel.value;
        sel.innerHTML = '<option value="TOTAL">Total do segmento</option>' + f.map(x => '<option value="' + x.empresa + '">' + esc(x.nome) + '</option>').join('');
        sel.value = [...sel.options].some(o => o.value === atual) ? atual : 'TOTAL';
    }

    // ── evolução mensal (filial escolhida ou total) ──
    function desenharTrend(seg, meses) {
        const fil = document.getElementById('fFilial').value;
        const nome = document.getElementById('fFilial').selectedOptions[0]?.text || '';
        document.getElementById('trendRot1').textContent = nome;
        document.getElementById('trendRot2').textContent = nome;
        const pega = m => { const d = cache[seg + '|' + m]; if (!d) return null; return fil === 'TOTAL' ? d.total : d.filiais.find(x => String(x.empresa) === fil) || null; };
        const pts = meses.map(pega);
        const labels = meses.map(rot);
        const temMeta = pts.some(p => p && p.meta != null);

        document.getElementById('legTrend1').innerHTML = '<span><i style="background:var(--series-1)"></i>GGF / Faturamento</span>' +
            (temMeta ? '<span><i class="dash"></i>Meta A3</span>' : '');
        const base = (fmtY) => ({
            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            scales: { x: { grid: { display: false }, ticks: { color: C.muted } },
                      y: { beginAtZero: true, grid: { color: C.grid }, border: { display: false }, ticks: { color: C.muted, callback: fmtY } } },
            plugins: { legend: { display: false } },
            elements: { line: { borderWidth: 2, tension: .25 }, point: { radius: 4, hoverRadius: 6, borderWidth: 2, borderColor: '#fff' } }
        });

        if (charts.tp) charts.tp.destroy();
        const pctVals = pts.map(p => p && p.ggf_fat != null ? p.ggf_fat : null);
        const capT = (() => { const ok = pctVals.filter(v => v != null && v <= 0.5); return pctVals.some(v => v > 0.5) && ok.length ? Math.max(...ok, ...(temMeta ? pts.map(p => p?.meta || 0) : [])) * 1.25 : undefined; })();
        const oT = base(v => (v * 100).toLocaleString('pt-BR') + '%');
        oT.scales.y.max = capT;
        oT.plugins.tooltip = { callbacks: { label: c => c.dataset.label + ': ' + pct(c.datasetIndex === 0 ? pctVals[c.dataIndex] : c.raw, c.datasetIndex === 0 ? 2 : 1) } };
        charts.tp = new Chart(document.getElementById('chTrendPct'), {
            type: 'line',
            data: { labels, datasets: [
                { label: 'GGF / Faturamento', data: pctVals.map(v => v == null ? null : (capT ? Math.min(v, capT) : v)), borderColor: C.s1, backgroundColor: C.s1, spanGaps: true },
                ...(temMeta ? [{ label: 'Meta A3', data: pts.map(p => p ? p.meta : null), borderColor: C.meta, borderDash: [5, 4], pointRadius: 0, borderWidth: 1.5, tension: 0, spanGaps: true }] : [])
            ] },
            options: oT
        });

        if (charts.tv) charts.tv.destroy();
        const oV = base(v => brlC(v));
        oV.plugins.tooltip = { callbacks: { label: c => c.dataset.label + ': ' + brl(c.raw, 2) } };
        charts.tv = new Chart(document.getElementById('chTrendVal'), {
            type: 'line',
            data: { labels, datasets: [
                { label: 'Faturamento', data: pts.map(p => p ? p.faturamento : null), borderColor: C.s1, backgroundColor: C.s1, spanGaps: true },
                { label: 'GGF', data: pts.map(p => p ? p.ggf : null), borderColor: C.s2, backgroundColor: C.s2, spanGaps: true }
            ] },
            options: oV
        });
    }

    async function atualizar() {
        const g = ++geracao;
        const seg = document.getElementById('fSeg').value, mes = document.getElementById('fMes').value;
        const meses = mesesAte(mes, parseInt(document.getElementById('fMeses').value, 10));
        const st = document.getElementById('status');
        if (!mes) return;
        st.textContent = 'Carregando ' + rot(mes) + '...';
        try {
            const d = await buscar(seg, mes);
            if (g !== geracao) return;
            desenharMes(d);
        } catch (e) { if (g === geracao) st.textContent = 'Erro: ' + e.message; return; }

        // evolução: busca os outros meses em paralelo (3 por vez) e vai redesenhando
        let feitos = meses.filter(m => cache[seg + '|' + m]).length;
        desenharTrend(seg, meses);
        const fila = meses.filter(m => !cache[seg + '|' + m]);
        const prox = async () => {
            while (fila.length && g === geracao) {
                const m = fila.shift();
                try { await buscar(seg, m); } catch (_) { /* mês sem dados fica em branco */ }
                if (g !== geracao) return;
                feitos++;
                st.textContent = feitos < meses.length ? 'Carregando evolução ' + feitos + '/' + meses.length + ' (1ª vez de cada mês demora alguns segundos)' : '';
                desenharTrend(seg, meses);
            }
        };
        if (fila.length) st.textContent = 'Carregando evolução ' + feitos + '/' + meses.length + '...';
        else st.textContent = '';
        await Promise.all([prox(), prox(), prox()]);
    }

    document.getElementById('btnAtualizar').addEventListener('click', atualizar);
    ['fSeg', 'fMes', 'fMeses'].forEach(id => document.getElementById(id).addEventListener('change', atualizar));
    document.getElementById('fFilial').addEventListener('change', () =>
        desenharTrend(document.getElementById('fSeg').value, mesesAte(document.getElementById('fMes').value, parseInt(document.getElementById('fMeses').value, 10))));
    atualizar();
})();
</script>
<?= $render('footer') ?>
