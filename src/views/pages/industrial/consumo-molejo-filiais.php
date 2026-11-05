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
    'pageTitle'  => 'Processos Industriais — Molejo: Produção x Consumo Filiais',
    'showNavbar' => true,
    'pageActive' => 'industrial-consumo-molejo-filiais',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.mf-root {
    --surface-0: #f0f2f5; --surface-1: #ffffff; --head: #1565c0;
    --text-primary: #1f2937; --text-secondary: #52514e; --text-muted: #8a8f98;
    --series-1: #2a78d6;   /* produção 10 + 16 */
    --series-2: #eb6834;   /* consumo das filiais */
    padding: 12px; background: var(--surface-0); min-height: calc(100vh - 56px); box-sizing: border-box; color: var(--text-primary);
}
.mf-filtros { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; background:var(--surface-1); border-radius:10px;
              box-shadow:0 1px 6px rgba(0,0,0,.08); padding:10px 16px; margin-bottom:12px; }
.mf-filtros label { display:block; font-size:.72rem; font-weight:600; color:var(--text-secondary); margin-bottom:2px; }
.mf-filtros .form-select, .mf-filtros .form-control { font-size:.8rem; }
.mf-status  { font-size:12px; color:var(--text-muted); margin-left:auto; align-self:center; }
.mf-nota    { font-size:11px; color:var(--text-muted); font-style:italic; flex-basis:100%; }

.mf-kpis    { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:10px; margin-bottom:12px; }
.mf-kpi     { background:var(--surface-1); border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); padding:10px 14px; border-left:4px solid transparent; }
.mf-kpi .l  { font-size:10.5px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.3px; font-weight:700; }
.mf-kpi .v  { font-size:20px; font-weight:700; line-height:1.2; margin-top:2px; font-variant-numeric:tabular-nums; }
.mf-kpi .s  { font-size:11px; color:var(--text-secondary); margin-top:2px; }

