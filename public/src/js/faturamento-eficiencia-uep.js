'use strict';

const EMPRESAS = {
    '1':'1-DOURADINA PR','2':'2-VILHENA RO','3':'3-CANDELÁRIA RS',
    '4':'4-F.SANTANA BA','5':'5-JACIARA MT','9':'9-S.GUIOMARD AC',
    '10':'10-MOLAS DOURAD.','11':'11-MOLAS CAND.','13':'13-ELOI MENDES MG',
    '14':'14-ARAGUATINS TO','15':'15-PATOS MINAS MG','16':'16-PATOS DE MINAS MG',
};

const fmt    = v => 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtN   = v => parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtXls = v => typeof v === 'number'
    ? v.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})
    : String(v ?? '');
const num    = v => parseFloat(v || 0);

let dadosGlobais = [];

function renderizar(rows) {
    const tbody = document.getElementById('uep-tbody');

    const grupos = new Map();
    for (const r of rows) {
        const id = String(r.EMPR_ID ?? '');
        if (!grupos.has(id)) grupos.set(id, []);
        grupos.get(id).push(r);
    }

    let html = '';
    let totValor = 0, totUep = 0;

    for (const [emprId, itens] of grupos) {
        const emprNome = EMPRESAS[emprId] || ('Empresa ' + emprId);
        const subValor = itens.reduce((s, r) => s + num(r.VALOR_PENDENTE), 0);
        const subUep   = itens.reduce((s, r) => s + num(r.UEP), 0);
        const subTaxa  = subUep ? subValor / subUep : 0;
        totValor += subValor;
        totUep   += subUep;

        html += `<tr style="background:#e8eeff;font-weight:700;">
            <td colspan="5" style="padding:6px 10px;">${emprNome}</td>
        </tr>`;

        for (const r of itens) {
            const params = new URLSearchParams({empr_id: emprId, classificacao: r.CLASSIFICACAO ?? ''});
            html += `<tr class="linha-cls" style="cursor:pointer;" data-href="/faturamento-eficiencia-uep-detalhe?${params}" title="Clique para ver os itens">
                <td style="color:#1a56db;text-decoration:underline;text-underline-offset:3px;">${r.CLASSIFICACAO ?? ''}</td>
                <td style="text-align:right;">${fmt(r.VALOR_PENDENTE)}</td>
                <td style="text-align:right;">${fmtN(r.UEP)}</td>
                <td style="text-align:right;">${fmtN(r.TAXA)}</td>
            </tr>`;
        }

        html += `<tr style="background:#f7f8fa;font-weight:600;border-top:2px solid #dee2e6;">
            <td style="color:#555;">Total ${emprNome}</td>
            <td style="text-align:right;">${fmt(subValor)}</td>
            <td style="text-align:right;">${fmtN(subUep)}</td>
            <td style="text-align:right;">${fmtN(subTaxa)}</td>
        </tr>
        <tr><td colspan="4" style="padding:2px;"></td></tr>`;
    }

    const taxaGeral = totUep ? totValor / totUep : 0;
    html += `<tr style="background:#1a1a2e;color:#fff;font-weight:700;border-top:3px solid #333;">
        <td style="padding:8px 10px;">TOTAL GERAL</td>
        <td style="text-align:right;padding:8px;">${fmt(totValor)}</td>
        <td style="text-align:right;padding:8px;">${fmtN(totUep)}</td>
        <td style="text-align:right;padding:8px;">${fmtN(taxaGeral)}</td>
    </tr>`;

    tbody.innerHTML = html;
    document.getElementById('uep-wrap').style.display = 'block';

    tbody.querySelectorAll('.linha-cls').forEach(tr => {
        tr.addEventListener('click', () => { window.location.href = tr.dataset.href; });
        tr.addEventListener('mouseenter', () => { tr.style.background = '#eef2ff'; });
        tr.addEventListener('mouseleave', () => { tr.style.background = ''; });
    });
}

