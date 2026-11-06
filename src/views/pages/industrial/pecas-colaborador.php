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
    'pageTitle'  => 'Processos Industriais — Peças por Colaborador',
    'showNavbar' => true,
    'pageActive' => 'industrial-pecas-colaborador',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.pc-wrap      { padding:12px; background:#f0f2f5; min-height:calc(100vh - 56px); box-sizing:border-box; }
.rel-card     { background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.08); padding:12px 18px; margin-bottom:12px; }
.rel-card .form-label { font-size:.75rem; font-weight:600; margin-bottom:2px; }
.pc-nota      { font-size:11px; color:#6b7280; font-style:italic; }

.pc-card      { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; margin-bottom:14px; }
.pc-head      { background:#1565c0; color:#fff; padding:7px 14px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
.pc-scroll    { overflow:auto; }
.pc-tbl                { border-collapse:separate; border-spacing:0; width:100%; min-width:max-content; }
.pc-tbl th             { background:#e3f2fd !important; color:#1565c0 !important; font-size:11px !important; font-weight:700 !important;
                         padding:5px 10px !important; text-align:center; white-space:nowrap; border-bottom:1px solid #bbdefb !important; }
.pc-tbl th.mes         { border-left:2px solid #90caf9 !important; font-size:12px !important; }
.pc-tbl th.loc         { text-align:left; min-width:220px; }
.pc-tbl td             { padding:4px 10px !important; font-size:12px !important; white-space:nowrap; border-bottom:1px solid #f0f0f0 !important;
                         text-align:right; font-variant-numeric:tabular-nums; }
.pc-tbl td.loc         { text-align:left; }
.pc-tbl td.ini         { border-left:2px solid #e3f2fd !important; }
.pc-tbl tr.tot td      { font-weight:700; background:#f5f8fe !important; border-bottom:2px solid #bbdefb !important; }
.pc-tbl tr.emp td.loc  { font-weight:600; }
.pc-tbl tr.emp.clk td  { cursor:pointer; }
.pc-tbl tr.emp:hover td { background:#f5f9ff !important; }
.pc-tbl tr.cc td       { font-size:11px !important; color:#555; background:#fafbfd !important; }
.pc-tbl tr.cc td.loc   { padding-left:34px !important; }
.pc-tbl .tg            { display:inline-block; width:16px; color:#1565c0; }
.pc-tbl .sem           { color:#bbb; }
.pc-vazio     { text-align:center; color:#999; padding:16px; font-size:12px; }
</style>

<div class="pc-wrap">
    <div class="rel-card">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">Ano/Mês inicial</label>
                <input type="month" id="fIni" class="form-control form-control-sm" value="<?= $mesPadrao ?>">
            </div>
            <div class="col-auto">
                <label class="form-label">Ano/Mês final</label>
                <input type="month" id="fFim" class="form-control form-control-sm" value="<?= $mesPadrao ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" id="btnFiltrar"><i class="bi bi-search"></i> Filtrar</button>
                <button class="btn btn-sm btn-outline-secondary" id="btnCsv"><i class="bi bi-download"></i> CSV</button>
            </div>
            <div class="col">
                <span id="status" style="font-size:12px;color:#666"></span>
            </div>
        </div>
        <div class="pc-nota mt-2">
            Peças: produção do mês (entradas EPP − estornos PPE, mesma regra do relatório de produção industrial).
            Colaboradores: ativos no mês em centros de custo de produção, pelo segmento do centro de custo
            (110 Colchão, 120 Estofado, 130 Espuma, 140 Caixa Box). Caixa Box inclui as peças de Conjugado.
        </div>
    </div>

    <div id="resultado"><div class="pc-vazio">Carregando...</div></div>
</div>

<script>
(function () {
    const BASE = <?= json_encode($base) ?>;
    const API  = BASE + 'industrial-api-pecas-colaborador';
    const MES  = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    let dados = null;

    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const n0  = v => Math.round(Number(v || 0)).toLocaleString('pt-BR');
    const rot = m => m.slice(0, 4) + '-' + MES[parseInt(m.slice(5), 10) - 1];

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
        st.textContent = 'Carregando... (o primeiro carregamento de cada mês pode levar alguns segundos)';
        document.getElementById('btnFiltrar').disabled = true;
        const t0 = Date.now();
        try {
            // Um mês por requisição (o servidor guarda cada mês em cache): assim nenhuma requisição
            // passa do tempo limite do servidor (504 Gateway Time-out) em períodos de vários meses.
            const meses = [];
            for (let [a, m] = ini.split('-').map(Number); a * 12 + m <= Number(fim.slice(0, 4)) * 12 + Number(fim.slice(5, 7)) && meses.length <= 12; m++) {
                if (m > 12) { m = 1; a++; }
                meses.push(a + '-' + String(m).padStart(2, '0'));
            }
            if (meses.length > 1 && meses.length <= 12) {
                for (let i = 0; i < meses.length; i++) {
                    st.textContent = 'Carregando ' + meses[i].slice(5) + '/' + meses[i].slice(0, 4) + ' (' + (i + 1) + ' de ' + meses.length + ')...';
                    await post(API, { ini: meses[i], fim: meses[i] });
                }
                st.textContent = 'Montando o período...';
            }
            dados = await post(API, { ini, fim });
            desenhar();
            st.textContent = 'Atualizado em ' + ((Date.now() - t0) / 1000).toFixed(1) + 's';
        } catch (e) {
            st.textContent = '';
            document.getElementById('resultado').innerHTML = '<div class="pc-vazio" style="color:#c62828">' + esc(e.message) + '</div>';
        } finally {
            document.getElementById('btnFiltrar').disabled = false;
        }
    }

    function celulas(v) {
        return '<td class="ini">' + n0(v.pecas) + '</td><td>' + n0(v.colab) + '</td><td>' + (v.por == null ? '-' : n0(v.por)) + '</td>';
    }

    function desenhar() {
        const meses = dados.meses;
        let h = '';
        dados.segmentos.forEach(s => {
            h += '<div class="pc-card"><div class="pc-head">Peças Produzidas por Colaborador - ' + esc(s.nome.toUpperCase()) + '</div><div class="pc-scroll">';
            if (!s.linhas.length) { h += '<div class="pc-vazio">Sem produção no período</div></div></div>'; return; }
            h += '<table class="pc-tbl"><thead><tr><th class="loc" rowspan="2">Local</th>' +
                 meses.map(m => '<th class="mes" colspan="3">' + rot(m) + '</th>').join('') + '</tr><tr>' +
                 meses.map(() => '<th class="mes">Peças Produzidas</th><th>Nr colaboradores</th><th>Por colaborador</th>').join('') +
                 '</tr></thead><tbody>';
            h += '<tr class="tot"><td class="loc">Totais</td>' + meses.map(m => celulas(s.totais[m])).join('') + '</tr>';
            s.linhas.forEach(l => {
                h += '<tr class="emp"><td class="loc">' + esc(l.nome) + '</td>' + meses.map(m => celulas(l.meses[m])).join('') + '</tr>';
            });
            h += '</tbody></table></div></div>';
        });
        document.getElementById('resultado').innerHTML = h;
    }

    function csv() {
        if (!dados) return;
        const q = v => { v = String(v ?? ''); return /[";\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
        const num = v => v == null ? '' : String(Math.round(Number(v)));
        const lin = [['Segmento', 'Local', 'Ano/Mês', 'Peças Produzidas', 'Nr colaboradores', 'Por colaborador'].join(';')];
        dados.segmentos.forEach(s => {
            dados.meses.forEach(m => lin.push([q(s.nome), 'Totais', rot(m), num(s.totais[m].pecas), num(s.totais[m].colab), num(s.totais[m].por)].join(';')));
            s.linhas.forEach(l => {
                dados.meses.forEach(m => lin.push([q(s.nome), q(l.nome), rot(m), num(l.meses[m].pecas), num(l.meses[m].colab), num(l.meses[m].por)].join(';')));
            });
        });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + lin.join('\n')], { type: 'text/csv;charset=utf-8' }));
        a.download = 'pecas_por_colaborador.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    document.getElementById('btnFiltrar').addEventListener('click', filtrar);
    document.getElementById('btnCsv').addEventListener('click', csv);
    filtrar();
})();
</script>
<?= $render('footer') ?>
