'use strict';

const EMPRESAS_DET = {
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

let dadosDetalhe = [];
const { emprId, classificacao } = window._detParams || {};

function renderizar(rows) {
    const tbody = document.getElementById('det-tbody');
    let totValor = 0, totUep = 0, html = '';

    for (const r of rows) {
        const vp = num(r.VALOR_PENDENTE);
        const up = num(r.UEP);
        totValor += vp; totUep += up;
        html += `<tr>
            <td>${r.COD_ITEM ?? ''}</td>
            <td>${r.DESC_ITEM ?? ''}</td>
            <td style="text-align:right;">${fmt(vp)}</td>
            <td style="text-align:right;">${fmtN(up)}</td>
            <td style="text-align:right;">${fmtN(r.TAXA)}</td>
        </tr>`;
    }

    const taxaTotal = totUep ? totValor / totUep : 0;
    html += `<tr style="background:#1a1a2e;color:#fff;font-weight:700;border-top:3px solid #333;">
        <td style="padding:8px 10px;" colspan="2">TOTAL</td>
        <td style="text-align:right;padding:8px;">${fmt(totValor)}</td>
        <td style="text-align:right;padding:8px;">${fmtN(totUep)}</td>
        <td style="text-align:right;padding:8px;">${fmtN(taxaTotal)}</td>
    </tr>`;

    tbody.innerHTML = html;
    document.getElementById('det-wrap').style.display = 'block';
}

function exportarExcel() {
    if (!dadosDetalhe.length) return;
    const emprNome = EMPRESAS_DET[String(emprId)] || ('Empresa ' + emprId);
    let totValor = 0, totUep = 0;

    const linhas = [
        [`Empresa: ${emprNome}`, `Classificação: ${classificacao}`, '', '', ''],
        ['Cód. Item', 'Descrição', 'Valor Pendente', 'UEP', 'Taxa (R$/UEP)'],
    ];

    for (const r of dadosDetalhe) {
        const vp = num(r.VALOR_PENDENTE), up = num(r.UEP);
        totValor += vp; totUep += up;
        linhas.push([r.COD_ITEM ?? '', r.DESC_ITEM ?? '', vp, up, num(r.TAXA)]);
    }
    linhas.push(['TOTAL', '', totValor, totUep, totUep ? totValor/totUep : 0]);

    const csv = linhas.map(l => l.map(c => {
        const s = fmtXls(c).replace(/"/g, '""');
        return s.includes(';') || s.includes('\n') || s.includes('"') ? `"${s}"` : s;
    }).join(';')).join('\r\n');

    const blob = new Blob(['﻿' + csv], {type: 'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `eficiencia-uep-detalhe-empr${emprId}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

function carregar() {
    if (!emprId || !classificacao) {
        document.getElementById('det-error').style.display = 'block';
        document.getElementById('det-error').textContent = 'Parâmetros inválidos.';
        document.getElementById('det-loading').style.display = 'none';
        return;
    }

    const params = new URLSearchParams({empr_id: emprId, classificacao});
    fetch('/faturamento-api-eficiencia-uep-detalhe?' + params)
        .then(r => r.json())
        .then(res => {
            document.getElementById('det-loading').style.display = 'none';
            if (!res.success) {
                document.getElementById('det-error').style.display = 'block';
                document.getElementById('det-error').textContent = res.error || 'Erro ao carregar';
                return;
            }
            const rows = res.data || [];
            if (!rows.length) {
                document.getElementById('det-wrap').style.display = 'block';
                document.getElementById('det-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center;">Nenhum item encontrado.</td></tr>';
                return;
            }
            dadosDetalhe = rows;
            renderizar(rows);
        })
        .catch(e => {
            document.getElementById('det-loading').style.display = 'none';
            document.getElementById('det-error').style.display = 'block';
            document.getElementById('det-error').textContent = 'Erro de comunicação: ' + e.message;
        });
}

document.getElementById('btnExcel').addEventListener('click', exportarExcel);
carregar();
