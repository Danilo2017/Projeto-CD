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
    'pageTitle'  => 'Processos Industriais — Consumo de Molinha',
    'showNavbar' => true,
    'pageActive' => 'industrial-consumo-molinha',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.cm-wrap      { padding:12px; background:#f0f2f5; min-height:calc(100vh - 56px); box-sizing:border-box; }
.rel-card     { background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.08); padding:12px 18px; margin-bottom:12px; }
.rel-card .form-label { font-size:.75rem; font-weight:600; margin-bottom:2px; }
.cm-nota      { font-size:11px; color:#6b7280; font-style:italic; }

.cm-card      { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; margin-bottom:14px; }
.cm-head      { background:#1565c0; color:#fff; padding:7px 14px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px;
                display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
.cm-head .tot { font-weight:400; text-transform:none; letter-spacing:0; }
.cm-scroll    { overflow:auto; }
.cm-tbl                { border-collapse:separate; border-spacing:0; width:100%; min-width:max-content; }
.cm-tbl th             { background:#e3f2fd !important; color:#1565c0 !important; font-size:11px !important; font-weight:700 !important;
                         padding:5px 10px !important; text-align:right; white-space:nowrap; border-bottom:1px solid #bbdefb !important; }
.cm-tbl th.mes         { text-align:center; border-left:2px solid #90caf9 !important; font-size:12px !important; }
.cm-tbl th.ini, .cm-tbl td.ini { border-left:2px solid #e3f2fd !important; }
.cm-tbl th.l, .cm-tbl td.l { text-align:left; }
.cm-tbl td             { padding:4px 10px !important; font-size:12px !important; white-space:nowrap; border-bottom:1px solid #f0f0f0 !important;
                         text-align:right; font-variant-numeric:tabular-nums; }
.cm-tbl tr.tot td      { font-weight:700; background:#f5f8fe !important; border-top:2px solid #bbdefb !important; }
.cm-tbl tbody tr:hover td { background:#f5f9ff !important; }
.cm-tbl .sem           { color:#c62828; font-style:italic; }
.cm-tbl .tag           { display:inline-block; font-size:10px; font-weight:700; padding:0 6px; border-radius:4px; background:#eef1f6; color:#52514e; }
.cm-det-btn   { font-size:12px; padding:6px 14px; border-top:1px solid #eef1f6; }
.cm-det-btn a { cursor:pointer; color:#1565c0; text-decoration:none; font-weight:600; }
.cm-vazio     { text-align:center; color:#999; padding:16px; font-size:12px; }
</style>

<div class="cm-wrap">
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
            <div class="col"><span id="status" style="font-size:12px;color:#666"></span></div>
        </div>
        <div class="cm-nota mt-2">
            Produção: fábricas de molas (empresas 10 e 16), entradas EPP − estornos PPE pela data do sistema, sem fator de conversão e sem filtro de classificação.
            <b>Produzidas</b> = produção da própria molinha. <b>Consumidas nos molejos</b> = molejos produzidos × molinhas por unidade da estrutura
            (TGAZIN_ADMIN_TANQUE_MOLA). <b>Diferença</b> = produzidas − consumidas nos molejos (semi-acabados, cordões, estoque ou envio).
        </div>
    </div>

    <div id="resultado"><div class="cm-vazio">Carregando...</div></div>
</div>

<script>
(function () {
    const BASE = <?= json_encode($base) ?>;
    const API  = BASE + 'industrial-api-consumo-molinha';
    const MES  = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    const TIPO = { MOLINHA: 'Molinha', MOLEJO: 'Molejo', OUTRO: 'Outro' };
    let dados = null;

    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const n0  = v => v == null ? '-' : Math.round(Number(v)).toLocaleString('pt-BR');
    const n2  = v => v == null ? '-' : Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
    const rot = m => m.slice(0, 4) + '-' + MES[parseInt(m.slice(5), 10) - 1];
    const somaCampo = (meses, c) => Object.values(meses).reduce((a, v) => a + v[c], 0);

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
            document.getElementById('resultado').innerHTML = '<div class="cm-vazio" style="color:#c62828">' + esc(e.message) + '</div>';
        } finally {
            document.getElementById('btnFiltrar').disabled = false;
        }
    }

    const tres = v => '<td class="ini">' + n0(v.produzidas) + '</td><td>' + n0(v.consumo) + '</td><td>' + n0(v.produzidas - v.consumo) + '</td>';

    function desenhar() {
        const meses = dados.meses, varios = meses.length > 1;
        const cols = varios ? meses.concat(['__TOTAL__']) : meses;
        let h = '';
        dados.filiais.forEach((f, i) => {
            const pr = somaCampo(f.total, 'produzidas'), co = somaCampo(f.total, 'consumo');
            const mj = Object.values(f.molejos).reduce((a, b) => a + b, 0);
            h += '<div class="cm-card"><div class="cm-head"><span>Molinhas - ' + esc(f.nome) + '</span>' +
                 '<span class="tot">No período: <b>' + n0(mj) + '</b> molejos produzidos · <b>' + n0(pr) + '</b> molinhas produzidas · <b>' +
                 n0(co) + '</b> consumidas nos molejos</span></div><div class="cm-scroll">';
            if (!f.tipos.length && !f.itens.length) { h += '<div class="cm-vazio">Sem produção de molas no período</div></div></div>'; return; }

            const val = (meses_, c) => c === '__TOTAL__' ? { produzidas: somaCampo(meses_, 'produzidas'), consumo: somaCampo(meses_, 'consumo') } : meses_[c];
            h += '<table class="cm-tbl"><thead><tr><th class="l" rowspan="2">Tipo de molinha</th>' +
                 cols.map(c => '<th class="mes" colspan="3">' + (c === '__TOTAL__' ? 'Total' : rot(c)) + '</th>').join('') + '</tr><tr>' +
                 cols.map(() => '<th class="ini">Produzidas</th><th>Consumidas nos molejos</th><th>Diferença</th>').join('') + '</tr></thead><tbody>';
            f.tipos.forEach(t => {
                h += '<tr><td class="l">' + esc(t.cod + ' - ' + t.molinha) + '</td>' + cols.map(c => tres(val(t.meses, c))).join('') + '</tr>';
            });
            h += '<tr class="tot"><td class="l">Total</td>' + cols.map(c => tres(val(f.total, c))).join('') + '</tr>';
            h += '<tr><td class="l"><b>Molejos produzidos</b></td>' + cols.map(c =>
                     '<td class="ini" colspan="3" style="text-align:center"><b>' + n0(c === '__TOTAL__' ? mj : f.molejos[c]) + '</b></td>').join('') + '</tr></tbody></table>';

            h += '<div class="cm-det-btn"><a data-det="' + i + '">▸ Ver itens produzidos (' + f.itens.length + ')</a>' +
                 (f.semEstrutura ? ' <span style="margin-left:10px;color:#c62828;font-style:italic">' + f.semEstrutura + ' molejo(s) sem estrutura de molinha</span>' : '') + '</div>';
            h += '<div id="det' + i + '" style="display:none"><table class="cm-tbl"><thead><tr><th class="l">Tipo</th><th class="l">Item</th><th class="l">Máscara</th>' +
                 '<th class="l">Molinha</th><th>Molinhas por unidade</th><th>Qtde produzida</th><th>Molinhas produzidas</th><th>Consumidas nos molejos</th></tr></thead><tbody>' +
                 f.itens.map(it => '<tr><td class="l"><span class="tag">' + TIPO[it.tipo] + '</span></td><td class="l">' + esc(it.cod_item + ' - ' + it.desc) + '</td><td class="l">' +
                     esc(it.mascara || '') + '</td><td class="l">' +
                     (it.molinha ? esc(it.molinha) : (it.tipo === 'MOLEJO' ? '<span class="sem">sem estrutura</span>' : '-')) + '</td><td>' + n2(it.por_unid) + '</td><td>' +
                     n2(it.produzido) + '</td><td>' + (it.produzidas ? n0(it.produzidas) : '-') + '</td><td>' + (it.consumo ? n0(it.consumo) : '-') + '</td></tr>').join('') +
                 '</tbody></table></div>';
            h += '</div></div>';
        });
        const r = document.getElementById('resultado');
        r.innerHTML = h;
        r.querySelectorAll('a[data-det]').forEach(a => a.addEventListener('click', () => {
            const d = document.getElementById('det' + a.dataset.det), abrir = d.style.display === 'none';
            d.style.display = abrir ? '' : 'none';
            a.textContent = (abrir ? '▾ Ocultar' : '▸ Ver') + ' itens produzidos (' + dados.filiais[a.dataset.det].itens.length + ')';
        }));
    }

    function csv() {
        if (!dados) return;
        const q   = v => { v = String(v ?? ''); return /[";\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
        const num = v => v == null ? '' : String(Math.round(Number(v)));
        const dec = v => v == null ? '' : String(Number(v).toFixed(2)).replace('.', ',');
        const lin = [['Filial', 'Tipo de molinha', 'Ano/Mês', 'Produzidas', 'Consumidas nos molejos', 'Diferença'].join(';')];
        dados.filiais.forEach(f => f.tipos.forEach(t => dados.meses.forEach(m => {
            const v = t.meses[m];
            lin.push([q(f.nome), q(t.cod + ' - ' + t.molinha), rot(m), num(v.produzidas), num(v.consumo), num(v.produzidas - v.consumo)].join(';'));
        })));
        lin.push('');
        lin.push(['Filial', 'Ano/Mês', 'Molejos produzidos'].join(';'));
        dados.filiais.forEach(f => dados.meses.forEach(m => lin.push([q(f.nome), rot(m), num(f.molejos[m])].join(';'))));
        lin.push('');
        lin.push(['Filial', 'Tipo', 'Item', 'Máscara', 'Molinha', 'Molinhas por unidade', 'Qtde produzida', 'Molinhas produzidas', 'Consumidas nos molejos'].join(';'));
        dados.filiais.forEach(f => f.itens.forEach(it => lin.push([q(f.nome), TIPO[it.tipo], q(it.cod_item + ' - ' + it.desc), q(it.mascara), q(it.molinha || ''),
            dec(it.por_unid), dec(it.produzido), num(it.produzidas), num(it.consumo)].join(';'))));
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + lin.join('\n')], { type: 'text/csv;charset=utf-8' }));
        a.download = 'consumo_molinha.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    document.getElementById('btnFiltrar').addEventListener('click', filtrar);
    document.getElementById('btnCsv').addEventListener('click', csv);
    filtrar();
})();
</script>
<?= $render('footer') ?>
