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
    'pageTitle'  => 'Processos Industriais — Faturamento por Colaborador',
    'showNavbar' => true,
    'pageActive' => 'industrial-faturamento-colaborador',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.fc-wrap      { padding:12px; background:#f0f2f5; min-height:calc(100vh - 56px); box-sizing:border-box; }
.rel-card     { background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.08); padding:12px 18px; margin-bottom:12px; }
.rel-card .form-label { font-size:.75rem; font-weight:600; margin-bottom:2px; }
.fc-nota      { font-size:11px; color:#6b7280; font-style:italic; }

.fc-card      { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; margin-bottom:14px; }
.fc-head      { background:#1565c0; color:#fff; padding:7px 14px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
.fc-scroll    { overflow:auto; }
.fc-tbl                { border-collapse:separate; border-spacing:0; width:100%; min-width:max-content; }
.fc-tbl th             { background:#e3f2fd !important; color:#1565c0 !important; font-size:11px !important; font-weight:700 !important;
                         padding:5px 10px !important; text-align:center; white-space:nowrap; border-bottom:1px solid #bbdefb !important; }
.fc-tbl th.mes         { border-left:2px solid #90caf9 !important; font-size:12px !important; }
.fc-tbl th.loc         { text-align:left; min-width:220px; }
.fc-tbl td             { padding:4px 10px !important; font-size:12px !important; white-space:nowrap; border-bottom:1px solid #f0f0f0 !important;
                         text-align:right; font-variant-numeric:tabular-nums; }
.fc-tbl td.loc         { text-align:left; }
.fc-tbl td.ini         { border-left:2px solid #e3f2fd !important; }
.fc-tbl tr.tot td      { font-weight:700; background:#f5f8fe !important; border-bottom:2px solid #bbdefb !important; }
.fc-tbl tr.emp td.loc  { font-weight:600; }
.fc-tbl tr.emp:hover td { background:#f5f9ff !important; }
.fc-vazio     { text-align:center; color:#999; padding:16px; font-size:12px; }
</style>

<div class="fc-wrap">
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
        <div class="fc-nota mt-2">
            Faturamento: faturamento líquido do mês (contábil saída − ST + devoluções do tipo de nota 5 − ST, sem pedidos 28411905), pelo agrupador.
            Colaboradores: ativos no mês em centros de custo de produção, pelo segmento do centro de custo
            (110 Colchão, 120 Estofado, 130 Espuma, 140 Caixa Box). Caixa Box inclui Conjugado.
        </div>
    </div>

    <div id="resultado"><div class="fc-vazio">Carregando...</div></div>
</div>

<script>
(function () {
    const BASE = <?= json_encode($base) ?>;
    const API  = BASE + 'industrial-api-faturamento-colaborador';
    const MES  = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    let dados = null;

    const esc   = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const n0    = v => Math.round(Number(v || 0)).toLocaleString('pt-BR');
    const moeda = v => Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const rot   = m => m.slice(0, 4) + '-' + MES[parseInt(m.slice(5), 10) - 1];

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
            dados = await post(API, { ini, fim });
            desenhar();
            st.textContent = 'Atualizado em ' + ((Date.now() - t0) / 1000).toFixed(1) + 's';
        } catch (e) {
            st.textContent = '';
            document.getElementById('resultado').innerHTML = '<div class="fc-vazio" style="color:#c62828">' + esc(e.message) + '</div>';
        } finally {
            document.getElementById('btnFiltrar').disabled = false;
        }
    }

    function celulas(v) {
        return '<td class="ini">' + moeda(v.faturamento) + '</td><td>' + n0(v.colab) + '</td><td>' + (v.por == null ? '-' : moeda(v.por)) + '</td>';
    }

    function desenhar() {
        const meses = dados.meses;
        let h = '';
        dados.segmentos.forEach(s => {
            h += '<div class="fc-card"><div class="fc-head">Faturamento por Colaborador - ' + esc(s.nome.toUpperCase()) + '</div><div class="fc-scroll">';
            if (!s.linhas.length) { h += '<div class="fc-vazio">Sem faturamento no período</div></div></div>'; return; }
            h += '<table class="fc-tbl"><thead><tr><th class="loc" rowspan="2">Local</th>' +
                 meses.map(m => '<th class="mes" colspan="3">' + rot(m) + '</th>').join('') + '</tr><tr>' +
                 meses.map(() => '<th class="mes">Faturamento Líquido (R$)</th><th>Nr colaboradores</th><th>Por colaborador (R$)</th>').join('') +
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
        const q   = v => { v = String(v ?? ''); return /[";\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
        const num = v => v == null ? '' : Number(v).toFixed(2).replace('.', ',');
        const lin = [['Segmento', 'Local', 'Ano/Mês', 'Faturamento Líquido', 'Nr colaboradores', 'Por colaborador'].join(';')];
        dados.segmentos.forEach(s => {
            dados.meses.forEach(m => lin.push([q(s.nome), 'Totais', rot(m), num(s.totais[m].faturamento), s.totais[m].colab, num(s.totais[m].por)].join(';')));
            s.linhas.forEach(l => {
                dados.meses.forEach(m => lin.push([q(s.nome), q(l.nome), rot(m), num(l.meses[m].faturamento), l.meses[m].colab, num(l.meses[m].por)].join(';')));
            });
        });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + lin.join('\n')], { type: 'text/csv;charset=utf-8' }));
        a.download = 'faturamento_por_colaborador.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    document.getElementById('btnFiltrar').addEventListener('click', filtrar);
    document.getElementById('btnCsv').addEventListener('click', csv);
    filtrar();
})();
</script>
<?= $render('footer') ?>
