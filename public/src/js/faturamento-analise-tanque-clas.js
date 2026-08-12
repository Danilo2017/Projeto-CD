'use strict';

const fmt    = v => 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtN   = v => parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtXls = v => typeof v === 'number'
    ? v.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})
    : String(v ?? '');
const num = v => parseFloat(v || 0);

const { emprId, codTanque, descTanq, emprNome } = window._atcParams || {};
let dadosGlobais = [];

function renderizar(rows) {
    const tbody = document.getElementById('atc-tbody');
    let totValor = 0, totUep = 0, html = '';

    for (const r of rows) {
        const vp = num(r.VALOR_PENDENTE), up = num(r.UEP);
        totValor += vp; totUep += up;
        const params = new URLSearchParams({empr_id: emprId, classificacao: r.CLASSIFICACAO ?? ''});
        html += `<tr class="linha-clas" style="cursor:pointer;" data-href="/faturamento-eficiencia-uep-detalhe?${params}">
            <td style="color:#1a56db;text-decoration:underline;text-underline-offset:3px;">${r.CLASSIFICACAO ?? ''}</td>
            <td style="text-align:right;">${fmt(vp)}</td>
            <td style="text-align:right;">${fmtN(up)}</td>
            <td style="text-align:right;">${fmtN(r.TAXA)}</td>
        </tr>`;
    }

    const taxaTotal = totUep ? totValor / totUep : 0;
    html += `<tr style="background:#1a1a2e;color:#fff;font-weight:700;border-top:3px solid #333;">
        <td style="padding:8px 10px;">TOTAL</td>
        <td style="text-align:right;padding:8px;">${fmt(totValor)}</td>
        <td style="text-align:right;padding:8px;">${fmtN(totUep)}</td>
        <td style="text-align:right;padding:8px;">${fmtN(taxaTotal)}</td>
    </tr>`;

    tbody.innerHTML = html;
    document.getElementById('atc-wrap').style.display = 'block';

    tbody.querySelectorAll('.linha-clas').forEach(tr => {
        tr.addEventListener('click', () => { window.location.href = tr.dataset.href; });
        tr.addEventListener('mouseenter', () => { tr.style.background = '#eef2ff'; });
        tr.addEventListener('mouseleave', () => { tr.style.background = ''; });
    });
}

function exportarExcel() {
    if (!dadosGlobais.length) return;
    const linhas = [
        [`Empresa: ${emprNome}`, `Tanque: ${descTanq}`, '', ''],
        ['Classificação', 'Valor Pendente', 'UEP', 'Taxa (R$/UEP)'],
    ];
    let totValor = 0, totUep = 0;
    for (const r of dadosGlobais) {
        const vp = num(r.VALOR_PENDENTE), up = num(r.UEP);
        totValor += vp; totUep += up;
        linhas.push([r.CLASSIFICACAO ?? '', vp, up, num(r.TAXA)]);
    }
    linhas.push(['TOTAL', totValor, totUep, totUep ? totValor/totUep : 0]);

    const csv = linhas.map(l => l.map(c => {
        const s = fmtXls(c).replace(/"/g, '""');
        return s.includes(';') || s.includes('\n') || s.includes('"') ? `"${s}"` : s;
    }).join(';')).join('\r\n');
    const blob = new Blob(['﻿' + csv], {type: 'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = `analise-tanque-clas-empr${emprId}-t${codTanque}.csv`; a.click();
    URL.revokeObjectURL(url);
}

function carregar() {
    if (!emprId || !codTanque) {
        document.getElementById('atc-error').style.display  = 'block';
        document.getElementById('atc-error').textContent    = 'Parâmetros inválidos.';
        document.getElementById('atc-loading').style.display = 'none';
        return;
    }

    fetch(`/faturamento-api-analise-tanque-clas?empr_id=${emprId}&cod_tanque=${codTanque}`)
        .then(r => r.json())
        .then(res => {
            document.getElementById('atc-loading').style.display = 'none';
            if (!res.success) {
                document.getElementById('atc-error').style.display = 'block';
                document.getElementById('atc-error').textContent   = res.error || 'Erro ao carregar';
                return;
            }
            const rows = res.data || [];
            if (!rows.length) {
                document.getElementById('atc-wrap').style.display = 'block';
                document.getElementById('atc-tbody').innerHTML =
                    '<tr><td colspan="4" style="text-align:center;">Nenhuma classificação encontrada.</td></tr>';
                return;
            }
            dadosGlobais = rows;
            renderizar(rows);
        })
        .catch(e => {
            document.getElementById('atc-loading').style.display = 'none';
            document.getElementById('atc-error').style.display   = 'block';
            document.getElementById('atc-error').textContent     = 'Erro de comunicação: ' + e.message;
        });
}

document.getElementById('btnExcel').addEventListener('click', exportarExcel);
carregar();
