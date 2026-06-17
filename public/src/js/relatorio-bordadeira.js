(function () {
    'use strict';

    const HISTORICO = [
        { data: '24/10/2024', alteracao: 'Elaboração Inicial' },
        { data: '13/08/2025', alteracao: 'Alteração do Título do documento e do rodapé.' },
    ];

    const LOGO_URL = 'https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png';

    function dataHoje() {
        const d = new Date();
        return String(d.getDate()).padStart(2,'0') + '/' +
               String(d.getMonth()+1).padStart(2,'0') + '/' +
               d.getFullYear();
    }

    function fmt(v)    { return (v === null || v === undefined || v === '') ? '' : v; }
    function fmtNum(v) { const n = parseFloat(v); return isNaN(n) ? '' : (n === 0 ? '0' : n); }
    function fmtDec(v) {
        const n = parseFloat(v);
        return isNaN(n) ? '' : n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /* ── Seção P1 recebe linhas sem tanque ou com P1 ─ */
    function isP1(row) {
        const t = (row.TANQUE || '').trim();
        return t === '' || /P1/i.test(t);
    }

    /* ── Monta tabela de seção ───────────────────────── */
    function tabelaSecao(rows, titulo, numLote, dataLote) {
        if (!rows || rows.length === 0) return '';

        let totalQtde   = 0;
        let totalLinear = 0;
        let corpo       = '';

        for (const r of rows) {
            totalQtde   += parseFloat(r.QTDE_TAMPO || 0);
            totalLinear += parseFloat(r.LINEAR     || 0);

            corpo += `
            <tr>
                <td class="text-center">${fmt(r.COD_ITEM)}</td>
                <td class="td-wrap">${fmt(r.DESC_TECNICA)}</td>
                <td class="td-wrap">${fmt(r.MASCARA)}</td>
                <td class="text-center">${fmtNum(r.QTDE_TAMPO)}</td>
                <td class="td-wrap">${fmt(r.TANQUE)}</td>
                <td>${fmt(r.TECIDO)}</td>
                <td>${fmt(r.BORDADO)}</td>
                <td class="text-right">${fmtDec(r.LINEAR)}</td>
                <td class="text-center">${fmtNum(r.ESPESSURA)}</td>
            </tr>`;
        }

        corpo += `
        <tr class="total-row">
            <td colspan="3"></td>
            <td style="text-align:center">${totalQtde}</td>
            <td colspan="3"></td>
            <td style="text-align:right">${fmtDec(totalLinear)}</td>
            <td></td>
        </tr>`;

        return `
    <div class="pcp-section-title">
        ${titulo} - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}
    </div>
    <div style="overflow-x:auto">
    <table class="pcp-table">
        <thead>
            <tr>
                <th>COD ITEM</th>
                <th>DESCRIÇÃO TÉCNICA</th>
                <th>MASCARA</th>
                <th>QTI</th>
                <th>TANQUE</th>
                <th>TECIDO</th>
                <th>BORDADO</th>
                <th>METRO LINEAR</th>
                <th>ESPESSURA</th>
            </tr>
        </thead>
        <tbody>${corpo}</tbody>
    </table>
    </div>`;
    }

    /* ── Renderiza relatório Bordadeira ──────────────── */
    function renderBordadeira(rows, numLote, dataLote) {
        if (!rows || rows.length === 0) {
            return '<p class="text-muted text-center py-4">Nenhum dado encontrado para este lote.</p>';
        }

        const hoje    = dataHoje();
        const p1Rows   = rows.filter(r =>  isP1(r));
        const p2p3Rows = rows.filter(r => !isP1(r));

        let corpo = '';
        corpo += tabelaSecao(p1Rows,   'BORDADEIRA P1',       numLote, dataLote);
        if (p1Rows.length && p2p3Rows.length) corpo += '<div style="margin-top:10px"></div>';
        corpo += tabelaSecao(p2p3Rows, 'BORDADEIRA P2 E P3',  numLote, dataLote);

        if (!corpo.trim()) {
            return '<p class="text-muted text-center py-4">Nenhum dado encontrado para este lote.</p>';
        }

        return `
<div class="pcp-section">
    <div class="pcp-report-header">
        <div class="col-logo">
            <img src="${LOGO_URL}" alt="Gazin">
        </div>
        <div class="col-title">RELATÓRIO DE PRODUÇÃO</div>
        <div class="col-right">
            <div><strong>SETOR</strong></div>
            <div>GESTÃO DE PRODUÇÃO</div>
        </div>
    </div>
    <div class="pcp-revisao">REVISÃO-02 &nbsp;&nbsp; DATA: ${hoje}</div>

    ${corpo}

    <div class="pcp-historico">
        <table>
            <thead>
                <tr><th colspan="2">Histórico de Revisões</th></tr>
                <tr><th style="width:120px">data</th><th>Alterações</th></tr>
            </thead>
            <tbody>
                ${HISTORICO.map(h => `<tr><td>${h.data}</td><td>${h.alteracao}</td></tr>`).join('')}
            </tbody>
        </table>
    </div>
</div>`;
    }

    /* ── Renderiza Rolo Bordado ─────────────────────── */
    function toNum(v) {
        if (v === null || v === undefined) return NaN;
        if (typeof v === 'number') return v;
        return parseFloat(String(v).replace(/\.(?=\d{3})/g, '').replace(',', '.'));
    }
    function fmtDec2(v) { const n = toNum(v); return isNaN(n) ? '' : n.toLocaleString('pt-BR', { minimumFractionDigits:2, maximumFractionDigits:2 }); }
    function fmtDec4(v) { const n = toNum(v); return isNaN(n) ? '' : n.toLocaleString('pt-BR', { minimumFractionDigits:4, maximumFractionDigits:4 }); }
    function fmtNumR(v) { const n = toNum(v); return isNaN(n) ? '' : (n === 0 ? '0' : n); }

    function renderRoloBordado(rows, numLote, dataLote) {
        if (!rows || rows.length === 0) return '';

        const hoje = dataHoje();
        let totalLinear = 0, totalFatias = 0, corpo = '';

        for (const r of rows) {
            totalLinear += toNum(r.MT_LINEAR) || 0;
            totalFatias += toNum(r.FATIAS)    || 0;

            corpo += `
            <tr>
                <td class="text-center">${fmt(r.ORDEM)}</td>
                <td class="td-wrap">${fmt(r.BORDADO)}</td>
                <td class="text-center">${fmt(r.COD_ITEM_TECIDO)}</td>
                <td class="text-center">${fmtNumR(r.ALT_FAIXA)}</td>
                <td class="text-right">${fmtDec2(r.MT_LINEAR)}</td>
                <td class="text-right">${fmtDec4(r.FATIAS)}</td>
            </tr>`;
        }

        corpo += `
        <tr class="total-row">
            <td colspan="4"></td>
            <td style="text-align:right">${fmtDec2(totalLinear)}</td>
            <td style="text-align:right">${fmtDec4(totalFatias)}</td>
        </tr>`;

        return `
<div class="pcp-section">
    <div class="pcp-report-header">
        <div class="col-logo"><img src="${LOGO_URL}" alt="Gazin"></div>
        <div class="col-title">RELATÓRIO DE PRODUÇÃO</div>
        <div class="col-right"><div><strong>SETOR</strong></div><div>GESTÃO DE PRODUÇÃO</div></div>
    </div>
    <div class="pcp-revisao">REVISÃO-02 &nbsp;&nbsp; DATA: ${hoje}</div>
    <div class="pcp-section-title">
        ROLO BORDADO - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}
    </div>
    <div style="overflow-x:auto">
    <table class="pcp-table">
        <thead>
            <tr>
                <th>ORDEM</th><th>BORDADO</th><th>COD ITEM TECIDO</th>
                <th>ALT FAIXA</th><th>MT LINEAR</th><th>FATIAS</th>
            </tr>
        </thead>
        <tbody>${corpo}</tbody>
    </table>
    </div>
    <div class="pcp-historico">
        <table>
            <thead>
                <tr><th colspan="2">Histórico de Revisões</th></tr>
                <tr><th style="width:120px">data</th><th>Alterações</th></tr>
            </thead>
            <tbody>${HISTORICO.map(h => `<tr><td>${h.data}</td><td>${h.alteracao}</td></tr>`).join('')}</tbody>
        </table>
    </div>
</div>`;
    }

    /* ── Janela de impressão ────────────────────────── */
    function abrirJanelaImpressao(conteudo) {
        const w = window.open('', '_blank');
        w.document.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8">
<title>Sequência de Produção — Bordadeira</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:8pt;background:#fff}
.pcp-section{padding:10px 14px}
.pcp-report-header{display:flex;align-items:center;border:1px solid #000}
.pcp-report-header .col-logo{width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0}
.pcp-report-header .col-logo img{width:90px}
.pcp-report-header .col-title{flex:1;text-align:center;font-weight:bold;font-size:11pt;padding:6px;border-right:1px solid #000}
.pcp-report-header .col-right{width:170px;font-size:8pt;padding:4px 8px;flex-shrink:0}
.pcp-report-header .col-right div{margin-bottom:2px}
.pcp-revisao{border:1px solid #000;border-top:none;padding:2px 8px;font-size:8pt;text-align:right}
.pcp-section-title{background:#002060;color:#fff;text-align:center;font-weight:bold;font-size:12pt;padding:5px;margin-top:6px;margin-bottom:2px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table{width:100%;border-collapse:collapse;font-size:7.5pt}
.pcp-table th{background:#1f3864;color:#fff;border:1px solid #999;padding:2px 3px;text-align:center;font-weight:bold;white-space:nowrap;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table td{border:1px solid #ccc;padding:1px 3px;white-space:nowrap}
.pcp-table tr:nth-child(even) td{background:#f2f2f2;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table tr.total-row td{background:#1f3864;color:#fff;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-historico{margin-top:10px;border:1px solid #999;font-size:8pt}
.pcp-historico table{width:100%;border-collapse:collapse}
.pcp-historico th{background:#d9d9d9;border:1px solid #999;padding:2px 6px;text-align:center;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-historico td{border:1px solid #ccc;padding:2px 6px}
.text-right{text-align:right}
@page{size:A4 landscape;margin:8mm}
@media screen{
  body{background:#6c757d;padding:24px}
  .pcp-section{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.25);border-radius:3px;margin-bottom:32px;padding:18px 20px}
}
@media print{
  body{background:#fff;padding:0}
  .pcp-section{page-break-after:always;box-shadow:none;border-radius:0;margin:0;padding:10px 14px}
  .pcp-section:last-child{page-break-after:avoid}
  .pcp-table td.td-wrap{white-space:normal;word-break:break-word}
}
</style>
</head><body>${conteudo}</body></html>`);
        w.document.close();
        w.focus();
        setTimeout(() => w.print(), 300);
    }

    /* ── Init ──────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        const btnGerar    = document.getElementById('btnGerar');
        const btnImprimir = document.getElementById('btnImprimir');
        const printArea   = document.getElementById('printArea');
        const statusMsg   = document.getElementById('statusMsg');

        btnGerar.addEventListener('click', async function () {
            const numLote = document.getElementById('inputLote').value.trim();
            if (!numLote || parseInt(numLote) <= 0) { alert('Informe o número do lote.'); return; }

            btnGerar.disabled = true;
            btnGerar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gerando...';
            btnImprimir.style.display = 'none';
            statusMsg.innerHTML = '';
            printArea.className = '';
            printArea.innerHTML = '';

            try {
                const res  = await fetch('pcp-api-relatorio-bordadeira', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ num_lote: parseInt(numLote) }),
                });
                const data = await res.json();

                if (data.error) {
                    statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                    return;
                }

                const dt = data.data_lote || '';
                let html = renderBordadeira(data.bordadeira_rows || [], numLote, dt);
                html    += renderRoloBordado(data.rolo_rows || [], numLote, dt);

                if (!html) {
                    statusMsg.innerHTML = '<span class="text-warning">Nenhum dado encontrado para este lote.</span>';
                    return;
                }

                printArea.innerHTML = html;
                printArea.className = 'visible';
                btnImprimir.style.display = '';

            } catch (e) {
                statusMsg.innerHTML = `<span class="text-danger">Erro ao buscar dados: ${e.message}</span>`;
            } finally {
                btnGerar.disabled = false;
                btnGerar.innerHTML = '<i class="bi bi-search"></i> Gerar';
            }
        });

        btnImprimir.addEventListener('click', function () {
            abrirJanelaImpressao(printArea.innerHTML);
        });
    });
})();
