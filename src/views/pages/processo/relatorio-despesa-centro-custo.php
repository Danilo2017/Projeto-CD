<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
/** @var array    $empresas */
$acessoProcesso = $is_admin || in_array('processo', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoProcesso) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Processo — Despesa por Centro de Custo',
    'showNavbar' => true,
    'pageActive' => 'processo-relatorio-despesa-centro-custo',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.rel-card { background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);padding:18px 22px;margin-bottom:14px; }

.dcc-card      { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; }
.dcc-card-head { background:#1565c0; color:#fff; padding:8px 14px; font-size:11px;
                 font-weight:700; text-transform:uppercase; letter-spacing:.4px;
                 display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; }
.dcc-tbl       { border-collapse:collapse; font-size:12px; table-layout:fixed; width:100%; min-width:1100px; }
.dcc-tbl th    { background:#e3f2fd; color:#1565c0; padding:6px 10px; font-size:11px; font-weight:700;
                 white-space:nowrap; border-bottom:2px solid #bbdefb; text-align:left; }
.dcc-tbl th.r  { text-align:right; }
.dcc-tbl td    { padding:5px 10px; border-bottom:1px solid #f0f0f0;
                 overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.dcc-tbl td.r  { text-align:right; }

/* ── Nível 1: Centro de Custo ── */
.row-l1 td              { font-weight:700; background:#e8edf5; color:#1e3a8a; border-top:2px solid #c7d2e8; cursor:pointer; }
.row-l1:hover td        { background:#dce4f5 !important; }
/* ── Nível 2: Conta ── */
.row-l2 td              { font-weight:600; background:#f0f4fb; color:#1565c0; cursor:pointer; }
.row-l2 td:first-child  { padding-left:28px; }
.row-l2:hover td        { background:#e3ecf8 !important; }
/* ── Nível 3: Lançamento ── */
.row-l3 td              { background:#fff; color:#374151; cursor:pointer; }
.row-l3 td:first-child  { padding-left:48px; }
.row-l3:hover td        { background:#f5f8fe !important; }
.row-l3.loading td      { opacity:.6; }
/* ── Nível 4: Nota / Itens ── */
.row-det > td           { background:#fafbfd; padding:8px 12px 12px 68px; white-space:normal; }
.nf-box                 { border:1px solid #dbe3ef; border-radius:6px; background:#fff; margin-bottom:8px; overflow:hidden; }
.nf-head                { background:#f3f6fb; padding:6px 10px; font-size:12px; display:flex; flex-wrap:wrap; gap:4px 18px; }
.nf-head b              { color:#1e3a8a; }
.nf-head .lbl           { color:#6b7280; font-size:11px; }
.nf-itens               { width:100%; border-collapse:collapse; font-size:11px; }
.nf-itens th            { background:#fff; color:#6b7280; font-weight:600; padding:4px 10px; border-bottom:1px solid #e5e7eb; text-align:left; }
.nf-itens th.r, .nf-itens td.r { text-align:right; }
.nf-itens td            { padding:3px 10px; border-bottom:1px solid #f3f4f6; }
.nf-vazio               { color:#9ca3af; font-size:12px; font-style:italic; }

.row-total td           { font-weight:700; background:#1e3a8a !important; color:#fff !important; }

/* Linha aberta: marca à esquerda */
.expanded > td:first-child { box-shadow:inset 3px 0 0 #1565c0; }
.btn-exp-all { font-size:11px; padding:3px 10px; }
.val-neg     { color:#dc3545; }
.dcc-msg     { text-align:center; padding:24px; color:#999; font-size:13px; }
.tag-tp      { display:inline-block; padding:0 6px; border-radius:4px; background:#eef2ff; color:#3730a3; font-size:10px; font-weight:600; }
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:12px;margin:0;">

    <div class="rel-card">
        <h5 class="mb-3 fw-semibold"><i class="bi bi-diagram-3 me-1 text-primary"></i> Despesa por Centro de Custo</h5>
        <div class="row g-3 align-items-end">
            <div class="col-auto">
                <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Data Início</label>
                <input type="date" id="inpDtIni" class="form-control form-control-sm" style="max-width:160px;">
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Data Fim</label>
                <input type="date" id="inpDtFim" class="form-control form-control-sm" style="max-width:160px;">
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Empresa</label>
                <select id="inpEmpresa" class="form-select" style="max-width:220px;font-size:.8rem;height:31px;padding-top:.25rem;padding-bottom:.25rem;">
                    <option value="">Selecione...</option>
                    <?php foreach ($empresas as $emp): ?>
                        <option value="<?= intval($emp['CODIGO']) ?>" <?= intval($emp['CODIGO']) === 1 ? 'selected' : '' ?>>
                            FL <?= htmlspecialchars($emp['CODIGO']) ?> — <?= htmlspecialchars($emp['RAZAO_SOCIAL']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Cód. Centro de Custo</label>
                <input type="text" id="inpCC" class="form-control form-control-sm" style="max-width:170px;" placeholder="Ex: 19014, 19015">
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Grupo Conta</label>
                <input type="text" id="inpGrupo" class="form-control form-control-sm" style="max-width:90px;" value="4" placeholder="Todos">
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Cód. Reduzido</label>
                <input type="text" id="inpRed" class="form-control form-control-sm" style="max-width:150px;" placeholder="Ex: 2677">
            </div>
            <div class="col-auto">
                <button id="btnGerar" class="btn btn-sm btn-primary">
                    <i class="bi bi-search"></i> Gerar
                </button>
                <button id="btnExcel" class="btn btn-sm btn-success ms-2" style="display:none;">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </button>
            </div>
            <div class="col">
                <span id="msgRelatorio"></span>
                <span id="spanTotalReg" class="text-muted small ms-3"></span>
            </div>
        </div>
    </div>

    <div class="dcc-card">
        <div class="dcc-card-head">
            <span>Centro de Custo / Conta / Lançamento — clique no lançamento para ver a nota</span>
            <div style="display:flex;gap:6px">
                <button class="btn btn-sm btn-outline-light btn-exp-all" id="btnExpandir">+ Expandir tudo</button>
                <button class="btn btn-sm btn-outline-light btn-exp-all" id="btnRecolher">− Recolher tudo</button>
            </div>
        </div>
        <div style="overflow-x:auto">
            <table class="dcc-tbl">
                <colgroup>
                    <col style="width:400px">
                    <col style="width:95px">
                    <col style="width:70px">
                    <col>
                    <col style="width:120px">
                    <col style="width:120px">
                    <col style="width:130px">
                </colgroup>
                <thead>
                    <tr>
                        <th>Centro de Custo / Conta / Lançamento</th>
                        <th>Dt. Lcto</th>
                        <th>Tp. Lcto</th>
                        <th>Histórico</th>
                        <th class="r">Débito (R$)</th>
                        <th class="r">Crédito (R$)</th>
                        <th class="r">Saldo (R$)</th>
                    </tr>
                </thead>
                <tbody id="dccBody">
                    <tr><td colspan="7" class="dcc-msg">Informe os filtros e clique em Gerar.</td></tr>
                </tbody>
                <tfoot id="dccFoot"></tfoot>
            </table>
        </div>
    </div>

</div>

<script>
(function () {
    'use strict';

    const btnGerar  = document.getElementById('btnGerar');
    const btnExcel  = document.getElementById('btnExcel');
    const msgEl     = document.getElementById('msgRelatorio');
    const spanTotal = document.getElementById('spanTotalReg');
    const body      = document.getElementById('dccBody');
    const foot      = document.getElementById('dccFoot');

    let _rows = [];
    const _cacheNf = {};

    (function () {
        const hoje = new Date();
        const pad  = n => String(n).padStart(2, '0');
        document.getElementById('inpDtIni').value = `${hoje.getFullYear()}-${pad(hoje.getMonth() + 1)}-01`;
        document.getElementById('inpDtFim').value = `${hoje.getFullYear()}-${pad(hoje.getMonth() + 1)}-${pad(hoje.getDate())}`;
    })();

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }

    function fmt2(v) {
        return parseFloat(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmt4(v) {
        return parseFloat(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
    }

    function fmtSaldo(v) {
        const n = parseFloat(v ?? 0);
        return n < 0 ? `<span class="val-neg">${fmt2(n)}</span>` : fmt2(n);
    }

    function fmtCnpj(v) {
        const d = String(v ?? '').replace(/\D/g, '');
        if (d.length === 14) return d.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
        if (d.length === 11) return d.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
        return v ?? '';
    }

    function novoTot() { return { deb: 0, cre: 0 }; }
    function somar(t, r) {
        const v = parseFloat(r.VALOR ?? 0);
        if (r.NATUREZA === 'D') t.deb += v; else t.cre += -v;
    }
    function celulasTot(t) {
        return `<td class="r">${fmt2(t.deb)}</td><td class="r">${fmt2(t.cre)}</td><td class="r">${fmtSaldo(t.deb - t.cre)}</td>`;
    }

    // Centro de Custo → Conta → Lançamento
    function montarArvore(rows) {
        const ccs = new Map();
        rows.forEach(r => {
            const kCc = String(r.COD_CC ?? '');
            if (!ccs.has(kCc)) ccs.set(kCc, { cod: r.COD_CC, desc: r.DESC_CC, tot: novoTot(), contas: new Map() });
            const cc = ccs.get(kCc);

            const kCta = `${r.CTA}|${r.COD_RED}`;
            if (!cc.contas.has(kCta)) cc.contas.set(kCta, { cta: r.CTA, red: r.COD_RED, desc: r.DESC_CONTA, tot: novoTot(), lctos: [] });
            const cta = cc.contas.get(kCta);

            somar(cc.tot, r);
            somar(cta.tot, r);
            cta.lctos.push(r);
        });
        return ccs;
    }

    function renderTabela(rows) {
        const arvore = montarArvore(rows);
        const total  = novoTot();
        let html = '';
        let i = 0;

        arvore.forEach(cc => {
            const k1 = `c${i++}`;
            total.deb += cc.tot.deb;
            total.cre += cc.tot.cre;
            const nomeCc = cc.cod ? `${esc(cc.cod)} — ${esc(cc.desc)}` : 'Sem centro de custo';
            html += `<tr class="row-l1" data-k="${k1}">
                <td colspan="4">${nomeCc} <span style="font-weight:400;color:#64748b">(${cc.contas.size} conta(s))</span></td>
                ${celulasTot(cc.tot)}
            </tr>`;

            let j = 0;
            cc.contas.forEach(cta => {
                const k2 = `${k1}-a${j++}`;
                html += `<tr class="row-l2" data-k="${k2}" data-p="${k1}" style="display:none">
                    <td colspan="4" title="${esc(cta.desc)}">${esc(cta.cta)} <span style="font-weight:400">(${esc(cta.red)})</span> — ${esc(cta.desc)}</td>
                    ${celulasTot(cta.tot)}
                </tr>`;

                cta.lctos.forEach((r, n) => {
                    const t = novoTot();
                    somar(t, r);
                    html += `<tr class="row-l3" data-k="${k2}-l${n}" data-p="${k2}" data-id="${esc(r.LCTO_ID)}" style="display:none">
                        <td>Lcto ${esc(r.LCTO)}${r.NATUREZA === 'C' ? ' <span class="val-neg" style="font-size:10px">(crédito)</span>' : ''}</td>
                        <td>${esc(r.DATA)}</td>
                        <td><span class="tag-tp">${esc(r.TP_LCTO)}</span></td>
                        <td title="${esc(r.HISTORICO)}">${esc(r.HISTORICO)}</td>
                        ${celulasTot(t)}
                    </tr>`;
                });
            });
        });

        body.innerHTML = html;
        foot.innerHTML = `<tr class="row-total"><td colspan="4">Total geral</td>${celulasTot(total)}</tr>`;
    }

    function renderNotas(rows) {
        if (!rows.length) return '<span class="nf-vazio">Nenhuma nota fiscal de entrada vinculada a este lançamento.</span>';

        const notas = new Map();
        rows.forEach(r => {
            if (!notas.has(r.NFE_ID)) notas.set(r.NFE_ID, { nf: r, itens: [] });
            if (r.ITNFE_ID) notas.get(r.NFE_ID).itens.push(r);
        });

        let html = '';
        notas.forEach(({ nf, itens }) => {
            const totItens = itens.reduce((s, it) => s + parseFloat(it.VALOR ?? 0), 0);
            html += `<div class="nf-box">
                <div class="nf-head">
                    <span><span class="lbl">NF</span> <b>${esc(nf.NUM_NF)}${nf.SERIE ? '-' + esc(nf.SERIE) : ''}</b></span>
                    <span><span class="lbl">Fornecedor</span> <b>${esc(nf.FORNECEDOR)}</b></span>
                    <span><span class="lbl">CNPJ/CPF</span> ${esc(fmtCnpj(nf.CNPJ_CPF))}</span>
                    <span><span class="lbl">Emissão</span> ${esc(nf.DT_EMIS)}</span>
                    <span><span class="lbl">Entrada</span> ${esc(nf.DT_ENT)}</span>
                </div>
                <table class="nf-itens">
                    <thead><tr>
                        <th style="width:50px">Item</th>
                        <th style="width:100px">Cód. Item</th>
                        <th>Descrição</th>
                        <th style="width:110px">Máscara</th>
                        <th style="width:50px">Unid.</th>
                        <th class="r" style="width:100px">Qtde</th>
                        <th class="r" style="width:120px">Vlr. Unit. (R$)</th>
                        <th class="r" style="width:120px">Valor (R$)</th>
                    </tr></thead>
                    <tbody>
                        ${itens.map(it => `<tr>
                            <td>${esc(it.NUM_ITE ?? '')}</td>
                            <td>${esc(it.COD_ITEM ?? '-')}</td>
                            <td>${esc(it.DESC_ITEM ?? '-')}</td>
                            <td>${esc(it.MASCARA ?? '')}</td>
                            <td>${esc(it.UNID ?? '')}</td>
                            <td class="r">${it.QTDE != null ? fmt4(it.QTDE) : '-'}</td>
                            <td class="r">${it.PRECO_UNIT != null ? fmt2(it.PRECO_UNIT) : '-'}</td>
                            <td class="r">${it.VALOR != null ? fmt2(it.VALOR) : '-'}</td>
                        </tr>`).join('')}
                        <tr><td colspan="7" style="text-align:right;font-weight:600">Total itens</td><td class="r" style="font-weight:600">${fmt2(totItens)}</td></tr>
                    </tbody>
                </table>
            </div>`;
        });
        return html;
    }

    function esconderDescendentes(k) {
        body.querySelectorAll(`tr[data-k^="${k}-"]`).forEach(tr => {
            tr.style.display = 'none';
            tr.classList.remove('expanded');
        });
    }

    async function abrirLancamento(tr) {
        const k  = tr.dataset.k;
        const id = tr.dataset.id;
        let det = body.querySelector(`tr[data-k="${k}-d"]`);

        if (tr.classList.contains('expanded')) {
            tr.classList.remove('expanded');
            if (det) det.style.display = 'none';
            return;
        }
        tr.classList.add('expanded');
        if (det) { det.style.display = ''; return; }

        tr.classList.add('loading');
        let conteudo;
        try {
            if (!_cacheNf[id]) {
                const res  = await fetch('processo-api-relatorio-despesa-centro-custo-nf', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ lcto_id: id }),
                });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                _cacheNf[id] = Array.isArray(data.rows) ? data.rows : [];
            }
            conteudo = renderNotas(_cacheNf[id]);
        } catch (e) {
            conteudo = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${esc(e.message)}</span>`;
        } finally {
            tr.classList.remove('loading');
        }

        det = document.createElement('tr');
        det.className = 'row-det';
        det.dataset.k = `${k}-d`;
        det.dataset.p = k;
        det.innerHTML = `<td colspan="7">${conteudo}</td>`;
        tr.after(det);
        if (!tr.classList.contains('expanded')) det.style.display = 'none';
    }

    body.addEventListener('click', function (ev) {
        const tr = ev.target.closest('tr.row-l1, tr.row-l2, tr.row-l3');
        if (!tr) return;

        if (tr.classList.contains('row-l3')) { abrirLancamento(tr); return; }

        const k = tr.dataset.k;
        if (tr.classList.contains('expanded')) {
            tr.classList.remove('expanded');
            esconderDescendentes(k);
        } else {
            tr.classList.add('expanded');
            body.querySelectorAll(`tr[data-p="${k}"]`).forEach(c => c.style.display = '');
        }
    });

    // Expande centro de custo e conta (não abre as notas, que são buscadas sob demanda)
    document.getElementById('btnExpandir').addEventListener('click', function () {
        body.querySelectorAll('tr.row-l1, tr.row-l2').forEach(tr => { tr.style.display = ''; tr.classList.add('expanded'); });
        body.querySelectorAll('tr.row-l3').forEach(tr => tr.style.display = '');
    });

    document.getElementById('btnRecolher').addEventListener('click', function () {
        body.querySelectorAll('tr.row-l1').forEach(tr => { tr.classList.remove('expanded'); esconderDescendentes(tr.dataset.k); });
    });

    btnExcel.addEventListener('click', function () {
        if (!_rows.length) return;
        const dtIni = document.getElementById('inpDtIni').value;
        const dtFim = document.getElementById('inpDtFim').value;

        const headers = ['Emp.','Cód. CC','Desc. CC','Conta','Cód. Reduzido','Desc. Conta','Custo Fixo','Lançamento','Dt. Lançamento','Tp. Lcto','Histórico','Natureza','Valor'];
        const linhas  = _rows.map(r => [
            r.COD_EMP, r.COD_CC, r.DESC_CC, r.CTA, r.COD_RED, r.DESC_CONTA, r.CUSTO_FIXO,
            r.LCTO, r.DATA, r.TP_LCTO, r.HISTORICO,
            r.NATUREZA === 'D' ? 'DÉBITO' : 'CRÉDITO',
            fmt2(r.VALOR),
        ]);

        const csv = [
            headers.join(';'),
            ...linhas.map(cols => cols.map(v => `"${(v ?? '').toString().replace(/"/g, '""')}"`).join(';'))
        ].join('\r\n');

        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = `despesa-centro-custo_${dtIni}_${dtFim}.csv`;
        a.click(); URL.revokeObjectURL(url);
    });

    btnGerar.addEventListener('click', async function () {
        const dtIni  = document.getElementById('inpDtIni').value;
        const dtFim  = document.getElementById('inpDtFim').value;
        const codEmp = parseInt(document.getElementById('inpEmpresa').value) || 0;

        if (!dtIni || !dtFim) { alert('Informe as datas de início e fim.'); return; }
        if (!codEmp) { alert('Selecione a empresa.'); return; }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        msgEl.innerHTML = '';
        spanTotal.textContent = '';
        btnExcel.style.display = 'none';
        body.innerHTML = '<tr><td colspan="7" class="dcc-msg">Carregando...</td></tr>';
        foot.innerHTML = '';

        try {
            const res  = await fetch('processo-api-relatorio-despesa-centro-custo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    dt_ini:      dtIni,
                    dt_fim:      dtFim,
                    cod_emp:     codEmp,
                    cc:          document.getElementById('inpCC').value,
                    grupo_conta: document.getElementById('inpGrupo').value,
                    cod_red:     document.getElementById('inpRed').value,
                }),
            });
            const data = await res.json();

            if (data.error) {
                msgEl.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${esc(data.error)}</span>`;
                body.innerHTML = '<tr><td colspan="7" class="dcc-msg">—</td></tr>';
                return;
            }

            _rows = Array.isArray(data.rows) ? data.rows : [];

            if (!_rows.length) {
                body.innerHTML = '<tr><td colspan="7" class="dcc-msg">Nenhum lançamento encontrado para os filtros informados.</td></tr>';
                return;
            }

            spanTotal.textContent  = `${_rows.length} lançamento(s) encontrado(s)`;
            btnExcel.style.display = '';
            renderTabela(_rows);

        } catch (e) {
            msgEl.innerHTML = `<span class="text-danger">Erro: ${esc(e.message)}</span>`;
            body.innerHTML = '<tr><td colspan="7" class="dcc-msg">—</td></tr>';
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-search"></i> Gerar';
        }
    });
})();
</script>

<?= $render('footer') ?>