function renderProjecao(tanques) {
    const projWrap  = document.getElementById('proj-wrap');
    const projErr   = document.getElementById('proj-error');
    const projTable = document.getElementById('proj-table-wrap');
    const tbody     = document.getElementById('proj-tbody');

    projWrap.style.display = 'block';

    if (!tanques || !tanques.length) {
        projErr.style.display = 'block';
        projErr.textContent   = 'Nenhum tanque com pedidos pendentes encontrado.';
        return;
    }

    // Agrupar por empresa
    const grupos = new Map();
    for (const t of tanques) {
        const id = String(t.EMPR_ID ?? '');
        if (!grupos.has(id)) grupos.set(id, []);
        grupos.get(id).push(t);
    }

    let html = '';
    let totProj = 0;

    for (const [emprId, tList] of grupos) {
        const emprNome = EMPRESAS[emprId] || ('Empresa ' + emprId);
        let subProj = 0;

        html += `<tr style="background:#e8eeff;font-weight:700;">
            <td colspan="5" style="padding:6px 10px;">${emprNome}</td>
        </tr>`;

        for (const t of tList) {
            const capTotal = num(t.CAP_UEP_DIA);
            const cap      = capTotal * 0.80;
            const taxa     = num(t.TAXA);
            const proj     = cap * taxa;
            subProj       += proj;
            totProj       += proj;
            html += `<tr>
                <td>${emprNome}</td>
                <td>${t.COD_TANQUE} — ${t.DESC_TANQUE ?? ''}</td>
                <td style="text-align:right;">${fmtN(cap)} <small style="color:#888;">(80%)</small></td>
                <td style="text-align:right;">${fmtN(taxa)}</td>
                <td style="text-align:right;">${fmt(proj)}</td>
            </tr>`;
        }

        html += `<tr style="background:#f7f8fa;font-weight:600;border-top:2px solid #dee2e6;">
            <td colspan="2" style="color:#555;">Total ${emprNome}</td>
            <td></td><td></td>
            <td style="text-align:right;">${fmt(subProj)}</td>
        </tr>
        <tr><td colspan="5" style="padding:2px;"></td></tr>`;
    }

    html += `<tr style="background:#1a1a2e;color:#fff;font-weight:700;border-top:3px solid #333;">
        <td colspan="2" style="padding:8px 10px;">TOTAL GERAL</td>
        <td colspan="2"></td>
        <td style="text-align:right;padding:8px;">${fmt(totProj)}</td>
    </tr>`;

    tbody.innerHTML = html;
    projTable.style.display = 'block';
}

function exportarExcel() {
    if (!dadosGlobais.length) return;

    const grupos = new Map();
    for (const r of dadosGlobais) {
        const id = String(r.EMPR_ID ?? '');
        if (!grupos.has(id)) grupos.set(id, []);
        grupos.get(id).push(r);
    }

    const linhas = [['Empresa', 'Classificação', 'Valor Pendente', 'UEP', 'Taxa (R$/UEP)']];
    let totValor = 0, totUep = 0;

    for (const [emprId, itens] of grupos) {
        const emprNome = EMPRESAS[emprId] || ('Empresa ' + emprId);
        const subValor = itens.reduce((s, r) => s + num(r.VALOR_PENDENTE), 0);
        const subUep   = itens.reduce((s, r) => s + num(r.UEP), 0);
        totValor += subValor; totUep += subUep;

        for (const r of itens) {
            linhas.push([emprNome, r.CLASSIFICACAO ?? '', num(r.VALOR_PENDENTE), num(r.UEP), num(r.TAXA)]);
        }
        linhas.push([`Total ${emprNome}`, '', subValor, subUep, subUep ? subValor/subUep : 0]);
        linhas.push(['', '', '', '', '']);
    }
    linhas.push(['TOTAL GERAL', '', totValor, totUep, totUep ? totValor/totUep : 0]);

    const csv = linhas.map(l => l.map(c => {
        const s = fmtXls(c).replace(/"/g, '""');
        return s.includes(';') || s.includes('\n') || s.includes('"') ? `"${s}"` : s;
    }).join(';')).join('\r\n');

    const blob = new Blob(['﻿' + csv], {type: 'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = 'taxa-pedidos-pendentes.csv'; a.click();
    URL.revokeObjectURL(url);
}

function carregar() {
    const loading = document.getElementById('uep-loading');
    const wrap    = document.getElementById('uep-wrap');
    const erro    = document.getElementById('uep-error');
    const projWrap = document.getElementById('proj-wrap');

    loading.style.display  = 'block';
    wrap.style.display     = 'none';
    erro.style.display     = 'none';
    projWrap.style.display = 'none';
    dadosGlobais           = [];

    Promise.all([
        fetch('/faturamento-api-eficiencia-uep').then(r => r.json()),
        fetch('/faturamento-api-eficiencia-uep-tanques').then(r => r.json()),
    ])
    .then(([resUep, resTanq]) => {
        loading.style.display = 'none';

        if (!resUep.success) {
            erro.style.display = 'block';
            erro.textContent   = resUep.error || 'Erro ao carregar';
            return;
        }

        const rows = resUep.data || [];
        if (!rows.length) {
            wrap.style.display = 'block';
            document.getElementById('uep-tbody').innerHTML =
                '<tr><td colspan="4" style="text-align:center;">Nenhum dado encontrado.</td></tr>';
            return;
        }

        dadosGlobais = rows;
        renderizar(rows);

        document.getElementById('proj-loading').style.display = 'none';
        renderProjecao(resTanq.success ? (resTanq.data || []) : []);
    })
    .catch(e => {
        loading.style.display = 'none';
        erro.style.display    = 'block';
        erro.textContent      = 'Erro de comunicação: ' + e.message;
    });
}

document.getElementById('btnAtualizar').addEventListener('click', carregar);
document.getElementById('btnExcel').addEventListener('click', exportarExcel);
carregar();