.mf-grid    { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; margin-bottom:12px; }
@media (max-width: 1100px) { .mf-grid { grid-template-columns:1fr; } }
.mf-card    { background:var(--surface-1); border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; margin-bottom:12px; }
.mf-grid .mf-card { margin-bottom:0; }
.mf-head    { background:var(--head); color:#fff; padding:7px 14px; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.4px;
              display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
.mf-head small { font-weight:400; text-transform:none; letter-spacing:0; opacity:.9; }
.mf-body    { padding:10px 12px; }
.mf-chart   { position:relative; height:300px; }
.mf-legend  { display:flex; gap:14px; font-size:11px; color:var(--text-secondary); padding:0 2px 6px; flex-wrap:wrap; }
.mf-legend i { display:inline-block; width:12px; height:3px; border-radius:2px; vertical-align:middle; margin-right:5px; }

.mf-tbl     { border-collapse:separate; border-spacing:0; width:100%; min-width:max-content; }
.mf-tbl th  { background:#e3f2fd !important; color:var(--head) !important; font-size:10.5px !important; font-weight:700 !important; padding:5px 8px !important;
              text-align:right; white-space:normal; line-height:1.2; vertical-align:bottom; border-bottom:2px solid #bbdefb !important;
              position:sticky; top:0; z-index:1; }
.mf-tbl th.l, .mf-tbl td.l { text-align:left; }
.mf-tbl th.sep, .mf-tbl td.sep { border-left:2px solid #bbdefb !important; }
.mf-tbl td  { padding:3px 8px !important; font-size:11.5px !important; text-align:right; white-space:nowrap; border-bottom:1px solid #f0f0f0 !important;
              font-variant-numeric:tabular-nums; }
.mf-tbl tr.fds td { background:#fafbfc !important; color:var(--text-muted); }
.mf-tbl tr.tot td { font-weight:700; background:#f5f8fe !important; border-top:2px solid #bbdefb !important; position:sticky; bottom:0; }
.mf-tbl tbody tr:hover td { background:#f5f9ff !important; }
.mf-tbl .neg { color:#d03b3b; }
.mf-tbl .sem { color:#c62828; font-style:italic; }
.mf-scroll  { overflow:auto; max-height:520px; }
.mf-det a   { cursor:pointer; color:#1565c0; text-decoration:none; font-weight:600; font-size:12px; }
.mf-vazio   { text-align:center; color:var(--text-muted); padding:16px; font-size:12px; }
</style>

<div class="mf-root">
    <div class="mf-filtros">
        <div>
            <label for="fIni">Ano/Mês inicial</label>
            <input type="month" id="fIni" class="form-control form-control-sm" value="<?= $mesPadrao ?>">
        </div>
        <div>
            <label for="fFim">Ano/Mês final</label>
            <input type="month" id="fFim" class="form-control form-control-sm" value="<?= $mesPadrao ?>">
        </div>
        <div>
            <label for="fUnid">Unidade</label>
            <select id="fUnid" class="form-select form-select-sm">
                <option value="molinhas">Molinhas</option>
                <option value="molejos">Molejos</option>
            </select>
        </div>
        <div>
            <button class="btn btn-sm btn-primary" id="btnFiltrar"><i class="bi bi-search"></i> Filtrar</button>
            <button class="btn btn-sm btn-outline-secondary" id="btnCsv"><i class="bi bi-download"></i> CSV</button>
        </div>
        <span class="mf-status" id="status"></span>
        <div class="mf-nota">
            Produção: molejos produzidos nas empresas 10 e 16 (EPP − PPE). Consumo: molejos requisitados pelas filiais para as ordens de produção (REP).
            Molinhas = molejos × molinhas por unidade da estrutura da empresa 10 (TGAZIN_ADMIN_TANQUE_MOLA), nos dois lados.
            Molejo Bonnel / Fio Contínuo não leva molinha ensacada: conta em molejos e fica com 0 molinhas.
        </div>
    </div>

    <div class="mf-kpis" id="kpis"></div>

    <div class="mf-grid">
        <div class="mf-card">
            <div class="mf-head"><span>Por dia: produção x consumo</span><small id="unidRot1"></small></div>
            <div class="mf-body">
                <div class="mf-legend">
                    <span><i style="background:var(--series-1)"></i>Produção empresas 10 + 16</span>
                    <span><i style="background:var(--series-2)"></i>Consumo das filiais</span>
                </div>
                <div class="mf-chart"><canvas id="chDia"></canvas></div>
            </div>
        </div>
        <div class="mf-card">
            <div class="mf-head"><span>Acumulado no período</span><small id="unidRot2"></small></div>
            <div class="mf-body">
                <div class="mf-legend">
                    <span><i style="background:var(--series-1)"></i>Produção empresas 10 + 16</span>
                    <span><i style="background:var(--series-2)"></i>Consumo das filiais</span>
                </div>
                <div class="mf-chart"><canvas id="chAcum"></canvas></div>
            </div>
        </div>
    </div>

    <div class="mf-card">
        <div class="mf-head"><span>Comparativo diário</span><small id="unidRot3"></small></div>
        <div class="mf-scroll"><table class="mf-tbl" id="tblDia"></table></div>
    </div>

    <div class="mf-grid">
        <div class="mf-card">
            <div class="mf-head"><span>Produção no período</span><small id="unidRot4"></small></div>
            <div class="mf-scroll" style="max-height:none"><table class="mf-tbl" id="tblProd"></table></div>
        </div>
        <div class="mf-card">
            <div class="mf-head"><span>Consumo das filiais no período</span><small id="unidRot5"></small></div>
            <div class="mf-scroll" style="max-height:none"><table class="mf-tbl" id="tblCons"></table></div>
        </div>
    </div>

    <div class="mf-card" id="cardDet" style="display:none">
        <div class="mf-head"><span id="detTitulo">Detalhe</span><small><a style="color:#fff;cursor:pointer" id="detFechar">fechar ✕</a></small></div>
        <div class="mf-scroll"><table class="mf-tbl" id="tblDet"></table></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const BASE = <?= json_encode($base) ?>;
    const API  = BASE + 'industrial-api-consumo-molejo-filiais';
    const SEM  = ['dom','seg','ter','qua','qui','sex','sáb'];
    const css  = n => getComputedStyle(document.querySelector('.mf-root')).getPropertyValue(n).trim();
    const C = { s1: css('--series-1'), s2: css('--series-2'), muted: css('--text-muted'), txt: css('--text-secondary'), grid: '#eef0f3' };
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 11;
    Chart.defaults.color = C.txt;

    let dados = null;
    const charts = {};
    const esc  = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const n0   = v => v == null ? '-' : Math.round(Number(v)).toLocaleString('pt-BR');
    const n2   = v => v == null ? '-' : Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
    const curto = v => { const a = Math.abs(v); return a >= 1e6 ? (v / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi'
                                                   : a >= 1e3 ? (v / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : n0(v); };
    const dRot = d => { const [a, m, dd] = d.split('-'); return dd + '/' + m; };
    const dSem = d => SEM[new Date(d + 'T12:00:00').getDay()];
    const unid = () => document.getElementById('fUnid').value;
    const soma = (obj, campo) => Object.values(obj || {}).reduce((a, v) => a + (v[campo] || 0), 0);

    async function post(url, body) {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) });
        let data;
        try { data = await res.json(); } catch (_) { throw new Error('Resposta inválida do servidor (HTTP ' + res.status + ').'); }
        if (data.error) throw new Error(data.error);
        return data;
    }

    async function filtrar() {
        const ini = document.getElementById('fIni').value, fim = document.getElementById('fFim').value;
        if (!ini || !fim) return;
        const st = document.getElementById('status');
        st.textContent = 'Carregando...';
        document.getElementById('btnFiltrar').disabled = true;
        const t0 = Date.now();
        try {
            dados = await post(API, { ini, fim });
            desenhar();
            st.textContent = 'Atualizado em ' + ((Date.now() - t0) / 1000).toFixed(1) + 's';
        } catch (e) {
            st.textContent = 'Erro: ' + e.message;
        } finally {
            document.getElementById('btnFiltrar').disabled = false;
        }
    }

    // séries diárias na unidade escolhida
    function series() {
        const u = unid(), fab = dados.fabricas;
        return dados.dias.map(d => {
            const p = {}; fab.forEach(e => p[e] = ((d.P || {})[e] || {})[u] || 0);
            return { dia: d.dia, fab: p, prod: Object.values(p).reduce((a, b) => a + b, 0), cons: soma(d.C, u), porFilial: d.C || {} };
        });
    }

    function desenhar() {
        const u = unid(), rotU = u === 'molinhas' ? 'em molinhas' : 'em molejos';
        ['unidRot1', 'unidRot2', 'unidRot3', 'unidRot4', 'unidRot5'].forEach(i => document.getElementById(i).textContent = rotU);
        const s = series();
        const tot = s.reduce((a, x) => ({ prod: a.prod + x.prod, cons: a.cons + x.cons }), { prod: 0, cons: 0 });
        const porFab = {}; dados.fabricas.forEach(e => porFab[e] = s.reduce((a, x) => a + x.fab[e], 0));
        const nomeFab = e => (dados.producao.find(x => x.empresa === e) || { nome: 'Empresa ' + e }).nome;

        // KPIs
        const k = dados.fabricas.map(e => ['Produção ' + nomeFab(e), n0(porFab[e]), '', C.s1])
            .concat([['Produção empresas 10 + 16', n0(tot.prod), '', C.s1],
                     ['Consumo das filiais', n0(tot.cons), '', C.s2],
                     ['Produção 10 + 16 − consumo', n0(tot.prod - tot.cons),
                      tot.cons ? 'empresas 10 + 16 cobrem ' + (tot.prod / tot.cons * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '% do consumo' : '', '#52514e']]);
        document.getElementById('kpis').innerHTML = k.map(([l, v, sub, cor]) =>
            '<div class="mf-kpi" style="border-left-color:' + cor + '"><div class="l">' + esc(l) + '</div><div class="v">' + v + '</div>' +
            (sub ? '<div class="s">' + sub + '</div>' : '') + '</div>').join('');

        // gráfico diário (3 séries, mesma unidade, um eixo)
        const labels = s.map(x => dRot(x.dia));
        const base = () => ({
            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            scales: { x: { grid: { display: false }, ticks: { color: C.muted, maxRotation: 0, autoSkip: true } },
                      y: { beginAtZero: true, grid: { color: C.grid }, border: { display: false }, ticks: { color: C.muted, callback: v => curto(v) } } },
            plugins: { legend: { display: false },
                       tooltip: { callbacks: { title: it => dRot(s[it[0].dataIndex].dia) + ' (' + dSem(s[it[0].dataIndex].dia) + ')',
                                               label: c => c.dataset.label + ': ' + n0(c.raw) } } },
            elements: { line: { borderWidth: 2, tension: .2 }, point: { radius: 3, hoverRadius: 6, borderWidth: 2, borderColor: '#fff' } }
        });
        const linha = (label, data, cor) => ({ label, data, borderColor: cor, backgroundColor: cor });
        if (charts.dia) charts.dia.destroy();
        charts.dia = new Chart(document.getElementById('chDia'), { type: 'line', options: base(), data: { labels, datasets: [
            linha('Produção empresas 10 + 16', s.map(x => x.prod), C.s1),
            linha('Consumo das filiais', s.map(x => x.cons), C.s2) ] } });

        let ap = 0, ac = 0;
        const acum = s.map(x => { ap += x.prod; ac += x.cons; return { p: ap, c: ac }; });
        if (charts.acum) charts.acum.destroy();
        const oA = base(); oA.elements.point.radius = 0;
        charts.acum = new Chart(document.getElementById('chAcum'), { type: 'line', options: oA, data: { labels, datasets: [
            linha('Produção empresas 10 + 16', acum.map(x => x.p), C.s1),
            linha('Consumo das filiais', acum.map(x => x.c), C.s2) ] } });

        tabelaDia(s, tot);
        tabelaResumo('tblProd', dados.producao.map(f => ({ ...f, grupo: '' })), 'producao');
        tabelaResumo('tblCons', dados.consumo.map(f => ({ ...f, grupo: '' })), 'consumo');
    }

    function tabelaDia(s, tot) {
        const u = unid();
        const filiais = dados.consumo.map(f => f.empresa);
        const nomeCons = e => (dados.consumo.find(x => x.empresa === e) || {}).nome || String(e);
        let acum = 0;
        let h = '<thead><tr><th class="l">Dia</th>' + dados.fabricas.map(e => '<th>Produção emp. ' + e + '</th>').join('') +
                '<th>Produção 10 + 16</th><th class="sep">Consumo das filiais</th><th>Diferença (10 + 16 − consumo)</th><th>Acumulado</th>' +
                filiais.map((e, i) => '<th' + (i === 0 ? ' class="sep"' : '') + '>Consumo ' + esc(nomeCons(e)) + '</th>').join('') +
                '</tr></thead><tbody>';
        s.forEach(x => {
            const dif = x.prod - x.cons; acum += dif;
            const w = new Date(x.dia + 'T12:00:00').getDay();
            h += '<tr class="' + (w === 0 || w === 6 ? 'fds' : '') + '"><td class="l">' + dRot(x.dia) + ' ' + dSem(x.dia) + '</td>' +
                 dados.fabricas.map(e => '<td>' + n0(x.fab[e]) + '</td>').join('') + '<td><b>' + n0(x.prod) + '</b></td>' +
                 '<td class="sep"><b>' + n0(x.cons) + '</b></td><td class="' + (dif < 0 ? 'neg' : '') + '">' + n0(dif) + '</td>' +
                 '<td class="' + (acum < 0 ? 'neg' : '') + '">' + n0(acum) + '</td>' +
                 filiais.map((e, i) => '<td' + (i === 0 ? ' class="sep"' : '') + '>' + n0(((x.porFilial[e] || {})[u]) || 0) + '</td>').join('') + '</tr>';
        });
        const totFil = e => s.reduce((a, x) => a + (((x.porFilial[e] || {})[u]) || 0), 0);
        const porFab = e => s.reduce((a, x) => a + x.fab[e], 0);
        h += '<tr class="tot"><td class="l">Total</td>' + dados.fabricas.map(e => '<td>' + n0(porFab(e)) + '</td>').join('') + '<td>' + n0(tot.prod) + '</td>' +
             '<td class="sep">' + n0(tot.cons) + '</td><td class="' + (tot.prod - tot.cons < 0 ? 'neg' : '') + '">' + n0(tot.prod - tot.cons) + '</td><td></td>' +
             filiais.map((e, i) => '<td' + (i === 0 ? ' class="sep"' : '') + '>' + n0(totFil(e)) + '</td>').join('') + '</tr></tbody>';
        document.getElementById('tblDia').innerHTML = h;
    }

    function tabelaResumo(id, linhas, lado) {
        const u = unid();
        if (!linhas.length) { document.getElementById(id).innerHTML = '<tbody><tr><td class="mf-vazio">Sem movimentos no período</td></tr></tbody>'; return; }
        let h = '<thead><tr><th class="l">Empresa</th><th>Molejos</th><th>Molinhas</th><th>Molinhas por molejo</th><th class="l">Itens</th></tr></thead><tbody>';
        let grupo = null, tm = 0, tl = 0;
        linhas.forEach((f, i) => {
            if (f.grupo && f.grupo !== grupo) { grupo = f.grupo; h += '<tr><td class="l" colspan="5" style="font-weight:700;color:#1565c0;background:#f7f9fc">' + esc(grupo) + '</td></tr>'; }
            tm += f.molejos; tl += f.molinhas;
            h += '<tr><td class="l">' + esc(f.nome) + '</td><td>' + n0(f.molejos) + '</td><td>' + n0(f.molinhas) + '</td><td>' +
                 (f.molejos ? n0(f.molinhas / f.molejos) : '-') + '</td><td class="l mf-det"><a data-lado="' + lado + '" data-i="' + i + '">ver ' + f.itens.length + '</a>' +
                 (f.semEstrutura ? ' <span class="sem">(' + f.semEstrutura + ' sem molinha)</span>' : '') + '</td></tr>';
        });
        if (lado === 'consumo') h += '<tr class="tot"><td class="l">Total</td><td>' + n0(tm) + '</td><td>' + n0(tl) + '</td><td>' + (tm ? n0(tl / tm) : '-') + '</td><td></td></tr>';
        const t = document.getElementById(id);
        t.innerHTML = h + '</tbody>';
        t.querySelectorAll('a[data-i]').forEach(a => a.addEventListener('click', () => detalhe(linhas[parseInt(a.dataset.i, 10)])));
    }

    function detalhe(f) {
        document.getElementById('detTitulo').textContent = 'Molejos — ' + f.nome + (f.grupo ? ' · ' + f.grupo : '');
        document.getElementById('tblDet').innerHTML = '<thead><tr><th class="l">Molejo</th><th class="l">Máscara</th><th class="l">Molinha (estrutura da empresa ' + dados.empresaEstrutura +
            ')</th><th>Molinhas por molejo</th><th>Molejos</th><th>Molinhas</th></tr></thead><tbody>' +
            f.itens.map(it => '<tr><td class="l">' + esc(it.cod_item + ' - ' + it.desc) + '</td><td class="l">' + esc(it.mascara || '') + '</td><td class="l">' +
                (it.molinha ? esc(it.molinha) : '<span class="sem">sem molinha ensacada</span>') + '</td><td>' + n2(it.por_unid) + '</td><td>' + n0(it.molejos) +
                '</td><td>' + n0(it.molinhas) + '</td></tr>').join('') + '</tbody>';
        const c = document.getElementById('cardDet');
        c.style.display = '';
        c.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function csv() {
        if (!dados) return;
        const q = v => { v = String(v ?? ''); return /[";\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
        const num = v => String(Math.round(Number(v || 0)));
        const lin = [['Dia', 'Lado', 'Empresa', 'Molejos', 'Molinhas'].join(';')];
        const rotLado = { P: 'Produção empresas de molas', C: 'Consumo das filiais' };
        const nome = e => { for (const k of ['producao', 'consumo']) { const x = dados[k].find(f => f.empresa == e); if (x) return x.nome; } return String(e); };
        dados.dias.forEach(d => ['P', 'C'].forEach(l => Object.entries(d[l] || {}).forEach(([e, v]) =>
            lin.push([d.dia.split('-').reverse().join('/'), rotLado[l], q(nome(e)), num(v.molejos), num(v.molinhas)].join(';')))));
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + lin.join('\n')], { type: 'text/csv;charset=utf-8' }));
        a.download = 'molejo_producao_x_consumo.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    document.getElementById('btnFiltrar').addEventListener('click', filtrar);
    document.getElementById('btnCsv').addEventListener('click', csv);
    document.getElementById('fUnid').addEventListener('change', () => { if (dados) desenhar(); });
    document.getElementById('detFechar').addEventListener('click', () => document.getElementById('cardDet').style.display = 'none');
    filtrar();
})();
</script>
<?= $render('footer') ?>
